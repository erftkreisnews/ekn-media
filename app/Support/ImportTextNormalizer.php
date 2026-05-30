<?php

namespace App\Support;

/**
 * Bereinigt aus PDFs/Word kopierten Text (Steuerzeichen, weiche Trennstriche, Zero-Width).
 */
final class ImportTextNormalizer
{
    public static function normalize(string $text): string
    {
        if ($text === '') {
            return '';
        }

        // C0-Steuerzeichen (außer Tab/Zeilenumbruch) → Leerzeichen (Backspace aus PDFs zerstört sonst Namen).
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $text) ?? $text;
        $text = preg_replace('/[\x{00AD}\x{FEFF}\x{200B}-\x{200D}]/u', '', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
