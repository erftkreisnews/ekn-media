<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class QueueHeartbeatCommand extends Command
{
    protected $signature = 'queue:heartbeat {queue=default : Queue-Name (nur für Anzeige/Debug)}';

    protected $description = 'Schreibt einen Cron-Heartbeat in den Cache für die Jobs-Statusseite.';

    public function handle(): int
    {
        $queue = (string) $this->argument('queue');

        Cache::put('cron_heartbeat_at', now()->toIso8601String(), 300);

        $this->line(sprintf('Cron-Heartbeat gesetzt (%s).', $queue));

        return self::SUCCESS;
    }
}
