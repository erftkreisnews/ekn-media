<?php

namespace App\Services\Admin;

use App\Jobs\GenerateImageMetadata;
use App\Jobs\GenerateNewsMediaPreview;
use App\Jobs\ProcessMediaRedaction;
use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use App\Services\ImageMetadataReader;
use App\Services\ImageMetadataWriter;
use App\Services\MediaQualityCheck;
use App\Services\MediaStorage;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class NewsItemImageIngestService
{
    /**
     * @return array{media: NewsItemMedia|null, skipped_duplicate: bool}
     */
    public function ingest(NewsItem $newsItem, UploadedFile $file): array
    {
        if (! $file->isValid()) {
            return ['media' => null, 'skipped_duplicate' => false];
        }

        $originalName = $file->getClientOriginalName();
        $normalizedName = mb_strtolower(trim($originalName));
        if ($normalizedName !== '' && $newsItem->images()
            ->whereRaw('LOWER(TRIM(original_name)) = ?', [$normalizedName])
            ->exists()) {
            return ['media' => null, 'skipped_duplicate' => true];
        }

        $sortOrder = (int) ($newsItem->media()->max('sort_order') ?? 0) + 1;
        $mediaStorage = app(MediaStorage::class);

        $media = $newsItem->media()->create([
            'type' => 'image',
            'path' => 'news-media/.pending',
            'original_name' => $originalName,
            'sort_order' => $sortOrder,
        ]);

        $path = $mediaStorage->generateMediaPath(
            $newsItem,
            $media,
            $file,
            'gallery'
        );
        $storedPath = $mediaStorage->storeUploadedFileAs($file, dirname($path), basename($path));
        $media->update(['path' => $storedPath]);

        $capturedAt = $this->detectUploadedImageCaptureTime($file);
        if ($capturedAt !== null) {
            $media->update([
                'capture_time' => $capturedAt,
                'metadata_recorded_at' => $capturedAt->toDateString(),
            ]);
        }

        $credit = trim((string) ($newsItem->author_credit ?? ''));
        if ($credit !== '') {
            $media->update(['photographer' => $credit]);
            $this->embedPhotographerIptc($media, $mediaStorage);
        }

        $media->update([
            'redaction_status' => $media->shouldAutoRedact()
                ? NewsItemMedia::REDACTION_PENDING
                : NewsItemMedia::REDACTION_DISABLED,
        ]);

        app(MediaQualityCheck::class)->runAndSave($media);

        $previewPath = $mediaStorage->generateDerivedMediaPath(
            $newsItem,
            $media,
            $file->getClientOriginalName().'.webp',
            'preview'
        );
        $media->update(['preview_path' => $previewPath]);
        $watermarkPath = public_path('images/erftkreis-news-logo.png');
        if (is_file($watermarkPath)) {
            GenerateNewsMediaPreview::dispatchSync(
                $mediaStorage->activeDiskName(),
                $storedPath,
                $previewPath,
                $watermarkPath
            );
        }

        try {
            $media->markAiQueued();
            GenerateImageMetadata::dispatch($media);
        } catch (QueryException $e) {
            Log::warning('NewsItemImageIngestService: AI columns missing, continue without AI', [
                'media_id' => $media->id,
                'path' => $media->path,
                'error' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('NewsItemImageIngestService: AI metadata job skipped', [
                'media_id' => $media->id,
                'error' => $e->getMessage(),
            ]);
        }

        if ($media->shouldAutoRedact()) {
            ProcessMediaRedaction::dispatch($media);
        }

        return ['media' => $media->fresh(), 'skipped_duplicate' => false];
    }

    private function embedPhotographerIptc(NewsItemMedia $media, MediaStorage $mediaStorage): void
    {
        try {
            $media->refresh();
            $iptcRel = $media->resolveIptcMasterRelativePath();
            if ($iptcRel === null) {
                return;
            }
            $resolved = $mediaStorage->resolveReadableLocalPath($iptcRel);
            $fullPath = $resolved['path'] ?? null;
            if (is_string($fullPath) && is_file($fullPath)) {
                $written = ImageMetadataWriter::write($fullPath, $media->resolvedIptcForEmbed());
                if ($written && ($resolved['temporary'] ?? false) === true) {
                    $mediaStorage->putFromLocalFile($iptcRel, $fullPath, ['visibility' => 'public']);
                }
            }
            $mediaStorage->cleanupResolvedPath($resolved);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function detectUploadedImageCaptureTime(UploadedFile $file): ?Carbon
    {
        $tmpPath = $file->getRealPath();
        if (! is_string($tmpPath) || $tmpPath === '' || ! is_file($tmpPath)) {
            return null;
        }

        $meta = ImageMetadataReader::read($tmpPath);

        return ImageMetadataReader::captureTimeCarbonFromMetadata($meta);
    }
}
