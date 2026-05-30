<?php

namespace Tests\Feature\Admin;

use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use App\Models\PlannedEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NewsMediaRedactionPlannedEventLockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('access_admin');
    }

    public function test_redaction_update_is_rejected_when_planned_event_assigned(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $event = PlannedEvent::query()->create([
            'name' => 'Lock Test Event',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $news = NewsItem::query()->create([
            'title' => 'Meldung mit Event',
            'slug' => 'meldung-lock-'.uniqid(),
            'planned_event_id' => $event->id,
            'author_id' => $user->id,
            'status' => 'draft',
        ]);

        $media = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'news-media/'.$news->id.'/image/000001.jpg',
            'original_name' => 'foto.jpg',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->patch(route('admin.news.media.redaction.update', [$news, $media]), [
                'boxes' => [[0.0, 0.0, 10.0, 10.0]],
                'method' => 'blur',
                'run_after' => '1',
            ])
            ->assertRedirect(route('admin.news.media.edit', [$news, $media]).'#redaction')
            ->assertSessionHas('error');

        $this->assertNull($media->fresh()->redaction_boxes);
    }

    public function test_redaction_update_allowed_without_planned_event(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $news = NewsItem::query()->create([
            'title' => 'Meldung ohne Event',
            'slug' => 'meldung-open-'.uniqid(),
            'author_id' => $user->id,
            'status' => 'draft',
        ]);

        $media = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'news-media/'.$news->id.'/image/000001.jpg',
            'original_name' => 'foto.jpg',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->patch(route('admin.news.media.redaction.update', [$news, $media]), [
                'boxes' => [[1.0, 2.0, 30.0, 40.0]],
                'method' => 'blur',
            ])
            ->assertRedirect(route('admin.news.media.edit', [$news, $media]))
            ->assertSessionHas('status');

        $boxes = $media->fresh()->redaction_boxes;
        $this->assertIsArray($boxes);
        $this->assertEquals([[1.0, 2.0, 30.0, 40.0]], $boxes);
    }
}
