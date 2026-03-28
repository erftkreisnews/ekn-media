<?php

namespace App\Services;

/**
 * Schreibt IPTC-Metadaten (Urheberschaft, Caption, Copyright) in JPEG-Dateien.
 * Wichtig: Die Metadaten bleiben im Original erhalten und sind beim Download vorhanden.
 *
 * IPTC IIM: 2#005 Object Name (Titel), 2#080 By-line (Fotograf), 2#120 Caption,
 * 2#110 Credit (Quelle), 2#116 Copyright.
 */
class ImageMetadataWriter
{
    /** Record 2 = IPTC envelope, Data 5 = Object Name / Title */
    private const TAG_OBJECT_NAME = 5;

    /** 2#080 By-line (Creator/Photographer) */
    private const TAG_BY_LINE = 80;

    /** 2#120 Caption/Description */
    private const TAG_CAPTION = 120;

    /** 2#110 Credit (Source) */
    private const TAG_CREDIT = 110;

    /** 2#116 Copyright */
    private const TAG_COPYRIGHT = 116;

    /**
     * Schreibt die übergebenen Metadaten in die JPEG-Datei (IPTC APP13).
     * Bestehende EXIF/APP1-Segmente werden nicht verändert (iptcembed arbeitet nur mit APP13).
     *
     * @param  string  $fullPath  Absoluter Pfad zur JPEG-Datei
     * @param  array{image_title?: string|null, photographer?: string|null, caption?: string|null, credit?: string|null, copyright?: string|null}  $metadata
     */
    public static function write(string $fullPath, array $metadata): bool
    {
        if (! is_file($fullPath) || ! is_readable($fullPath) || ! is_writable($fullPath)) {
            return false;
        }
        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        if (! in_array($ext, ['jpg', 'jpeg'], true)) {
            return false;
        }

        $data = '';
        $record = 2;

        $title = self::trim($metadata['image_title'] ?? null);
        if ($title !== '') {
            $data .= self::iptcMakeTag($record, self::TAG_OBJECT_NAME, $title);
        }
        $byline = self::trim($metadata['photographer'] ?? null);
        if ($byline !== '') {
            $data .= self::iptcMakeTag($record, self::TAG_BY_LINE, $byline);
        }
        $caption = self::trim($metadata['caption'] ?? null);
        if ($caption !== '') {
            $data .= self::iptcMakeTag($record, self::TAG_CAPTION, $caption);
        }
        $credit = self::trim($metadata['credit'] ?? null);
        if ($credit !== '') {
            $data .= self::iptcMakeTag($record, self::TAG_CREDIT, $credit);
        }
        $copyright = self::trim($metadata['copyright'] ?? null);
        if ($copyright !== '') {
            $data .= self::iptcMakeTag($record, self::TAG_COPYRIGHT, $copyright);
        }

        if ($data === '') {
            return true;
        }

        if (! function_exists('iptcembed')) {
            return false;
        }

        $content = iptcembed($data, $fullPath, 0);
        if ($content === false) {
            return false;
        }

        $fp = fopen($fullPath, 'wb');
        if (! $fp) {
            return false;
        }
        $written = fwrite($fp, $content);
        fclose($fp);

        return $written !== false && $written === strlen($content);
    }

    private static function trim(?string $s): string
    {
        return trim((string) $s);
    }

    /**
     * Erzeugt ein IPTC-Tag im IIM-Format (1C + record + data + Länge + Wert).
     */
    private static function iptcMakeTag(int $record, int $dataTag, string $value): string
    {
        $length = strlen($value);
        $retval = chr(0x1C).chr($record).chr($dataTag);
        if ($length < 0x8000) {
            $retval .= chr($length >> 8).chr($length & 0xFF);
        } else {
            $retval .= chr(0x80).chr(0x04)
                .chr(($length >> 24) & 0xFF).chr(($length >> 16) & 0xFF)
                .chr(($length >> 8) & 0xFF).chr($length & 0xFF);
        }

        return $retval.$value;
    }
}
