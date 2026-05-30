<?php

namespace App\Support;

use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use App\Models\PlannedEvent;
use Carbon\Carbon;

/**
 * Redaktions-Schluss für Bildunterschriften: Ort aus der geplanten Veranstaltung
 * (Stadt + Kurz-Ort aus {@see PlannedEvent::venue_city} / {@see PlannedEvent::location}),
 * Datum aus Aufnahmezeit ({@see NewsItemMedia::capture_time}).
 */
final class MediaCaptionLocationDateTail
{
    public const CAPTION_MAX_BYTES = 1800;

    public static function formatTail(?NewsItem $news, NewsItemMedia $media): ?string
    {
        $variants = self::buildTailVariantStrings($news, $media);

        return $variants[0] ?? null;
    }

    /**
     * Schlusszeile(n) mit gleichem Ort, Datum in üblichen Schreibweisen (z. B. 9.5. vs 09.05.),
     * damit vorhandene Captions aus IPTC/KI erkannt und nicht doppelt angehängt werden.
     *
     * @return list<string> Kanonisch (d.m.Y) zuerst, dann kürzere Datumsdarstellungen.
     */
    private static function buildTailVariantStrings(?NewsItem $news, NewsItemMedia $media): array
    {
        if (! $media->isImage() || ! $media->capture_time) {
            return [];
        }

        $venueLine = self::resolveVenueLineForCaption($news, $media);
        $dt = Carbon::parse($media->capture_time);
        $out = [];
        foreach (['d.m.Y', 'j.n.Y', 'd.n.Y', 'j.m.Y'] as $fmt) {
            $dateStr = $dt->format($fmt);
            $parts = array_values(array_filter([$venueLine, $dateStr], static fn ($s) => is_string($s) && $s !== ''));
            if ($parts !== []) {
                $out[] = implode(', ', $parts);
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Kurz-Ort für Unterschriften (Stadt + Veranstaltungsstätte / location_label / IPTC-Stadt),
     * ohne Datum – auch nutzbar wenn die Aufnahmezeit noch fehlt.
     */
    public static function formatVenueLineOnly(?NewsItem $news, NewsItemMedia $media): ?string
    {
        if (! $media->isImage()) {
            return null;
        }

        $venueLine = self::resolveVenueLineForCaption($news, $media);

        return $venueLine !== '' ? $venueLine : null;
    }

    private static function resolveVenueLineForCaption(?NewsItem $news, NewsItemMedia $media): string
    {
        $venueLine = '';

        if ($news && (int) ($news->planned_event_id ?? 0) > 0) {
            $event = $news->relationLoaded('plannedEvent')
                ? $news->plannedEvent
                : $news->plannedEvent()->first();
            if ($event instanceof PlannedEvent) {
                $venueLine = self::venueLineFromPlannedEvent($event);
            }
        }

        if ($venueLine === '' && $news) {
            $venueLine = trim((string) ($news->location_label ?? ''));
        }
        if ($venueLine === '') {
            $venueLine = trim((string) ($media->city ?? ''));
        }

        return $venueLine;
    }

    /**
     * Kurz-Ort für die Unterschrift: nur Felder der geplanten Veranstaltung (nicht IPTC-Stadt am Medium).
     */
    public static function venueLineFromPlannedEvent(PlannedEvent $event): string
    {
        $venueCity = trim((string) ($event->venue_city ?? ''));
        $location = trim((string) ($event->location ?? ''));

        if ($venueCity !== '' && $location !== '') {
            if (mb_stripos($location, $venueCity, 0, 'UTF-8') !== false) {
                return $location;
            }

            return $venueCity.', '.$location;
        }

        if ($location !== '') {
            return $location;
        }

        return $venueCity;
    }

    /**
     * Setzt den Künstler-/Teamnamen vor den Motiv-Teil der Unterschrift (redaktionsüblich vor Ort/Datum-Schwanz).
     * Wenn der Name im Motiv-Teil schon vorkommt, bleibt die Unterschrift unverändert (keine Doppelung).
     */
    public static function prependMotivName(?NewsItem $news, NewsItemMedia $media, string $caption, string $motivName): string
    {
        $motivName = trim($motivName);
        if ($motivName === '' || ! $media->isImage()) {
            return self::appendToCaption($news, $media, $caption);
        }

        $tailVariants = self::buildTailVariantStrings($news, $media);
        $caption = trim($caption);
        $body = $tailVariants !== [] ? self::stripTrailingTailVariants($caption, $tailVariants) : $caption;

        if ($body !== '' && mb_stripos($body, $motivName, 0, 'UTF-8') !== false) {
            return self::appendToCaption($news, $media, $caption);
        }

        $newBody = $body === '' ? $motivName : ($motivName.'. '.$body);

        return self::appendToCaption($news, $media, $newBody);
    }

    /**
     * Entfernt bekannte Ort/Datum-Schlusszeilen am Textende (inkl. optionaler Satzzeichen).
     */
    public static function stripKnownTails(?NewsItem $news, NewsItemMedia $media, string $caption): string
    {
        $tailVariants = self::buildTailVariantStrings($news, $media);
        if ($tailVariants === []) {
            return self::stripGenericTrailingLocationDateTails(trim($caption));
        }

        $caption = self::stripTrailingTailVariants(trim($caption), $tailVariants);

        return self::stripGenericTrailingLocationDateTails($caption);
    }

    /**
     * Entfernt generische Ort/Datum-Schlusszeilen am Textende (z. B. aus KI oder IPTC),
     * auch wenn sie nicht exakt der geplanten Veranstaltung entsprechen.
     */
    public static function stripGenericTrailingLocationDateTails(string $caption): string
    {
        $caption = trim($caption);
        while ($caption !== '') {
            if (preg_match(self::genericLocationDateTailPattern(), $caption, $matches) !== 1) {
                break;
            }
            $tail = trim((string) ($matches[0] ?? ''));
            if ($tail === '') {
                break;
            }
            $tailLen = mb_strlen($tail, 'UTF-8');
            $captionLen = mb_strlen($caption, 'UTF-8');
            if ($captionLen <= $tailLen) {
                $caption = '';

                break;
            }
            $caption = rtrim(trim(mb_substr($caption, 0, $captionLen - $tailLen, 'UTF-8')), " \t\n\r\0\x0B.,;");
        }

        return trim($caption);
    }

    public static function appendToCaption(?NewsItem $news, NewsItemMedia $media, string $caption): string
    {
        $tailVariants = self::buildTailVariantStrings($news, $media);
        if ($tailVariants === []) {
            return self::truncateToMax(self::stripGenericTrailingLocationDateTails(trim($caption)));
        }

        $tail = $tailVariants[0];
        $caption = trim($caption);
        $body = self::stripTrailingTailVariants($caption, $tailVariants);
        $body = self::stripGenericTrailingLocationDateTails($body);

        if ($body === '') {
            $out = $tail;
        } else {
            $body = rtrim($body, " \t\n\r\0\x0B.");
            $out = $body.'. '.$tail;
        }

        return self::truncateToMax(ImportTextNormalizer::normalize($out));
    }

    /**
     * @param  list<string>  $tailVariants
     */
    private static function stripTrailingTailVariants(string $caption, array $tailVariants): string
    {
        $caption = trim($caption);
        $tails = array_values(array_filter($tailVariants, static fn ($t) => is_string($t) && $t !== ''));
        usort($tails, static fn (string $a, string $b): int => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));

        $progress = true;
        while ($progress) {
            $progress = false;
            foreach ($tails as $tail) {
                while (true) {
                    $stripped = self::tryStripTrailingTailSuffix($caption, $tail);
                    if ($stripped === null) {
                        break;
                    }
                    $caption = $stripped;
                    $progress = true;
                }
            }
        }

        return trim($caption);
    }

    private static function tryStripTrailingTailSuffix(string $caption, string $tail): ?string
    {
        $tailLower = mb_strtolower($tail, 'UTF-8');
        $tailLen = mb_strlen($tail, 'UTF-8');
        $captionLen = mb_strlen($caption, 'UTF-8');
        if ($captionLen < $tailLen) {
            return null;
        }

        $end = mb_substr($caption, -$tailLen, null, 'UTF-8');
        if (mb_strtolower($end, 'UTF-8') === $tailLower) {
            return rtrim(trim(mb_substr($caption, 0, $captionLen - $tailLen, 'UTF-8')), " \t\n\r\0\x0B.,;");
        }

        foreach (['.', ',', ';'] as $punct) {
            $suffixLen = $tailLen + mb_strlen($punct, 'UTF-8');
            if ($captionLen < $suffixLen) {
                continue;
            }
            $endWithPunct = mb_substr($caption, -$suffixLen, null, 'UTF-8');
            $endTail = mb_substr($endWithPunct, 0, $tailLen, 'UTF-8');
            $endPunct = mb_substr($endWithPunct, $tailLen, null, 'UTF-8');
            if ($endPunct === $punct && mb_strtolower($endTail, 'UTF-8') === $tailLower) {
                return rtrim(trim(mb_substr($caption, 0, $captionLen - $suffixLen, 'UTF-8')), " \t\n\r\0\x0B.,;");
            }
        }

        return null;
    }

    private static function genericLocationDateTailPattern(): string
    {
        return '/(?:[A-ZÄÖÜa-zäöüß\-\s\(\)]+,\s*)+\d{1,2}\.\d{1,2}\.\d{4}\.?$/u';
    }

    private static function truncateToMax(string $text): string
    {
        if (mb_strlen($text, 'UTF-8') <= self::CAPTION_MAX_BYTES) {
            return $text;
        }

        return mb_substr($text, 0, self::CAPTION_MAX_BYTES, 'UTF-8');
    }
}
