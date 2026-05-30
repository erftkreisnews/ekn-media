<?php

namespace App\Console\Commands;

use App\Jobs\GenerateIngestPreviewJob;
use App\Models\IngestFile;
use App\Services\MediaStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SyncIngestPreviewsToLocalCommand extends Command
{
    protected $signature = 'ingest:sync-previews-to-local
                            {--id= : Nur diese ingest_files-ID}
                            {--regenerate : Fehlende lokale Vorschau per FFmpeg neu erzeugen statt von S3 kopieren}';

    protected $description = 'Kopiert Ingest-Vorschauen vom Medien-S3 auf den lokalen Preview-Disk (public), falls nur dort vorhanden.';

    public function handle(MediaStorage $mediaStorage): int
    {
        $previewDiskName = $mediaStorage->ingestPreviewDiskName();
        $previewDisk = $mediaStorage->ingestPreviewDisk();
        $activeDiskName = $mediaStorage->activeDiskName();

        if ($previewDiskName === $activeDiskName) {
            $this->warn('Preview-Disk ist identisch mit active disk — nichts zu synchronisieren.');

            return self::SUCCESS;
        }

        $query = IngestFile::query()
            ->whereNotNull('preview_path')
            ->where('preview_path', '!=', '')
            ->orderBy('id');

        $id = $this->option('id');
        if (is_string($id) && ctype_digit(trim($id))) {
            $query->where('id', (int) $id);
        }

        $copied = 0;
        $queued = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($query->cursor() as $file) {
            if (! $file->isIngestVideoCandidate()) {
                $skipped++;

                continue;
            }

            $path = (string) $file->preview_path;
            if ($previewDisk->exists($path)) {
                $skipped++;

                continue;
            }

            if ($this->option('regenerate')) {
                GenerateIngestPreviewJob::dispatch($file->id);
                $queued++;

                continue;
            }

            if ($activeDiskName === $previewDiskName || ! Storage::disk($activeDiskName)->exists($path)) {
                GenerateIngestPreviewJob::dispatch($file->id);
                $queued++;

                continue;
            }

            try {
                $stream = Storage::disk($activeDiskName)->readStream($path);
                if ($stream === false) {
                    throw new \RuntimeException('readStream fehlgeschlagen');
                }

                $ok = $previewDisk->writeStream($path, $stream, [
                    'visibility' => 'public',
                    'ContentType' => 'video/mp4',
                ]);
                if (is_resource($stream)) {
                    fclose($stream);
                }

                if (! $ok || ! $previewDisk->exists($path)) {
                    throw new \RuntimeException('writeStream fehlgeschlagen');
                }

                $file->update([
                    'status' => IngestFile::STATUS_PREVIEW_READY,
                    'preview_status' => IngestFile::PREVIEW_STATUS_READY,
                    'preview_error_message' => null,
                ]);
                $copied++;
                $this->line("  #{$file->id} → {$previewDiskName}:{$path}");
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('ingest.sync_preview_to_local_failed', [
                    'ingest_file_id' => $file->id,
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
                $this->error("  #{$file->id} fehlgeschlagen: ".$e->getMessage());
            }
        }

        $this->info("Fertig. Kopiert: {$copied}, neu eingereiht: {$queued}, übersprungen: {$skipped}, fehlgeschlagen: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
