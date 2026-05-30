<?php

namespace App\Services\PlannedEvents;

use App\Models\NewsItemMedia;
use App\Models\PlannedEvent;
use Carbon\Carbon;

/**
 * Ordnet Bild-Aufnahmezeiten den Slots aus dem extrahierten Programmtext (PDF) zu.
 *
 * Unterstützt u. a. ADAC-/Nürburgring-Layouts: Wochentag + Datum, numerisches Datum,
 * Zeitfenster mit Unicode-Bindestrichen, optionales „Uhr“, sowie Bezeichnungen in der
 * **nächsten** Zeile nach einer reinen „hh:mm – hh:mm Uhr“-Zeile (häufig bei PDF-Extrakt).
 */
final class ScheduleSlotMatcher
{
    private const TZ = 'Europe/Berlin';

    /** @var array<string, int> */
    private const MONTH_DE = [
        'januar' => 1,
        'februar' => 2,
        'märz' => 3,
        'april' => 4,
        'mai' => 5,
        'juni' => 6,
        'juli' => 7,
        'august' => 8,
        'september' => 9,
        'oktober' => 10,
        'november' => 11,
        'dezember' => 12,
    ];

    /**
     * Kurzer Hinweistext für die Bild-KI (leer, wenn keine Zuordnung möglich).
     */
    public function slotHintLineForMedia(NewsItemMedia $media, PlannedEvent $event): string
    {
        $match = $this->matchMediaToSchedule($media, $event);
        if ($match === null) {
            return '';
        }

        $captureLocal = $media->capture_time->copy()->timezone(self::TZ);
        $startLocal = $match['start']->copy()->timezone(self::TZ);
        $endLocal = $match['end']->copy()->timezone(self::TZ);

        return 'Rennslot laut Programm (Aufnahmezeit '.$captureLocal->format('d.m.Y, H:i').' Uhr): „'
            .$match['label'].'“ — Zeitfenster im Plan: '.$startLocal->format('d.m., H:i').'–'.$endLocal->format('H:i')
            .' Uhr. Nutze diese Bezeichnung in der Unterschrift nur, wenn sie zum sichtbaren Motiv passt.';
    }

    /**
     * @return array{label: string, start: Carbon, end: Carbon}|null
     */
    public function matchMediaToSchedule(NewsItemMedia $media, PlannedEvent $event): ?array
    {
        if (! $media->capture_time) {
            return null;
        }

        $text = trim((string) ($event->schedule_pdf_extracted_text ?? ''));
        if ($text === '') {
            return null;
        }

        return $this->matchCaptureToSchedule($media->capture_time, $text);
    }

    /**
     * @return array{label: string, start: Carbon, end: Carbon}|null
     */
    public function matchCaptureToSchedule(Carbon $capture, string $scheduleText): ?array
    {
        $slots = $this->parseSlots($scheduleText);
        if ($slots === []) {
            return null;
        }

        $t = $capture->copy()->timezone(self::TZ);

        foreach ($slots as $slot) {
            if ($t->greaterThanOrEqualTo($slot['start']) && $t->lessThanOrEqualTo($slot['end'])) {
                return [
                    'label' => $slot['label'],
                    'start' => $slot['start']->copy(),
                    'end' => $slot['end']->copy(),
                ];
            }
        }

        return null;
    }

