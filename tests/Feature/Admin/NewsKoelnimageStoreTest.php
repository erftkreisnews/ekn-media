<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\NewsItem;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NewsKoelnimageStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate(AdminPermissions::ACCESS);
        Permission::findOrCreate(AdminPermissions::NEWS);
    }

    public function test_koelnimage_photo_draft_can_be_stored_without_images_and_redirects_to_edit(): void
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

        $response = $this->actingAs($user)->post(route('admin.news.store'), [
            'title' => 'Kölnimage Test ohne Bild',
            'status' => 'draft',
            'author_credit_user_id' => $user->id,
            'news_entry_flow' => 'koelnimage_photo',
            'no_wdr_job' => '1',
            'brand_id' => $brand->id,
        ]);

        $news = NewsItem::query()->where('title', 'Kölnimage Test ohne Bild')->firstOrFail();
        $response->assertRedirect(route('admin.news.edit', ['newsItem' => $news, 'tab' => 'bilder'], false).'#section-media');
        $this->assertSame((int) $brand->id, (int) $news->brand_id);
        $this->assertSame('final', $news->update_type);
    }

    public function test_koelnimage_photo_validation_failure_redirects_to_create_koelnimage(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([AdminPermissions::ACCESS, AdminPermissions::NEWS]);

        Brand::query()->updateOrCreate(
            ['key' => 'koelnimage'],
            [
                'name' => 'KoelnImage',
                'primary_host' => 'koelnimage.de',
                'secondary_hosts' => [],
                'is_active' => true,
            ]
        );

        $this->actingAs($user)->post(route('admin.news.store'), [
            'title' => '',
            'status' => 'draft',
            'author_credit_user_id' => $user->id,
            'news_entry_flow' => 'koelnimage_photo',
            'no_wdr_job' => '1',
        ])->assertRedirect(route('admin.news.create.koelnimage'));
    }

    public function test_koelnimage_update_without_no_wdr_job_checkbox_sets_is_wdr_job_false(): void
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

        $news = NewsItem::query()->create([
            'title' => 'Kölnimage WDR-Flag Test',
            'status' => 'draft',
            'author_id' => $user->id,
            'author_credit' => 'Test',
            'brand_id' => $brand->id,
            'is_wdr_job' => true,
            'update_type' => 'final',
        ]);

        $this->actingAs($user)->patch(route('admin.news.update', $news), [
            'title' => 'Kölnimage WDR-Flag Test',
            'status' => 'draft',
            'author_credit_user_id' => $user->id,
            'brand_id' => $brand->id,
        ])->assertRedirect();

        $news->refresh();
        $this->assertFalse($news->is_wdr_job);
        $this->assertFalse($news->isWdrJob());
    }

    public function test_koelnimage_prepare_send_does_not_require_moid_even_when_is_wdr_job_true(): void
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

        $news = NewsItem::query()->create([
            'title' => 'Kölnimage Versand ohne MoID',
            'status' => 'draft',
            'author_id' => $user->id,
            'author_credit' => 'Test',
            'brand_id' => $brand->id,
            'is_wdr_job' => true,
            'moid' => null,
            'update_type' => 'final',
        ]);

        $this->actingAs($user)
            ->get(route('admin.news.send', $news))
            ->assertOk()
            ->assertViewIs('admin.news.prepare-send');
    }
}
