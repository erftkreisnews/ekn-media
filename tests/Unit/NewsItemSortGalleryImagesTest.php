<?php

namespace Tests\Unit;

use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use Carbon\Carbon;
use Tests\TestCase;

class NewsItemSortGalleryImagesTest extends TestCase
{
    public function test_sortiert_nach_aufnahmezeit_neueste_zuerst_ohne_zeit_am_ende(): void
    {
        $newsItem = new NewsItem;
        $oldest = (new NewsItemMedia(['capture_time' => Carbon::parse('2026-05-14 08:00:00')]))->forceFill(['id' => 1]);
        $newest = (new NewsItemMedia(['capture_time' => Carbon::parse('2026-05-15 12:00:00')]))->forceFill(['id' => 2]);
        $noTime = (new NewsItemMedia(['capture_time' => null]))->forceFill(['id' => 3]);

        $sorted = $newsItem->sortGalleryImagesByCaptureTime(collect([$oldest, $noTime, $newest]), newestFirst: true);

        $this->assertSame([2, 1, 3], $sorted->pluck('id')->all());
    }

    public function test_sortiert_aufsteigend_wenn_aelteste_zuerst(): void
    {
        $newsItem = new NewsItem;
        $oldest = (new NewsItemMedia(['capture_time' => Carbon::parse('2026-05-14 08:00:00')]))->forceFill(['id' => 1]);
        $newest = (new NewsItemMedia(['capture_time' => Carbon::parse('2026-05-15 12:00:00')]))->forceFill(['id' => 2]);

        $sorted = $newsItem->sortGalleryImagesByCaptureTime(collect([$newest, $oldest]), newestFirst: false);

        $this->assertSame([1, 2], $sorted->pluck('id')->all());
    }
}
