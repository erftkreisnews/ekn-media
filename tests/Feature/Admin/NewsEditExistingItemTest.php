<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\NewsItem;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NewsEditExistingItemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate(AdminPermissions::ACCESS);
        Permission::findOrCreate(AdminPermissions::NEWS);
    }

    public function test_edit_route_resolves_news_item_by_database_id(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([AdminPermissions::ACCESS, AdminPermissions::NEWS]);

        $brand = Brand::query()->updateOrCreate(
            ['key' => 'koelnimage'],
            [
                'name' => 'KoelnImage',
                'primary_host' => 'koelnimage.de',
                'secondary_hosts' => [],
                'is_active' => true,
            ]
        );

        $news = NewsItem::create([
            'title' => 'Nürburgring Test',
            'body' => 'Basistext sichtbar',
            'author_id' => $user->id,
            'brand_id' => $brand->id,
            'update_type' => 'final',
            'status' => 'published',
        ]);

        $this->actingAs($user)
            ->get('/admin/news/'.$news->id.'/edit')
            ->assertOk()
            ->assertSee('Nürburgring Test', false)
            ->assertSee('Basistext sichtbar', false);
    }

    public function test_media_edit_page_loads_for_news_image(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([AdminPermissions::ACCESS, AdminPermissions::NEWS]);

        $news = NewsItem::create([
            'title' => 'Bild bearbeiten Test',
            'author_id' => $user->id,
            'status' => 'draft',
        ]);

        $media = $news->media()->create([
            'type' => 'image',
            'path' => 'news-media/test/image/000001.jpg',
            'original_name' => 'test.jpg',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('admin.news.media.edit', [$news, $media]))
            ->assertOk()
            ->assertSee('Bild bearbeiten Test', false);

        $this->actingAs($user)
            ->get(route('admin.news.media.image-editor', [$news, $media]))
            ->assertOk()
            ->assertSee('Bild-Editor', false)
            ->assertSee('Als Kopie speichern', false)
            ->assertSee('admin-image-editor-crop-box', false)
            ->assertSee('editorRailPreview', false)
            ->assertSee('admin-image-editor-rail', false);

        $media2 = $news->media()->create([
            'type' => 'image',
            'path' => 'news-media/test/image/000002.jpg',
            'original_name' => 'test2.jpg',
            'sort_order' => 2,
        ]);

        $this->actingAs($user)
            ->get(route('admin.news.media.image-editor', [$news, $media2]))
            ->assertOk()
            ->assertSee('admin-image-editor-filmstrip', false);
    }
}
