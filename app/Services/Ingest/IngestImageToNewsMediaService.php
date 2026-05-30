<?php

namespace App\Services\Ingest;

use App\Jobs\GenerateImageMetadata;
use App\Jobs\GenerateNewsMediaPreview;
use App\Jobs\ProcessMediaRedaction;
use App\Models\IngestFile;
use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use App\Services\ImageMetadataReader;
use App\Services\MediaQualityCheck;
use App\Services\MediaStorage;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Übernimmt ein JPEG aus dem Ingest in die News-Galerie (gleiche Pipeline wie News-Upload).
 */
final class IngestImageToNewsMediaService
{
    public function __construct(
        protected MediaStorage $mediaStorage,
    ) {}

    public function finalize(IngestFile $ingestFile, NewsItem $newsItem): NewsItemMedia
    {
        if (! $ingestFile->isIngestImageFile()) {
            throw new \InvalidArgumentException('Nur JPEG-Ingest-Dateien können so übernommen werden.');
        }

        $localPath = $ingestFile->absolute_path;
        if (! is_file($localPath)) {
            throw new \RuntimeException('Quelldatei fehlt auf dem Server.');
        }

        return DB::transaction(function () use ($ingestFile, $newsItem, $localPath) {
            $sortOrder = (int) ($newsItem->media()->max('sort_order') ?? 0);
            $sortOrder++;
            $originalName = $ingestFile->original_name;

            $attrs = [
                'type' => 'image',
                'path' => 'news-media/.pending',
                'original_name' => $originalName,
                'sort_order' => $sortOrder,
            ];
            if (Schema::hasColumn('news_item_media', 'brand_id') && ! empty($newsItem->brand_id)) {
                $attrs['brand_id'] = (int) $newsItem->brand_id;
            }

            $media = $newsItem->media()->create($attrs);

            $targetPath = $this->mediaStorage->generateMediaPath(
                $newsItem,
                $media,
                $originalName,
                'gallery'
            );

            if (! $this->mediaStorage->putFromLocalFile($targetPath, $localPath)) {
                throw new \RuntimeException('Speichern in den Medien-Speicher fehlgeschlagen.');
            }

            $media->update(['path' => $targetPath]);

            $meta = ImageMetadataReader::read($localPath);
            $capturedAt = ImageMetadataReader::captureTimeCarbonFromMetadata($meta);
            if ($capturedAt !== null) {
                $media->update([
                    'capture_time' => $capturedAt,
                    'metadata_recorded_at' => $capturedAt->toDateString(),
                ]);
            }

            $media->update([
                'redaction_status' => $media->shouldAutoRedact()
                    ? NewsItemMedia::REDACTION_PENDING
                    : NewsItemMedia::REDACTION_DISABLED,
            ]);
            app(MediaQualityCheck::class)->runAndSave($media);
            $previewPath = $this->mediaStorage->generateDerivedMediaPath($newsItem, $media, $originalName.'.webp', 'preview');
            $media->update(['preview_path' => $previewPath]);
            $watermarkPath = public_path('images/erftkreis-news-logo.png');
            if (is_file($watermarkPath)) {
                GenerateNewsMediaPreview::dispatchSync($this->mediaStorage->activeDiskName(), $targetPath, $previewPath, $watermarkPath);
            }
            try {
                $media->markAiQueued();
                GenerateImageMetadata::dispatch($media);
            } catch (QueryException $e) {
                Log::warning('ingest.image_to_news: AI columns missing', [
                    'media_id' => $media->id,
                    'error' => $e->getMessage(),
                ]);
            }
            if ($media->shouldAutoRedact()) {
                ProcessMediaRedaction::dispatch($media);
            }

            return $media->fresh();
        });
    }
}
