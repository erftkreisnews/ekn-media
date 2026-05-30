<?php

namespace Tests\Unit;

use App\Models\PlannedEvent;
use App\Models\PlannedEventTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlannedEventFormatTest extends TestCase
{
    use RefreshDatabase;

    public function test_venue_address_preset_candidates_are_unique_by_location_label(): void
    {
        PlannedEvent::query()->create([
            'name' => 'Event A',
            'is_active' => true,
            'sort_order' => 0,
            'location' => 'Lanxess Arena',
            'venue_street' => 'Willy-Brandt-Platz 3',
            'venue_postal_code' => '50679',
            'venue_city' => 'Köln',
            'venue_state' => 'Nordrhein-Westfalen',
            'venue_country' => 'Deutschland',
            'venue_country_code' => 'DE',
        ]);
        PlannedEvent::query()->create([
            'name' => 'Event B Duplikat-Ort',
            'is_active' => true,
            'sort_order' => 0,
            'location' => 'Lanxess Arena',
            'venue_street' => 'Andere Straße 1',
            'venue_postal_code' => '11111',
            'venue_city' => 'X',
            'venue_state' => 'Y',
            'venue_country' => 'Deutschland',
            'venue_country_code' => 'DE',
        ]);

        $candidates = PlannedEvent::venueAddressPresetCandidates(null);
        $this->assertCount(1, $candidates);
        $this->assertSame('Lanxess Arena', $candidates->first()->location);
    }

    public function test_format_for_ai_prompt_includes_venue_address_for_iptc(): void
    {
        $event = PlannedEvent::query()->create([
            'name' => 'Qualifiers',
            'is_active' => true,
            'sort_order' => 0,
            'venue_street' => 'Otto-Flimm-Straße 1',
            'venue_postal_code' => '53520',
            'venue_city' => 'Nürburg',
            'venue_state' => 'Rheinland-Pfalz',
            'venue_country' => 'Deutschland',
            'venue_country_code' => 'DE',
        ]);

        $text = $event->formatForAiPrompt();
        $this->assertStringContainsString('Veranstaltungsadresse', $text);
        $this->assertStringContainsString('53520', $text);
        $this->assertStringContainsString('Nürburg', $text);
        $this->assertStringContainsString('Rheinland-Pfalz', $text);
    }

    public function test_format_for_ai_prompt_includes_teams(): void
    {
        $event = PlannedEvent::query()->create([
            'name' => 'ADAC RAVENOL 24h Nürburgring Qualifiers 2026',
            'date_label' => '17.–19.04.2026',
            'starts_at' => '2026-04-17',
            'ends_at' => '2026-04-19',
            'ai_context' => 'Nordschleife, Fokus GT3.',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        PlannedEventTeam::query()->create([
            'planned_event_id' => $event->id,
            'name' => 'Team Adrenalin',
            'notes' => 'Mercedes-AMG GT3, #16',
            'sort_order' => 0,
        ]);

        $event->load('teams');
        $text = $event->formatForAiPrompt();

        $this->assertStringContainsString('ADAC RAVENOL 24h', $text);
        $this->assertStringContainsString('17.–19.04.2026', $text);
        $this->assertStringContainsString('Team Adrenalin', $text);
        $this->assertStringContainsString('#16', $text);
    }

    public function test_format_for_ai_prompt_strips_html_kader_table_from_ai_context(): void
    {
        $html = <<<'HTML'
<table><thead><tr><th>#</th><th>Name</th></tr></thead><tbody>
<tr><th colspan="2" class="role">Torwart</th></tr>
<tr class="entry"><td>1</td><td class="person-name">Marvin Schwäbe</td></tr>
</tbody></table>
HTML;

        $event = PlannedEvent::query()->create([
            'name' => '1. FC Köln Kader',
            'is_active' => true,
            'sort_order' => 0,
            'ai_context' => $html,
        ]);

        $text = $event->formatForAiPrompt();

        $this->assertStringNotContainsString('<table', $text);
        $this->assertStringNotContainsString('person-name', $text);
        $this->assertStringContainsString('Marvin Schwäbe', $text);
        $this->assertStringContainsString('Torwart', $text);
    }

    public function test_format_for_ai_prompt_includes_extracted_schedule_text(): void
    {
        $event = PlannedEvent::query()->create([
            'name' => 'Test Event',
            'is_active' => true,
            'sort_order' => 0,
            'schedule_pdf_path' => 'planned-event-schedules/1/x.pdf',
            'schedule_pdf_original_name' => '24h-Zeitplan.pdf',
            'schedule_pdf_extracted_text' => "Freies Training 10:00\nQualifying 14:30",
        ]);

        $text = $event->formatForAiPrompt();

        $this->assertStringContainsString('extrahiert', $text);
        $this->assertStringContainsString('Qualifying', $text);
    }

    public function test_label_with_date_for_display_uses_date_label_or_starts_at(): void
    {
        $a = PlannedEvent::query()->create([
            'name' => 'ADAC RAVENOL 24h Nürburgring Qualifiers',
            'date_label' => '18.04.2026',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $this->assertSame(
            'ADAC RAVENOL 24h Nürburgring Qualifiers - 18.04.2026',
            $a->labelWithDateForDisplay()
        );

        $b = PlannedEvent::query()->create([
            'name' => 'Test Event',
            'starts_at' => '2026-04-17',
            'ends_at' => '2026-04-19',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $this->assertSame(
            'Test Event - 17.04.2026–19.04.2026',
            $b->labelWithDateForDisplay()
        );
    }

    public function test_compact_starter_list_for_ai_prompt_maps_start_number_and_box(): void
    {
        $event = PlannedEvent::query()->create([
            'name' => 'Test Rennen',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        PlannedEventTeam::query()->create([
            'planned_event_id' => $event->id,
            'name' => 'Box 9 | Startnr. 3 | Mercedes-AMG Team SP9',
            'notes' => 'Fahrer / Fahrzeugdetails: Max Mustermann (DEU); Jane Doe (GBR)',
            'sort_order' => 0,
        ]);

        $event->load('teams');
        $compact = $event->compactStarterListForAiPrompt();

        $this->assertStringContainsString('Kompakte Zuordnung', $compact);
        $this->assertStringContainsString('Startnummer 3', $compact);
        $this->assertStringContainsString('Mercedes-AMG Team', $compact);
        $this->assertStringContainsString('Garage/Box 9', $compact);
        $this->assertStringContainsString('Max Mustermann', $compact);
    }
}
