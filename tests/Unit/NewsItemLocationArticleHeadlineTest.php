<?php

namespace Tests\Unit;

use App\Models\NewsItem;
use PHPUnit\Framework\TestCase;

class NewsItemLocationArticleHeadlineTest extends TestCase
{
    public function test_headline_prefers_city_then_detail_after_em_dash(): void
    {
        $n = new NewsItem([
            'street' => 'Bahnstraße 53',
            'city' => 'Rommerskirchen',
            'region' => 'Rhein-Kreis Neuss',
            'country' => 'Deutschland',
        ]);

        $this->assertSame(
            'Einsatz in Rommerskirchen – Bahnstraße 53, Rhein-Kreis Neuss, Deutschland',
            $n->location_article_headline
        );
    }

    public function test_headline_city_only(): void
    {
        $n = new NewsItem(['city' => 'Köln']);

        $this->assertSame('Einsatz in Köln', $n->location_article_headline);
    }

    public function test_headline_falls_back_when_no_city(): void
    {
        $n = new NewsItem([
            'street' => 'Hauptstraße 1',
            'region' => 'SK Köln',
            'country' => 'Deutschland',
        ]);

        $this->assertSame(
            'Einsatzort: Hauptstraße 1, SK Köln, Deutschland',
            $n->location_article_headline
        );
    }

    public function test_headline_empty_location(): void
    {
        $n = new NewsItem([]);

        $this->assertSame('Einsatzort und Ereignis', $n->location_article_headline);
    }
}
