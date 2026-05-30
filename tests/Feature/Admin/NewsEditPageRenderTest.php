<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\NewsItem;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NewsEditPageRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate(AdminPermissions::ACCESS);
        Permission::findOrCreate(AdminPermissions::NEWS);
    }

    public function test_koelnimage_edit_with_invalid_tab_query_uses_nachricht_tab(): void
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
            'title' => 'Kölnimage Tab Fallback',
            'body' => 'Sichtbarer Basistext',
            'author_id' => $user->id,
            'brand_id' => $brand->id,
            'update_type' => 'final',
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->get(route('admin.news.edit', $news).'?tab=videos')
            ->assertOk()
            ->assertSee("activeTab: 'nachricht'", false)
            ->assertSee('Sichtbarer Basistext', false);
    }
}
