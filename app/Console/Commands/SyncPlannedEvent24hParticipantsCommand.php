<?php

namespace App\Console\Commands;

use App\Models\PlannedEvent;
use App\Services\PlannedEvents\SyncPlannedEvent24hParticipants;
use Illuminate\Console\Command;

class SyncPlannedEvent24hParticipantsCommand extends Command
{
    protected $signature = 'planned-events:sync-24h-teilnehmer
                            {plannedEvent : ID der geplanten Veranstaltung}
                            {--dry-run : Nur Abgleich anzeigen, nichts speichern}
                            {--no-images : Keine Referenzfotos herunterladen}';

    protected $description = 'Starterliste von 24h-rennen.de/teilnehmer/ abgleichen und Referenzfotos speichern';

    public function handle(SyncPlannedEvent24hParticipants $sync): int
    {
        $eventId = (int) $this->argument('plannedEvent');
        $event = PlannedEvent::query()->find($eventId);
        if (! $event) {
            $this->error('Veranstaltung #'.$eventId.' nicht gefunden.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $downloadImages = ! (bool) $this->option('no-images');

        $this->info(($dryRun ? '[Dry-Run] ' : '').'Abgleich: '.$event->name);

        try {
            $stats = $sync->sync($event, $downloadImages, $dryRun);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Kennzahl', 'Anzahl'],
            [
                ['Online-Liste', (string) $stats['fetched']],
                ['Aktualisiert', (string) $stats['updated']],
                ['Neu angelegt', (string) $stats['created']],
                ['Entfernt', (string) $stats['removed']],
                ['Referenzfotos geladen', (string) $stats['images_downloaded']],
                ['Referenzfotos unverändert', (string) $stats['images_skipped']],
            ]
        );

        foreach ($stats['errors'] as $error) {
            $this->warn($error);
        }

        if ($dryRun) {
            $this->comment('Dry-Run: keine Änderungen in der Datenbank.');
        } else {
            $this->info('Abgleich abgeschlossen.');
        }

        return $stats['errors'] !== [] ? self::FAILURE : self::SUCCESS;
    }
}
