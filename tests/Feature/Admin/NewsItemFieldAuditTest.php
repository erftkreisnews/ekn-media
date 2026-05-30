<?php

namespace Tests\Feature\Admin;

use App\Models\NewsItem;
use App\Models\NewsItemFieldAudit;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NewsItemFieldAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate(AdminPermissions::ACCESS);
        Permission::findOrCreate(AdminPermissions::NEWS);
    }

    public function test_store_creates_field_audit_row(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([AdminPermissions::ACCESS, AdminPermissions::NEWS]);

        $this->actingAs($user)->post(route('admin.news.store'), [
            'title' => 'Audit Test Meldung',
            'status' => 'draft',
            'update_type' => 'update',
            'author_credit_user_id' => $user->id,
        ])->assertRedirect();

        $news = NewsItem::query()->where('title', 'Audit Test Meldung')->firstOrFail();

        $this->assertDatabaseHas('news_item_field_audits', [
            'news_item_id' => $news->id,
            'user_id' => $user->id,
            'event' => NewsItemFieldAudit::EVENT_CREATED,
        ]);

        $audit = NewsItemFieldAudit::query()->where('news_item_id', $news->id)->firstOrFail();
        $this->assertIsArray($audit->new_values);
        $this->assertArrayHasKey('published_at', $audit->new_values);
        $this->assertArrayHasKey('status', $audit->new_values);
        $this->assertNull($audit->old_values);
    }

    public function test_update_logs_tracked_field_changes(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([AdminPermissions::ACCESS, AdminPermissions::NEWS]);

        $news = NewsItem::create([
            'title' => 'Audit Update Test',
            'slug' => 'audit-update-test-'.uniqid(),
            'status' => 'draft',
            'published_at' => now()->subDay(),
            'author_id' => $user->id,
            'author_credit' => $user->name,
        ]);

        $this->actingAs($user)->patch(route('admin.news.update', $news), [
            'title' => 'Audit Update Test',
            'teaser' => null,
            'subheadline' => null,
            'body' => null,
            'keywords' => null,
            'region' => null,
            'country' => null,
            'federal_state' => null,
            'city' => null,
            'street' => null,
            'status' => 'published',
            'published_at' => now()->addHour()->format('Y-m-d\TH:i'),
            'embargo_at' => null,
            'author_credit_user_id' => $user->id,
            'media_ai_context' => null,
        ])->assertRedirect();

        $updated = NewsItemFieldAudit::query()
            ->where('news_item_id', $news->id)
            ->where('event', NewsItemFieldAudit::EVENT_UPDATED)
            ->first();

        $this->assertNotNull($updated);
        $this->assertIsArray($updated->old_values);
        $this->assertIsArray($updated->new_values);
        $this->assertArrayHasKey('published_at', $updated->new_values);
        $this->assertArrayHasKey('status', $updated->new_values);
    }
}
