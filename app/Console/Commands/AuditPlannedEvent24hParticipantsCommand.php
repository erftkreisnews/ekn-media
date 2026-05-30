<?php

namespace App\Console\Commands;

use App\Models\PlannedEvent;
use App\Models\PlannedEventTeam;
use App\Services\PlannedEvents\Adac24hParticipantListFetcher;
use App\Support\PlannedEventTeamReferenceImageLocator;
use Illuminate\Console\Command;

class AuditPlannedEvent24hParticipantsCommand extends Command
{
    protected $signature = 'planned-events:audit-24h-teilnehmer {plannedEvent : ID der geplanten Veranstaltung}';

    protected $description = 'Starterliste im System mit 24h-rennen.de/teilnehmer/ vergleichen';

    public function handle(Adac24hParticipantListFetcher $fetcher): int
    {
        $event = PlannedEvent::query()->find((int) $this->argument('plannedEvent'));
        if (! $event) {
            $this->error('Veranstaltung nicht gefunden.');

            return self::FAILURE;
        }

        $web = [];
        foreach ($fetcher->fetch() as $entry) {
            $web[$entry['start_number']] = $entry;
        }

        $db = [];
        foreach ($event->teams()->get() as $team) {
            $nr = $team->startNumber();
            if ($nr !== null) {
                $db[$nr] = $team;
            }
        }

        $onlyWeb = array_diff(array_keys($web), array_keys($db));
        $onlyDb = array_diff(array_keys($db), array_keys($web));
        $mismatches = [];

        foreach ($web as $nr => $w) {
            $team = $db[$nr] ?? null;
            if (! $team instanceof PlannedEventTeam) {
                continue;
            }

            $issues = [];
            if (trim($team->name) !== trim($fetcher->formatTeamName($w))) {
                $issues[] = 'name';
            }
            if (trim((string) $team->notes) !== trim($fetcher->formatTeamNotes($w))) {
                $issues[] = 'notes';
            }
            if (! filled($team->reference_image_path) || ! PlannedEventTeamReferenceImageLocator::exists((string) $team->reference_image_path)) {
                $issues[] = 'referenzfoto';
            }

            if ($issues !== []) {
                $mismatches[$nr] = $issues;
            }
        }

        $this->info($event->name);
        $this->table(['Quelle', 'Anzahl'], [
            ['24h-rennen.de', (string) count($web)],
            ['Datenbank', (string) count($db)],
            ['Nur online', (string) count($onlyWeb)],
            ['Nur DB', (string) count($onlyDb)],
            ['Abweichungen', (string) count($mismatches)],
        ]);

        if ($onlyWeb !== []) {
            $this->warn('Nur auf der Website: '.implode(', ', $onlyWeb));
        }
        if ($onlyDb !== []) {
            $this->warn('Nur in der DB: '.implode(', ', $onlyDb));
        }

        foreach ($mismatches as $nr => $issues) {
            $w = $web[$nr];
            $this->line("Nr. {$nr} (".implode(', ', $issues).'): '.$w['team'].' | '.$w['vehicle']);
        }

        if ($onlyWeb === [] && $onlyDb === [] && $mismatches === []) {
            $this->info('Alle Startnummern stimmen mit der Online-Liste überein.');
        }

        return ($onlyWeb === [] && $onlyDb === [] && $mismatches === []) ? self::SUCCESS : self::FAILURE;
    }
}
