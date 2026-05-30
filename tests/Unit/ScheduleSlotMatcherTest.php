<?php

namespace Tests\Unit;

use App\Services\PlannedEvents\ScheduleSlotMatcher;
use Carbon\Carbon;
use Tests\TestCase;

class ScheduleSlotMatcherTest extends TestCase
{
    public function test_match_capture_falls_in_time_range_slot(): void
    {
        $text = <<<'TXT'
Donnerstag, 14. Mai 2026
08:30 – 12:30 Uhr RCN Rundstrecken Challenge
13:15 – 15:15 Uhr ADAC RAVENOL 24h Nürburgring GE Qualifying 1
TXT;

        $m = new ScheduleSlotMatcher;
        $capture = Carbon::parse('2026-05-14 13:42:00', 'Europe/Berlin');
        $match = $m->matchCaptureToSchedule($capture, $text);
        $this->assertNotNull($match);
        $this->assertStringContainsString('Qualifying 1', $match['label']);
    }

    public function test_match_saturday_main_race_start_slot(): void
    {
        $text = <<<'TXT'
Samstag, 16. Mai 2026
13:00 – 14:40 Uhr ADAC RAVENOL 24h Nürburgring Startaufstellung
14:40 Uhr ADAC RAVENOL 24h Nürburgring GE Formationsrunde
15:00 Uhr ADAC RAVENOL 24h Nürburgring GE Start Rennen
TXT;

        $m = new ScheduleSlotMatcher;
        $capture = Carbon::parse('2026-05-16 15:30:00', 'Europe/Berlin');
        $match = $m->matchCaptureToSchedule($capture, $text);
        $this->assertNotNull($match);
        $this->assertStringContainsString('Start Rennen', $match['label']);
    }

    public function test_single_time_slot_extends_until_next_slot_same_day(): void
    {
        $text = <<<'TXT'
Freitag, 15. Mai 2026
12:40 – 13:05 Uhr Tourenwagen-Legenden GP Rennen 1
13:35 – 14:35 Uhr ADAC RAVENOL 24h Nürburgring GE Top Quali 3
TXT;

        $m = new ScheduleSlotMatcher;
        $capture = Carbon::parse('2026-05-15 13:40:00', 'Europe/Berlin');
        $match = $m->matchCaptureToSchedule($capture, $text);
        $this->assertNotNull($match);
        $this->assertStringContainsString('Top Quali 3', $match['label']);
    }

    public function test_no_match_outside_event_days(): void
    {
        $text = 'Donnerstag, 14. Mai 2026'."\n08:30 – 12:30 Uhr RCN\n";
        $m = new ScheduleSlotMatcher;
        $capture = Carbon::parse('2026-05-20 10:00:00', 'Europe/Berlin');
        $this->assertNull($m->matchCaptureToSchedule($capture, $text));
    }

    public function test_match_when_event_title_follows_time_row_on_next_line(): void
    {
        $text = <<<'TXT'
Donnerstag, 14. Mai 2026
08:30 – 12:30 Uhr
RCN Rundstrecken Challenge Nürburgring GE Leistungsprüfung
13:15 – 15:15 Uhr ADAC RAVENOL 24h Nürburgring GE Qualifying 1
TXT;

        $m = new ScheduleSlotMatcher;
        $capture = Carbon::parse('2026-05-14 10:15:00', 'Europe/Berlin');
        $match = $m->matchCaptureToSchedule($capture, $text);
        $this->assertNotNull($match);
        $this->assertStringContainsString('Leistungsprüfung', $match['label']);
        $this->assertStringContainsString('RCN Rundstrecken Challenge', $match['label']);
    }

    public function test_numeric_date_header(): void
    {
        $text = <<<'TXT'
14.05.2026
08:30–12:30 RCN Nürburgring GE Leistungsprüfung
13:15 - 15:15 ADAC Qualifying
TXT;

        $m = new ScheduleSlotMatcher;
        $capture = Carbon::parse('2026-05-14 09:45:00', 'Europe/Berlin');
        $match = $m->matchCaptureToSchedule($capture, $text);
        $this->assertNotNull($match);
        $this->assertStringContainsString('Leistungsprüfung', $match['label']);
    }
}
