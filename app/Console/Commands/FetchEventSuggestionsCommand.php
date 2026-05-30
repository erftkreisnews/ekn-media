<?php

namespace App\Console\Commands;

use App\Services\EventDiscovery\EventSuggestionSyncService;
use Illuminate\Console\Command;

class FetchEventSuggestionsCommand extends Command
{
    protected $signature = 'event-discovery:fetch {--no-mail : Keinen E-Mail-Versand auslösen}';

    protected $description = 'Lädt öffentliche Veranstaltungsdaten (Ticketmaster / LANXESS / RSS) und legt Vorschläge für die Eventplanung an.';

    public function handle(EventSuggestionSyncService $sync): int
    {
        if (! config('event_discovery.enabled', false)) {
            $this->warn('EVENT_DISCOVERY_ENABLED ist nicht aktiv — Abbruch (siehe config/event_discovery.php).');

            return self::SUCCESS;
        }

        $result = $sync->syncRound();

        $this->info('Abruf abgeschlossen. Neu: '.count($result['new_ids']).', Aktualisierungen/Zugriffe: '.$result['updated']);
        $this->info(
            'Entfernte veraltete pending-Vorschläge (nicht mehr im letzten Abruf): Ticketmaster: '
            .($result['pruned_ticketmaster'] ?? 0).', LANXESS: '.($result['pruned_lanxess_arena'] ?? 0)
        );

        if (! $this->option('no-mail') && $result['new_ids'] !== []) {
            $sync->sendDigestMail($result['new_ids']);
            $this->info('Digest-E-Mail(en) wurden versendet (sofern Empfänger konfiguriert sind).');
        } elseif ($this->option('no-mail') && $result['new_ids'] !== []) {
            $this->line('(--no-mail) E-Mail-Versand übersprungen.');
        }

        return self::SUCCESS;
    }
}
