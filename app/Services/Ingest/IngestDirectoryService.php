<?php

namespace App\Services\Ingest;

use Illuminate\Support\Facades\File;

class IngestDirectoryService
{
    public function ensureDirectoriesExist(): void
    {
        foreach (config('ingest.paths', []) as $path) {
            if (is_string($path) && $path !== '') {
                File::ensureDirectoryExists($path);
            }
        }
    }

    public function path(string $key): string
    {
        return (string) config('ingest.paths.'.$key, '');
    }

    /**
     * Prüft, ob eine Datei unter einem der konfigurierten Ingest-Wurzelverzeichnisse liegt (lokal, kein Symlink-Escape).
     */
    public function isManagedAbsoluteFile(string $absolutePath): bool
    {
        $real = realpath($absolutePath);
        if ($real === false || ! is_file($real)) {
            return false;
        }

        foreach (config('ingest.paths', []) as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }
            $root = realpath($path);
            if ($root === false) {
                continue;
            }
            if (str_starts_with($real, $root.DIRECTORY_SEPARATOR) || $real === $root) {
                return true;
            }
        }

        return false;
    }
}
