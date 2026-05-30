<?php

namespace App\Services\Ingest;

use App\Models\IngestFile;
use Illuminate\Support\Facades\File;

/**
 * Löscht Dateien in einem Verzeichnis, deren mtime älter als die angegebene Frist ist.
 * Nur oberste Ebene (wie der Ingest-Inbox-Scan), keine Unterordner.
 */
final class IngestFilesystemCleanupService
{
    /**
     * @return int Anzahl gelöschter Dateien
     */
    public function pruneTopLevelFilesOlderThanHours(string $directory, int $maxAgeHours): int
    {
        if ($maxAgeHours <= 0) {
            return 0;
        }

        $directory = rtrim($directory, '/\\');
        if ($directory === '' || ! is_dir($directory)) {
            return 0;
        }

        $cutoff = time() - ($maxAgeHours * 3600);
        $removed = 0;

        foreach (File::glob($directory.'/*') ?: [] as $path) {
            if (! is_file($path)) {
                continue;
            }
            $base = basename($path);
            if (str_starts_with($base, '.')) {
                continue;
            }
            clearstatcache(true, $path);
            if (@filemtime($path) !== false && filemtime($path) < $cutoff) {
                if (@unlink($path)) {
                    $removed++;
                }
            }
        }

        return $removed;
    }

    /**
     * Löscht lokale Ingest-Thumbnails ohne referenzierenden ingest_files-Eintrag.
     *
     * @return int Anzahl gelöschter Orphans
     */
    public function pruneOrphanIngestThumbnails(string $directory): int
    {
        $directory = rtrim($directory, '/\\');
        if ($directory === '' || ! is_dir($directory)) {
            return 0;
        }

        $validThumbPaths = IngestFile::query()
            ->whereNotNull('thumb_path')
            ->pluck('thumb_path')
            ->filter(fn ($p) => is_string($p) && trim($p) !== '')
            ->map(fn ($p) => $this->normalizePath((string) $p))
            ->filter()
            ->all();

        $validMap = array_fill_keys($validThumbPaths, true);
        $removed = 0;

        foreach (File::glob($directory.'/*') ?: [] as $path) {
            if (! is_file($path)) {
                continue;
            }

            $normalized = $this->normalizePath($path);
            if ($normalized !== null && isset($validMap[$normalized])) {
                continue;
            }

            if (@unlink($path)) {
                $removed++;
            }
        }

        return $removed;
    }

    private function normalizePath(string $path): ?string
    {
        $real = realpath($path);
        if ($real !== false) {
            return $real;
        }

        $trimmed = trim($path);

        return $trimmed !== '' ? $trimmed : null;
    }
}
