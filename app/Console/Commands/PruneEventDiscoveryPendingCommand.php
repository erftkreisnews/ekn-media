<?php

namespace App\Console\Commands;

use App\Models\EventSuggestion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PruneEventDiscoveryPendingCommand extends Command
{
    protected $signature = 'event-discovery:prune-pending
                            {source : Quelle: ticketmaster, lanxess_arena, rss oder all}
                            {--force : Ohne Rückfrage ausführen}';

    protected $description = 'Löscht alle noch offenen (pending) Event-Vorschläge einer Quelle — z. B. nach Filter-Umstellung, wenn kein Abruf mehr greift.';

    public function handle(): int
    {
        if (! Schema::hasTable('event_suggestions')) {
            $this->error('Tabelle event_suggestions fehlt.');

            return self::FAILURE;
        }

        $raw = strtolower(trim((string) $this->argument('source')));
        $allowed = ['ticketmaster', 'lanxess_arena', 'rss', 'all'];
        if (! in_array($raw, $allowed, true)) {
            $this->error('Ungültige Quelle. Erlaubt: '.implode(', ', $allowed));

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Wirklich alle pending-Vorschläge dieser Quelle löschen?', false)) {
            $this->line('Abgebrochen.');

            return self::SUCCESS;
        }

        $q = EventSuggestion::query()->where('status', EventSuggestion::STATUS_PENDING);

        if ($raw !== 'all') {
            $q->where('source', $raw);
        }

        $n = (int) $q->delete();
        $this->info('Gelöscht: '.$n.' Zeile(n).');

        return self::SUCCESS;
    }
}
