<?php

namespace App\Console\Commands;

use App\Models\NewsItemMedia;
use Illuminate\Console\Command;

class FixRedactionNoDetectionCommand extends Command
{
    protected $signature = 'media:fix-redaction-no-detection {--dry-run : Nur zählen, nichts ändern}';

    protected $description = 'Setzt Redaction-Status „failed“ (keine Erkennung) auf „done“, damit Bilder sofort auslieferbar sind.';

    public function handle(): int
    {
        $query = NewsItemMedia::query()
            ->where('type', 'image')
            ->where('redaction_status', NewsItemMedia::REDACTION_FAILED)
            ->where(function ($q) {
                $q->where('redaction_error', 'like', '%keine Bereiche%')
                    ->orWhere('redaction_error', 'like', '%no boxes%')
                    ->orWhere('redaction_error', 'like', '%no detection%');
            });

        $count = (int) $query->count();
        if ($count === 0) {
            $this->info('Keine betroffenen Datensätze.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Dry-run: {$count} Datensätze würden auf „done“ gesetzt.");

            return self::SUCCESS;
        }

        $updated = $query->update([
            'redaction_status' => NewsItemMedia::REDACTION_DONE,
            'redaction_error' => null,
            'redacted_at' => now(),
        ]);

        $this->info("{$updated} Bilder: Redaction „failed“ (keine Erkennung) → „done“ (Original auslieferbar).");

        return self::SUCCESS;
    }
}
