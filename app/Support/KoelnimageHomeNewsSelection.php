<?php

namespace App\Support;

use App\Models\NewsItem;
use Illuminate\Support\Collection;

/**
 * Mischt die Startseiten-Meldungen: neueste zuerst, aber höchstens N pro geplanter Veranstaltung.
 */
final class KoelnimageHomeNewsSelection
{
    public const DEFAULT_LIMIT = 8;

    public const DEFAULT_MAX_PER_PLANNED_EVENT = 3;

    /** Wie viele Kandidaten aus der DB geladen werden, bevor gedrosselt wird. */
    public const DEFAULT_POOL_SIZE = 60;

    /**
     * @param  Collection<int, NewsItem>  $newestFirst  bereits nach published_at absteigend sortiert
     * @return Collection<int, NewsItem>
     */
    public static function diversifyByPlannedEvent(
        Collection $newestFirst,
        int $limit = self::DEFAULT_LIMIT,
        int $maxPerPlannedEvent = self::DEFAULT_MAX_PER_PLANNED_EVENT,
    ): Collection {
        if ($limit <= 0 || $maxPerPlannedEvent <= 0) {
            return collect();
        }

        $selected = collect();
        $countsByEvent = [];

        foreach ($newestFirst as $item) {
            if ($selected->count() >= $limit) {
                break;
            }

            $eventKey = $item->planned_event_id ?? 'none';
            $count = $countsByEvent[$eventKey] ?? 0;
            if ($count >= $maxPerPlannedEvent) {
                continue;
            }

            $selected->push($item);
            $countsByEvent[$eventKey] = $count + 1;
        }

        return $selected;
    }
}
