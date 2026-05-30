<?php

namespace App\Console\Commands;

use App\Jobs\QueueWorkerHeartbeatJob;
use Illuminate\Console\Command;

class QueueWorkerHeartbeatCommand extends Command
{
    protected $signature = 'queue:worker-heartbeat {queue=heartbeat : Queue für den Heartbeat-Job}';

    protected $description = 'Stellt Heartbeat-Jobs in die Queue und verarbeitet sie (eigene Queue, unabhängig von langen Render-Jobs).';

    public function handle(): int
    {
        $queue = (string) $this->argument('queue');
        if ($queue === 'default') {
            $queue = 'heartbeat';
        }

        QueueWorkerHeartbeatJob::dispatch()->onQueue($queue);

        // Heartbeat sofort abarbeiten — blockiert nicht hinter ProcessIngestRenderJob auf „default“.
        $exit = $this->call('queue:work', [
            '--queue' => $queue,
            '--stop-when-empty' => true,
            '--max-jobs' => 30,
            '--sleep' => 1,
            '--timeout' => 30,
            '--tries' => 1,
        ]);

        $this->line(sprintf('Queue-Worker-Heartbeat auf Queue "%s" verarbeitet.', $queue));

        return $exit === 0 ? self::SUCCESS : self::FAILURE;
    }
}
