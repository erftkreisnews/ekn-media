<?php

namespace App\Console\Commands;

use App\Models\IngestRenderJob;
use App\Services\Ingest\IngestDirectoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class IngestCleanupCommand extends Command
{
    protected $signature = 'ingest:cleanup
                            {--tmp-max-age-hours=48 : Dateien im tmp-Ordner älter als X Stunden löschen}
                            {--remove-completed-local-renders : Abgeschlossene lokale Render-MP4s entfernen (nur wenn Job completed)}';

    protected $description = 'Räumt Ingest-tmp auf und optional alte lokale Render-Ausgaben nach erfolgreichem S3-Upload.';

    public function handle(IngestDirectoryService $directories): int
    {
        $directories->ensureDirectoriesExist();

        $tmp = $directories->path('tmp');
        $maxAge = max(1, (int) $this->option('tmp-max-age-hours'));
        $cutoff = time() - ($maxAge * 3600);

        $removedTmp = 0;
        if ($tmp !== '' && is_dir($tmp)) {
            foreach (File::glob(rtrim($tmp, '/').'/*') ?: [] as $path) {
                if (! is_file($path)) {
                    continue;
                }
                if (@filemtime($path) < $cutoff) {
                    if (@unlink($path)) {
                        $removedTmp++;
                    }
                }
            }
        }

        $this->info('Tmp-Dateien entfernt: '.$removedTmp);

        if ($this->option('remove-completed-local-renders')) {
            $removed = 0;
            IngestRenderJob::query()
                ->where('status', IngestRenderJob::STATUS_COMPLETED)
                ->whereNotNull('local_output_path')
                ->chunkById(50, function ($jobs) use (&$removed) {
                    foreach ($jobs as $job) {
                        $p = $job->local_output_path;
                        if (is_string($p) && $p !== '' && is_file($p)) {
                            if (@unlink($p)) {
                                $removed++;
                                $job->update(['local_output_path' => null]);
                            }
                        }
                    }
                });

            $this->info('Lokale Render-Dateien entfernt: '.$removed);
        }

        return self::SUCCESS;
    }
}
