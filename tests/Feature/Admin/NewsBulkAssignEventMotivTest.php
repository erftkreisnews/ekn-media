<?php

namespace Tests\Feature\Admin;

use App\Jobs\GenerateImageMetadata;
use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use App\Models\PlannedEvent;
use App\Models\PlannedEventTeam;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NewsBulkAssignEventMotivTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('access_admin');
    }

    private function createNewsWithBand(User $user): array
    {
        $event = PlannedEvent::query()->create([
            'name' => 'Stadtfest',
            'is_active' => true,
            'sort_order' => 0,
            'venue_city' => 'Köln',
            'location' => 'Bühne Süd',
        ]);
        PlannedEventTeam::query()->create([
            'planned_event_id' => $event->id,
            'name' => 'Die Beispielband',
            'sort_order' => 0,
        ]);

        $news = NewsItem::query()->create([
            'title' => 'Konzert',
            'slug' => 'konzert-'.uniqid(),
            'planned_event_id' => $event->id,
            'author_id' => $user->id,
            'status' => 'draft',
        ]);

        $mk = static fn (int $n, string $cap) => NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'news-media/'.$news->id.'/image/00000'.$n.'.jpg',
            'original_name' => $n.'.jpg',
            'sort_order' => $n,
            'is_visible' => true,
            'versand' => true,
            'delivery_visible_for_organization_ids' => [],
            'caption' => $cap,
            'capture_time' => Carbon::parse('2026-06-01 20:00:00', 'Europe/Berlin'),
            'media_keywords' => 'live',
        ]);

        $m1 = $mk(1, 'Publikum tanzt.');
        $m2 = $mk(2, 'Szene in der Halle.');

        return [$news, $m1, $m2];
    }

    public function test_bulk_assign_prepends_artist_to_multiple_captions(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');
        [$news, $m1, $m2] = $this->createNewsWithBand($user);

        $this->actingAs($user)
            ->post(route('admin.news.media.bulk-assign-event-motiv', $news), [
                'bulk_assign_media_ids' => [$m1->id, $m2->id],
                'event_motiv_assign' => 'Die Beispielband',
                'bulk_motiv_action' => 'caption_only',
            ])
            ->assertRedirect(route('admin.news.edit', $news).'?tab=bilder');

        $this->assertStringContainsString('Die Beispielband', (string) $m1->fresh()->caption);
        $this->assertStringContainsString('Publikum', (string) $m1->fresh()->caption);
        $this->assertStringContainsString('Die Beispielband', (string) $m2->fresh()->caption);
    }

    public function test_bulk_assign_and_ai_dispatches_jobs(): void
    {
        Queue::fake();
        config(['media_ai.api_key' => 'sk-test-assign']);

        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');
        [$news, $m1, $m2] = $this->createNewsWithBand($user);

        $this->actingAs($user)
            ->post(route('admin.news.media.bulk-assign-event-motiv', $news), [
                'bulk_assign_media_ids' => [$m1->id, $m2->id],
                'event_motiv_assign' => 'Die Beispielband',
                'bulk_motiv_action' => 'caption_and_ai',
            ])
            ->assertRedirect(route('admin.news.edit', $news).'?tab=bilder');

        $this->assertStringContainsString('Die Beispielband', (string) $m1->fresh()->caption);

        Queue::assertPushed(GenerateImageMetadata::class, 2);
    }
}
