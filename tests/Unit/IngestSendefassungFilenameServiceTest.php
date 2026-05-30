<?php

namespace Tests\Unit;

use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use App\Services\Ingest\IngestSendefassungFilenameService;
use Carbon\Carbon;
use Tests\TestCase;

class IngestSendefassungFilenameServiceTest extends TestCase
{
    public function test_editorial_filename_for_koeln_kalk_einsturz(): void
    {
        $news = new NewsItem([
            'title' => 'Baugrube Köln-Kalk: Risse an Fassade von Mehrfamilienhaus',
            'city' => 'Köln',
            'keywords' => '2026, Köln, Feuerwehr, THW, Bauarbeiten, Einsturzgefahr, Hilfeleistung',
            'author_credit' => 'Alexander Franz',
            'event_at' => Carbon::parse('2026-05-28 15:15:00'),
        ]);
        $news->id = 114;

        $service = new IngestSendefassungFilenameService;

        $this->assertSame(
            '2026-05-28_koeln-kalk_einsturz_teil1_AF(alexander-franz).mp4',
            $service->buildOriginalName($news, 1, true)
        );
    }

    public function test_single_sendefassung_omits_teil_segment(): void
    {
        $news = new NewsItem([
            'title' => 'Unfall auf der A45 bei Meinerzhagen',
            'city' => 'Meinerzhagen',
            'keywords' => 'Verkehrsunfall, 2026',
            'author_credit' => 'Max Mustermann',
            'event_at' => Carbon::parse('2026-04-02 10:00:00'),
        ]);

        $service = new IngestSendefassungFilenameService;

        $this->assertSame(
            '2026-04-02_meinerzhagen_unfall_AF(max-mustermann).mp4',
            $service->buildOriginalName($news, 1, false)
        );
    }

    public function test_multiple_sendefassung_videos_get_teil_suffix(): void
    {
        $news = NewsItem::query()->create([
            'title' => 'Großbrand in Köln-Ehrenfeld',
            'city' => 'Köln',
            'keywords' => 'Brand, Feuerwehr, 2026',
            'author_credit' => 'Anna Redaktion',
            'event_at' => Carbon::parse('2026-03-10 18:00:00'),
            'slug' => 'grossbrand-in-koeln-ehrenfeld-'.uniqid('', true),
            'status' => 'draft',
        ]);

        NewsItemMedia::query()->create([
            'news_item_id' => $news->id,
            'type' => 'video',
            'path' => 'news-media/2026/05/29/test/100-sendefassung-alt.mp4',
            'original_name' => 'alt.mp4',
            'sort_order' => 1,
        ]);

        $service = new IngestSendefassungFilenameService;
        $naming = $service->assignForNewSendefassung($news);

        $first = $news->media()->orderBy('id')->first();
        $this->assertSame('2026-03-10_koeln-ehrenfeld_brand_teil1_AF(anna-redaktion).mp4', $first->original_name);
        $this->assertSame('2026-03-10_koeln-ehrenfeld_brand_teil2_AF(anna-redaktion).mp4', $naming['original_name']);
        $this->assertTrue($naming['use_part_suffix']);

        $news->media()->delete();
        $news->delete();
    }
}
