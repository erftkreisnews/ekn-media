<?php

namespace App\Jobs;

use App\Models\NewsItemMedia;
use App\Services\MediaStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class GenerateVideoPoster implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected NewsItemMedia $media,
    ) {}

    public function handle(): void
    {
        if (! $this->media->isVideo()) {
            return;
        }

        $mediaStorage = app(MediaStorage::class);
        $disk = $mediaStorage->activeDisk();
        $videoPath = $this->media->path;
        if (! $videoPath) {
            return;
        }

        $resolved = $mediaStorage->resolveReadableLocalPath($videoPath);
        $absVideo = $resolved['path'] ?? null;
        if (! is_string($absVideo) || ! is_file($absVideo) || ! is_readable($absVideo)) {
            Log::warning('GenerateVideoPoster: video not readable', [
                'media_id' => $this->media->id,
                'path' => $videoPath,
            ]);

            return;
        }

        $newsItem = $this->media->newsItem;
        $previewRel = $mediaStorage->isStructuredNewsMediaPath($videoPath)
            ? $mediaStorage->generateDerivedMediaPath($newsItem, $this->media, ($this->media->original_name ?: basename($videoPath)).'.webp', 'poster')
            : dirname($videoPath).'/preview/'.pathinfo($videoPath, PATHINFO_FILENAME).'.webp';
        $previewDir = dirname($previewRel);
        if ($previewDir !== '' && ! $disk->exists($previewDir)) {
            $disk->makeDirectory($previewDir);
        }

        $absPreview = tempnam(sys_get_temp_dir(), 'poster_');
        if ($absPreview === false) {
            return;
        }
        $absPreview .= '.webp';

        $ffmpegPath = config('media.ffmpeg_path', 'ffmpeg');
        if (str_contains($ffmpegPath, DIRECTORY_SEPARATOR) && ! file_exists($ffmpegPath)) {
            Log::warning('GenerateVideoPoster: ffmpeg binary not found', [
                'media_id' => $this->media->id,
                'ffmpeg_path' => $ffmpegPath,
            ]);

            return;
        }

        // Ein Frame bei Sekunde 1, auf max. 1600px Breite skaliert, als WebP speichern.
        $process = new Process([
            $ffmpegPath,
            '-y',
            '-ss', '00:00:01',
            '-i', $absVideo,
            '-frames:v', '1',
            '-vf', 'scale=1600:-2',
            '-f', 'image2',
            $absPreview,
        ]);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            Log::warning('GenerateVideoPoster: ffmpeg failed', [
                'media_id' => $this->media->id,
                'exit_code' => $process->getExitCode(),
                'stderr' => $process->getErrorOutput(),
            ]);

            return;
        }

        if (is_file($absPreview)) {
            $mediaStorage->putFromLocalFile($previewRel, $absPreview, [
                'visibility' => 'public',
                'ContentType' => 'image/webp',
            ]);
            $this->media->update(['preview_path' => $previewRel]);
            Log::info('GenerateVideoPoster: preview generated', [
                'media_id' => $this->media->id,
                'preview_path' => $previewRel,
            ]);
        }

        @unlink($absPreview);
        $mediaStorage->cleanupResolvedPath($resolved);
    }
}
