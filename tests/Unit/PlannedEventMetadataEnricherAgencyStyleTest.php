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

class PlannedEventMetadataEnricherAgencyStyleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['media_ai.caption_style' => 'agency']);
    }

    public function test_agency_style_matches_gruppe_c_single_car_example(): void
    {
        $event = PlannedEvent::query()->create([
            'name' => 'ADAC RAVENOL 24h Nürburgring 2026',
            'location' => 'Nürburgring',
            'venue_city' => 'Nürburg',
            'venue_state' => 'Rheinland-Pfalz',
            'venue_country' => 'Deutschland',
            'ai_context' => "caption_edition: 54\n",
            'is_active' => true,
            'sort_order' => 0,
        ]);
        PlannedEventTeam::query()->create([
            'planned_event_id' => $event->id,
            'name' => 'Box 1 | Startnr. 1 | ROWE RACING SP 9',
            'notes' => "Fahrer / Fahrzeugdetails: Augusto Farfus (BRA); Raffaele Marciello (ITA); Jordan Pepper (ZAF); Kelvin van der Linde (ZAF)\nFahrzeug: BMW M4 GT3 EVO",
            'sort_order' => 0,
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
            'path' => 'news-media/rowe.jpg',
            'photographer' => 'Gruppe C Photography',
            'capture_time' => '2026-05-17 14:00:00',
        ]);
        $media->setRelation('newsItem', $news);

        $enriched = (new PlannedEventMetadataEnricher)->enrich($media, [
            'caption' => 'BMW vor Zuschauern.',
            'detected_start_number' => 1,
            'needs_review' => [],
        ]);

        $cap = (string) ($enriched['caption'] ?? '');
        $this->assertStringStartsWith('54. ADAC RAVENOL 24h Nürburgring 2026 1 BMW M4 GT3 EVO, ROWE RACING:', $cap);
        $this->assertStringContainsString('Augusto Farfus, Raffaele Marciello, Jordan Pepper, Kelvin van der Linde', $cap);
        $this->assertStringContainsString(' - picture by Gruppe C Photography', $cap);
        $this->assertStringContainsString('Nürburg Nürburgring Rheinland-Pfalz Germany', $cap);
        $this->assertStringNotContainsString('(#1)', $cap);
        $this->assertFalse($enriched['caption_append_location_tail'] ?? true);
    }

    public function test_agency_style_two_cars_in_one_caption(): void
    {
        $event = PlannedEvent::query()->create([
            'name' => 'ADAC RAVENOL 24h Nürburgring 2026',
            'location' => 'Nürburgring',
            'venue_city' => 'Nürburg',
            'venue_state' => 'Rheinland-Pfalz',
            'venue_country' => 'Deutschland',
            'ai_context' => 'caption_edition: 54',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        PlannedEventTeam::query()->create([
            'planned_event_id' => $event->id,
            'name' => 'Box 1 | Startnr. 95 | Sante Royal Racing Team Cup 2',
            'notes' => "Fahrzeug: Porsche 911 GT3 Cup (992)\nFahrer: Stefan Kiefer (DEU); Marius Kiefer (DEU); David Kiefer (DEU); Luca Rettenbacher (AUT)",
            'sort_order' => 0,
        ]);
        PlannedEventTeam::query()->create([
            'planned_event_id' => $event->id,
            'name' => 'Box 2 | Startnr. 55 | Dinamic GT SP 9',
            'notes' => "Fahrzeug: Porsche 911 GT3 R (992) Evo26\nFahrer: Michele Beretta (ITA); Alessandro Ghiretti (ITA); Joel Sturm (DEU); Loek Hartog (NLD)",
            'sort_order' => 1,
        ]);

        $news = NewsItem::query()->create([
            'title' => 'Rennen',
            'status' => 'draft',
            'author_id' => User::factory()->create()->id,
            'planned_event_id' => $event->id,
        ]);
        $media = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'news-media/two-porsche.jpg',
            'photographer' => 'Gruppe C Photography',
            'copyright' => 'xGruppexCxPhotographyx',
        ]);
        $media->setRelation('newsItem', $news);

        $enriched = (new PlannedEventMetadataEnricher)->enrich($media, [
            'caption' => 'Zwei Porsche auf der Strecke.',
            'detected_start_number' => 95,
            'detected_car_numbers' => [95, 55],
            'needs_review' => [],
        ]);

        $cap = (string) ($enriched['caption'] ?? '');
        $this->assertStringContainsString('95 Porsche 911 GT3 Cup (992), Sante Royal Racing Team Cup 2:', $cap);
        $this->assertStringContainsString('Stefan Kiefer, Marius Kiefer', $cap);
        $this->assertStringContainsString('55 Porsche 911 GT3 R (992) Evo26, Dinamic GT:', $cap);
        $this->assertStringContainsString('Loek Hartog', $cap);
        $this->assertStringContainsString('Copyright: xGruppexCxPhotographyx', $cap);
    }
}
