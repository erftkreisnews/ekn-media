<?php

namespace Tests\Feature\KoelnImage;

use App\Models\Brand;
use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsGalleryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_liefert_nur_oeffentlich_sichtbare_bilder_der_news(): void
    {
        $brand = $this->koelnimageBrand();
        $news = $this->publishedNews($brand->id, 'galerie-test-1');
        $otherNews = $this->publishedNews($brand->id, 'andere-news');

        $visible = $this->createImage($news->id, [
            'caption' => 'Sichtbares Bild',
            'path' => 'news-media/1/image/visible.jpg',
            'versand' => true,
            'is_visible' => true,
            'delivery_visible_for_organization_ids' => [],
        ]);

        $this->createImage($news->id, [
            'caption' => 'Nur fuer B2B',
            'path' => 'news-media/1/image/b2b.jpg',
            'delivery_visible_for_organization_ids' => [999],
        ]);

        $this->createImage($news->id, [
            'caption' => 'Nicht im Versand',
            'path' => 'news-media/1/image/no-send.jpg',
            'versand' => false,
        ]);

        NewsItemMedia::create([
            'news_item_id' => $news->id,
            'type' => 'video',
            'path' => 'news-media/1/video/demo.mp4',
            'original_name' => 'demo.mp4',
            'sort_order' => 5,
            'is_visible' => true,
            'versand' => true,
        ]);

        $this->createImage($otherNews->id, [
            'caption' => 'Bild anderer News',
            'path' => 'news-media/2/image/other.jpg',
        ]);

        $response = $this->getJson('http://koelnimage.de/gallery/photos/'.$news->slug);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $visible->id);
        $response->assertJsonPath('data.0.is_favorite', false);
        $response->assertJsonPath('data.0.is_in_cart', false);
        $response->assertJsonPath('data.0.download_url', null);
        $response->assertJsonPath('meta.licensed_download', false);
        $response->assertJsonPath('meta.total', 1);
    }

    public function test_api_filtert_nach_suche_und_sortiert_dateiname_aufsteigend(): void
    {
        $brand = $this->koelnimageBrand();
        $news = $this->publishedNews($brand->id, 'galerie-test-2');

        $first = $this->createImage($news->id, [
            'caption' => 'Action in der Boxengasse',
            'original_name' => 'b_file.jpg',
            'path' => 'news-media/3/image/b_file.jpg',
        ]);
        $second = $this->createImage($news->id, [
            'caption' => 'Action auf der Zielgeraden',
            'original_name' => 'a_file.jpg',
            'path' => 'news-media/3/image/a_file.jpg',
        ]);
        $this->createImage($news->id, [
            'caption' => 'Ruhige Szene',
            'original_name' => 'c_file.jpg',
            'path' => 'news-media/3/image/c_file.jpg',
        ]);

        $response = $this->getJson('http://koelnimage.de/gallery/photos/'.$news->slug.'?q=Action&sort=filename_az');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.id', $second->id);
        $response->assertJsonPath('data.1.id', $first->id);
    }

    public function test_api_verwendet_per_page_und_liefert_pagination_meta(): void
    {
        $brand = $this->koelnimageBrand();
        $news = $this->publishedNews($brand->id, 'galerie-test-3');

        for ($i = 1; $i <= 12; $i++) {
            $this->createImage($news->id, [
                'caption' => 'Bild '.$i,
                'original_name' => 'bild-'.$i.'.jpg',
                'path' => 'news-media/4/image/bild-'.$i.'.jpg',
                'capture_time' => now()->subMinutes($i),
            ]);
        }

        $response = $this->getJson('http://koelnimage.de/gallery/photos/'.$news->slug.'?per_page=10&page=2');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.current_page', 2);
        $response->assertJsonPath('meta.per_page', 10);
        $response->assertJsonPath('meta.total', 12);
        $response->assertJsonPath('meta.last_page', 2);
    }

    public function test_favorite_toggle_persistiert_in_session_und_spiegelt_sich_im_feed(): void
    {
        $brand = $this->koelnimageBrand();
        $news = $this->publishedNews($brand->id, 'galerie-test-4');
        $image = $this->createImage($news->id, [
            'path' => 'news-media/5/image/favorite.jpg',
        ]);

        $toggleOn = $this->postJson('http://koelnimage.de/gallery/photos/'.$news->slug.'/favorites/toggle', [
            'media_id' => $image->id,
        ]);
        $toggleOn->assertOk();
        $toggleOn->assertJsonPath('media_id', $image->id);
        $toggleOn->assertJsonPath('is_favorite', true);
        $toggleOn->assertJsonPath('favorites_count', 1);

        $list = $this->getJson('http://koelnimage.de/gallery/photos/'.$news->slug);
        $list->assertOk();
        $list->assertJsonPath('data.0.is_favorite', true);
        $list->assertJsonPath('meta.favorites_count', 1);

        $toggleOff = $this->postJson('http://koelnimage.de/gallery/photos/'.$news->slug.'/favorites/toggle', [
            'media_id' => $image->id,
        ]);
        $toggleOff->assertOk();
        $toggleOff->assertJsonPath('is_favorite', false);
        $toggleOff->assertJsonPath('favorites_count', 0);
    }

    public function test_cart_toggle_persistiert_in_session_und_spiegelt_sich_im_feed(): void
    {
        $brand = $this->koelnimageBrand();
        $news = $this->publishedNews($brand->id, 'galerie-test-5');
        $image = $this->createImage($news->id, [
            'path' => 'news-media/6/image/cart.jpg',
        ]);

        $toggleOn = $this->postJson('http://koelnimage.de/gallery/photos/'.$news->slug.'/cart/toggle', [
            'media_id' => $image->id,
        ]);
        $toggleOn->assertOk();
        $toggleOn->assertJsonPath('media_id', $image->id);
        $toggleOn->assertJsonPath('is_in_cart', true);
        $toggleOn->assertJsonPath('cart_count', 1);

        $list = $this->getJson('http://koelnimage.de/gallery/photos/'.$news->slug);
        $list->assertOk();
        $list->assertJsonPath('data.0.is_in_cart', true);
        $list->assertJsonPath('meta.cart_count', 1);

        $toggleOff = $this->postJson('http://koelnimage.de/gallery/photos/'.$news->slug.'/cart/toggle', [
            'media_id' => $image->id,
        ]);
        $toggleOff->assertOk();
        $toggleOff->assertJsonPath('is_in_cart', false);
        $toggleOff->assertJsonPath('cart_count', 0);
    }

    public function test_browser_request_liefert_html_galerieseite_statt_json(): void
    {
        $brand = $this->koelnimageBrand();
        $news = $this->publishedNews($brand->id, 'galerie-html-ansicht');
        $this->createImage($news->id, [
            'path' => 'news-media/html-test/image/one.jpg',
        ]);

        $response = $this->get('http://koelnimage.de/gallery/photos/'.$news->slug);

        $response->assertOk();
        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
        $response->assertSee('koelnimage-hub-gallery-v2', false);
        $response->assertSee('koelnimageGalleryHub', false);
        $response->assertSee($news->title, false);
    }

    public function test_api_liefert_download_url_fuer_lizenzierten_angemeldeten_nutzer(): void
    {
        $brand = $this->koelnimageBrand();
        $news = $this->publishedNews($brand->id, 'galerie-licensed-json');
        $user = \App\Models\User::factory()->koelnimageLicensedDownload()->create();
        $image = $this->createImage($news->id, [
            'path' => 'news-media/licensed/img.jpg',
        ]);

        $response = $this->actingAs($user)->getJson('http://koelnimage.de/gallery/photos/'.$news->slug);

        $response->assertOk();
        $response->assertJsonPath('meta.licensed_download', true);
        $response->assertJsonPath('data.0.id', $image->id);
        $this->assertNotNull($response->json('data.0.download_url'));
        $this->assertStringContainsString('/gallery/photos/'.$news->slug.'/download/'.$image->id, (string) $response->json('data.0.download_url'));
    }

    public function test_time_window_liefert_bilder_im_aufgezeichneten_zeitraum(): void
    {
        $brand = $this->koelnimageBrand();
        $news = $this->publishedNews($brand->id, 'galerie-time-window');
        $refTime = now()->subHour();

        $reference = $this->createImage($news->id, [
            'caption' => 'Referenz',
            'path' => 'news-media/tw/ref.jpg',
            'capture_time' => $refTime,
        ]);
        $inside = $this->createImage($news->id, [
            'caption' => 'Im Fenster',
            'path' => 'news-media/tw/in.jpg',
            'capture_time' => $refTime->copy()->addSeconds(20),
        ]);
        $outside = $this->createImage($news->id, [
            'caption' => 'Außerhalb',
            'path' => 'news-media/tw/out.jpg',
            'capture_time' => $refTime->copy()->addMinutes(10),
        ]);

        $response = $this->getJson('http://koelnimage.de/gallery/photos/'.$news->slug.'/time-window?reference_media_id='.$reference->id.'&window=60s&direction=both');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($reference->id, $ids);
        $this->assertContains($inside->id, $ids);
        $this->assertNotContains($outside->id, $ids);
        $this->assertCount(2, $ids);
    }

    public function test_legacy_news_gallery_photos_url_301_zur_kanonischen_galerie(): void
    {
        $brandId = $this->koelnimageBrand()->id;
        $news = $this->publishedNews($brandId, 'legacy-redirect-slug');

        $response = $this->get('http://koelnimage.de/news/'.$news->slug.'/gallery/photos?sort=oldest');

        $response->assertRedirect('http://koelnimage.de/gallery/photos/'.$news->slug.'?sort=oldest');
        $this->assertSame(301, $response->getStatusCode());
    }

    public function test_news_detail_url_leitet_301_auf_kanonische_galerie(): void
    {
        $brandId = $this->koelnimageBrand()->id;
        $news = $this->publishedNews($brandId, 'news-path-redirect');

        $response = $this->get('http://koelnimage.de/news/'.$news->slug);

        $response->assertRedirect('http://koelnimage.de/gallery/photos/'.$news->slug);
        $this->assertSame(301, $response->getStatusCode());
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
