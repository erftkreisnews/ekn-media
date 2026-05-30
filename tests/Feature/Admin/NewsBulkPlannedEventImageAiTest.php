<?php

namespace Tests\Feature\Admin;

use App\Jobs\GenerateImageMetadata;
use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use App\Models\PlannedEvent;
use App\Models\PlannedEventTeam;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use ReflectionClass;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NewsBulkPlannedEventImageAiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('access_admin');
        config(['media_ai.api_key' => 'sk-test-bulk-ai']);
    }

    public function test_bulk_ai_dispatches_jobs_per_image_with_motiv_hint(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $event = PlannedEvent::query()->create([
            'name' => 'Open Air Sommer',
            'is_active' => true,
            'sort_order' => 0,
            'ai_context' => 'Bühnenshow mit mehreren Acts.',
        ]);
        PlannedEventTeam::query()->create([
            'planned_event_id' => $event->id,
            'name' => 'Act Nova',
            'notes' => null,
            'sort_order' => 0,
        ]);

        $news = NewsItem::query()->create([
            'title' => 'Konzerthappen',
            'slug' => 'konzerthappen-'.uniqid(),
            'planned_event_id' => $event->id,
            'author_id' => $user->id,
            'status' => 'draft',
        ]);

        $m1 = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'news-media/'.$news->id.'/image/000001.jpg',
            'original_name' => 'a.jpg',
            'sort_order' => 1,
            'is_visible' => true,
            'versand' => true,
            'delivery_visible_for_organization_ids' => [],
            'caption' => 'Publikum winkt.',
            'media_keywords' => 'Konzert, Live',
            'photographer' => 'Foto Person',
        ]);
        $m2 = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'news-media/'.$news->id.'/image/000002.jpg',
            'original_name' => 'b.jpg',
            'sort_order' => 2,
            'is_visible' => true,
            'versand' => true,
            'delivery_visible_for_organization_ids' => [],
            'caption' => 'Bühne mit Licht.',
            'media_keywords' => 'Stage',
            'photographer' => 'Foto Person',
        ]);

        $this->actingAs($user)
            ->post(route('admin.news.media.bulk-planned-event-ai', $news), [
                'bulk_ai_media_ids' => [$m1->id, $m2->id],
                'event_motiv_focus' => 'Act Nova',
            ])
            ->assertRedirect(route('admin.news.edit', $news).'?tab=bilder')
            ->assertSessionHas('status');

        Queue::assertPushed(GenerateImageMetadata::class, 2);

        $captured = [];
        Queue::assertPushed(GenerateImageMetadata::class, function (GenerateImageMetadata $job) use (&$captured): bool {
            $ref = new ReflectionClass($job);
            $mediaProp = $ref->getProperty('media');
            $mediaProp->setAccessible(true);
            /** @var NewsItemMedia $m */
            $m = $mediaProp->getValue($job);
            $hintProp = $ref->getProperty('refinementHint');
            $hintProp->setAccessible(true);
            $capProp = $ref->getProperty('captionContext');
            $capProp->setAccessible(true);
            $kwProp = $ref->getProperty('keywordsContext');
            $kwProp->setAccessible(true);
            $captured[$m->id] = [
                'hint' => (string) $hintProp->getValue($job),
                'caption' => (string) $capProp->getValue($job),
                'keywords' => (string) $kwProp->getValue($job),
            ];

            return true;
        });

        $this->assertCount(2, $captured);
        $this->assertStringContainsString('Act Nova', $captured[$m1->id]['hint']);
        $this->assertStringContainsString('Open Air Sommer', $captured[$m1->id]['hint']);
        $this->assertSame('Publikum winkt.', $captured[$m1->id]['caption']);
        $this->assertSame('Konzert, Live', $captured[$m1->id]['keywords']);
        $this->assertSame('Bühne mit Licht.', $captured[$m2->id]['caption']);
        $this->assertSame('Stage', $captured[$m2->id]['keywords']);
    }

    public function test_bulk_ai_rejects_unknown_motiv(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $event = PlannedEvent::query()->create([
            'name' => 'Festival',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        PlannedEventTeam::query()->create([
            'planned_event_id' => $event->id,
            'name' => 'Nur Dieses Team',
            'sort_order' => 0,
        ]);

        $news = NewsItem::query()->create([
            'title' => 'Meldung',
            'slug' => 'meldung-'.uniqid(),
            'planned_event_id' => $event->id,
            'author_id' => $user->id,
            'status' => 'draft',
        ]);

        $m1 = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'news-media/'.$news->id.'/image/000099.jpg',
            'original_name' => 'z.jpg',
            'sort_order' => 1,
            'is_visible' => true,
            'versand' => false,
            'delivery_visible_for_organization_ids' => [],
        ]);

        $this->actingAs($user)
            ->post(route('admin.news.media.bulk-planned-event-ai', $news), [
                'bulk_ai_media_ids' => [$m1->id],
                'event_motiv_focus' => 'Phantom Act',
            ])
            ->assertRedirect(route('admin.news.edit', $news).'?tab=bilder')
            ->assertSessionHas('error');

        Queue::assertNothingPushed();
    }
}