    /**
     * @return list<array{start: Carbon, end: Carbon, label: string}>
     */
    public function parseSlots(string $text): array
    {
        $split = preg_split("/\r\n|\r|\n/", $text) ?: [];
        $lines = [];
        foreach ($split as $row) {
            $t = trim((string) $row);
            if ($t !== '') {
                $lines[] = $t;
            }
        }

        $lines = $this->mergeWrappedTimeLabels($lines);

        $currentBase = null;

        /** @var list<array{start: Carbon, end: ?Carbon, label: string}> */
        $raw = [];

        foreach ($lines as $line) {
            $header = $this->tryParseDateHeaderLine($line);
            if ($header !== null) {
                $currentBase = $header;

                continue;
            }

            if ($currentBase === null) {
                continue;
            }

            $range = $this->tryParseTimeRangeLine($line, $currentBase);
            if ($range !== null) {
                [$start, $end, $label] = $range;
                if ($label !== '') {
                    $raw[] = ['start' => $start, 'end' => $end, 'label' => $label];
                }

                continue;
            }

            $point = $this->tryParseSingleTimeLine($line, $currentBase);
            if ($point !== null) {
                [$start, $label] = $point;
                if ($label !== '') {
                    $raw[] = ['start' => $start, 'end' => null, 'label' => $label];
                }
            }
        }

        usort($raw, static fn ($a, $b): int => $a['start']->timestamp <=> $b['start']->timestamp);

        /** @var list<array{start: Carbon, end: Carbon, label: string}> */
        $out = [];

        foreach ($raw as $i => $row) {
            if ($row['end'] !== null) {
                $out[] = [
                    'start' => $row['start']->copy(),
                    'end' => $row['end']->copy(),
                    'label' => $row['label'],
                ];

                continue;
            }

            $nextStart = null;
            for ($j = $i + 1; $j < count($raw); $j++) {
                if ($raw[$j]['start']->greaterThan($row['start'])) {
                    $nextStart = $raw[$j]['start']->copy();

                    break;
                }
            }

            if ($nextStart !== null && $nextStart->isSameDay($row['start'])) {
                $end = $nextStart->copy()->subSecond();
            } else {
                $end = $row['start']->copy()->addHours(6);
            }

            if ($end->lessThan($row['start'])) {
                $end = $row['start']->copy()->addHours(6);
            }

            $out[] = [
                'start' => $row['start']->copy(),
                'end' => $end,
                'label' => $row['label'],
            ];
        }

        return $out;
    }

