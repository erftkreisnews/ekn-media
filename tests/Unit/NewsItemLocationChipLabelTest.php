<?php

namespace Tests\Unit;

use App\Models\NewsItem;
use App\Models\PlannedEvent;
use PHPUnit\Framework\TestCase;

class NewsItemLocationChipLabelTest extends TestCase
{
    public function test_chip_uses_planned_event_location_when_set(): void
    {
        $event = new PlannedEvent(['location' => 'Lanxess Arena']);
        $news = new NewsItem([
            'planned_event_id' => 1,
            'country' => 'Deutschland',
        ]);
        $news->setRelation('plannedEvent', $event);

        $this->assertSame('Lanxess Arena', $news->location_chip_label);
    }

    public function test_chip_falls_back_to_city_without_country_when_event_name_empty(): void
    {
        $event = new PlannedEvent(['location' => '']);
        $news = new NewsItem([
            'planned_event_id' => 1,
            'city' => 'Köln',
            'country' => 'Deutschland',
        ]);
        $news->setRelation('plannedEvent', $event);

        $this->assertSame('Köln', $news->location_chip_label);
    }

    public function test_chip_without_planned_event_omits_country_only(): void
    {
        $news = new NewsItem(['country' => 'Deutschland']);

        $this->assertNull($news->location_chip_label);
    }

    public function test_chip_uses_planned_event_venue_address_when_location_name_empty(): void
    {
        $event = new PlannedEvent([
            'location' => '',
            'venue_street' => 'Willy-Brandt-Platz 3',
            'venue_postal_code' => '50679',
            'venue_city' => 'Köln',
            'venue_state' => 'NRW',
        ]);
        $news = new NewsItem([
            'planned_event_id' => 1,
            'country' => 'Deutschland',
        ]);
        $news->setRelation('plannedEvent', $event);

        $this->assertSame('Willy-Brandt-Platz 3, 50679 Köln, NRW', $news->location_chip_label);
    }
}
