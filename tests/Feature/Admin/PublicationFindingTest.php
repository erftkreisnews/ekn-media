<?php

namespace Tests\Feature\Admin;

use App\Models\MediaPublicationFinding;
use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use App\Models\Organization;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PublicationFindingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate(AdminPermissions::ACCESS);
        Permission::findOrCreate(AdminPermissions::BACKOFFICE);
    }

    public function test_admin_can_create_publication_finding_from_media_link(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([AdminPermissions::ACCESS, AdminPermissions::BACKOFFICE]);

        $org = Organization::query()->create([
            'name' => 'WDR',
            'active' => true,
            'publication_domains' => "wdr.de\nwww.wdr.de",
        ]);

        $news = NewsItem::query()->create([
            'title' => 'Test',
            'slug' => 'test-pf-'.uniqid(),
            'status' => 'draft',
            'author_id' => $user->id,
        ]);

        $media = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'news-media/'.$news->id.'/image/x.jpg',
            'original_name' => 'x.jpg',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('admin.backoffice.publication-findings.create', [
                'news_item_media_id' => $media->id,
                'news_item_id' => $news->id,
            ]))
            ->assertOk()
            ->assertSee('Fundstelle erfassen', false);

        $media2 = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'news-media/'.$news->id.'/image/y2.jpg',
            'original_name' => 'y2.jpg',
            'sort_order' => 2,
        ]);

        $this->actingAs($user)
            ->post(route('admin.backoffice.publication-findings.store'), [
                'news_item_id' => $news->id,
                'news_item_media_ids' => [$media->id, $media2->id],
                'kind' => MediaPublicationFinding::KIND_LICENSED,
                'url' => 'https://www.wdr.de/nachrichten/test-artikel',
                'page_title' => 'Test-Artikel',
                'found_at' => now()->format('Y-m-d'),
                'confirmed' => '1',
            ])
            ->assertRedirect();

        $finding = MediaPublicationFinding::query()->first();
        $this->assertNotNull($finding);
        $this->assertDatabaseHas('media_publication_findings', [
            'id' => $finding->id,
            'news_item_media_id' => $media->id,
            'kind' => MediaPublicationFinding::KIND_LICENSED,
            'organization_id' => $org->id,
            'confirmed' => 1,
        ]);
        $this->assertDatabaseHas('media_publication_finding_media', [
            'media_publication_finding_id' => $finding->id,
            'news_item_media_id' => $media->id,
        ]);
        $this->assertDatabaseHas('media_publication_finding_media', [
            'media_publication_finding_id' => $finding->id,
            'news_item_media_id' => $media2->id,
        ]);
    }

    public function test_admin_can_store_youtube_infringement_with_sha256_url_hash(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([AdminPermissions::ACCESS, AdminPermissions::BACKOFFICE]);

        $news = NewsItem::query()->create([
            'title' => 'YouTube-Test',
            'slug' => 'yt-pf-'.uniqid(),
            'status' => 'draft',
            'author_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('admin.backoffice.publication-findings.store'), [
                'news_item_id' => $news->id,
                'kind' => MediaPublicationFinding::KIND_INFRINGEMENT,
                'url' => 'https://www.youtube.com/watch?v=qJAaxpSj2AY',
                'page_title' => 'Test-Video',
                'found_at' => now()->format('Y-m-d'),
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $finding = MediaPublicationFinding::query()->first();
        $this->assertNotNull($finding);
        $this->assertSame(64, strlen((string) $finding->url_hash));
    }

    public function test_news_images_endpoint_returns_images_for_news_item(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([AdminPermissions::ACCESS, AdminPermissions::BACKOFFICE]);

        $news = NewsItem::query()->create([
            'title' => 'Bilder-Meldung',
            'slug' => 'bilder-pf-api-'.uniqid(),
            'status' => 'draft',
            'author_id' => $user->id,
        ]);

        $media = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'news-media/'.$news->id.'/image/z.jpg',
            'original_name' => 'z.jpg',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->getJson(route('admin.backoffice.publication-findings.news-images', ['news_item_id' => $news->id]))
            ->assertOk()
            ->assertJsonPath('news_item_id', $news->id)
            ->assertJsonPath('title', 'Bilder-Meldung')
            ->assertJsonPath('images.0.id', $media->id);
    }

    public function test_videos_are_auto_linked_when_news_item_is_set(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([AdminPermissions::ACCESS, AdminPermissions::BACKOFFICE]);

        $news = NewsItem::query()->create([
            'title' => 'Video-Meldung',
            'slug' => 'video-pf-'.uniqid(),
            'status' => 'draft',
            'author_id' => $user->id,
        ]);

        $image = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'news-media/'.$news->id.'/image/a.jpg',
            'original_name' => 'a.jpg',
            'sort_order' => 1,
        ]);

        $video = NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'video',
            'path' => 'news-media/'.$news->id.'/video/a.mp4',
            'original_name' => 'a.mp4',
            'sort_order' => 2,
        ]);

        $this->actingAs($user)
            ->post(route('admin.backoffice.publication-findings.store'), [
                'news_item_id' => $news->id,
                'news_item_media_ids' => [$image->id],
                'kind' => MediaPublicationFinding::KIND_INFRINGEMENT,
                'url' => 'https://www.youtube.com/watch?v=autolinktest1',
                'found_at' => now()->format('Y-m-d'),
            ])
            ->assertRedirect();

        $finding = MediaPublicationFinding::query()->first();
        $this->assertNotNull($finding);
        $this->assertTrue($finding->mediaItems->contains('id', $video->id));
        $this->assertTrue($finding->mediaItems->contains('id', $image->id));
        $this->assertSame($image->id, $finding->news_item_media_id);
    }

    public function test_admin_can_upload_manual_channel_screenshot(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([AdminPermissions::ACCESS, AdminPermissions::BACKOFFICE]);

        $finding = MediaPublicationFinding::query()->create([
            'kind' => MediaPublicationFinding::KIND_INFRINGEMENT,
            'url' => 'https://www.youtube.com/watch?v=test1234567',
            'found_at' => now(),
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('admin.backoffice.publication-findings.manual-evidence.store', $finding), [
                'evidence_type' => 'screenshot_kanal',
                'file' => \Illuminate\Http\UploadedFile::fake()->image('kanal.png', 10, 10),
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $finding->refresh();
        $this->assertIsArray($finding->evidence_manual_files);
        $this->assertArrayHasKey('screenshot_kanal', $finding->evidence_manual_files);
    }

    public function test_authority_can_download_evidence_zip_with_password(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([AdminPermissions::ACCESS, AdminPermissions::BACKOFFICE]);

        $finding = MediaPublicationFinding::query()->create([
            'kind' => MediaPublicationFinding::KIND_INFRINGEMENT,
            'url' => 'https://example.com/video',
            'found_at' => now(),
            'created_by' => $user->id,
            'evidence_dossier_path' => 'publication-evidence/test/behoerde.zip',
            'authority_access_token' => 'testtoken123',
            'authority_access_expires_at' => now()->addDays(7),
            'authority_access_password' => \Illuminate\Support\Facades\Hash::make('geheim1234'),
            'authority_access_recipient' => 'STA Test',
            'authority_access_created_at' => now(),
        ]);

        \Illuminate\Support\Facades\Storage::fake(config('media_storage.disk', 'local'));
        \Illuminate\Support\Facades\Storage::disk(config('media_storage.disk', 'local'))
            ->put('publication-evidence/test/behoerde.zip', 'zip-content');

        $this->post(route('publication-finding.authority.password', 'testtoken123'), [
            'password' => 'geheim1234',
        ])->assertRedirect();

        $signed = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'publication-finding.authority.download',
            now()->addHour(),
            ['token' => 'testtoken123']
        );

        $this->withSession(['publication_finding_authority_password_ok_testtoken123' => true])
            ->get($signed)
            ->assertOk();

        $this->assertDatabaseHas('publication_finding_authority_downloads', [
            'media_publication_finding_id' => $finding->id,
        ]);
    }

    public function test_image_library_links_to_finding_create(): void
    {
        Permission::findOrCreate('access_admin');
        $user = User::factory()->create();
        $user->givePermissionTo(['access_admin', AdminPermissions::BACKOFFICE, 'admin.media']);

        $news = NewsItem::query()->create([
            'title' => 'Bild-Test',
            'slug' => 'bild-pf-'.uniqid(),
            'status' => 'draft',
            'author_id' => $user->id,
        ]);

        NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'news-media/'.$news->id.'/image/y.jpg',
            'original_name' => 'y.jpg',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('admin.images.index'))
            ->assertOk()
            ->assertSee('Fundstelle erfassen', false)
            ->assertSee('Google Lens', false);
    }
}
