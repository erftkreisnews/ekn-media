<?php

namespace Tests\Unit;

use App\Models\NewsItem;
use App\Support\KoelnimageHomeNewsSelection;
use Tests\TestCase;

class KoelnimageHomeNewsSelectionTest extends TestCase
{
    private function newsItemStub(int $id, ?int $plannedEventId, \DateTimeInterface $publishedAt): NewsItem
    {
        $item = new NewsItem([
            'planned_event_id' => $plannedEventId,
            'published_at' => $publishedAt,
        ]);
        $item->id = $id;

        return $item;
    }

    public function test_caps_items_per_planned_event(): void
    {
        $items = collect(range(1, 6))->map(fn (int $i) => $this->newsItemStub(
            $i,
            99,
            now()->subHours($i),
        ));

        $selected = KoelnimageHomeNewsSelection::diversifyByPlannedEvent($items, limit: 8, maxPerPlannedEvent: 3);

        $this->assertCount(3, $selected);
        $this->assertSame([1, 2, 3], $selected->pluck('id')->all());
    }

    public function test_fills_limit_from_multiple_events(): void
    {
        $items = collect();
        foreach ([10, 10, 10, 20, 20, 30] as $i => $eventId) {
            $items->push($this->newsItemStub(
                $i + 1,
                $eventId,
                now()->subMinutes($i),
            ));
        }

        $selected = KoelnimageHomeNewsSelection::diversifyByPlannedEvent($items, limit: 8, maxPerPlannedEvent: 3);

        $this->assertCount(6, $selected);
        $this->assertSame(3, $selected->where('planned_event_id', 10)->count());
        $this->assertSame(2, $selected->where('planned_event_id', 20)->count());
        $this->assertSame(1, $selected->where('planned_event_id', 30)->count());
    }

    public function test_uncategorized_items_share_one_cap_bucket(): void
    {
        $items = collect(range(1, 5))->map(fn (int $i) => $this->newsItemStub(
            $i,
            null,
            now()->subHours($i),
        ));

        $selected = KoelnimageHomeNewsSelection::diversifyByPlannedEvent($items, limit: 8, maxPerPlannedEvent: 3);

        $this->assertCount(3, $selected);
    }
}
