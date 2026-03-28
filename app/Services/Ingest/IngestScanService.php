<?php

namespace App\Services\Ingest;

use App\Jobs\ValidateIngestFileJob;
use App\Models\IngestBatch;
use App\Models\IngestFile;
use App\Models\IngestSource;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class IngestScanService
{
    public function __construct(
        protected IngestDirectoryService $directories,
    ) {}

    /**
     * @return array{imported: int, skipped: int, errors: list<string>}
     */
    public function scanInbox(?IngestSource $source = null): array
    {
        if (! config('ingest.enabled', false)) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['Ingest ist per INGEST_ENABLED=false deaktiviert.']];
        }

        $this->directories->ensureDirectoriesExist();

        $source = $source ?? IngestSource::where('slug', 'mc60-default')->first()
            ?? IngestSource::where('is_active', true)->first();

        if (! $source) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['Keine Ingest-Quelle konfiguriert.']];
        }

        $inbox = $this->directories->path('inbox');
        $processing = $this->directories->path('processing');

        if ($inbox === '' || $processing === '') {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['Ingest-Pfade fehlen in config/ingest.php']];
        }

        $batch = IngestBatch::create([
            'ingest_source_id' => $source->id,
            'reference_label' => 'scan-'.now()->format('Y-m-d_H-i-s'),
            'scanned_at' => now(),
        ]);

        $imported = 0;
        $skipped = 0;
        $errors = [];

        $allowed = array_map('strtolower', config('ingest.allowed_extensions', ['mp4']));
        $interval = max(1, (int) config('ingest.stable_check_interval_seconds', 2));
        $checks = max(2, (int) config('ingest.stable_checks_required', 2));

        $files = File::glob(rtrim($inbox, '/').'/*') ?: [];
        foreach ($files as $fullPath) {
            if (! is_file($fullPath)) {
                continue;
            }

            $basename = basename($fullPath);
            if (str_starts_with($basename, '.')) {
                continue;
            }

            $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
            if (! in_array($ext, $allowed, true)) {
                $skipped++;

                continue;
            }

            if (! $this->isFileStable($fullPath, $interval, $checks)) {
                Log::info('ingest.scan.unstable', ['path' => $fullPath]);
                $skipped++;

                continue;
            }

            $hash = hash_file('sha256', $fullPath);
            if ($hash !== false && IngestFile::where('content_hash', $hash)->exists()) {
                Log::info('ingest.scan.duplicate', ['hash' => $hash]);
                $skipped++;

                continue;
            }

            $targetName = now()->format('Ymd_His').'_'.Str::uuid().'_'.$basename;
            $targetPath = rtrim($processing, '/').'/'.$targetName;

            try {
                if (! @rename($fullPath, $targetPath)) {
                    $errors[] = 'Verschieben fehlgeschlagen: '.$basename;

                    continue;
                }
            } catch (\Throwable $e) {
                $errors[] = $basename.': '.$e->getMessage();

                continue;
            }

            $mime = mime_content_type($targetPath) ?: null;

            $record = IngestFile::create([
                'ingest_source_id' => $source->id,
                'ingest_batch_id' => $batch->id,
                'original_name' => $basename,
                'relative_path' => $targetName,
                'absolute_path' => $targetPath,
                'file_size' => (int) filesize($targetPath),
                'content_hash' => $hash ?: null,
                'mime' => $mime,
                'status' => IngestFile::STATUS_IMPORTED,
            ]);

            $imported++;
            ValidateIngestFileJob::dispatch($record->id);
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    private function isFileStable(string $path, int $intervalSeconds, int $checksRequired): bool
    {
        $lastSize = null;
        $lastMtime = null;
        for ($i = 0; $i < $checksRequired; $i++) {
            clearstatcache(true, $path);
            if (! is_file($path)) {
                return false;
            }
            $size = filesize($path);
            $mtime = filemtime($path);
            if ($lastSize !== null && ($size !== $lastSize || $mtime !== $lastMtime)) {
                return false;
            }
            $lastSize = $size;
            $lastMtime = $mtime;
            if ($i < $checksRequired - 1) {
                sleep($intervalSeconds);
            }
        }

        return true;
    }
}
