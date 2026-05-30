<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NominatimGeocodingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('access_admin');
    }

    public function test_guest_cannot_access_geocoding_search(): void
    {
        $this->getJson(route('admin.geocoding.search', ['q' => 'Köln']))
            ->assertUnauthorized();
    }

    public function test_admin_search_returns_mapped_fields(): void
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/search*' => Http::response([
                [
                    'lat' => '50.9',
                    'lon' => '6.9',
                    'display_name' => 'Testort',
                    'address' => [
                        'country' => 'Deutschland',
                        'state' => 'Nordrhein-Westfalen',
                        'city' => 'Erftstadt',
                        'road' => 'Hauptstraße',
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $this->actingAs($user)
            ->getJson(route('admin.geocoding.search', ['q' => 'Erftstadt']))
            ->assertOk()
            ->assertJsonPath('results.0.fields.city', 'Erftstadt')
            ->assertJsonPath('results.0.fields.federal_state', 'Nordrhein-Westfalen');
    }
}
