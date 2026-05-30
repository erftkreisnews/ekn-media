<?php

namespace Tests\Feature\KoelnImage;

use App\Models\NewsItem;
use App\Models\PlannedEvent;
use Tests\TestCase;

class NewsCardLocationBadgeTest extends TestCase
{
    public function test_news_card_shows_planned_event_location_badge(): void
    {
        $event = new PlannedEvent(['location' => 'Nürburgring']);
        $item = new NewsItem([
            'id' => 1,
            'slug' => 'test-nbr',
            'title' => 'Test',
            'planned_event_id' => 1,
            'keywords' => '2026',
            'published_at' => now(),
        ]);
        $item->setRelation('plannedEvent', $event);

        $html = view('koelnimage.partials.news-card', ['item' => $item])->render();

        $this->assertStringContainsString('NÜRBURGRING', $html);
        $this->assertStringContainsString('Nürburgring', $html);
    }

    public function test_news_card_falls_back_to_non_year_keyword_for_location(): void
    {
        $item = new NewsItem([
            'id' => 2,
            'slug' => 'test-kw',
            'title' => 'Test 2',
            'keywords' => '2026, Nürburgring',
            'published_at' => now(),
        ]);

        $html = view('koelnimage.partials.news-card', ['item' => $item])->render();

        $this->assertStringContainsString('NÜRBURGRING', $html);
    }
}
