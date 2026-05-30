<?php

namespace App\Jobs;

use App\Models\MediaAsset;
use App\Models\NewsItemMedia;
use App\Services\MediaQualityCheck;
use App\Services\MediaStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMediaPipeline implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected MediaAsset $media,
    ) {}

    public function handle(): void
    {
        /** @var MediaAsset|null $media */
        $media = $this->media->fresh();
        if (! $media) {
            return;
        }

        if ($media->isImage()) {
            $this->processImage($media);
        }

        if ($media->isVideo()) {
            $this->processVideo($media);
        }

        if ($media->isAudio()) {
            $this->processAudio($media);
        }
    }

    protected function processImage(MediaAsset $media): void
    {
        $media->update([
            'redaction_status' => $media->shouldAutoRedact()
                ? NewsItemMedia::REDACTION_PENDING
                : NewsItemMedia::REDACTION_DISABLED,
        ]);

        try {
            app(MediaQualityCheck::class)->runAndSave($media);
        } catch (\Throwable $e) {
            Log::warning('ProcessMediaPipeline: MediaQualityCheck failed', [
                'media_id' => $media->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Vorschau-Pfad analog zu processMediaUploads() setzen
        $path = $media->path;
        if ($path) {
            $newsItem = $media->newsItem;
            $previewPath = app(MediaStorage::class)->isStructuredNewsMediaPath($path)
                ? app(MediaStorage::class)->generateDerivedMediaPath($newsItem, $media, ($media->original_name ?: basename($path)).'.webp', 'preview')
                : dirname($path).'/preview/'.pathinfo($path, PATHINFO_FILENAME).'.webp';
            $media->update(['preview_path' => $previewPath]);

            $watermarkPath = public_path('images/erftkreis-news-logo.png');
            if (is_file($watermarkPath)) {
                try {
                    GenerateNewsMediaPreview::dispatch(app(MediaStorage::class)->activeDiskName(), $path, $previewPath, $watermarkPath);
                } catch (\Throwable $e) {
                    Log::warning('ProcessMediaPipeline: preview dispatch failed', [
                        'media_id' => $media->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        try {
            $media->markAiQueued();
            GenerateImageMetadata::dispatch($media);
        } catch (QueryException $e) {
            Log::warning('ProcessMediaPipeline: AI columns missing, continue without AI', [
                'media_id' => $media->id,
                'path' => $media->path,
                'error' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('ProcessMediaPipeline: AI dispatch failed', [
                'media_id' => $media->id,
                'error' => $e->getMessage(),
            ]);
        }

        if ($media->shouldAutoRedact()) {
            try {
                ProcessMediaRedaction::dispatch($media);
            } catch (\Throwable $e) {
                Log::warning('ProcessMediaPipeline: redaction dispatch failed', [
                    'media_id' => $media->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function processVideo(MediaAsset $media): void
    {
        $resolved = app(MediaStorage::class)->resolveReadableLocalPath($media->path);
        $absPath = $resolved['path'] ?? null;
        if (! is_string($absPath) || ! is_file($absPath)) {
            Log::warning('ProcessMediaPipeline: video file missing', [
                'media_id' => $media->id,
                'path' => $media->path,
            ]);

            return;
        }

        try {
            ExtractVideoMetadata::dispatch($media);
        } catch (\Throwable $e) {
            Log::warning('ProcessMediaPipeline: video metadata dispatch failed', [
                'media_id' => $media->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            GenerateVideoPoster::dispatch($media);
        } catch (\Throwable $e) {
            Log::warning('ProcessMediaPipeline: video poster dispatch failed', [
                'media_id' => $media->id,
                'error' => $e->getMessage(),
            ]);
        }
        try {
            GenerateVideoStills::dispatch($media);
        } catch (\Throwable $e) {
            Log::warning('ProcessMediaPipeline: video stills dispatch failed', [
                'media_id' => $media->id,
                'error' => $e->getMessage(),
            ]);
        }
        app(MediaStorage::class)->cleanupResolvedPath($resolved);
    }

    protected function processAudio(MediaAsset $media): void
    {
        $resolved = app(MediaStorage::class)->resolveReadableLocalPath($media->path);
        $absPath = $resolved['path'] ?? null;
        if (! is_string($absPath) || ! is_file($absPath)) {
            Log::warning('ProcessMediaPipeline: audio file missing', [
                'media_id' => $media->id,
                'path' => $media->path,
            ]);

            return;
        }

        try {
            ExtractAudioMetadata::dispatch($media);
        } catch (\Throwable $e) {
            Log::warning('ProcessMediaPipeline: audio metadata dispatch failed', [
                'media_id' => $media->id,
                'error' => $e->getMessage(),
            ]);
        }
        app(MediaStorage::class)->cleanupResolvedPath($resolved);
    }
}
