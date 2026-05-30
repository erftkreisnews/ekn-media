<?php

namespace App\Services\Ingest;

use App\Models\IngestFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Intervention\Image\ImageManager;

final class IngestImageThumbnailService
{
    /**
     * Erzeugt ein lokales Thumbnail für ein Ingest-Bild und speichert den Pfad in ingest_files.thumb_path.
     */
    public function ensureThumbnail(IngestFile $ingestFile, bool $force = false): ?string
    {
        if (! $ingestFile->isIngestImageFile()) {
            return null;
        }

        if (! (bool) config('ingest.thumbs.enabled', true)) {
            return null;
        }

        $sourcePath = (string) $ingestFile->absolute_path;
        if ($sourcePath === '' || ! is_file($sourcePath) || ! is_readable($sourcePath)) {
            return null;
        }

        $thumbPath = $this->thumbnailPathFor($ingestFile);
        if ($thumbPath === '') {
            return null;
        }

        if (! $force && is_file($thumbPath) && filesize($thumbPath) > 0) {
            $this->persistThumbPath($ingestFile, $thumbPath);

            return $thumbPath;
        }

        File::ensureDirectoryExists(dirname($thumbPath));

        try {
            $manager = ImageManager::gd();
            $image = $manager->read($sourcePath);
            $maxWidth = max(120, (int) config('ingest.thumbs.max_width', 480));
            $quality = max(40, min(95, (int) config('ingest.thumbs.quality', 60)));

            $thumb = $image->scaleDown(width: $maxWidth);
            File::put($thumbPath, (string) $thumb->toWebp($quality));
        } catch (\Throwable $e) {
            Log::warning('ingest.thumb.generate_failed', [
                'ingest_file_id' => $ingestFile->id,
                'source' => $sourcePath,
                'thumb' => $thumbPath,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! is_file($thumbPath) || filesize($thumbPath) < 64) {
            return null;
        }

        $this->persistThumbPath($ingestFile, $thumbPath);

        return $thumbPath;
    }

    public function removeThumbnail(IngestFile $ingestFile): void
    {
        $thumbPath = $this->resolveStoredThumbPath($ingestFile);
        if ($thumbPath !== null && is_file($thumbPath)) {
            @unlink($thumbPath);
        }

        if ($this->ingestFilesTableHasThumbPath()) {
            $ingestFile->forceFill(['thumb_path' => null])->save();
        }
    }

    public function thumbnailPathFor(IngestFile $ingestFile): string
    {
        $thumbRoot = rtrim((string) config('ingest.paths.thumbs', ''), '/');
        if ($thumbRoot === '') {
            return '';
        }

        $ext = strtolower((string) pathinfo((string) $ingestFile->original_name, PATHINFO_EXTENSION));
        $fingerprint = sha1(
            (string) $ingestFile->id.'|'
            .(string) $ingestFile->absolute_path.'|'
            .(string) (@filemtime((string) $ingestFile->absolute_path) ?: 0).'|'
            .(string) (@filesize((string) $ingestFile->absolute_path) ?: 0)
        );

        return $thumbRoot.'/ingest-'.$ingestFile->id.'-'.$fingerprint.'-'.($ext !== '' ? $ext : 'jpg').'.webp';
    }

    public function resolveStoredThumbPath(IngestFile $ingestFile): ?string
    {
        $stored = $ingestFile->getAttribute('thumb_path');
        if (! is_string($stored) || trim($stored) === '') {
            return null;
        }

        return $stored;
    }

    private function persistThumbPath(IngestFile $ingestFile, string $thumbPath): void
    {
        if (! $this->ingestFilesTableHasThumbPath()) {
            return;
        }

        if ((string) $ingestFile->getAttribute('thumb_path') === $thumbPath) {
            return;
        }

        $ingestFile->forceFill(['thumb_path' => $thumbPath])->save();
    }

    private function ingestFilesTableHasThumbPath(): bool
    {
        static $hasColumn = null;

        if ($hasColumn === null) {
            $hasColumn = Schema::hasTable('ingest_files') && Schema::hasColumn('ingest_files', 'thumb_path');
        }

        return $hasColumn;
    }
}
