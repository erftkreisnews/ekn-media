<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminBrandNewsEntryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate(AdminPermissions::ACCESS);
        Permission::findOrCreate(AdminPermissions::NEWS);
    }

    public function test_koelnimage_brand_filter_redirects_news_create_to_foto(): void
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

        $this->actingAs($user)
            ->withSession(['admin.brand_filter' => $brand->id])
            ->get(route('admin.news.create'))
            ->assertRedirect(route('admin.koelnimage.foto.create'));
    }

    public function test_erftkreis_brand_filter_redirects_foto_create_to_news_create(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([AdminPermissions::ACCESS, AdminPermissions::NEWS]);

        $brand = Brand::query()->updateOrCreate(
            ['key' => 'erftkreis_news'],
            [
                'name' => 'Erftkreis News',
                'primary_host' => 'erftkreis-news.media',
                'secondary_hosts' => [],
                'is_active' => true,
            ]
        );

        $this->actingAs($user)
            ->withSession(['admin.brand_filter' => $brand->id])
            ->get(route('admin.koelnimage.foto.create'))
            ->assertRedirect(route('admin.news.create'));
    }

    public function test_news_index_shows_only_foto_button_for_koelnimage_filter(): void
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

        $response = $this->actingAs($user)
            ->withSession(['admin.brand_filter' => $brand->id])
            ->get(route('admin.news.index'));

        $response->assertOk();
        $response->assertSee('Foto (Kölnimage)', false);
        $response->assertDontSee('+ Neue Nachricht', false);
    }

    public function test_news_index_shows_only_news_create_button_for_erftkreis_filter(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([AdminPermissions::ACCESS, AdminPermissions::NEWS]);

        $brand = Brand::query()->updateOrCreate(
            ['key' => 'erftkreis_news'],
            [
                'name' => 'Erftkreis News',
                'primary_host' => 'erftkreis-news.media',
                'secondary_hosts' => [],
                'is_active' => true,
            ]
        );

        $response = $this->actingAs($user)
            ->withSession(['admin.brand_filter' => $brand->id])
            ->get(route('admin.news.index'));

        $response->assertOk();
        $response->assertSee('+ Neue Nachricht', false);
        $response->assertDontSee('Foto (Kölnimage)', false);
    }
}
