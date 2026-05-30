<?php

namespace Tests\Feature;

use App\Models\NewsItem;
use App\Models\NewsItemWitnessLink;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WitnessUploadFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('admin');
        Permission::findOrCreate(AdminPermissions::ACCESS);
        Permission::findOrCreate(AdminPermissions::NEWS);
        config(['witness.portal_host' => '']);
    }

    public function test_unknown_token_returns_404(): void
    {
        $token = Str::random(48);

        $this->get(route('witness.upload.show', ['token' => $token]))
            ->assertNotFound();
    }

    public function test_witness_can_submit_image_after_form_started(): void
    {
        $news = NewsItem::query()->create([
            'title' => 'Zeugen-Test',
            'slug' => 'zeugen-test-'.Str::random(8),
            'status' => 'draft',
        ]);

        $plain = Str::random(48);
        NewsItemWitnessLink::query()->create([
            'news_item_id' => $news->id,
            'token_hash' => NewsItemWitnessLink::hashToken($plain),
            'max_uploads' => 5,
        ]);

        $this->get(route('witness.upload.show', ['token' => $plain]))
            ->assertOk();

        $file = UploadedFile::fake()->image('photo.jpg', 80, 80);

        $response = $this->post(route('witness.upload.store', ['token' => $plain]), [
            'submitter_name' => 'Test Zeuge',
            'submitter_email' => 'zeuge@example.test',
            'submitter_phone' => '',
            'consent_terms' => '1',
            'consent_rights' => '1',
            'media' => [$file],
            'website' => '',
        ]);

        $response->assertRedirect(route('witness.upload.show', ['token' => $plain]));
        $response->assertSessionHas('status');
        $this->assertDatabaseCount('news_item_witness_submissions', 1);
    }

    public function test_witness_can_submit_multiple_images_in_one_request(): void
    {
        $news = NewsItem::query()->create([
            'title' => 'Zeugen-Mehrfach',
            'slug' => 'zeugen-multi-'.Str::random(8),
            'status' => 'draft',
        ]);

        $plain = Str::random(48);
        NewsItemWitnessLink::query()->create([
            'news_item_id' => $news->id,
            'token_hash' => NewsItemWitnessLink::hashToken($plain),
            'max_uploads' => 5,
        ]);

        $this->get(route('witness.upload.show', ['token' => $plain]))->assertOk();

        $a = UploadedFile::fake()->image('a.jpg', 40, 40);
        $b = UploadedFile::fake()->image('b.jpg', 40, 40);

        $this->post(route('witness.upload.store', ['token' => $plain]), [
            'submitter_name' => 'Multi Zeuge',
            'submitter_email' => 'multi@example.test',
            'submitter_phone' => '',
            'consent_terms' => '1',
            'consent_rights' => '1',
            'media' => [$a, $b],
            'website' => '',
        ])->assertRedirect(route('witness.upload.show', ['token' => $plain]));

        $this->assertDatabaseCount('news_item_witness_submissions', 2);
    }

    public function test_admin_can_download_submission(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $user->givePermissionTo([AdminPermissions::ACCESS, AdminPermissions::NEWS]);

        $news = NewsItem::query()->create([
            'title' => 'DL Test',
            'slug' => 'dl-test-'.Str::random(8),
            'status' => 'draft',
        ]);

        $plain = Str::random(48);
        $link = NewsItemWitnessLink::query()->create([
            'news_item_id' => $news->id,
            'token_hash' => NewsItemWitnessLink::hashToken($plain),
            'max_uploads' => 5,
        ]);

        $this->get(route('witness.upload.show', ['token' => $plain]))->assertOk();

        $file = UploadedFile::fake()->image('x.png', 40, 40);
        $this->post(route('witness.upload.store', ['token' => $plain]), [
            'submitter_name' => 'A',
            'submitter_email' => 'a@b.cd',
            'consent_terms' => '1',
            'consent_rights' => '1',
            'media' => [$file],
            'website' => '',
        ])->assertRedirect();

        $sub = $news->witnessSubmissions()->firstOrFail();

        $this->actingAs($user)
            ->get(route('admin.news.witness-submissions.download', [$news, $sub]))
            ->assertOk();
    }

    public function test_admin_can_preview_submission_inline(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $user->givePermissionTo([AdminPermissions::ACCESS, AdminPermissions::NEWS]);

        $news = NewsItem::query()->create([
            'title' => 'Preview Test',
            'slug' => 'preview-test-'.Str::random(8),
            'status' => 'draft',
        ]);

        $plain = Str::random(48);
        NewsItemWitnessLink::query()->create([
            'news_item_id' => $news->id,
            'token_hash' => NewsItemWitnessLink::hashToken($plain),
            'max_uploads' => 5,
        ]);

        $this->get(route('witness.upload.show', ['token' => $plain]))->assertOk();

        $file = UploadedFile::fake()->image('preview.png', 30, 30);
        $this->post(route('witness.upload.store', ['token' => $plain]), [
            'submitter_name' => 'B',
            'submitter_email' => 'b@c.de',
            'consent_terms' => '1',
            'consent_rights' => '1',
            'media' => [$file],
            'website' => '',
        ])->assertRedirect();

        $sub = $news->witnessSubmissions()->firstOrFail();

        $response = $this->actingAs($user)
            ->get(route('admin.news.witness-submissions.preview', [$news, $sub]));

        $response->assertOk();
        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
    }
}
