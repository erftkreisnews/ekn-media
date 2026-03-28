<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\NewsItem;
use App\Models\Organization;
use App\Models\Product;
use App\Models\RecipientConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryRecipientConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_is_auto_confirmed_when_recipient_has_recent_confirmation(): void
    {
        $org = Organization::create(['name' => 'Tag24']);
        $product = Product::create(['organization_id' => $org->id, 'name' => 'Online']);
        $news = NewsItem::create(['title' => 'Test']);

        $delivery = Delivery::create([
            'news_item_id' => $news->id,
            'recipient_email' => 'koeln@tag24.de',
            'expires_at' => now()->addDay(),
            'allowed_organization_id' => $org->id,
        ]);

        RecipientConfirmation::create([
            'email' => 'koeln@tag24.de',
            'organization_id' => $org->id,
            'product_id' => $product->id,
            'last_confirmed_at' => now()->subDay(),
            'confirmed_until' => now()->addMonthsNoOverflow(6),
        ]);

        $res = $this->get(route('delivery.show', ['token' => $delivery->token]));
        $res->assertOk();
        $res->assertDontSee('Redaktion &amp; Produkt (optional)', false);

        $delivery->refresh();
        $this->assertNotNull($delivery->confirmed_at);
        $this->assertSame($org->id, $delivery->organization_id);
        $this->assertSame($product->id, $delivery->product_id);
    }
}
