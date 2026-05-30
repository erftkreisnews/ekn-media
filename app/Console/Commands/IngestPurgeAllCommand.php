<?php

namespace App\Console\Commands;

use App\Models\IngestBatch;
use App\Models\IngestFile;
use App\Models\IngestRenderJob;
use App\Services\Ingest\IngestDirectoryService;
use App\Services\Ingest\IngestImageThumbnailService;
use Illuminate\Console\Command;

class IngestPurgeAllCommand extends Command
{
    public const CONFIRM_PHRASE = 'INGEST ALLES LOESCHEN';

    protected $signature = 'ingest:purge-all
                            {--force : Notfallmodus explizit aktivieren}
                            {--confirm= : Sicherheitsphrase (muss exakt "INGEST ALLES LOESCHEN" sein)}';

    protected $description = 'Notfall-Reset: löscht alle Ingest-Einträge, zugehörige lokale Dateien/Thumbnails sowie Ingest-Jobs und -Batches.';

    public function handle(IngestDirectoryService $directories, IngestImageThumbnailService $thumbs): int
    {
        if (! $this->option('force')) {
            $this->error('Abbruch: --force fehlt.');

            return self::FAILURE;
        }

        $confirm = trim((string) $this->option('confirm'));
        if ($confirm !== self::CONFIRM_PHRASE) {
            $this->error('Abbruch: ungültige Sicherheitsphrase.');
            $this->line('Erwartet: '.self::CONFIRM_PHRASE);

            return self::FAILURE;
        }

        $deletedRows = 0;
        $deletedFiles = 0;

        IngestFile::query()
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$deletedRows, &$deletedFiles, $directories, $thumbs): void {
                foreach ($rows as $ingestFile) {
                    try {
                        $thumbs->removeThumbnail($ingestFile);
                    } catch (\Throwable $e) {
                        report($e);
                    }

                    $path = $ingestFile->absolute_path;
                    if (is_string($path) && $path !== '' && $directories->isManagedAbsoluteFile($path) && is_file($path)) {
                        if (@unlink($path)) {
                            $deletedFiles++;
                        }
                    }

                    $ingestFile->delete();
                    $deletedRows++;
                }
            });

        $deletedJobs = IngestRenderJob::query()->delete();
        $deletedBatches = IngestBatch::query()->delete();

        $this->info('Ingest-Notfallreset abgeschlossen.');
        $this->line('  ingest_files gelöscht: '.$deletedRows);
        $this->line('  lokale Dateien gelöscht: '.$deletedFiles);
        $this->line('  ingest_render_jobs gelöscht: '.$deletedJobs);
        $this->line('  ingest_batches gelöscht: '.$deletedBatches);

        return self::SUCCESS;
    }
}
