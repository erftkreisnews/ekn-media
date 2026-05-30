<?php

namespace Tests\Unit;

use App\Models\PlannedEvent;
use App\Models\PlannedEventTeam;
use App\Services\MediaAi\ReferenceImageTeamMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReferenceImageTeamMatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_rank_candidates_prefers_prosport_gt4_over_factory_ravenol_gt3(): void
    {
        Storage::fake('local');

        $event = PlannedEvent::query()->create([
            'name' => '24h Test',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->storeRef($event->id, 26);
        $this->storeRef($event->id, 80);
        $this->storeRef($event->id, 175);

        PlannedEventTeam::query()->create([
            'planned_event_id' => $event->id,
            'name' => 'Box 22 | Startnr. 26 | PROsport racing SP 9',
            'notes' => "Fahrzeug: Mercedes-AMG GT3\n",
            'reference_image_path' => 'planned-event-team-references/'.$event->id.'/26.jpg',
            'sort_order' => 0,
        ]);
        PlannedEventTeam::query()->create([
            'planned_event_id' => $event->id,
            'name' => 'Box 9 | Startnr. 80 | Mercedes-AMG Team RAVENOL SP 9',
            'notes' => "Fahrzeug: Mercedes-AMG GT3\n",
            'reference_image_path' => 'planned-event-team-references/'.$event->id.'/80.jpg',
            'sort_order' => 1,
        ]);
        PlannedEventTeam::query()->create([
            'planned_event_id' => $event->id,
            'name' => 'Box 22 | Startnr. 175 | PROsport racing SP 10',
            'notes' => "Fahrzeug: Mercedes-AMG GT4\n",
            'reference_image_path' => 'planned-event-team-references/'.$event->id.'/175.jpg',
            'sort_order' => 2,
        ]);

        $event->load('teams');
        $matcher = new ReferenceImageTeamMatcher;
        $ranked = $matcher->rankCandidates($event, [
            'caption' => 'Orangefarbener schwarzer Mercedes-AMG mit PROsport Racing auf dem Frontsplitter und RAVENOL.',
            'primary_car_color' => 'orange',
            'livery_cues' => ['PROsport', 'RAVENOL', 'H&R', 'Mercedes-AMG'],
            'keywords' => [],
        ]);

        $this->assertNotSame([], $ranked);
        $this->assertSame(175, $ranked[0]['start_number']);
        $this->assertNotContains(80, array_column($ranked, 'start_number'));
    }

    public function test_rank_candidates_orders_prosport_gt4_siblings_with_lower_start_number_first(): void
    {
        Storage::fake('local');

        $event = PlannedEvent::query()->create([
            'name' => '24h Test',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->storeRef($event->id, 175);
        $this->storeRef($event->id, 176);

        foreach ([175, 176] as $nr) {
            PlannedEventTeam::query()->create([
                'planned_event_id' => $event->id,
                'name' => 'Box 22 | Startnr. '.$nr.' | PROsport racing SP 10',
                'notes' => "Fahrzeug: Mercedes-AMG GT4\n",
                'reference_image_path' => 'planned-event-team-references/'.$event->id.'/'.$nr.'.jpg',
                'sort_order' => $nr === 175 ? 0 : 1,
            ]);
        }

        $event->load('teams');
        $matcher = new ReferenceImageTeamMatcher;
        $ranked = $matcher->rankCandidates($event, [
            'caption' => 'Orangefarbener schwarzer Mercedes-AMG GT4 mit PROsport Racing auf dem Frontsplitter.',
            'primary_car_color' => 'orange',
            'livery_cues' => ['PROsport', 'H&R'],
            'keywords' => [],
        ]);

        $numbers = array_column($ranked, 'start_number');
        $this->assertSame([175, 176], $numbers);
    }

    public function test_prosport_gt4_sibling_boost_override_prefers_gran_turismo_candidate(): void
    {
        $matcher = new ReferenceImageTeamMatcher;
        $candidates = [
            [
                'start_number' => 175,
                'team_name' => 'PROsport racing SP 10',
                'vehicle_label' => 'Mercedes-AMG GT4',
                'data_url' => 'data:image/jpeg;base64,/9j/4AAQ',
                'score' => 40,
            ],
            [
                'start_number' => 176,
                'team_name' => 'PROsport racing SP 10',
                'vehicle_label' => 'Mercedes-AMG GT4',
                'data_url' => 'data:image/jpeg;base64,/9j/4AAQ',
                'score' => 40,
            ],
        ];

        $method = new \ReflectionMethod(ReferenceImageTeamMatcher::class, 'applyProsportGt4SiblingLiveryBoostOverride');
        $method->setAccessible(true);

        $adjusted = $method->invoke($matcher, [
            'start_number' => 176,
            'confidence' => 'high',
            'reason' => 'Vision wählte 176',
        ], $candidates, [
            'livery_cues' => ['GT Gran Turismo', 'PROsport', 'RAVENOL'],
            'primary_car_color' => 'orange',
            'caption' => 'Orangefarbener schwarzer Mercedes-AMG GT4',
        ]);

        $this->assertSame(175, $adjusted['start_number']);
    }

    public function test_rank_candidates_returns_empty_without_hints(): void
    {
        Storage::fake('local');

        $event = PlannedEvent::query()->create([
            'name' => '24h Test',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $this->storeRef($event->id, 3);
        PlannedEventTeam::query()->create([
            'planned_event_id' => $event->id,
            'name' => 'Box 1 | Startnr. 3 | Team X',
            'notes' => 'Fahrzeug: Porsche 911',
            'reference_image_path' => 'planned-event-team-references/'.$event->id.'/3.jpg',
            'sort_order' => 0,
        ]);

        $event->load('teams');
        $matcher = new ReferenceImageTeamMatcher;
        $ranked = $matcher->rankCandidates($event, [
            'caption' => 'Ein Gebäude vor blauem Himmel.',
            'livery_cues' => [],
            'keywords' => [],
        ]);

        $this->assertSame([], $ranked);
    }

    private function storeRef(int $eventId, int $startNumber): void
    {
        $path = 'planned-event-team-references/'.$eventId.'/'.$startNumber.'.jpg';
        Storage::disk('local')->put($path, base64_decode(
            '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA8A/9k=',
            true
        ) ?: 'jpeg');
    }
}
