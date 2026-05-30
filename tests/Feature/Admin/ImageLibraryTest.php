<?php

namespace Tests\Feature\Admin;

use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ImageLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('access_admin');
    }

    public function test_guest_is_redirected_from_image_library(): void
    {
        $this->get(route('admin.images.index'))
            ->assertRedirect();
    }

    public function test_admin_can_open_image_library(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $this->actingAs($user)
            ->get(route('admin.images.index'))
            ->assertOk();
    }

    public function test_image_library_lists_image_media(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $news = NewsItem::query()->create([
            'title' => 'Testmeldung Bild',
            'slug' => 'testmeldung-il-'.uniqid(),
            'status' => 'draft',
            'author_id' => $user->id,
        ]);

        NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'news-media/'.$news->id.'/image/000001.jpg',
            'original_name' => 'foto.jpg',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('admin.images.index'))
            ->assertOk()
            ->assertSee('Bilder', false)
            ->assertSee('adminImageLibrary', false)
            ->assertSee('admin-image-detail-overlay', false)
            ->assertSee('#', false)
            ->assertSee('Google Lens', false);
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
            'title' => 'Koeln Meldung',
            'slug' => 'koeln-meldung-'.uniqid(),
            'status' => 'draft',
            'author_id' => $user->id,
        ]);
        $otherNews = NewsItem::query()->create([
            'brand_id' => $brandOther->id,
            'title' => 'Andere Meldung',
            'slug' => 'andere-meldung-'.uniqid(),
            'status' => 'draft',
            'author_id' => $user->id,
        ]);

        $koelnMedia = NewsItemMedia::query()->create([
            'news_item_id' => $koelnNews->id,
            'type' => 'image',
            'brand_id' => null,
            'path' => 'news-media/'.$koelnNews->id.'/image/koeln.jpg',
            'original_name' => 'koeln.jpg',
            'sort_order' => 1,
        ]);
        NewsItemMedia::query()->create([
            'news_item_id' => $otherNews->id,
            'type' => 'image',
            'brand_id' => null,
            'path' => 'news-media/'.$otherNews->id.'/image/other.jpg',
            'original_name' => 'other.jpg',
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['admin.brand_filter' => $brandKoeln->id])
            ->get(route('admin.images.index'));

        $response->assertOk();
        $response->assertSee('#'.$koelnMedia->id, false);
        $response->assertDontSee('Andere Meldung');
    }

    public function test_image_detail_json_liefert_metadaten(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $news = NewsItem::query()->create([
            'title' => 'Detail Test',
            'slug' => 'detail-test-'.uniqid(),
            'status' => 'published',
            'author_id' => $user->id,
        ]);

        $media = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'news-media/'.$news->id.'/image/detail.jpg',
            'original_name' => 'detail.jpg',
            'image_title' => 'Test Headline',
            'caption' => 'Test Caption',
            'media_keywords' => 'sport, test',
            'versand' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($user)->getJson(route('admin.images.show', $media->id));

        $response->assertOk();
        $response->assertJsonPath('id', $media->id);
        $response->assertJsonPath('headline', 'Test Headline');
        $response->assertJsonPath('status.versand', true);
        $response->assertJsonPath('news_item.title', 'Detail Test');
        $response->assertJsonPath('flags.processed', true);
        $response->assertJsonStructure(['preview_urls' => ['original'], 'urls' => ['update', 'editor_source', 'image_editor']]);
        $response->assertJsonMissing(['preview_urls' => ['watermark' => true]]);
        $response->assertJsonPath('urls.image_editor', route('admin.news.media.image-editor', [$news, $media->id]));
        $response->assertJsonPath('exif_details', []);
    }

    public function test_image_detail_can_be_updated_via_patch(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $news = NewsItem::query()->create([
            'title' => 'Patch Test',
            'slug' => 'patch-test-'.uniqid(),
            'status' => 'draft',
            'author_id' => $user->id,
        ]);

        $media = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'news-media/'.$news->id.'/image/patch.jpg',
            'original_name' => 'patch.jpg',
            'image_title' => 'Alt',
            'versand' => false,
            'is_visible' => false,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($user)->patchJson(route('admin.images.update', $media->id), [
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

    public function test_editor_source_returns_not_found_when_file_missing(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $news = NewsItem::query()->create([
            'title' => 'Editor Source',
            'slug' => 'editor-source-'.uniqid(),
            'status' => 'draft',
            'author_id' => $user->id,
        ]);

        $media = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'news-media/'.$news->id.'/image/missing-on-disk.jpg',
            'original_name' => 'missing.jpg',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('admin.images.editor-source', $media->id))
            ->assertNotFound();
    }

    public function test_editor_source_streams_original_not_preview(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        config([
            'media_storage.disk' => 'public',
            'media_storage.fallback_disk' => 'public',
        ]);

        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $news = NewsItem::query()->create([
            'title' => 'Editor Original',
            'slug' => 'editor-original-'.uniqid(),
            'status' => 'draft',
            'author_id' => $user->id,
        ]);

        \Illuminate\Support\Facades\Storage::disk('public')->put('orig/editor-full.jpg', 'ORIGINAL-JPEG-BYTES');
        \Illuminate\Support\Facades\Storage::disk('public')->put('derived/preview.webp', 'preview-only');

        $media = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'orig/editor-full.jpg',
            'preview_path' => 'derived/preview.webp',
            'original_name' => 'editor-full.jpg',
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($user)
            ->get(route('admin.images.editor-source', $media->id));

        $response->assertOk();
        $this->assertStringContainsString('image/', (string) $response->headers->get('content-type'));
        $filePath = $response->baseResponse instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse
            ? $response->baseResponse->getFile()->getPathname()
            : null;
        $this->assertNotNull($filePath);
        $this->assertSame('ORIGINAL-JPEG-BYTES', (string) file_get_contents($filePath));
        $response->assertHeader('Cache-Control');
        $this->assertNotEmpty($response->headers->get('ETag'));
    }

    public function test_editor_source_config_includes_proxy_and_cache_key(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        config([
            'media_storage.disk' => 'public',
            'media_storage.fallback_disk' => 'public',
            'media_storage.editor_use_presigned_source' => false,
        ]);

        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $news = NewsItem::query()->create([
            'title' => 'Editor Config',
            'slug' => 'editor-config-'.uniqid(),
            'status' => 'draft',
            'author_id' => $user->id,
        ]);

        \Illuminate\Support\Facades\Storage::disk('public')->put('orig/cfg.jpg', 'x');

        $media = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'orig/cfg.jpg',
            'original_name' => 'cfg.jpg',
            'sort_order' => 1,
        ]);

        $config = $media->editorSourceConfig();
        $this->assertSame('editor-media-'.$media->id, $config['cache_key']);
        $this->assertStringContainsString('/admin/images/'.$media->id.'/editor-source', (string) $config['proxy']);
        $this->assertNull($config['direct']);
    }

    public function test_editor_source_returns_not_modified_with_matching_etag(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        config([
            'media_storage.disk' => 'public',
            'media_storage.fallback_disk' => 'public',
        ]);

        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $news = NewsItem::query()->create([
            'title' => 'Editor ETag',
            'slug' => 'editor-etag-'.uniqid(),
            'status' => 'draft',
            'author_id' => $user->id,
        ]);

        \Illuminate\Support\Facades\Storage::disk('public')->put('orig/etag.jpg', 'SAME-BYTES');

        $media = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'orig/etag.jpg',
            'original_name' => 'etag.jpg',
            'sort_order' => 1,
        ]);

        $first = $this->actingAs($user)->get(route('admin.images.editor-source', $media->id));
        $first->assertOk();
        $etag = (string) $first->headers->get('ETag');
        $this->assertNotSame('', $etag);

        $this->actingAs($user)
            ->get(route('admin.images.editor-source', $media->id), ['If-None-Match' => $etag])
            ->assertNotModified();
    }

    public function test_editor_source_not_found_when_only_preview_on_disk(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        config([
            'media_storage.disk' => 'public',
            'media_storage.fallback_disk' => 'public',
        ]);

        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $news = NewsItem::query()->create([
            'title' => 'Editor Preview Only',
            'slug' => 'editor-preview-only-'.uniqid(),
            'status' => 'draft',
            'author_id' => $user->id,
        ]);

        \Illuminate\Support\Facades\Storage::disk('public')->put('derived/only-preview.webp', 'preview');

        $media = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'missing/original.jpg',
            'preview_path' => 'derived/only-preview.webp',
            'original_name' => 'x.jpg',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('admin.images.editor-source', $media->id))
            ->assertNotFound();
    }

    public function test_editor_save_requires_valid_image_upload(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $news = NewsItem::query()->create([
            'title' => 'Editor Save',
            'slug' => 'editor-save-'.uniqid(),
            'status' => 'draft',
            'author_id' => $user->id,
        ]);

        $media = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'news-media/'.$news->id.'/image/source.jpg',
            'original_name' => 'source.jpg',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.images.editor-save', $media->id), [])
            ->assertStatus(422);
    }

    public function test_author_can_delete_image_from_library(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');
        Permission::findOrCreate('admin.access');
        Permission::findOrCreate('admin.media');
        $user->givePermissionTo('admin.access');
        $user->givePermissionTo('admin.media');

        $news = NewsItem::query()->create([
            'title' => 'Bild zum Löschen',
            'slug' => 'bild-loeschen-'.uniqid(),
            'status' => 'draft',
            'author_id' => $user->id,
        ]);

        $media = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'news-media/'.$news->id.'/image/delete-me.jpg',
            'original_name' => 'delete-me.jpg',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->delete(route('admin.images.destroy', $media->id), [
                'confirmation' => 'ja',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('news_item_media', [
            'id' => $media->id,
        ]);
    }

    public function test_image_library_dedupes_same_original_name_per_news_item(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $news = NewsItem::query()->create([
            'title' => 'Dedupe Test',
            'slug' => 'dedupe-test-'.uniqid(),
            'status' => 'draft',
            'author_id' => $user->id,
        ]);

        $older = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'news-media/'.$news->id.'/image/older.jpg',
            'original_name' => 'gleiches-foto.jpg',
            'sort_order' => 1,
        ]);

        $newer = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'news-media/'.$news->id.'/image/newer.jpg',
            'original_name' => 'gleiches-foto.jpg',
            'sort_order' => 2,
        ]);

        $this->actingAs($user)
            ->get(route('admin.images.index', ['news_item_id' => $news->id]))
            ->assertOk()
            ->assertSee('#'.$newer->id, false)
            ->assertDontSee('#'.$older->id, false);
    }

    public function test_year_filter_includes_images_without_capture_time_via_created_at(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $news = NewsItem::query()->create([
            'title' => 'Jahr-Filter Test',
            'slug' => 'jahr-filter-'.uniqid(),
            'status' => 'draft',
            'author_id' => $user->id,
        ]);

        $withCapture = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'news-media/'.$news->id.'/image/with-exif.jpg',
            'original_name' => 'with-exif.jpg',
            'capture_time' => now()->startOfYear()->addMonths(2),
            'sort_order' => 1,
        ]);

        $withoutCapture = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'news-media/'.$news->id.'/image/still-like.jpg',
            'original_name' => 'still-like.jpg',
            'capture_time' => null,
            'created_at' => now()->startOfYear()->addMonths(3),
            'sort_order' => 2,
        ]);

        $year = (int) now()->format('Y');

        $this->actingAs($user)
            ->get(route('admin.images.index', [
                'news_item_id' => $news->id,
                'year' => $year,
            ]))
            ->assertOk()
            ->assertSee('#'.$withCapture->id, false)
            ->assertSee('#'.$withoutCapture->id, false);
    }
}
