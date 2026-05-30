<?php

namespace App\Support;

use App\Models\NewsItemMedia;

class CaptionDateSanitizer
{
    /**
     * @param  list<string>  $allowedDates
     */
    public static function sanitize(string $caption, array $allowedDates): string
    {
        $allowedLookup = [];
        foreach ($allowedDates as $date) {
            $trimmed = trim((string) $date);
            if ($trimmed !== '') {
                $allowedLookup[$trimmed] = true;
            }
        }

        $cleaned = preg_replace_callback('/\b(\d{2}\.\d{2}\.\d{4})\b/u', static function (array $m) use ($allowedLookup): string {
            $date = (string) ($m[1] ?? '');

            return isset($allowedLookup[$date]) ? $date : '';
        }, $caption) ?? $caption;

        // Satzzeichen/Leerzeichen nach Datum-Entfernung glätten.
        $cleaned = preg_replace('/,\s*\./u', '.', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/\(\s*\)/u', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/\s+,/u', ',', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/,\s*$/u', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/\s{2,}/u', ' ', $cleaned) ?? $cleaned;
        $cleaned = trim($cleaned);

        return $cleaned;
    }

    /**
     * @return list<string>
     */
    public static function allowedDatesForMedia(NewsItemMedia $media): array
    {
        $dates = [];

        if ($media->capture_time) {
            $dates[] = $media->capture_time->format('d.m.Y');
        }

        return array_values(array_unique(array_filter($dates, static fn ($d) => is_string($d) && $d !== '')));
    }
}
