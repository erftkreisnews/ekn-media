<?php

namespace App\Services;

/**
 * Liest Titel, Fotograf, Bildunterschrift, Schlagwörter und Beschreibung aus EXIF und IPTC.
 * IPTC: 2#005 Title, 2#080 By-line (Fotograf), 2#120 Caption, 2#025 Keywords, 2#105 Headline.
 * EXIF: IFD0.ImageDescription, WINXP (Unicode).
 */
class ImageMetadataReader
{
    public static function read(string $fullPath): array
    {
        $out = [
            'title' => null,
            'photographer' => null,
            'caption' => null,
            'keywords' => null,
            'description' => null,
        ];

        if (! is_file($fullPath) || ! is_readable($fullPath)) {
            return $out;
        }

        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        if (! in_array($ext, ['jpg', 'jpeg', 'tiff', 'tif'], true)) {
            return $out;
        }

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
                    $out['description'] = trim($iptc['2#105'][0]);
                }
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
            }
        }

        return $out;
    }

    private static function winxpString(string $s): string
    {
        if (str_starts_with($s, "\x1b")) {
            return trim(mb_convert_encoding(substr($s, 1), 'UTF-8', 'UCS-2LE'));
        }

        return trim($s);
    }
}
