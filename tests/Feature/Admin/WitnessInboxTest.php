<?php

namespace Tests\Feature\Admin;

use App\Models\NewsItem;
use App\Models\NewsItemWitnessLink;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WitnessInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate(AdminPermissions::ACCESS);
        Permission::findOrCreate(AdminPermissions::NEWS);
        config(['witness.portal_host' => '']);
    }

    public function test_general_witness_link_accepts_upload_without_news_assignment(): void
    {
        $plain = Str::random(48);
        NewsItemWitnessLink::query()->create([
            'news_item_id' => null,
            'token_hash' => NewsItemWitnessLink::hashToken($plain),
            'max_uploads' => 5,
        ]);

        $this->get(route('witness.upload.show', ['token' => $plain]))->assertOk();

        $file = UploadedFile::fake()->image('global.jpg', 80, 80);
        $this->post(route('witness.upload.store', ['token' => $plain]), [
            'submitter_name' => 'Global Zeuge',
            'submitter_email' => 'global@example.test',
            'consent_terms' => '1',
            'consent_rights' => '1',
            'media' => [$file],
            'website' => '',
        ])->assertRedirect(route('witness.upload.show', ['token' => $plain]));

        $this->assertDatabaseHas('news_item_witness_submissions', [
            'news_item_id' => null,
            'submitter_email' => 'global@example.test',
        ]);
    }

    public function test_admin_can_assign_unassigned_witness_submission_to_news_item(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(AdminPermissions::ACCESS, AdminPermissions::NEWS);

        $news = NewsItem::query()->create([
            'title' => 'Zuordnungstest',
            'slug' => 'zuordnungstest-'.Str::random(8),
            'status' => 'draft',
            'author_id' => $user->id,
        ]);

        $plain = Str::random(48);
        NewsItemWitnessLink::query()->create([
            'news_item_id' => null,
            'token_hash' => NewsItemWitnessLink::hashToken($plain),
            'max_uploads' => 5,
        ]);

        $this->get(route('witness.upload.show', ['token' => $plain]))->assertOk();

        $file = UploadedFile::fake()->image('assign.jpg', 40, 40);
        $this->post(route('witness.upload.store', ['token' => $plain]), [
            'submitter_name' => 'Assign Zeuge',
            'submitter_email' => 'assign@example.test',
            'consent_terms' => '1',
            'consent_rights' => '1',
            'media' => [$file],
            'website' => '',
        ])->assertRedirect();

        $submissionId = (int) \DB::table('news_item_witness_submissions')
            ->where('submitter_email', 'assign@example.test')
            ->value('id');

        $this->actingAs($user)
            ->post(route('admin.witness.submissions.assign', ['submission' => $submissionId]), [
                'news_item_id' => $news->id,
            ])
            ->assertRedirect(route('admin.news.edit', [
                'newsItem' => $news,
                'tab' => 'witness',
            ]));

        $this->assertDatabaseHas('news_item_witness_submissions', [
            'id' => $submissionId,
            'news_item_id' => $news->id,
        ]);
    }

    public function test_admin_can_revoke_general_witness_link_with_delete_confirmation(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(AdminPermissions::ACCESS, AdminPermissions::NEWS);

        $link = NewsItemWitnessLink::query()->create([
            'news_item_id' => null,
            'token_hash' => NewsItemWitnessLink::hashToken(Str::random(48)),
            'max_uploads' => 5,
        ]);

        $this->actingAs($user)
            ->delete(route('admin.witness.global-links.destroy', $link), [
                'confirmation' => 'ja',
            ])
            ->assertRedirect(route('admin.witness.inbox'));

        $link->refresh();
        $this->assertNotNull($link->revoked_at);
    }
}
