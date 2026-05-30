<?php

namespace Tests\Unit;

use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use App\Models\PlannedEvent;
use App\Support\MediaCaptionLocationDateTail;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MediaCaptionLocationDateTailTest extends TestCase
{
    #[Test]
    public function format_tail_uses_city_venue_and_capture_date(): void
    {
        $event = new PlannedEvent;
        $event->forceFill([
            'venue_city' => 'Köln',
            'location' => 'LANXESS arena',
        ]);

        $news = new NewsItem;
        $news->forceFill(['planned_event_id' => 1]);
        $news->setRelation('plannedEvent', $event);

        $media = new NewsItemMedia;
        $media->forceFill([
            'type' => 'image',
            'city' => 'Köln',
            'capture_time' => Carbon::parse('2026-05-09 20:00:00'),
        ]);

        $tail = MediaCaptionLocationDateTail::formatTail($news, $media);

        $this->assertSame('Köln, LANXESS arena, 09.05.2026', $tail);
    }

    #[Test]
    public function format_tail_returns_null_without_capture_time(): void
    {
        $media = new NewsItemMedia;
        $media->forceFill(['type' => 'image', 'city' => 'Köln', 'capture_time' => null]);

        $this->assertNull(MediaCaptionLocationDateTail::formatTail(null, $media));
    }

    #[Test]
    public function format_venue_line_only_works_without_capture_time(): void
    {
        $event = new PlannedEvent;
        $event->forceFill(['venue_city' => 'Köln', 'location' => 'LANXESS arena']);

        $news = new NewsItem;
        $news->forceFill(['planned_event_id' => 1]);
        $news->setRelation('plannedEvent', $event);

        $media = new NewsItemMedia;
        $media->forceFill(['type' => 'image', 'city' => 'Berlin', 'capture_time' => null]);

        $this->assertSame(
            'Köln, LANXESS arena',
            MediaCaptionLocationDateTail::formatVenueLineOnly($news, $media)
        );
    }

    #[Test]
    public function append_strips_duplicate_tail_and_reappends(): void
    {
        $event = new PlannedEvent;
        $event->forceFill(['venue_city' => 'Köln', 'location' => 'LANXESS arena']);

        $news = new NewsItem;
        $news->forceFill(['planned_event_id' => 1]);
        $news->setRelation('plannedEvent', $event);

        $media = new NewsItemMedia;
        $media->forceFill([
            'type' => 'image',
            'city' => 'Köln',
            'capture_time' => Carbon::parse('2026-05-09'),
        ]);

        $caption = 'Die Band auf der Bühne. Köln, LANXESS arena, 09.05.2026';
        $out = MediaCaptionLocationDateTail::appendToCaption($news, $media, $caption);

        $this->assertSame('Die Band auf der Bühne. Köln, LANXESS arena, 09.05.2026', $out);
    }

    #[Test]
    public function append_recognizes_tail_with_short_date_and_does_not_duplicate(): void
    {
        $event = new PlannedEvent;
        $event->forceFill(['venue_city' => 'Köln', 'location' => 'LANXESS arena']);

        $news = new NewsItem;
        $news->forceFill(['planned_event_id' => 1]);
        $news->setRelation('plannedEvent', $event);

        $media = new NewsItemMedia;
        $media->forceFill([
            'type' => 'image',
            'city' => 'Köln',
            'capture_time' => Carbon::parse('2026-05-09'),
        ]);

        $caption = 'Die Band auf der Bühne. Köln, LANXESS arena, 9.5.2026';
        $out = MediaCaptionLocationDateTail::appendToCaption($news, $media, $caption);

        $this->assertSame('Die Band auf der Bühne. Köln, LANXESS arena, 09.05.2026', $out);
    }

    #[Test]
    public function append_body_only_adds_tail(): void
    {
        $event = new PlannedEvent;
        $event->forceFill(['venue_city' => '', 'location' => 'Halle']);

        $news = new NewsItem;
        $news->forceFill(['planned_event_id' => 1]);
        $news->setRelation('plannedEvent', $event);

        $media = new NewsItemMedia;
        $media->forceFill([
            'type' => 'image',
            'city' => 'Bonn',
            'capture_time' => Carbon::parse('2026-01-02'),
        ]);

        $out = MediaCaptionLocationDateTail::appendToCaption($news, $media, 'Konzert');

        $this->assertSame('Konzert. Halle, 02.01.2026', $out);
    }

    #[Test]
    public function format_tail_ignores_media_city_when_planned_event_is_linked(): void
    {
        $event = new PlannedEvent;
        $event->forceFill(['venue_city' => 'Köln', 'location' => 'LANXESS arena']);

        $news = new NewsItem;
        $news->forceFill(['planned_event_id' => 1]);
        $news->setRelation('plannedEvent', $event);

        $media = new NewsItemMedia;
        $media->forceFill([
            'type' => 'image',
            'city' => 'Berlin',
            'capture_time' => Carbon::parse('2026-05-09'),
        ]);

        $this->assertSame(
            'Köln, LANXESS arena, 09.05.2026',
            MediaCaptionLocationDateTail::formatTail($news, $media)
        );
    }

    #[Test]
    public function prepend_motiv_inserts_before_body_keeps_tail(): void
    {
        $event = new PlannedEvent;
        $event->forceFill(['venue_city' => 'Köln', 'location' => 'LANXESS arena']);

        $news = new NewsItem;
        $news->forceFill(['planned_event_id' => 1]);
        $news->setRelation('plannedEvent', $event);

        $media = new NewsItemMedia;
        $media->forceFill([
            'type' => 'image',
            'city' => '',
            'capture_time' => Carbon::parse('2026-05-09'),
        ]);

        $caption = 'Publikum vor der Bühne. Köln, LANXESS arena, 09.05.2026';
        $out = MediaCaptionLocationDateTail::prependMotivName($news, $media, $caption, 'Ben Zucker');

        $this->assertSame(
            'Ben Zucker. Publikum vor der Bühne. Köln, LANXESS arena, 09.05.2026',
            $out
        );
    }

    #[Test]
    public function prepend_motiv_skips_when_name_already_in_body(): void
    {
        $event = new PlannedEvent;
        $event->forceFill(['venue_city' => 'Köln', 'location' => 'Halle']);

        $news = new NewsItem;
        $news->forceFill(['planned_event_id' => 1]);
        $news->setRelation('plannedEvent', $event);

        $media = new NewsItemMedia;
        $media->forceFill([
            'type' => 'image',
            'capture_time' => Carbon::parse('2026-01-02'),
        ]);

        $caption = 'Mit Sängerin Anna auf der Bühne. Köln, Halle, 02.01.2026';
        $out = MediaCaptionLocationDateTail::prependMotivName($news, $media, $caption, 'Anna');

        $this->assertSame($caption, $out);
    }

    #[Test]
    public function append_removes_duplicate_generic_location_date_tails(): void
    {
        $event = new PlannedEvent;
        $event->forceFill(['venue_city' => '', 'location' => 'Nürburgring']);

        $news = new NewsItem;
        $news->forceFill(['planned_event_id' => 1]);
        $news->setRelation('plannedEvent', $event);

        $media = new NewsItemMedia;
        $media->forceFill([
            'type' => 'image',
            'capture_time' => Carbon::parse('2026-05-14'),
        ]);

        $caption = 'Ein Porsche fährt eine Kurve. Nürburgring, 14.05.2026. Nürburgring, 14.05.2026';
        $out = MediaCaptionLocationDateTail::appendToCaption($news, $media, $caption);

        $this->assertSame('Ein Porsche fährt eine Kurve. Nürburgring, 14.05.2026', $out);
    }

    #[Test]
    public function strip_generic_removes_multi_segment_venue_tail(): void
    {
        $caption = 'Die Band auf der Bühne. Köln, LANXESS arena, 09.05.2026';
        $out = MediaCaptionLocationDateTail::stripGenericTrailingLocationDateTails($caption);

        $this->assertSame('Die Band auf der Bühne', $out);
    }

    #[Test]
    public function format_tail_when_location_already_contains_city_uses_location_once(): void
    {
        $event = new PlannedEvent;
        $event->forceFill([
            'venue_city' => 'Köln',
            'location' => 'Köln, LANXESS arena',
        ]);

        $news = new NewsItem;
        $news->forceFill(['planned_event_id' => 1]);
        $news->setRelation('plannedEvent', $event);

        $media = new NewsItemMedia;
        $media->forceFill([
            'type' => 'image',
            'city' => '',
            'capture_time' => Carbon::parse('2026-05-09'),
        ]);

        $this->assertSame(
            'Köln, LANXESS arena, 09.05.2026',
            MediaCaptionLocationDateTail::formatTail($news, $media)
        );
    }
}
