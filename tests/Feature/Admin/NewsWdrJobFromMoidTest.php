<?php

namespace Tests\Feature\Admin;

use App\Models\NewsItem;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NewsWdrJobFromMoidTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate(AdminPermissions::ACCESS);
        Permission::findOrCreate(AdminPermissions::NEWS);
    }

    public function test_new_item_without_moid_is_not_wdr_job(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([AdminPermissions::ACCESS, AdminPermissions::NEWS]);

        $response = $this->actingAs($user)->post(route('admin.news.store'), [
            'title' => 'Ohne MoID',
            'status' => 'draft',
            'author_credit_user_id' => $user->id,
            'no_wdr_job' => '1',
            'update_type' => 'update',
        ]);

        $response->assertRedirect();
        $news = NewsItem::query()->where('title', 'Ohne MoID')->firstOrFail();
        $this->assertFalse($news->is_wdr_job);
        $this->assertFalse($news->blocksAdminSendWithoutMoid());
    }

    public function test_moid_on_update_activates_wdr_job(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([AdminPermissions::ACCESS, AdminPermissions::NEWS]);

        $news = NewsItem::create([
            'title' => 'Wird WDR',
            'author_id' => $user->id,
            'status' => 'draft',
            'is_wdr_job' => false,
        ]);

        $this->actingAs($user)->patch(route('admin.news.update', $news), [
            'title' => 'Wird WDR',
            'status' => 'draft',
            'author_credit_user_id' => $user->id,
            'moid' => 'MoID_TEST_123',
        ])->assertRedirect();

        $news->refresh();
        $this->assertTrue($news->is_wdr_job);
        $this->assertTrue($news->hasMoidRestriction());
    }

    public function test_no_wdr_job_checkbox_overrides_moid(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([AdminPermissions::ACCESS, AdminPermissions::NEWS]);

        $news = NewsItem::create([
            'title' => 'MoID intern',
            'author_id' => $user->id,
            'status' => 'draft',
            'moid' => 'MoID_INTERNAL',
            'is_wdr_job' => true,
        ]);

        $this->actingAs($user)->patch(route('admin.news.update', $news), [
            'title' => 'MoID intern',
            'status' => 'draft',
            'author_credit_user_id' => $user->id,
            'moid' => 'MoID_INTERNAL',
            'no_wdr_job' => '1',
        ])->assertRedirect();

        $news->refresh();
        $this->assertFalse($news->is_wdr_job);
        $this->assertFalse($news->blocksAdminSendWithoutMoid());
    }
}
