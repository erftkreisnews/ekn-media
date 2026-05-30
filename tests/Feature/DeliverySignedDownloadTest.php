<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class DeliverySignedDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_download_requires_valid_signature(): void
    {
        $news = NewsItem::create(['title' => 'Signed download test']);
        $media = NewsItemMedia::create([
            'news_item_id' => $news->id,
            'type' => 'image',
            'path' => 'news-media/default/image/default.jpg',
            'original_name' => 'default.jpg',
            'is_visible' => true,
            'versand' => true,
            'delivery_visible_for_organization_ids' => [],
        ]);

        $delivery = Delivery::create([
            'news_item_id' => $news->id,
            'recipient_email' => 'desk@example.com',
            'expires_at' => now()->addDay(),
        ]);

        $unsigned = route('delivery.download', ['token' => $delivery->token, 'media' => $media->id], absolute: false);
        $this->get($unsigned)->assertForbidden();

        $signed = URL::temporarySignedRoute(
            'delivery.download',
            now()->addMinutes(10),
            ['token' => $delivery->token, 'media' => $media->id]
        );
        $this->get($signed)->assertNotFound();
    }
}
