<?php

namespace Tests\Unit;

use App\Models\Organization;
use App\Models\Product;
use App\Services\Lexware\LexwareContactService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class LexwareContactServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('lexware.base_url', 'https://api.lexoffice.io');
        Config::set('lexware.api_token', 'test-token');
    }

    public function test_it_resolves_lexware_contact_id_from_api_by_buyer_reference(): void
    {
        Http::fake([
            'https://api.lexoffice.io/v1/contacts*' => Http::response([
                'content' => [
                    [
                        'id' => '11111111-2222-3333-4444-555555555555',
                        'roles' => [
                            'customer' => [
                                'number' => 10022,
                            ],
                        ],
                    ],
                ],
                'last' => true,
                'totalPages' => 1,
            ], 200),
        ]);

        $org = Organization::create([
            'name' => 'TAG24 News Deutschland GmbH',
            'buyer_reference' => '10022',
        ]);

        $product = Product::create([
            'organization_id' => $org->id,
            'name' => 'Fotoredaktion',
            'buyer_reference' => null,
            'billing_company' => 'TAG24 News Deutschland GmbH',
            'billing_street' => 'Teststr. 1',
            'billing_postal_code' => '01067',
            'billing_city' => 'Dresden',
            'billing_country' => 'DE',
        ]);

        app(LexwareContactService::class)->ensureContact($product->fresh('organization'));

        $product->refresh();
        $org->refresh();

        $this->assertSame('11111111-2222-3333-4444-555555555555', $product->lexware_contact_id);
        $this->assertSame('11111111-2222-3333-4444-555555555555', $org->lexware_contact_id);
    }

    public function test_it_throws_when_api_lists_no_matching_customer(): void
    {
        Http::fake([
            'https://api.lexoffice.io/v1/contacts*' => Http::response([
                'content' => [
                    [
                        'id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
                        'roles' => [
                            'customer' => [
                                'number' => 99999,
                            ],
                        ],
                    ],
                ],
                'last' => true,
            ], 200),
        ]);

        $org = Organization::create([
            'name' => 'TAG24 News Deutschland GmbH',
            'buyer_reference' => '10022',
        ]);

        $product = Product::create([
            'organization_id' => $org->id,
            'name' => 'Fotoredaktion',
            'buyer_reference' => null,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('kein Kontakt mit passender Kundennummer');

        app(LexwareContactService::class)->ensureContact($product->fresh('organization'));
    }
}
