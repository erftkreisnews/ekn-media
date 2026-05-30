<?php

namespace App\Services\Witness;

use App\Jobs\ExtractAudioMetadata;
use App\Jobs\ExtractVideoMetadata;
use App\Jobs\GenerateImageMetadata;
use App\Jobs\GenerateNewsMediaPreview;
use App\Jobs\GenerateVideoPoster;
use App\Jobs\GenerateVideoStills;
use App\Jobs\ProcessMediaRedaction;
use App\Models\NewsItemMedia;
use App\Models\NewsItemWitnessSubmission;
use App\Services\MediaQualityCheck;
use App\Services\MediaStorage;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PromoteWitnessSubmissionService
{
    public function __construct(
        private MediaStorage $mediaStorage,
        private MediaQualityCheck $mediaQualityCheck,
    ) {}

    /**
     * @param  array{media_type: string, image_title?: ?string, caption?: ?string, photographer?: ?string, apply_anonymous_credit?: bool}  $data
     */
    public function promote(NewsItemWitnessSubmission $submission, array $data): NewsItemMedia
    {
        if ($submission->status !== NewsItemWitnessSubmission::STATUS_PENDING) {
            throw new RuntimeException('Einreichung ist nicht mehr offen.');
        }

        if ($submission->news_item_media_id !== null) {
            throw new RuntimeException('Bereits übernommen.');
        }

        $path = $submission->stored_path;
        $diskName = $submission->stored_disk;
        if (! is_string($path) || $path === '' || ! is_string($diskName) || $diskName === '') {
            throw new RuntimeException('Keine Datei vorhanden.');
        }

        $srcDisk = Storage::disk($diskName);
        if (! $srcDisk->exists($path)) {
            throw new RuntimeException('Quelldatei fehlt.');
        }

        $type = $data['media_type'];
        if (! in_array($type, ['image', 'video', 'audio'], true)) {
            throw new RuntimeException('Ungültiger Medientyp.');
        }

        $newsItem = $submission->newsItem;
        if (! $newsItem) {
            throw new RuntimeException('Meldung fehlt.');
        }

        $photographer = $this->resolvePhotographer($submission, $data);

        $sortOrder = (int) ($newsItem->media()->max('sort_order') ?? 0) + 1;

        return DB::transaction(function () use ($submission, $data, $type, $photographer, $sortOrder, $path, $srcDisk, $newsItem): NewsItemMedia {
            $media = $newsItem->media()->create([
                'type' => $type,
                'path' => 'news-media/.pending',
                'original_name' => $submission->original_filename,
                'sort_order' => $sortOrder,
                'image_title' => $data['image_title'] ?? null,
                'caption' => $data['caption'] ?? null,
                'photographer' => $photographer,
            ]);

            $targetPath = $this->mediaStorage->generateMediaPath(
                $newsItem,
                $media,
                $submission->original_filename,
                $type === 'image' ? 'gallery' : $type
            );

            $stream = $srcDisk->readStream($path);
            if (! is_resource($stream)) {
                throw new RuntimeException('Quelle konnte nicht gelesen werden.');
            }

            try {
                $written = $this->mediaStorage->activeDisk()->put($targetPath, $stream);
                if ($written !== true) {
                    throw new RuntimeException('Zielspeicher konnte nicht beschrieben werden.');
                }
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            $media->update(['path' => $targetPath]);

            $srcDisk->delete($path);

            $submission->update([
                'status' => NewsItemWitnessSubmission::STATUS_ACCEPTED,
                'news_item_media_id' => $media->id,
                'stored_path' => null,
            ]);

            $this->dispatchPostProcessing($media, $type);

            return $media->fresh();
        });
    }

    /**
     * @param  array{image_title?: ?string, caption?: ?string, photographer?: ?string, apply_anonymous_credit?: bool}  $data
     */
    private function resolvePhotographer(NewsItemWitnessSubmission $submission, array $data): ?string
    {
        $manual = trim((string) ($data['photographer'] ?? ''));
        if ($manual !== '') {
            return $manual;
        }

        $anonymous = $submission->credit_anonymous || ! empty($data['apply_anonymous_credit']);
        if ($anonymous) {
            return (string) config('witness.anonymous_photographer_label', 'Leser / Zeuge (ohne Namensnennung)');
        }

        return $submission->submitter_name ?: null;
    }

    private function dispatchPostProcessing(NewsItemMedia $media, string $type): void
    {
        if ($type === 'image') {
            $media->update([
                'redaction_status' => $media->shouldAutoRedact()
                    ? NewsItemMedia::REDACTION_PENDING
                    : NewsItemMedia::REDACTION_DISABLED,
            ]);
            $this->mediaQualityCheck->runAndSave($media);
            $previewPath = $this->mediaStorage->generateDerivedMediaPath(
                $media->newsItem,
                $media,
                ($media->original_name ?? 'file').'.webp',
                'preview'
            );
            $media->update(['preview_path' => $previewPath]);
            $watermarkPath = public_path('images/erftkreis-news-logo.png');
            if (is_file($watermarkPath)) {
                GenerateNewsMediaPreview::dispatchSync(
                    $this->mediaStorage->activeDiskName(),
                    (string) $media->path,
                    $previewPath,
                    $watermarkPath
                );
            }
            try {
                $media->markAiQueued();
                GenerateImageMetadata::dispatch($media);
            } catch (QueryException $e) {
                Log::warning('witness_promote: AI columns missing', [
                    'media_id' => $media->id,
                    'error' => $e->getMessage(),
                ]);
            }
            if ($media->shouldAutoRedact()) {
                ProcessMediaRedaction::dispatch($media);
            }
        } elseif ($type === 'video') {
            ExtractVideoMetadata::dispatch($media);
            GenerateVideoPoster::dispatch($media);
            GenerateVideoStills::dispatch($media);
        } elseif ($type === 'audio') {
            ExtractAudioMetadata::dispatch($media);
        }
    }

    public static function inferMediaTypeFromMime(?string $mime): ?string
    {
        $m = (string) $mime;

        return match (true) {
            str_starts_with($m, 'image/') => 'image',
            str_starts_with($m, 'video/') => 'video',
            str_starts_with($m, 'audio/') => 'audio',
            default => null,
        };
    }
}
