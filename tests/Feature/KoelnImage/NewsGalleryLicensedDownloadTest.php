<?php

namespace Tests\Feature\KoelnImage;

use App\Models\Brand;
use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewsGalleryLicensedDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config([
            'media_storage.disk' => 'local',
            'media_storage.fallback_disk' => 'local',
        ]);
    }

    public function test_guest_wird_von_download_zum_login_umgeleitet(): void
    {
        $brand = $this->koelnimageBrand();
        $news = $this->publishedNews($brand->id, 'dl-guest');
        Storage::disk('local')->put('m.jpg', 'x');
        $media = $this->createImage($news->id, ['path' => 'm.jpg']);

        $response = $this->get('http://koelnimage.de/gallery/photos/'.$news->slug.'/download/'.$media->id);

        $response->assertRedirect();
    }

    public function test_nutzer_ohne_lizenz_flag_erhaelt_403(): void
    {
        $brand = $this->koelnimageBrand();
        $news = $this->publishedNews($brand->id, 'dl-no-flag');
        Storage::disk('local')->put('m2.jpg', 'full-bytes');
        $media = $this->createImage($news->id, ['path' => 'm2.jpg']);
        $user = User::factory()->create(['koelnimage_licensed_download' => false]);

        $response = $this->actingAs($user)->get('http://koelnimage.de/gallery/photos/'.$news->slug.'/download/'.$media->id);

        $response->assertForbidden();
    }

    public function test_lizenzierter_nutzer_laedt_master_datei(): void
    {
        $brand = $this->koelnimageBrand();
        $news = $this->publishedNews($brand->id, 'dl-ok');
        Storage::disk('local')->put('master.jpg', 'full-image-bytes');
        $media = $this->createImage($news->id, [
            'path' => 'master.jpg',
            'original_name' => 'Pressebild.jpg',
        ]);
        $user = User::factory()->koelnimageLicensedDownload()->create();

        $response = $this->actingAs($user)->get('http://koelnimage.de/gallery/photos/'.$news->slug.'/download/'.$media->id);

        $response->assertOk();
        $response->assertDownload('Pressebild.jpg');
    }

    private function koelnimageBrand(): Brand
    {
        return Brand::query()->updateOrCreate(
            ['key' => 'koelnimage'],
            [
                'name' => 'KoelnImage',
                'primary_host' => 'koelnimage.de',
                'secondary_hosts' => [],
                'is_active' => true,
            ]
        );
    }

    private function publishedNews(int $brandId, string $slug): NewsItem
    {
        return NewsItem::create([
            'brand_id' => $brandId,
            'title' => 'Testmeldung '.$slug,
            'slug' => $slug,
            'status' => 'published',
            'published_at' => now()->subHour(),
            'embargo_at' => now()->subMinutes(10),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createImage(int $newsItemId, array $overrides = []): NewsItemMedia
    {
        return NewsItemMedia::create(array_merge([
            'news_item_id' => $newsItemId,
            'type' => 'image',
            'path' => 'news-media/default/image/default.jpg',
            'original_name' => 'default.jpg',
            'caption' => null,
            'sort_order' => 0,
            'is_visible' => true,
            'versand' => true,
            'delivery_visible_for_organization_ids' => [],
            'capture_time' => now(),
        ], $overrides));
    }
}
