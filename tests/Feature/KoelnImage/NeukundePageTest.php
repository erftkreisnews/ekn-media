<?php

namespace Tests\Feature\KoelnImage;

use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NeukundePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_neukunde_seite_ist_auf_koelnimage_host_erreichbar(): void
    {
        $this->koelnimageBrand();

        $response = $this->get('http://koelnimage.de/neukunde');

        $response->assertOk();
        $response->assertSee('Neukunde werden', false);
        $response->assertSee('Anfrage senden', false);
    }

    public function test_neukunden_alternative_pfad_leitet_auf_neukunde_um(): void
    {
        $this->koelnimageBrand();

        $response = $this->get('http://koelnimage.de/neukunden');

        $response->assertRedirect('http://koelnimage.de/neukunde');
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
}
