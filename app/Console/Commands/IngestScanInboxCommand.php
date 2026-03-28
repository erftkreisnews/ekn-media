<?php

namespace App\Console\Commands;

use App\Services\Ingest\IngestScanService;
use Illuminate\Console\Command;

class IngestScanInboxCommand extends Command
{
    protected $signature = 'ingest:scan-inbox';

    protected $description = 'Scannt das Ingest-Inbox-Verzeichnis, importiert stabile Rohvideos und startet die Validierung. (Alias: ingest:scan-upload)';

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
