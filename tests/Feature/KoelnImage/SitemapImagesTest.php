<?php

namespace Tests\Feature\KoelnImage;

use App\Models\Brand;
use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_images_xml_ist_erreichbar_und_enthaelt_galerie_mit_bild(): void
    {
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
            'brand_id' => $brand->id,
            'title' => 'Sitemap Bildtest',
            'slug' => 'sitemap-bildtest',
            'status' => 'published',
            'published_at' => now()->subHour(),
            'embargo_at' => now()->subMinutes(10),
        ]);

        NewsItemMedia::create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'news-media/sitemap/image.jpg',
            'preview_path' => 'news-media/sitemap/preview.webp',
            'original_name' => 'image.jpg',
            'caption' => 'Testbild für Sitemap',
            'image_title' => 'Titel Sitemap',
            'sort_order' => 0,
            'is_visible' => true,
            'versand' => true,
            'delivery_visible_for_organization_ids' => [],
        ]);

        $response = $this->get('http://koelnimage.de/sitemap-images.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=utf-8');
        $content = $response->getContent();
        $this->assertStringContainsString('xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"', (string) $content);
        $this->assertStringContainsString('https://koelnimage.de/gallery/photos/sitemap-bildtest', (string) $content);
        $this->assertStringContainsString('<image:loc>', (string) $content);
        $this->assertStringContainsString('Titel Sitemap', (string) $content);
    }
}
