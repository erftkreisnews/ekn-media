<?php

namespace Tests\Unit;

use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use App\Models\PlannedEvent;
use App\Models\PlannedEventTeam;
use App\Models\User;
use App\Services\MediaAi\PlannedEventMetadataEnricher;
use App\Services\MediaAi\ReferenceImageTeamMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlannedEventMetadataEnricherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['media_ai.caption_style' => 'agency']);
    }

    public function test_enrich_matches_start_number_and_injects_team_and_drivers(): void
    {
        $event = PlannedEvent::query()->create([
            'name' => 'ADAC RAVENOL 24h Nürburgring Qualifiers 2026',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        PlannedEventTeam::query()->create([
            'planned_event_id' => $event->id,
            'name' => 'Box 9 | Startnr. 3 | Mercedes-AMG Team Verstappen Racing SP 9 PRO',
            'notes' => 'Fahrer / Fahrzeugdetails: Max Verstappen, Swalmen (NLD) Mercedes-AMG; Lucas Auer, Kufstein (AUT) GT3',
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

        $result = [
            'image_title' => 'Rennszene',
            'caption' => 'Der Mercedes-AMG GT3 fährt auf der Rennstrecke. Nürburg, 19.04.2026.',
            'keywords' => ['GT3', 'Motorsport'],
            'detected_start_number' => 3,
            'needs_review' => [],
        ];

        $enricher = new PlannedEventMetadataEnricher;
        $enriched = $enricher->enrich($media, $result);

        $this->assertTrue((bool) data_get($enriched, 'planned_event_enrichment.matched'));
        $this->assertSame(3, data_get($enriched, 'planned_event_enrichment.start_number'));
        $cap = (string) $enriched['caption'];
        $this->assertStringContainsString('ADAC RAVENOL 24h Nürburgring Qualifiers 2026 3 Mercedes-AMG GT3, Mercedes-AMG Team Verstappen Racing:', $cap);
        $this->assertStringContainsString('Max Verstappen, Lucas Auer', $cap);
        $this->assertStringContainsString(' - picture by ', $cap);
        $this->assertContains('#3', $enriched['keywords']);
        $this->assertContains('Max Verstappen (NLD)', $enriched['keywords']);
        $this->assertContains('Lucas Auer (AUT)', $enriched['keywords']);
        $this->assertContains('Max Verstappen', $enriched['keywords']);
        $this->assertContains('Lucas Auer', $enriched['keywords']);
    }

    public function test_enrich_marks_needs_review_when_no_start_number_detected(): void
    {
        $event = PlannedEvent::query()->create([
            'name' => 'Test Event',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        PlannedEventTeam::query()->create([
            'planned_event_id' => $event->id,
            'name' => 'Box 9 | Startnr. 3 | Team X',
            'notes' => '',
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
            'path' => 'news-media/test2.jpg',
        ]);
        $media->setRelation('newsItem', $news);

        $enricher = new PlannedEventMetadataEnricher;
        $enriched = $enricher->enrich($media, [
            'image_title' => 'Bild',
            'caption' => 'Ohne klare Nummer.',
            'keywords' => [],
            'needs_review' => [],
        ]);

        $this->assertFalse((bool) data_get($enriched, 'planned_event_enrichment.matched'));
        $this->assertContains('Startnummer im Bild nicht eindeutig erkannt', $enriched['needs_review']);
    }

    public function test_enrich_merges_planned_event_name_with_schedule_slot_label(): void
    {
        $schedule = <<<'TXT'
Donnerstag, 14. Mai 2026
08:30 – 12:30 Uhr Nürburgring GE Leistungsprüfung
13:15 – 15:15 Uhr ADAC RAVENOL 24h Nürburgring GE Qualifying 1
TXT;

        $event = PlannedEvent::query()->create([
            'name' => 'RCN Rundstrecken Challenge',
            'schedule_pdf_extracted_text' => $schedule,
            'is_active' => true,
            'sort_order' => 0,
        ]);
        PlannedEventTeam::query()->create([
            'planned_event_id' => $event->id,
            'name' => 'Box 12 | Startnr. 286 | Toyota Team Racing',
            'notes' => '',
            'sort_order' => 0,
        ]);

        $news = NewsItem::query()->create([
            'title' => 'RCN',
            'status' => 'draft',
            'author_id' => User::factory()->create()->id,
            'planned_event_id' => $event->id,
        ]);
        $media = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'news-media/rcn.jpg',
            'capture_time' => '2026-05-14 10:00:00',
        ]);
        $media->setRelation('newsItem', $news);

        $enricher = new PlannedEventMetadataEnricher;
        $enriched = $enricher->enrich($media, [
            'image_title' => 'Toyota in der Box',
            'caption' => 'Ein Toyota steht in der Boxengasse. Nürburgring, 14.05.2026.',
            'keywords' => ['Toyota', 'Boxengasse'],
            'detected_start_number' => 286,
            'needs_review' => [],
        ]);

        $this->assertTrue((bool) data_get($enriched, 'planned_event_enrichment.matched'));
        $cap = (string) ($enriched['caption'] ?? '');
        $this->assertStringContainsString('RCN Rundstrecken Challenge', $cap);
        $this->assertStringContainsString('Nürburgring GE Leistungsprüfung', $cap);
        $this->assertStringContainsString('RCN Rundstrecken Challenge Nürburgring GE Leistungsprüfung 286', $cap);
    }

    public function test_enrich_strips_redundant_startnummer_when_paren_format_present(): void
    {
        $event = PlannedEvent::query()->create([
            'name' => 'Testserie 2026',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        PlannedEventTeam::query()->create([
            'planned_event_id' => $event->id,
            'name' => 'Box 2 | Startnr. 7 | Team Z Racing',
            'notes' => '',
            'sort_order' => 0,
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
            'path' => 'news-media/tzr.jpg',
        ]);
        $media->setRelation('newsItem', $news);

        $enricher = new PlannedEventMetadataEnricher;
        $enriched = $enricher->enrich($media, [
            'image_title' => 'Szene',
            'caption' => 'Der Wagen mit Startnummer 7 fährt auf der Rennstrecke. Nürburg, 19.04.2026.',
            'keywords' => [],
            'detected_start_number' => 7,
            'needs_review' => [],
        ]);

        $cap = (string) ($enriched['caption'] ?? '');
        $this->assertStringContainsString('Testserie 2026 7', $cap);
        $this->assertStringContainsString('Team Z Racing', $cap);
        $this->assertStringNotContainsString('Startnummer 7', $cap);
        $this->assertStringNotContainsString('mit Startnummer', $cap);
        $this->assertStringNotContainsString('(#7)', $cap);
    }

    public function test_enrich_strips_pdf_control_chars_from_driver_names(): void
    {
        $event = PlannedEvent::query()->create([
            'name' => '24h Nürburgring',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        PlannedEventTeam::query()->create([
            'planned_event_id' => $event->id,
            'name' => 'Box 1 | Startnr. 911 | Manthey SP 9 PRO (IGTC)',
            'notes' => "Fahrer / Fahrzeugdetails: Kevin Estre\x08Heilbronn (FRA); Ayhancan Güven (TUR); Thomas Preining (AUT)",
            'sort_order' => 0,
        ]);

        $news = NewsItem::query()->create([
            'title' => 'Qualifying',
            'status' => 'draft',
            'author_id' => User::factory()->create()->id,
            'planned_event_id' => $event->id,
        ]);
        $media = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'news-media/manthey-bs.jpg',
        ]);
        $media->setRelation('newsItem', $news);

        $enriched = (new PlannedEventMetadataEnricher)->enrich($media, [
            'caption' => 'Porsche auf der Strecke.',
            'keywords' => [],
            'detected_start_number' => 911,
            'needs_review' => [],
        ]);

        $cap = (string) ($enriched['caption'] ?? '');
        $this->assertStringContainsString('Kevin Estre', $cap);
        $this->assertStringNotContainsString("\x08", $cap);
        $this->assertDoesNotMatchRegularExpression('/Estre\s+chst/u', $cap);
    }

    public function test_enrich_with_capture_time_strips_location_tail_for_later_append(): void
    {
        $event = PlannedEvent::query()->create([
            'name' => 'ADAC RAVENOL 24h Nürburgring',
            'venue_city' => 'Nürburgring',
            'location' => 'Nürburgring',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        PlannedEventTeam::query()->create([
            'planned_event_id' => $event->id,
            'name' => 'Box 1 | Startnr. 911 | Manthey SP 9 PRO (IGTC)',
            'notes' => 'Fahrer: Kevin Estre (FRA); Ayhancan Güven (TUR); Thomas Preining (AUT)',
            'sort_order' => 0,
        ]);

        $news = NewsItem::query()->create([
            'title' => 'Qualifying',
            'status' => 'draft',
            'author_id' => User::factory()->create()->id,
            'planned_event_id' => $event->id,
        ]);
        $media = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'news-media/manthey.jpg',
            'capture_time' => '2026-05-14 14:00:00',
        ]);
        $media->setRelation('newsItem', $news);

        $enricher = new PlannedEventMetadataEnricher;
        $enriched = $enricher->enrich($media, [
            'caption' => 'Ein gelb-grüner Manthey-Porsche (#911) fährt eine Kurve beim Qualifying. Nürburgring, 14.05.2026. Nürburgring, 14.05.2026',
            'keywords' => ['Manthey', 'Porsche'],
            'detected_start_number' => 911,
            'needs_review' => [],
        ]);

        $cap = (string) ($enriched['caption'] ?? '');
        $this->assertStringNotContainsString('Nürburgring, 14.05.2026', $cap);
        $this->assertStringContainsString('911', $cap);
        $this->assertStringContainsString('Manthey SP 9 PRO (IGTC):', $cap);
        $this->assertStringContainsString('Kevin Estre', $cap);
        $this->assertStringNotContainsString('(FRA)', $cap);
    }

    public function test_enrich_uses_reference_image_match_when_start_number_is_missing(): void
    {
        $event = PlannedEvent::query()->create([
            'name' => 'ADAC RAVENOL 24h Nürburgring',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        PlannedEventTeam::query()->create([
            'planned_event_id' => $event->id,
            'name' => 'Box 9 | Startnr. 80 | Mercedes-AMG Team RAVENOL SP 9',
            'notes' => 'Fahrzeug: Mercedes-AMG GT3',
            'sort_order' => 0,
        ]);

        $news = NewsItem::query()->create([
            'title' => 'Qualifying 2',
            'status' => 'draft',
            'author_id' => User::factory()->create()->id,
            'planned_event_id' => $event->id,
        ]);
        $media = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'news-media/ravenol.jpg',
        ]);
        $media->setRelation('newsItem', $news);

        Storage::fake('local');
        $refPath = 'planned-event-team-references/'.$event->id.'/80.jpg';
        Storage::disk('local')->put($refPath, 'jpeg');
        PlannedEventTeam::query()->where('planned_event_id', $event->id)->update([
            'reference_image_path' => $refPath,
        ]);

        $this->mock(ReferenceImageTeamMatcher::class, function ($mock): void {
            $mock->shouldReceive('match')
                ->once()
                ->andReturn([
                    'start_number' => 80,
                    'confidence' => 'high',
                    'reason' => 'RAVENOL-Lackierung passt zu Referenzfoto Nr. 80',
                ]);
        });

        $enriched = (new PlannedEventMetadataEnricher)->enrich($media, [
            'caption' => 'Orangefarbener Mercedes-AMG GT3 mit RAVENOL, LED-Marshal-Anzeige 058 an der Scheibe.',
            'keywords' => ['Mercedes', 'RAVENOL'],
            'detected_start_number' => null,
            'primary_car_color' => 'orange',
            'livery_cues' => ['RAVENOL'],
            'needs_review' => [],
        ]);

        $this->assertTrue((bool) data_get($enriched, 'planned_event_enrichment.matched'));
        $this->assertSame('matched_from_reference_image', data_get($enriched, 'planned_event_enrichment.reason'));
        $this->assertSame(80, data_get($enriched, 'planned_event_enrichment.start_number'));
        $cap = (string) ($enriched['caption'] ?? '');
        $this->assertStringContainsString('80 Mercedes-AMG GT3, Mercedes-AMG Team RAVENOL', $cap);
    }

    public function test_enrich_uses_reference_match_when_body_number_not_clearly_visible(): void
    {
        Storage::fake('local');

        $event = PlannedEvent::query()->create([
            'name' => 'ADAC RAVENOL 24h Nürburgring',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $refPath = 'planned-event-team-references/'.$event->id.'/175.jpg';
        Storage::disk('local')->put($refPath, base64_decode(
            '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA8A/9k=',
            true
        ) ?: 'jpeg');
        PlannedEventTeam::query()->create([
            'planned_event_id' => $event->id,
            'name' => 'Box 22 | Startnr. 175 | PROsport racing SP 10',
            'notes' => 'Fahrzeug: Mercedes-AMG GT4',
            'reference_image_path' => $refPath,
            'sort_order' => 0,
        ]);

        $news = NewsItem::query()->create([
            'title' => 'Qualifying 2',
            'status' => 'draft',
            'author_id' => User::factory()->create()->id,
            'planned_event_id' => $event->id,
        ]);
        $media = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'news-media/prosport.jpg',
        ]);
        $media->setRelation('newsItem', $news);

        $this->mock(ReferenceImageTeamMatcher::class, function ($mock): void {
            $mock->shouldReceive('match')
                ->once()
                ->andReturn([
                    'start_number' => 175,
                    'confidence' => 'high',
                    'reason' => 'H&R und MATECRA an der Stoßstange passen zu Referenzfoto Nr. 175',
                ]);
        });

        $enriched = (new PlannedEventMetadataEnricher)->enrich($media, [
            'caption' => 'Orangefarbener Mercedes-AMG auf nasser Strecke, Scheinwerfer an.',
            'keywords' => ['PROsport', 'H&R', 'MATECRA'],
            'detected_start_number' => 26,
            'number_readability' => 'low',
            'primary_car_color' => 'orange',
            'livery_cues' => ['PROsport', 'H&R', 'MATECRA'],
            'needs_review' => [],
        ]);

        $this->assertTrue((bool) data_get($enriched, 'planned_event_enrichment.matched'));
        $this->assertSame('matched_from_reference_image', data_get($enriched, 'planned_event_enrichment.reason'));
        $this->assertSame(175, data_get($enriched, 'planned_event_enrichment.start_number'));
    }

    public function test_enrich_uses_appearance_memory_when_start_number_is_missing(): void
    {
        $event = PlannedEvent::query()->create([
            'name' => 'Test Event',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        PlannedEventTeam::query()->create([
            'planned_event_id' => $event->id,
            'name' => 'Box 26 | Startnr. 911 | Manthey SP 9 PRO',
            'notes' => 'Fahrer / Fahrzeugdetails: Thomas Preining, Traun (AUT); Matt Campbell, Huntersville (USA)',
            'sort_order' => 0,
        ]);
        $news = NewsItem::query()->create([
            'title' => 'Testmeldung',
            'status' => 'draft',
            'author_id' => User::factory()->create()->id,
            'planned_event_id' => $event->id,
        ]);
        $mediaA = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'news-media/test-a.jpg',
        ]);
        $mediaB = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'news-media/test-b.jpg',
        ]);
        $mediaA->setRelation('newsItem', $news);
        $mediaB->setRelation('newsItem', $news);

        $enricher = new PlannedEventMetadataEnricher;
        $enricher->enrich($mediaA, [
            'image_title' => 'Manthey im Vordergrund',
            'caption' => 'Gelb-grüner Porsche auf der Rennstrecke.',
            'keywords' => ['Manthey', 'Porsche', 'gelb-grün'],
            'detected_start_number' => 911,
            'primary_car_color' => 'gelb-grün',
            'livery_cues' => ['Manthey', 'Porsche'],
            'needs_review' => [],
        ]);

        $enriched = $enricher->enrich($mediaB, [
            'image_title' => 'Porsche im Vordergrund',
            'caption' => 'Das Fahrzeug ist teilweise verdeckt.',
            'keywords' => ['Manthey', 'Porsche', 'gelb-grün'],
            'detected_start_number' => null,
            'primary_car_color' => 'gelb-grün',
            'livery_cues' => ['Manthey', 'Porsche'],
            'needs_review' => [],
        ]);

        $this->assertTrue((bool) data_get($enriched, 'planned_event_enrichment.matched'));
        $this->assertSame('matched_from_appearance_memory', data_get($enriched, 'planned_event_enrichment.reason'));
        $this->assertSame(911, data_get($enriched, 'planned_event_enrichment.start_number'));
        $cap = (string) ($enriched['caption'] ?? '');
        $this->assertStringContainsString('911', $cap);
        $this->assertStringContainsString('Manthey', $cap);
    }
}
