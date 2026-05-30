<?php

namespace App\Services\PlannedEvents;

use App\Models\PlannedEvent;
use App\Models\PlannedEventTeam;
use App\Support\PlannedEventTeamReferenceImageLocator;
use Illuminate\Support\Facades\DB;

final class SyncPlannedEvent24hParticipants
{
    public function __construct(
        private readonly Adac24hParticipantListFetcher $fetcher,
    ) {}

    /**
     * @return array{
     *   fetched: int,
     *   created: int,
     *   updated: int,
     *   removed: int,
     *   images_downloaded: int,
     *   images_skipped: int,
     *   errors: list<string>
     * }
     */
    public function sync(PlannedEvent $event, bool $downloadImages = true, bool $dryRun = false): array
    {
        $entries = $this->fetcher->fetch();
        $stats = [
            'fetched' => count($entries),
            'created' => 0,
            'updated' => 0,
            'removed' => 0,
            'images_downloaded' => 0,
            'images_skipped' => 0,
            'errors' => [],
        ];

        if ($entries === []) {
            $stats['errors'][] = 'Keine Teilnehmer aus der Online-Liste gelesen.';

            return $stats;
        }

        $existingByStart = [];
        foreach ($event->teams()->get() as $team) {
            $nr = $this->extractStartNumber((string) $team->name);
            if ($nr !== null) {
                $existingByStart[$nr] = $team;
            }
        }

        $seenNumbers = [];
        $sort = 0;

        $run = function () use (
            $event,
            $entries,
            &$stats,
            &$existingByStart,
            &$seenNumbers,
            &$sort,
            $downloadImages,
            $dryRun
        ): void {
            foreach ($entries as $entry) {
                $startNumber = (int) $entry['start_number'];
                $seenNumbers[$startNumber] = true;
                $name = $this->fetcher->formatTeamName($entry);
                $notes = $this->fetcher->formatTeamNotes($entry);
                $referencePath = null;

                if ($downloadImages) {
                    try {
                        $binary = $this->fetcher->downloadReferenceImage((string) $entry['image_url']);
                        if (! $dryRun) {
                            $referencePath = PlannedEventTeamReferenceImageLocator::store(
                                (int) $event->id,
                                $startNumber,
                                $binary
                            );
                        }
                        $stats['images_downloaded']++;
                    } catch (\Throwable $e) {
                        $stats['errors'][] = 'Nr. '.$startNumber.' Foto: '.$e->getMessage();
                        $existing = $existingByStart[$startNumber] ?? null;
                        if ($existing instanceof PlannedEventTeam) {
                            $referencePath = $existing->reference_image_path;
                        }
                    }
                } else {
                    $existing = $existingByStart[$startNumber] ?? null;
                    if ($existing instanceof PlannedEventTeam && filled($existing->reference_image_path)) {
                        $referencePath = $existing->reference_image_path;
                        $stats['images_skipped']++;
                    }
                }

                $payload = [
                    'name' => $name,
                    'notes' => $notes !== '' ? $notes : null,
                    'sort_order' => $sort,
                    'reference_image_path' => $referencePath,
                ];
                $sort++;

                $team = $existingByStart[$startNumber] ?? null;
                if ($team instanceof PlannedEventTeam) {
                    if (! $dryRun) {
                        $team->fill($payload)->save();
                    }
                    $stats['updated']++;
                } else {
                    if (! $dryRun) {
                        $event->teams()->create(array_merge($payload, [
                            'planned_event_id' => $event->id,
                        ]));
                    }
                    $stats['created']++;
                }
            }

            foreach ($existingByStart as $startNumber => $team) {
                if (isset($seenNumbers[$startNumber])) {
                    continue;
                }
                if (! $dryRun) {
                    if (filled($team->reference_image_path)) {
                        PlannedEventTeamReferenceImageLocator::delete((string) $team->reference_image_path);
                    }
                    $team->delete();
                }
                $stats['removed']++;
            }

            // Kopfzeilen / Einträge ohne Startnummer entfernen
            foreach ($event->teams()->get() as $team) {
                if ($this->extractStartNumber((string) $team->name) !== null) {
                    continue;
                }
                if (! $dryRun) {
                    if (filled($team->reference_image_path)) {
                        PlannedEventTeamReferenceImageLocator::delete((string) $team->reference_image_path);
                    }
                    $team->delete();
                }
                $stats['removed']++;
            }
        };

        if ($dryRun) {
            $run();
        } else {
            DB::transaction($run);
        }

        return $stats;
    }

    private function extractStartNumber(string $teamName): ?int
    {
        if (preg_match('/Startnr\.?\s*(\d{1,4})/iu', $teamName, $match) !== 1) {
            return null;
        }

        $nr = (int) $match[1];

        return $nr > 0 ? $nr : null;
    }
}
