<?php

namespace App\Console\Commands;

use App\Models\IngestRenderJob;
use App\Services\Ingest\IngestDirectoryService;
use App\Services\Ingest\IngestFilesystemCleanupService;
use Illuminate\Console\Command;

class IngestCleanupCommand extends Command
{
    protected $signature = 'ingest:cleanup
                            {--tmp-max-age-hours= : Tmp-Ordner: Dateien älter als X Stunden löschen (0 = überspringen, Default: config ingest.cleanup.tmp_max_age_hours)}
                            {--inbox-max-age-hours= : Inbox/Upload: Dateien älter als X Stunden löschen (0 = überspringen, Default: config ingest.cleanup.inbox_max_age_hours)}
                            {--failed-max-age-hours= : Failed-Ordner: Dateien älter als X Stunden löschen (0 = überspringen, Default: config ingest.cleanup.failed_max_age_hours)}
                            {--remove-completed-local-renders : Abgeschlossene lokale Render-MP4s entfernen (nur wenn Job completed)}';

    protected $description = 'Räumt Ingest-Inbox, Tmp und optional Failed auf; optional alte lokale Render-Ausgaben nach erfolgreichem S3-Upload.';

    public function handle(IngestDirectoryService $directories, IngestFilesystemCleanupService $fsCleanup): int
    {
        $directories->ensureDirectoriesExist();

        $tmpHours = $this->resolvedHoursOption('tmp-max-age-hours', 'ingest.cleanup.tmp_max_age_hours', 24);
        $inboxHours = $this->resolvedHoursOption('inbox-max-age-hours', 'ingest.cleanup.inbox_max_age_hours', 24);
        $failedHours = $this->resolvedHoursOption('failed-max-age-hours', 'ingest.cleanup.failed_max_age_hours', 24);

        $tmp = $directories->path('tmp');
        $inbox = $directories->path('inbox');
        $failed = $directories->path('failed');
        $thumbs = $directories->path('thumbs');

        $removedInbox = $fsCleanup->pruneTopLevelFilesOlderThanHours($inbox, $inboxHours);
        $this->info('Inbox/Upload-Dateien entfernt (älter als '.($inboxHours > 0 ? $inboxHours.'h' : '—').'): '.$removedInbox);

        $removedTmp = $fsCleanup->pruneTopLevelFilesOlderThanHours($tmp, $tmpHours);
        $this->info('Tmp-Dateien entfernt (älter als '.($tmpHours > 0 ? $tmpHours.'h' : '—').'): '.$removedTmp);

        $removedFailed = $fsCleanup->pruneTopLevelFilesOlderThanHours($failed, $failedHours);
        $this->info('Failed-Dateien entfernt (älter als '.($failedHours > 0 ? $failedHours.'h' : '—').'): '.$removedFailed);

        $removedThumbOrphans = $fsCleanup->pruneOrphanIngestThumbnails($thumbs);
        $this->info('Ingest-Thumb-Orphans entfernt: '.$removedThumbOrphans);

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

    private function resolvedHoursOption(string $optionName, string $configKey, int $fallbackDefault): int
    {
        if ($this->input->hasParameterOption('--'.$optionName)) {
            return max(0, (int) $this->option($optionName));
        }

        $v = config($configKey);

        return max(0, is_numeric($v) ? (int) $v : $fallbackDefault);
    }
}
