<?php

namespace App\Console\Commands;

use App\Models\NewsItemMedia;
use Illuminate\Console\Command;

class BackfillImageCaptureTimeCommand extends Command
{
    protected $signature = 'media:backfill-capture-time
                            {--dry-run : Nur zählen, nichts speichern}
                            {--limit=0 : Max. Anzahl Datensätze (0 = alle)}';

    protected $description = 'Setzt fehlende capture_time bei Bildern (EXIF, Quellvideo, Upload-Datum).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(0, (int) $this->option('limit'));

        $query = NewsItemMedia::query()
            ->where('type', 'image')
            ->whereNull('capture_time')
            ->orderBy('id');

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('Keine Bilder ohne capture_time gefunden.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[Dry-Run] ' : '').$total.' Bild(er) ohne capture_time.');

        if ($dryRun) {
            return self::SUCCESS;
        }

        $updated = 0;
        $processed = 0;

        $query->chunkById(100, function ($chunk) use (&$updated, &$processed, $limit): void {
            foreach ($chunk as $medium) {
                if ($limit > 0 && $processed >= $limit) {
                    return;
                }

                $processed++;
                if ($medium->backfillCaptureTimeIfMissing()) {
                    $updated++;
                }
            }
        });

        $this->info("Fertig: {$updated} von {$processed} Datensätzen mit capture_time ergänzt.");

        return self::SUCCESS;
    }
}
