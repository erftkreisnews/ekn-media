<?php

namespace Tests\Unit;

use App\Models\NewsItem;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NewsItemMapsSearchUrlTest extends TestCase
{
    #[Test]
    public function maps_url_uses_coordinates_when_both_set(): void
    {
        $item = new NewsItem([
            'latitude' => 50.827,
            'longitude' => 6.978,
            'street' => 'Teststraße 1',
            'city' => 'Köln',
        ]);

        $url = $item->maps_search_url;

        $this->assertStringStartsWith('https://www.google.com/maps?q=', $url);
        $this->assertStringContainsString('50.', $url);
        $this->assertStringContainsString('6.', $url);
    }

    #[Test]
    public function maps_url_falls_back_to_location_label_without_coordinates(): void
    {
        $item = new NewsItem([
            'street' => 'Hauptstraße',
            'city' => 'Bonn',
            'region' => 'Rhein-Sieg-Kreis',
            'country' => 'Deutschland',
        ]);

        $url = $item->maps_search_url;

        $this->assertStringContainsString('google.com/maps', $url);
        $this->assertStringContainsString('query=', $url);
        $this->assertStringContainsString('Bonn', urldecode($url));
    }

    #[Test]
    public function maps_url_is_null_when_no_location_and_no_coordinates(): void
    {
        $item = new NewsItem([]);

        $this->assertNull($item->maps_search_url);
    }
}
