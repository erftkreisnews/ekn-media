<?php

namespace App\Services\Ingest;

use App\Models\IngestFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Intervention\Image\ImageManager;
use Symfony\Component\Process\Process;

final class IngestVideoPosterService
{
    public function __construct(
        protected IngestFfprobeService $ffprobe,
    ) {}

    /**
     * Ein Standbild aus dem Clip (schnell, für Sichtung ohne FFmpeg-Preview-MP4).
     */
    public function ensurePoster(IngestFile $ingestFile, bool $force = false): ?string
    {
        if (! $ingestFile->isIngestVideoFile()) {
            return null;
        }

        if (! (bool) config('ingest.thumbs.enabled', true)) {
            return null;
        }

        if (! (bool) config('ingest.thumbs.generate_video_posters', true)) {
            return null;
        }

        $sourcePath = (string) $ingestFile->absolute_path;
        if ($sourcePath === '' || ! is_file($sourcePath) || ! is_readable($sourcePath)) {
            return null;
        }

        $thumbPath = $this->posterPathFor($ingestFile);
        if ($thumbPath === '') {
            return null;
        }

        if (! $force && is_file($thumbPath) && filesize($thumbPath) > 0) {
            $this->persistThumbPath($ingestFile, $thumbPath);

            return $thumbPath;
        }

        $stored = $this->resolveStoredThumbPath($ingestFile);
        if (! $force && $stored !== null && is_file($stored) && filesize($stored) > 0) {
            return $stored;
        }

        File::ensureDirectoryExists(dirname($thumbPath));

        $frameJpg = dirname($thumbPath).'/ingest-poster-src-'.$ingestFile->id.'-'.uniqid('', true).'.jpg';
        $seek = $this->resolvePosterSeekSeconds($ingestFile, $sourcePath);

        try {
            if (! $this->extractFrameJpeg($sourcePath, $frameJpg, $seek)) {
                return null;
            }

            $manager = ImageManager::gd();
            $image = $manager->read($frameJpg);
            $maxWidth = max(120, (int) config('ingest.thumbs.max_width', 480));
            $quality = max(40, min(95, (int) config('ingest.thumbs.quality', 60)));
            $thumb = $image->scaleDown(width: $maxWidth);
            File::put($thumbPath, (string) $thumb->toWebp($quality));
        } catch (\Throwable $e) {
            Log::warning('ingest.video_poster.generate_failed', [
                'ingest_file_id' => $ingestFile->id,
                'source' => $sourcePath,
                'error' => $e->getMessage(),
            ]);

            return null;
        } finally {
            if (is_file($frameJpg)) {
                @unlink($frameJpg);
            }
        }

        if (! is_file($thumbPath) || filesize($thumbPath) < 64) {
            return null;
        }

        $this->persistThumbPath($ingestFile, $thumbPath);

        return $thumbPath;
    }

    public function resolveStoredThumbPath(IngestFile $ingestFile): ?string
    {
        $stored = $ingestFile->getAttribute('thumb_path');
        if (! is_string($stored) || trim($stored) === '') {
            return null;
        }

        return $stored;
    }

    private function extractFrameJpeg(string $sourcePath, string $outputJpg, float $seekSeconds): bool
    {
        $ffmpeg = (string) config('media.ffmpeg_path', 'ffmpeg');
        if (str_contains($ffmpeg, DIRECTORY_SEPARATOR) && ! is_file($ffmpeg)) {
            return false;
        }

        $maxW = max(120, (int) config('ingest.thumbs.max_width', 480));
        $threads = max(1, (int) config('ingest.preview.ffmpeg_threads', 1));

        $cmd = [
            $ffmpeg,
            '-hide_banner',
            '-nostdin',
            '-y',
            '-threads', (string) $threads,
            '-filter_threads', '1',
            '-ss', sprintf('%.3f', max(0.0, $seekSeconds)),
            '-i', $sourcePath,
            '-frames:v', '1',
            '-vf', 'scale='.$maxW.':-2:flags=fast_bilinear',
            '-q:v', '3',
            $outputJpg,
        ];

        $process = new Process($cmd);
        $process->setTimeout(max(30, (int) config('ingest.thumbs.video_poster_timeout_seconds', 90)));
        $process->run();

        return $process->isSuccessful() && is_file($outputJpg) && filesize($outputJpg) > 128;
    }

    private function resolvePosterSeekSeconds(IngestFile $ingestFile, string $sourcePath): float
    {
        $configured = (float) config('ingest.thumbs.video_poster_seek_seconds', 1.0);
        $duration = $ingestFile->duration_s !== null ? (float) $ingestFile->duration_s : null;
        if ($duration === null || $duration <= 0) {
            $probe = $this->ffprobe->analyze($sourcePath);
            if ($probe['ok'] && isset($probe['data']['duration_s'])) {
                $duration = (float) $probe['data']['duration_s'];
            }
        }

        if ($duration !== null && $duration > 2.0) {
            return min(max(0.5, $configured), max(0.5, $duration * 0.08));
        }

        return max(0.0, $configured);
    }

    private function posterPathFor(IngestFile $ingestFile): string
    {
        $thumbRoot = rtrim((string) config('ingest.paths.thumbs', ''), '/');
        if ($thumbRoot === '') {
            return '';
        }

        $fingerprint = sha1(
            (string) $ingestFile->id.'|'
            .(string) $ingestFile->absolute_path.'|'
            .(string) (@filemtime((string) $ingestFile->absolute_path) ?: 0).'|'
            .(string) (@filesize((string) $ingestFile->absolute_path) ?: 0)
            .'|poster'
        );

        return $thumbRoot.'/ingest-'.$ingestFile->id.'-'.$fingerprint.'-poster.webp';
    }

    private function persistThumbPath(IngestFile $ingestFile, string $thumbPath): void
    {
        if (! Schema::hasTable('ingest_files') || ! Schema::hasColumn('ingest_files', 'thumb_path')) {
            return;
        }

        if ((string) $ingestFile->getAttribute('thumb_path') === $thumbPath) {
            return;
        }

        $ingestFile->forceFill(['thumb_path' => $thumbPath])->save();
    }

    public function removePoster(IngestFile $ingestFile): void
    {
        $stored = $this->resolveStoredThumbPath($ingestFile);
        if ($stored !== null && is_file($stored)) {
            @unlink($stored);
        }

        $generated = $this->posterPathFor($ingestFile);
        if ($generated !== '' && is_file($generated) && $generated !== $stored) {
            @unlink($generated);
        }

        if (Schema::hasTable('ingest_files') && Schema::hasColumn('ingest_files', 'thumb_path')) {
            $ingestFile->forceFill(['thumb_path' => null])->save();
        }
    }
}
