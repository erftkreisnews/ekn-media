<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Liest Titel, Fotograf, Bildunterschrift, Schlagwörter und Beschreibung aus EXIF und IPTC.
 * IPTC: 2#005 Title, 2#080 By-line, 2#120 Caption, 2#025 Keywords, 2#105 Headline,
 * 2#090 City, 2#095 Province/State, 2#101 Country, 2#100 Country ISO, 2#110 Credit, 2#116 Copyright,
 * 2#115 Source, 2#055/2#060 Date/Time Created.
 * EXIF: IFD0.ImageDescription, WINXP, EXIF.DateTimeOriginal (Aufnahmezeit: EXIF vor IPTC 055/060).
 */
class ImageMetadataReader
{
    public static function read(string $fullPath): array
    {
        $out = [
            'title' => null,
            'headline' => null,
            'photographer' => null,
            'caption' => null,
            'keywords' => null,
            'description' => null,
            'city' => null,
            'state' => null,
            'country' => null,
            'country_code' => null,
            'credit' => null,
            'copyright' => null,
            'source' => null,
            'datetime_original' => null,
        ];

        if (! is_file($fullPath) || ! is_readable($fullPath)) {
            return $out;
        }

        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        if (! in_array($ext, ['jpg', 'jpeg', 'tiff', 'tif'], true)) {
            return $out;
        }

        $iptcDateTimeFallback = null;

        // IPTC (in JPEG/TIFF APP13)
        $info = [];
        @getimagesize($fullPath, $info);
        if (! empty($info['APP13'])) {
            $iptc = @iptcparse($info['APP13']);
            if ($iptc) {
                if (! empty($iptc['2#005'][0])) {
                    $out['title'] = trim($iptc['2#005'][0]);
                }
                if (! empty($iptc['2#080'][0])) {
                    $out['photographer'] = trim($iptc['2#080'][0]);
                }
                if (! empty($iptc['2#120'][0])) {
                    $out['caption'] = trim($iptc['2#120'][0]);
                }
                if (! empty($iptc['2#025']) && is_array($iptc['2#025'])) {
                    $out['keywords'] = implode(', ', array_map('trim', $iptc['2#025']));
                }
                if (! empty($iptc['2#105'][0])) {
                    $head = trim($iptc['2#105'][0]);
                    $out['headline'] = $head;
                    $out['description'] = $head;
                }
                if (! empty($iptc['2#090'][0])) {
                    $out['city'] = trim($iptc['2#090'][0]);
                }
                if (! empty($iptc['2#095'][0])) {
                    $out['state'] = trim($iptc['2#095'][0]);
                }
                if (! empty($iptc['2#101'][0])) {
                    $out['country'] = trim($iptc['2#101'][0]);
                }
                if (! empty($iptc['2#100'][0])) {
                    $out['country_code'] = strtoupper(trim($iptc['2#100'][0]));
                }
                if (! empty($iptc['2#110'][0])) {
                    $out['credit'] = trim($iptc['2#110'][0]);
                }
                if (! empty($iptc['2#116'][0])) {
                    $out['copyright'] = trim($iptc['2#116'][0]);
                }
                if (! empty($iptc['2#115'][0])) {
                    $out['source'] = trim($iptc['2#115'][0]);
                }
                // 2#055/2#060 nur als Fallback für datetime_original – kann durch Redaktion/iptcembed überschrieben sein.
                $iptcDate = ! empty($iptc['2#055'][0]) ? trim($iptc['2#055'][0]) : '';
                $iptcTime = ! empty($iptc['2#060'][0]) ? trim($iptc['2#060'][0]) : '';
                $iptcDateTimeFallback = self::iptcDateTimeToDatetimeLocal($iptcDate, $iptcTime);
            }
        }

        // EXIF (falls IPTC leer)
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($fullPath, 0, true);
            if ($exif) {
                if (empty($out['title']) && ! empty($exif['IFD0']['ImageDescription'])) {
                    $out['title'] = trim((string) $exif['IFD0']['ImageDescription']);
                }
                if (empty($out['caption']) && ! empty($exif['IFD0']['ImageDescription'])) {
                    $out['caption'] = trim((string) $exif['IFD0']['ImageDescription']);
                }
                if (empty($out['description']) && ! empty($exif['IFD0']['ImageDescription'])) {
                    $out['description'] = trim((string) $exif['IFD0']['ImageDescription']);
                }
                if (! empty($exif['WINXP']) && is_array($exif['WINXP'])) {
                    foreach (['Title', 'Author', 'Comments'] as $key) {
                        if (empty($out['title']) && $key === 'Title' && ! empty($exif['WINXP'][$key])) {
                            $out['title'] = self::winxpString($exif['WINXP'][$key]);
                        }
                        if (empty($out['photographer']) && $key === 'Author' && ! empty($exif['WINXP'][$key])) {
                            $out['photographer'] = self::winxpString($exif['WINXP'][$key]);
                        }
                        if (empty($out['caption']) && $key === 'Comments' && ! empty($exif['WINXP'][$key])) {
                            $out['caption'] = self::winxpString($exif['WINXP'][$key]);
                        }
                    }
                }
                // Aufnahmezeit: EXIF DateTimeOriginal (Kamera) hat Vorrang vor IPTC 055/060 (oft nachträglich geschrieben).
                if (! empty($exif['EXIF']['DateTimeOriginal'])) {
                    $parsed = self::exifDateTimeOriginalToDatetimeLocal((string) $exif['EXIF']['DateTimeOriginal']);
                    if ($parsed !== null) {
                        $out['datetime_original'] = $parsed;
                    }
                }
            }
        }

        if ($out['datetime_original'] === null && $iptcDateTimeFallback !== null) {
            $out['datetime_original'] = $iptcDateTimeFallback;
        }

        return $out;
    }

    /**
     * Wandelt datetime_original aus {@see read()} (Format Y-m-d\TH:i) in Carbon um.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function captureTimeCarbonFromMetadata(array $metadata): ?Carbon
    {
        $raw = isset($metadata['datetime_original']) ? trim((string) $metadata['datetime_original']) : '';
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function iptcDateTimeToDatetimeLocal(string $date, string $time): ?string
    {
        $date = preg_replace('/\D/', '', $date) ?? '';
        $timeRaw = preg_replace('/[^0-9+-]/', '', $time) ?? '';
        if (strlen($date) < 8) {
            return null;
        }
        $y = substr($date, 0, 4);
        $m = substr($date, 4, 2);
        $d = substr($date, 6, 2);
        $h = strlen($timeRaw) >= 2 ? substr($timeRaw, 0, 2) : '00';
        $i = strlen($timeRaw) >= 4 ? substr($timeRaw, 2, 2) : '00';
        try {
            return Carbon::createFromFormat('Y-m-d H:i', "$y-$m-$d $h:$i")->format('Y-m-d\TH:i');
        } catch (\Throwable) {
            return null;
        }
    }

    private static function exifDateTimeOriginalToDatetimeLocal(string $raw): ?string
    {
        try {
            return Carbon::createFromFormat('Y:m:d H:i:s', trim($raw))->format('Y-m-d\TH:i');
        } catch (\Throwable) {
            return null;
        }
    }

    private static function winxpString(string $s): string
    {
        if (str_starts_with($s, "\x1b")) {
            return trim(mb_convert_encoding(substr($s, 1), 'UTF-8', 'UCS-2LE'));
        }

        return trim($s);
    }
}
