<?php

namespace Tests\Feature\Admin;

use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VideoLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('access_admin');
    }

    public function test_guest_is_redirected_from_video_library(): void
    {
        $this->get(route('admin.video.index'))
            ->assertRedirect();
    }

    public function test_admin_can_open_video_library(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $this->actingAs($user)
            ->get(route('admin.video.index'))
            ->assertOk();
    }

    public function test_video_library_lists_video_media(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $news = NewsItem::query()->create([
            'title' => 'Testmeldung',
            'slug' => 'testmeldung-vl-'.uniqid(),
            'status' => 'draft',
            'author_id' => $user->id,
        ]);

        NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'video',
            'path' => 'news-media/'.$news->id.'/video/000001.mp4',
            'original_name' => 'clip.mp4',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('admin.video.index'))
            ->assertOk()
            ->assertSee('Video-Mediathek', false)
            ->assertSee('adminVideoLibrary', false)
            ->assertSee('#', false);
    }

    public function test_video_detail_json_liefert_metadaten(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $news = NewsItem::query()->create([
            'title' => 'Video Detail Test',
            'slug' => 'video-detail-'.uniqid(),
            'status' => 'draft',
            'author_id' => $user->id,
        ]);

        $media = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'video',
            'path' => 'news-media/'.$news->id.'/video/detail.mp4',
            'original_name' => 'detail.mp4',
            'image_title' => 'Test Titel',
            'caption' => 'Test Caption',
            'versand' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($user)->getJson(route('admin.video.show', $media->id));

        $response->assertOk();
        $response->assertJsonPath('id', $media->id);
        $response->assertJsonPath('headline', 'Test Titel');
        $response->assertJsonPath('status.versand', true);
        $response->assertJsonPath('news_item.title', 'Video Detail Test');
        $response->assertJsonStructure(['urls' => ['update', 'edit', 'quick_send']]);
    }

    public function test_video_detail_can_be_updated_via_patch(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $news = NewsItem::query()->create([
            'title' => 'Video Patch Test',
            'slug' => 'video-patch-'.uniqid(),
            'status' => 'draft',
            'author_id' => $user->id,
        ]);

        $media = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'video',
            'path' => 'news-media/'.$news->id.'/video/patch.mp4',
            'original_name' => 'patch.mp4',
            'image_title' => 'Alt',
            'versand' => false,
            'is_visible' => false,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($user)->patchJson(route('admin.video.update', $media->id), [
            'image_title' => 'Neu',
            'versand' => true,
            'is_visible' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('detail.image_title', 'Neu');
        $response->assertJsonPath('detail.status.versand', true);
        $response->assertJsonPath('detail.status.is_visible', true);

        $media->refresh();
        $this->assertSame('Neu', $media->image_title);
        $this->assertTrue($media->versand);
        $this->assertTrue($media->is_visible);
    }

    public function test_redaktion_with_admin_access_sees_all_videos_in_library(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');
        Permission::findOrCreate('admin.access');
        $user->givePermissionTo('admin.access');
        Permission::findOrCreate('admin.media');
        $user->givePermissionTo('admin.media');

        $author = User::factory()->create();
        $author->assignRole(Role::findOrCreate('admin', 'web'));

        $news = NewsItem::query()->create([
            'title' => 'Fremde Meldung Video',
            'slug' => 'fremde-meldung-video-'.uniqid(),
            'status' => 'draft',
            'author_id' => $author->id,
        ]);

        NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'video',
            'path' => 'news-media/'.$news->id.'/video/fremd.mp4',
            'original_name' => 'fremd.mp4',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('admin.video.index'))
            ->assertOk()
            ->assertSee('fremd.mp4', false);
    }

    public function test_ekn_brand_filter_includes_legacy_videos_without_brand_ids(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $brandEkn = \App\Models\Brand::query()->updateOrCreate([
            'key' => 'erftkreis_news',
        ], [
            'name' => 'Erftkreis News',
            'primary_host' => 'erftkreis-news.media',
            'secondary_hosts' => [],
            'is_active' => true,
        ]);

        $news = NewsItem::query()->create([
            'brand_id' => null,
            'title' => 'Legacy ohne brand_id',
            'slug' => 'legacy-ohne-brand-'.uniqid(),
            'status' => 'draft',
            'author_id' => $user->id,
        ]);

        $media = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'video',
            'brand_id' => null,
            'path' => 'news-media/'.$news->id.'/video/legacy.mp4',
            'original_name' => 'legacy.mp4',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->withSession(['admin.brand_filter' => $brandEkn->id])
            ->get(route('admin.video.index'))
            ->assertOk()
            ->assertSee('#'.$media->id, false);
    }

    public function test_brand_filter_uses_news_brand_when_media_brand_is_null(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $brandKoeln = \App\Models\Brand::query()->updateOrCreate([
            'key' => 'koelnimage',
        ], [
            'name' => 'KoelnImage',
            'primary_host' => 'koelnimage.de',
            'secondary_hosts' => [],
            'is_active' => true,
        ]);
        $brandOther = \App\Models\Brand::query()->updateOrCreate([
            'key' => 'erftkreis_news',
        ], [
            'name' => 'Erftkreis News',
            'primary_host' => 'erftkreis-news.media',
            'secondary_hosts' => [],
            'is_active' => true,
        ]);

        $koelnNews = NewsItem::query()->create([
            'brand_id' => $brandKoeln->id,
            'title' => 'Koeln Meldung Video',
            'slug' => 'koeln-meldung-video-'.uniqid(),
            'status' => 'draft',
            'author_id' => $user->id,
        ]);
        $otherNews = NewsItem::query()->create([
            'brand_id' => $brandOther->id,
            'title' => 'Andere Meldung Video',
            'slug' => 'andere-meldung-video-'.uniqid(),
            'status' => 'draft',
            'author_id' => $user->id,
        ]);

        $koelnMedia = NewsItemMedia::query()->create([
            'news_item_id' => $koelnNews->id,
            'type' => 'video',
            'brand_id' => null,
            'path' => 'news-media/'.$koelnNews->id.'/video/koeln.mp4',
            'original_name' => 'koeln.mp4',
            'sort_order' => 1,
        ]);
        NewsItemMedia::query()->create([
            'news_item_id' => $otherNews->id,
            'type' => 'video',
            'brand_id' => null,
            'path' => 'news-media/'.$otherNews->id.'/video/other.mp4',
            'original_name' => 'other.mp4',
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['admin.brand_filter' => $brandKoeln->id])
            ->get(route('admin.video.index'));

        $response->assertOk();
        $response->assertSee('#'.$koelnMedia->id, false);
        $response->assertDontSee('Andere Meldung Video');
    }
}
