<?php

namespace Tests\Unit;

use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use App\Models\PlannedEvent;
use App\Models\PlannedEventTeam;
use App\Models\User;
use App\Services\MediaAi\PlannedEventMetadataEnricher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlannedEventMetadataEnricherImagoStyleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['media_ai.caption_style' => 'imago']);
    }

    public function test_imago_style_caption_with_drivers_vehicle_and_team(): void
    {
        $event = PlannedEvent::query()->create([
            'name' => 'ADAC RAVENOL 24h Nürburgring Qualifiers 2026',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        PlannedEventTeam::query()->create([
            'planned_event_id' => $event->id,
            'name' => 'Box 9 | Startnr. 3 | Mercedes-AMG Team Verstappen Racing SP 9 PRO',
            'notes' => "Fahrer / Fahrzeugdetails: Max Verstappen, Swalmen (NLD) Mercedes-AMG; Lucas Auer, Kufstein (AUT) GT3\nFahrzeug: Mercedes-AMG GT3",
            'sort_order' => 0,
        ]);

        $news = NewsItem::query()->create([
            'title' => 'Testmeldung',
            'status' => 'draft',
            'author_id' => User::factory()->create()->id,
            'planned_event_id' => $event->id,
        ]);
        $media = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'news-media/test.jpg',
        ]);
        $media->setRelation('newsItem', $news);

        $enriched = (new PlannedEventMetadataEnricher)->enrich($media, [
            'image_title' => 'Rennszene',
            'caption' => 'Der Mercedes-AMG GT3 fährt durch eine Kurve vor Zuschauern. Nürburg, 19.04.2026.',
            'keywords' => ['GT3'],
            'detected_start_number' => 3,
            'needs_review' => [],
        ]);

        $cap = (string) ($enriched['caption'] ?? '');
        $this->assertStringContainsString('Max Verstappen (NLD), Lucas Auer (AUT), 3, Mercedes-AMG GT3, Team: Mercedes-AMG Team Verstappen Racing', $cap);
        $this->assertStringContainsString('fährt durch eine Kurve', $cap);
        $this->assertStringContainsString('ADAC RAVENOL 24h Nürburgring Qualifiers 2026', $cap);
        $this->assertStringNotContainsString('(#3)', $cap);
        $this->assertStringNotContainsString('mit den Fahrern', $cap);

        $title = (string) ($enriched['image_title'] ?? '');
        $this->assertStringContainsString('Max Verstappen (NLD), 3, Mercedes-AMG GT3', $title);
        $this->assertSame($title, $enriched['description']);

        $this->assertContains('Motorsport', $enriched['keywords']);
        $this->assertContains('24h Nürburgring', $enriched['keywords']);
    }

    public function test_imago_style_includes_background_car_block(): void
    {
        $event = PlannedEvent::query()->create([
            'name' => 'ADAC RAVENOL 24h Nürburgring 2026',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        PlannedEventTeam::query()->create([
            'planned_event_id' => $event->id,
            'name' => 'Box 1 | Startnr. 808 | asBest Racing SP 3T',
            'notes' => 'Fahrzeug: BMW M4 GT3',
            'sort_order' => 0,
        ]);
        PlannedEventTeam::query()->create([
            'planned_event_id' => $event->id,
            'name' => 'Box 2 | Startnr. 925 | Huber Motorsport Cup 2',
            'notes' => 'Fahrzeug: Porsche 911 GT3 R',
            'sort_order' => 1,
        ]);

        $news = NewsItem::query()->create([
            'title' => 'Hauptrennen',
            'status' => 'draft',
            'author_id' => User::factory()->create()->id,
            'planned_event_id' => $event->id,
        ]);
        $media = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'news-media/two-cars.jpg',
        ]);
        $media->setRelation('newsItem', $news);

        $enriched = (new PlannedEventMetadataEnricher)->enrich($media, [
            'caption' => 'Zwei GT3-Fahrzeuge auf der Strecke.',
            'keywords' => [],
            'detected_start_number' => 808,
            'detected_car_numbers' => [808, 925],
            'needs_review' => [],
        ]);

        $cap = (string) ($enriched['caption'] ?? '');
        $this->assertStringContainsString('808, BMW M4 GT3, Team: asBest Racing', $cap);
        $this->assertStringContainsString('925, Porsche 911 GT3 R, Team: Huber Motorsport', $cap);
    }
}
