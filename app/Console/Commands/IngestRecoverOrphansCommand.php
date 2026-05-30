<?php

namespace App\Console\Commands;

use App\Jobs\ValidateIngestFileJob;
use App\Models\IngestBatch;
use App\Models\IngestFile;
use App\Models\IngestSource;
use App\Services\Ingest\IngestDirectoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class IngestRecoverOrphansCommand extends Command
{
    protected $signature = 'ingest:recover-orphans
                            {--dry-run : Nur anzeigen, nicht importieren}';

    protected $description = 'Registriert MP4/MOV-Dateien in processing/, die ohne ingest_files-Datensatz liegen (z. B. nach Scan-Fehler).';

    public function handle(IngestDirectoryService $directories): int
    {
        if (! config('ingest.enabled', false)) {
            $this->error('INGEST_ENABLED=false — Abbruch.');

            return self::FAILURE;
        }

        $processing = $directories->path('processing');
        if ($processing === '' || ! is_dir($processing)) {
            $this->error('Processing-Ordner nicht gefunden.');

            return self::FAILURE;
        }

        $source = IngestSource::where('slug', 'mc60-default')->first()
            ?? IngestSource::where('is_active', true)->first();
        if (! $source) {
            $this->error('Keine Ingest-Quelle.');

            return self::FAILURE;
        }

        $allowed = array_map('strtolower', config('ingest.allowed_extensions', ['mp4', 'mov']));
        $paths = File::glob(rtrim($processing, '/').'/*') ?: [];
        $recovered = 0;

        foreach ($paths as $fullPath) {
            if (! is_file($fullPath)) {
                continue;
            }

            $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
            if (! in_array($ext, $allowed, true)) {
                continue;
            }

            if (filesize($fullPath) < 1) {
                continue;
            }

            if (IngestFile::query()->where('absolute_path', $fullPath)->exists()) {
                continue;
            }

            $basename = basename($fullPath);
            $this->line('Verwaist: '.$basename);

            if ($this->option('dry-run')) {
                $recovered++;

                continue;
            }

            $hash = hash_file('sha256', $fullPath);
            if ($hash !== false && IngestFile::where('content_hash', $hash)->exists()) {
                $this->warn('  Übersprungen (Hash bereits in DB).');

                continue;
            }

            $batch = IngestBatch::create([
                'ingest_source_id' => $source->id,
                'reference_label' => 'recover-'.now()->format('Y-m-d_H-i-s'),
                'scanned_at' => now(),
            ]);

            $record = IngestFile::create([
                'ingest_source_id' => $source->id,
                'ingest_batch_id' => $batch->id,
                'original_name' => $basename,
                'relative_path' => $basename,
                'absolute_path' => $fullPath,
                'file_size' => (int) filesize($fullPath),
                'content_hash' => $hash ?: null,
                'mime' => mime_content_type($fullPath) ?: null,
                'status' => IngestFile::STATUS_IMPORTED,
            ]);

            ValidateIngestFileJob::dispatch($record->id);
            $this->info('  → ingest_files #'.$record->id.' angelegt, Validierung gestartet.');
            $recovered++;
        }

        $this->info('Fertig. '.$recovered.' Datei(en)'.($this->option('dry-run') ? ' (Dry-Run)' : ' wiederhergestellt.').'.');

        return self::SUCCESS;
    }
}
