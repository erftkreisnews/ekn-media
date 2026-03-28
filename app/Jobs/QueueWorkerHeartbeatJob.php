<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Wird vom Scheduler jede Minute in die Queue gestellt. Wenn der Queue-Worker läuft,
 * wird dieser Job ausgeführt und schreibt einen Zeitstempel – für die Ampel-Anzeige
 * auf der Admin-Seite „Jobs / Warteschlange“ (Queue-Worker erreichbar = grün).
 */
class QueueWorkerHeartbeatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Cache::put('queue_worker_heartbeat_at', now()->toIso8601String(), 300);
    }
}