    /**
     * Hängt Zeilen mit Programmtitel an eine vorausgehende reine Uhrzeile (ohne Text in derselben Zeile).
     *
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function mergeWrappedTimeLabels(array $lines): array
    {
        $n = count($lines);
        if ($n === 0) {
            return [];
        }

        $dummy = Carbon::create(2020, 6, 15, 12, 0, 0, self::TZ);
        $out = [];

        for ($i = 0; $i < $n; $i++) {
            $line = $lines[$i];
            $range = $this->tryParseTimeRangeLine($line, $dummy);
            if ($range !== null && trim($range[2]) === '') {
                $extra = [];
                $j = $i + 1;
                while ($j < $n) {
                    $next = $lines[$j];
                    if ($this->tryParseDateHeaderLine($next) !== null) {
                        break;
                    }
                    if ($this->lineStartsNewTimeSlot($next, $dummy)) {
                        break;
                    }
                    $extra[] = $next;
                    $j++;
                }
                if ($extra !== []) {
                    $out[] = trim($line.' '.implode(' ', $extra));
                    $i = $j - 1;

                    continue;
                }
            }

            $point = $this->tryParseSingleTimeLine($line, $dummy);
            if ($point !== null && trim($point[1]) === '') {
                $extra = [];
                $j = $i + 1;
                while ($j < $n) {
                    $next = $lines[$j];
                    if ($this->tryParseDateHeaderLine($next) !== null) {
                        break;
                    }
                    if ($this->lineStartsNewTimeSlot($next, $dummy)) {
                        break;
                    }
                    $extra[] = $next;
                    $j++;
                }
                if ($extra !== []) {
                    $out[] = trim($line.' '.implode(' ', $extra));
                    $i = $j - 1;

                    continue;
                }
            }

            $out[] = $line;
        }

        return $out;
    }

    private function lineStartsNewTimeSlot(string $line, Carbon $dummyBase): bool
    {
        return $this->tryParseTimeRangeLine($line, $dummyBase) !== null
            || $this->tryParseSingleTimeLine($line, $dummyBase) !== null;
    }

    private function tryParseDateHeaderLine(string $line): ?Carbon
    {
        if (preg_match(
            '/(?:Montag|Dienstag|Mittwoch|Donnerstag|Freitag|Samstag|Sonntag),?\s+(\d{1,2})\.\s*'
            .'(Januar|Februar|März|April|Mai|Juni|Juli|August|September|Oktober|November|Dezember)\s+(\d{4})/iu',
            $line,
            $m
        )) {
            return $this->dateFromGerman((int) $m[1], mb_strtolower($m[2], 'UTF-8'), (int) $m[3]);
        }

        if (preg_match(
            '/^(?:Mo|Di|Mi|Do|Fr|Sa|So)\.?,?\s+(\d{1,2})\.\s*'
            .'(Januar|Februar|März|April|Mai|Juni|Juli|August|September|Oktober|November|Dezember)\s+(\d{4})/iu',
            $line,
            $m
        )) {
            return $this->dateFromGerman((int) $m[1], mb_strtolower($m[2], 'UTF-8'), (int) $m[3]);
        }

        if (preg_match(
            '/^(\d{1,2})\.\s*(Januar|Februar|März|April|Mai|Juni|Juli|August|September|Oktober|November|Dezember)\s+(\d{4})\s*$/iu',
            $line,
            $m
        )) {
            return $this->dateFromGerman((int) $m[1], mb_strtolower($m[2], 'UTF-8'), (int) $m[3]);
        }

        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(20\d{2})\s*$/u', $line, $m)) {
            $day = (int) $m[1];
            $month = (int) $m[2];
            $year = (int) $m[3];

            return Carbon::createFromDate($year, $month, $day, self::TZ)->startOfDay();
        }

        return null;
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string}|null
     */
    private function tryParseTimeRangeLine(string $line, Carbon $currentBase): ?array
    {
        $dash = '\s*\p{Pd}\s*';

        if (preg_match(
            '/^(\d{1,2}):(\d{2})'.$dash.'(\d{1,2}):(\d{2})\s*(?:Uhr\b)?\s*(.*)$/u',
            $line,
            $m
        )) {
            $start = $currentBase->copy()->setTime((int) $m[1], (int) $m[2], 0);
            $end = $currentBase->copy()->setTime((int) $m[3], (int) $m[4], 0);
            if ($end->lessThanOrEqualTo($start)) {
                $end = $end->copy()->addDay();
            }
            $label = trim((string) $m[5]);

            return [$start, $end, $label];
        }

        if (preg_match(
            '/^(\d{1,2})\.(\d{2})'.$dash.'(\d{1,2})\.(\d{2})\s*(?:Uhr\b)?\s*(.*)$/u',
            $line,
            $m
        )) {
            $start = $currentBase->copy()->setTime((int) $m[1], (int) $m[2], 0);
            $end = $currentBase->copy()->setTime((int) $m[3], (int) $m[4], 0);
            if ($end->lessThanOrEqualTo($start)) {
                $end = $end->copy()->addDay();
            }
            $label = trim((string) $m[5]);

            return [$start, $end, $label];
        }

        return null;
    }

    /**
     * @return array{0: Carbon, 1: string}|null
     */
    private function tryParseSingleTimeLine(string $line, Carbon $currentBase): ?array
    {
        if (preg_match('/^(\d{1,2}):(\d{2})\s*Uhr\s*(.*)$/u', $line, $m)) {
            $start = $currentBase->copy()->setTime((int) $m[1], (int) $m[2], 0);
            $label = trim((string) ($m[3] ?? ''));

            return [$start, $label];
        }

        if (preg_match('/^(\d{1,2})\.(\d{2})\s*Uhr\s*(.*)$/u', $line, $m)) {
            $start = $currentBase->copy()->setTime((int) $m[1], (int) $m[2], 0);
            $label = trim((string) ($m[3] ?? ''));

            return [$start, $label];
        }

        return null;
    }

    private function dateFromGerman(int $day, string $monthLowerDe, int $year): Carbon
    {
        $month = self::MONTH_DE[$monthLowerDe] ?? null;
        if ($month === null) {
            return Carbon::now(self::TZ)->startOfDay();
        }

        return Carbon::createFromDate($year, $month, $day, self::TZ)->startOfDay();
    }
}
