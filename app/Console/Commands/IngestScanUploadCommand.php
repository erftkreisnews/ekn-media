<?php

namespace App\Console\Commands;

use App\Services\Ingest\IngestScanService;
use Illuminate\Console\Command;

/**
 * Alias für {@see IngestScanInboxCommand}: gleiche Scan-Logik, klarer Name für den Upload-Eingangsordner.
 */
class IngestScanUploadCommand extends Command
{
    protected $signature = 'ingest:scan-upload';

    protected $description = 'Scannt den konfigurierten Ingest-Inbox-Ordner (identisch zu ingest:scan-inbox).';

    public function handle(IngestScanService $scan): int
    {
        $result = $scan->scanInbox();

        $this->info('Importiert: '.$result['imported'].', übersprungen: '.$result['skipped']);

        foreach ($result['errors'] ?? [] as $err) {
            $this->warn($err);
        }

        return self::SUCCESS;
    }
}
