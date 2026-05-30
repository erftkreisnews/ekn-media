<?php

namespace App\Services\Ingest;

use App\Jobs\ValidateIngestFileJob;
use App\Models\IngestBatch;
use App\Models\IngestFile;
use App\Models\IngestSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class IngestScanService
{
    private const EMPTY_FILE_HASH = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

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

            clearstatcache(true, $fullPath);
            $size = (int) filesize($fullPath);
            if ($size < 1) {
                @unlink($fullPath);
                $skipped++;

                continue;
            }

            $hash = hash_file('sha256', $fullPath);
            if ($hash === self::EMPTY_FILE_HASH) {
                @unlink($fullPath);
                $skipped++;

                continue;
            }

            if ($hash !== false && IngestFile::where('content_hash', $hash)->exists()) {
                Log::debug('ingest.scan.duplicate', ['hash' => $hash]);
                $skipped++;

                continue;
            }

            $batchId = $this->resolveBatchId($batch);

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

            try {
                $record = DB::transaction(function () use ($source, $batchId, $basename, $targetName, $targetPath, $size, $hash, $mime) {
                    if (! IngestBatch::whereKey($batchId)->exists()) {
                        throw new \RuntimeException('Ingest-Batch #'.$batchId.' existiert nicht mehr.');
                    }

                    return IngestFile::create([
                        'ingest_source_id' => $source->id,
                        'ingest_batch_id' => $batchId,
                        'original_name' => $basename,
                        'relative_path' => $targetName,
                        'absolute_path' => $targetPath,
                        'file_size' => $size,
                        'content_hash' => $hash ?: null,
                        'mime' => $mime,
                        'status' => IngestFile::STATUS_IMPORTED,
                    ]);
                });
            } catch (\Throwable $e) {
                @rename($targetPath, $fullPath);
                Log::error('ingest.scan.import_failed', [
                    'file' => $basename,
                    'error' => $e->getMessage(),
                ]);
                $errors[] = $basename.': '.$e->getMessage();

                continue;
            }

            $imported++;
            ValidateIngestFileJob::dispatch($record->id);
        }

        if ($imported === 0 && IngestFile::where('ingest_batch_id', $batch->id)->doesntExist()) {
            $batch->delete();
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    private function resolveBatchId(IngestBatch $batch): int
    {
        if (IngestBatch::whereKey($batch->id)->exists()) {
            return (int) $batch->id;
        }

        $fresh = IngestBatch::create([
            'ingest_source_id' => $batch->ingest_source_id,
            'reference_label' => 'scan-resume-'.now()->format('Y-m-d_H-i-s'),
            'scanned_at' => now(),
        ]);

        return (int) $fresh->id;
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
