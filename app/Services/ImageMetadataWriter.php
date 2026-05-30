<?php

namespace App\Services;

use Carbon\Carbon;
use DateTimeInterface;

/**
 * Schreibt IPTC (APP13) und XMP (APP1, Adobe-Schema) in JPEG-Dateien — UTF-8.
 * XMP-Struktur orientiert an z. B. public_html/media/metadaten-aus-ekn-media.XMP (dc:*, photoshop:Credit, Datum).
 *
 * IPTC IIM: 1#090 Coded Character Set (UTF-8: Esc % G), 2#005 Object Name, 2#105 Headline,
 * 2#025 Keywords (mehrfach), 2#080 By-line, 2#120 Caption, 2#110 Credit, 2#116 Copyright,
 * 2#115 Source, 2#090 City, 2#095 Province/State, 2#101 Country, 2#100 Country ISO,
 * 2#055 Date Created, 2#060 Time Created.
 */
class ImageMetadataWriter
{
    /** Record 1 = Envelope */
    private const RECORD_ENVELOPE = 1;

    /** 1#090 Coded Character Set — Wert „Esc % G“ kennzeichnet UTF-8 (IPTC-IIM / ISO 2022) */
    private const TAG_CHARACTER_SET = 90;

    /** Record 2 = Application record */
    private const RECORD_APPLICATION = 2;

    /** 2#005 Object Name / Titel (Adobe „Titel“) */
    private const TAG_OBJECT_NAME = 5;

    /** 2#025 Schlagwort (wiederholbar) */
    private const TAG_KEYWORDS = 25;

    /** 2#105 Headline / Überschrift (Adobe „Überschrift“) */
    private const TAG_HEADLINE = 105;

    /** 2#055 Date Created (YYYYMMDD) */
    private const TAG_DATE_CREATED = 55;

    /** 2#060 Time Created (HHMMSS±…) */
    private const TAG_TIME_CREATED = 60;

    /** 2#080 By-line (Creator/Photographer) */
    private const TAG_BY_LINE = 80;

    /** 2#090 City */
    private const TAG_CITY = 90;

    /** 2#095 Province/State */
    private const TAG_STATE = 95;

    /** 2#100 Country-Primary Location Code */
    private const TAG_COUNTRY_CODE = 100;

    /** 2#101 Country-Primary Location Name */
    private const TAG_COUNTRY = 101;

    /** 2#115 Source */
    private const TAG_SOURCE = 115;

    /** 2#120 Caption/Description */
    private const TAG_CAPTION = 120;

    /** 2#110 Credit (Source) */
    private const TAG_CREDIT = 110;

    /** 2#116 Copyright */
    private const TAG_COPYRIGHT = 116;

    /**
     * Schreibt IPTC (APP13) und optional XMP (APP1) in die JPEG-Datei.
     * Vorhandene Adobe-XMP-APP1-Segmente werden ersetzt; übrige Marker bleiben erhalten.
     *
     * @param  string  $fullPath  Absoluter Pfad zur JPEG-Datei
     * @param  array<string, mixed>  $metadata
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

        $record = self::RECORD_APPLICATION;
        $iptcPayload = '';

        $title = self::iptcUtf8Truncate(self::trim($metadata['image_title'] ?? null), 64);
        if ($title !== '') {
            $iptcPayload .= self::iptcMakeTag($record, self::TAG_OBJECT_NAME, $title);
        }
        $headline = self::iptcUtf8Truncate(self::trim($metadata['headline'] ?? null), 256);
        if ($headline !== '') {
            $iptcPayload .= self::iptcMakeTag($record, self::TAG_HEADLINE, $headline);
        }
        foreach (self::normalizeKeywordList($metadata['keywords'] ?? null) as $kw) {
            $kw = self::iptcUtf8Truncate($kw, 64);
            if ($kw !== '') {
                $iptcPayload .= self::iptcMakeTag($record, self::TAG_KEYWORDS, $kw);
            }
        }
        $byline = self::iptcUtf8Truncate(self::trim($metadata['photographer'] ?? null), 128);
        if ($byline !== '') {
            $iptcPayload .= self::iptcMakeTag($record, self::TAG_BY_LINE, $byline);
        }
        $caption = self::iptcUtf8Truncate(self::trim($metadata['caption'] ?? null), 2000);
        if ($caption !== '') {
            $iptcPayload .= self::iptcMakeTag($record, self::TAG_CAPTION, $caption);
        }
        $credit = self::iptcUtf8Truncate(self::trim($metadata['credit'] ?? null), 128);
        if ($credit !== '') {
            $iptcPayload .= self::iptcMakeTag($record, self::TAG_CREDIT, $credit);
        }
        $copyright = self::iptcUtf8Truncate(self::trim($metadata['copyright'] ?? null), 256);
        if ($copyright !== '') {
            $iptcPayload .= self::iptcMakeTag($record, self::TAG_COPYRIGHT, $copyright);
        }
        $source = self::iptcUtf8Truncate(self::trim($metadata['source'] ?? null), 128);
        if ($source !== '') {
            $iptcPayload .= self::iptcMakeTag($record, self::TAG_SOURCE, $source);
        }
        $city = self::iptcUtf8Truncate(self::trim($metadata['city'] ?? null), 32);
        if ($city !== '') {
            $iptcPayload .= self::iptcMakeTag($record, self::TAG_CITY, $city);
        }
        $state = self::iptcUtf8Truncate(self::trim($metadata['state'] ?? null), 32);
        if ($state !== '') {
            $iptcPayload .= self::iptcMakeTag($record, self::TAG_STATE, $state);
        }
        $country = self::iptcUtf8Truncate(self::trim($metadata['country'] ?? null), 64);
        if ($country !== '') {
            $iptcPayload .= self::iptcMakeTag($record, self::TAG_COUNTRY, $country);
        }
        $countryCode = self::trim($metadata['country_code'] ?? null);
        if ($countryCode !== '') {
            $iptcPayload .= self::iptcMakeTag($record, self::TAG_COUNTRY_CODE, strtoupper(substr($countryCode, 0, 3)));
        }

        $capture = $metadata['capture_time'] ?? null;
        $dt = null;
        if ($capture instanceof DateTimeInterface) {
            $dt = Carbon::instance($capture);
        } elseif (is_string($capture) && trim($capture) !== '') {
            try {
                $dt = Carbon::parse($capture);
            } catch (\Throwable) {
                $dt = null;
            }
        }
        if ($dt !== null) {
            $iptcPayload .= self::iptcMakeTag($record, self::TAG_DATE_CREATED, $dt->format('Ymd'));
            $iptcPayload .= self::iptcMakeTag($record, self::TAG_TIME_CREATED, $dt->format('His'));
        }

        $iptcBlock = $iptcPayload === ''
            ? null
            : self::iptcMakeTag(self::RECORD_ENVELOPE, self::TAG_CHARACTER_SET, "\x1B\x25\x47").$iptcPayload;

        $xmpInner = self::buildXmpRdfFragment($metadata, $dt);

        if ($iptcBlock === null && $xmpInner === null) {
            return true;
        }

        if ($iptcBlock !== null) {
            if (! function_exists('iptcembed')) {
                return false;
            }
            $content = iptcembed($iptcBlock, $fullPath, 0);
            if ($content === false) {
                return false;
            }
        } else {
            $raw = file_get_contents($fullPath);
            if ($raw === false) {
                return false;
            }
            $content = $raw;
        }

        if ($xmpInner !== null) {
            $xmpPacket = self::wrapAdobeXmpPacket($xmpInner);
            $content = self::replaceOrInjectXmpApp1Segment($content, $xmpPacket);
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
     * Kürzt einen UTF-8-String auf höchstens $maxBytes Oktette an einer Zeichengrenze (IPTC-Längenlimits).
     */
    private static function iptcUtf8Truncate(string $s, int $maxBytes): string
    {
        if ($s === '' || $maxBytes <= 0) {
            return '';
        }
        if (strlen($s) <= $maxBytes) {
            return $s;
        }

        return mb_strcut($s, 0, $maxBytes, 'UTF-8');
    }

    /**
     * @return list<string>
     */
    private static function normalizeKeywordList(mixed $keywords): array
    {
        if ($keywords === null || $keywords === []) {
            return [];
        }
        if (is_string($keywords)) {
            $parts = preg_split('/\s*,\s*/u', $keywords) ?: [];

            return self::trimKeywordParts($parts);
        }
        if (! is_array($keywords)) {
            return [];
        }

        return self::trimKeywordParts($keywords);
    }

    /**
     * @param  array<int, mixed>  $parts
     * @return list<string>
     */
    private static function trimKeywordParts(array $parts): array
    {
        $out = [];
        foreach ($parts as $item) {
            if (! is_string($item)) {
                continue;
            }
            $t = trim($item);
            if ($t !== '') {
                $out[] = $t;
            }
        }

        return $out;
    }

    /**
     * RDF/XML-Inneres für XMP (ohne xpacket-Hülle). Null, wenn nichts Sinnvolles anzulegen ist.
     */
    private static function buildXmpRdfFragment(array $metadata, ?DateTimeInterface $dt): ?string
    {
        $esc = static fn (string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $title = self::trim($metadata['image_title'] ?? null);
        if ($title === '') {
            $title = self::trim($metadata['headline'] ?? null);
        }
        $caption = self::trim($metadata['caption'] ?? null);
        $credit = self::trim($metadata['credit'] ?? null);
        $copyright = self::trim($metadata['copyright'] ?? null);
        $photographer = self::trim($metadata['photographer'] ?? null);
        $creator = $photographer !== '' ? $photographer : $credit;
        $keywords = self::normalizeKeywordList($metadata['keywords'] ?? null);

        if ($title === '' && $caption === '' && $creator === '' && $copyright === '' && $credit === '' && $keywords === []) {
            return null;
        }

        $when = $dt !== null ? Carbon::instance($dt) : Carbon::now((string) config('app.timezone'));
        $iso = $when->timezone((string) config('app.timezone'))->format('Y-m-d\TH:i:sP');

        $attrLines = [
            'xmlns:photoshop="http://ns.adobe.com/photoshop/1.0/"',
            'xmlns:xmp="http://ns.adobe.com/xap/1.0/"',
            'xmlns:xmpRights="http://ns.adobe.com/xap/1.0/rights/"',
            'xmlns:dc="http://purl.org/dc/elements/1.1/"',
            'xmlns:xml="http://www.w3.org/XML/1998/namespace"',
        ];
        if ($credit !== '') {
            $attrLines[] = 'photoshop:Credit="'.$esc($credit).'"';
        }
        $attrLines[] = 'photoshop:DateCreated="'.$esc($iso).'"';
        $attrLines[] = 'xmp:CreateDate="'.$esc($iso).'"';
        if ($copyright !== '') {
            $attrLines[] = 'xmpRights:Marked="True"';
        }

        $lines = [
            '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">',
            ' <rdf:Description rdf:about=""',
            '  '.implode("\n  ", $attrLines).'>',
        ];
        if ($caption !== '') {
            $lines[] = '  <dc:description><rdf:Alt><rdf:li xml:lang="x-default">'.$esc($caption).'</rdf:li></rdf:Alt></dc:description>';
        }
        if ($creator !== '') {
            $lines[] = '  <dc:creator><rdf:Seq><rdf:li>'.$esc($creator).'</rdf:li></rdf:Seq></dc:creator>';
        }
        if ($title !== '') {
            $lines[] = '  <dc:title><rdf:Alt><rdf:li xml:lang="x-default">'.$esc($title).'</rdf:li></rdf:Alt></dc:title>';
        }
        if ($copyright !== '') {
            $lines[] = '  <dc:rights><rdf:Alt><rdf:li xml:lang="x-default">'.$esc($copyright).'</rdf:li></rdf:Alt></dc:rights>';
        }
        if ($keywords !== []) {
            $lis = '';
            foreach ($keywords as $k) {
                $lis .= '<rdf:li>'.$esc($k).'</rdf:li>';
            }
            $lines[] = '  <dc:subject><rdf:Bag>'.$lis.'</rdf:Bag></dc:subject>';
        }
        $lines[] = ' </rdf:Description>';
        $lines[] = '</rdf:RDF>';

        return implode("\n", $lines);
    }

    /**
     * Vollständiges UTF-8-XMP-Paket für APP1 (inkl. minimalem Padding für Adobe-Tools).
     */
    private static function wrapAdobeXmpPacket(string $rdfInner): string
    {
        $pad = str_repeat(' ', 80);

        return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'."\n"
            .'<x:xmpmeta xmlns:x="adobe:ns:meta/">'."\n"
            .$rdfInner."\n"
            .'</x:xmpmeta>'."\n"
            .'<?xpacket end="w"?>'.$pad."\n";
    }

    /**
     * Entfernt vorhandene Adobe-XMP-APP1-Segmente und setzt unseres direkt nach SOI.
     */
    private static function replaceOrInjectXmpApp1Segment(string $jpeg, string $xmpUtf8): string
    {
        $len = strlen($jpeg);
        if ($len < 4 || $jpeg[0] !== "\xFF" || $jpeg[1] !== "\xD8") {
            return $jpeg;
        }

        $xmpSegment = self::jpegApp1AdobeXmpSegment($xmpUtf8);
        $pos = 2;
        $accum = '';
        while ($pos + 3 < $len) {
            if ($jpeg[$pos] !== "\xFF") {
                return $jpeg;
            }
            $marker = ord($jpeg[$pos + 1]);
            if ($marker === 0xD8 || $marker === 0xD9) {
                return $jpeg;
            }
            if ($marker === 0xDA) {
                break;
            }
            if (($marker >= 0xD0 && $marker <= 0xD7) || $marker === 0x01) {
                $accum .= substr($jpeg, $pos, 2);
                $pos += 2;

                continue;
            }
            if ($pos + 4 > $len) {
                return $jpeg;
            }
            $segLen = (ord($jpeg[$pos + 2]) << 8) | ord($jpeg[$pos + 3]);
            if ($segLen < 2) {
                return $jpeg;
            }
            $total = 2 + $segLen;
            if ($pos + $total > $len) {
                return $jpeg;
            }
            $segment = substr($jpeg, $pos, $total);
            if ($marker === 0xE1 && self::jpegSegmentIsAdobeXmpPrimary($segment)) {
                $pos += $total;

                continue;
            }
            $accum .= $segment;
            $pos += $total;
        }

        return substr($jpeg, 0, 2).$xmpSegment.$accum.substr($jpeg, $pos);
    }

    private static function jpegSegmentIsAdobeXmpPrimary(string $segment): bool
    {
        if (strlen($segment) < 4 + 28) {
            return false;
        }
        if ($segment[0] !== "\xFF" || $segment[1] !== "\xE1") {
            return false;
        }

        return str_starts_with(substr($segment, 4), 'http://ns.adobe.com/xap/1.0/');
    }

    private static function jpegApp1AdobeXmpSegment(string $xmpUtf8): string
    {
        $payload = 'http://ns.adobe.com/xap/1.0/'."\0".$xmpUtf8;
        $lengthField = 2 + strlen($payload);

        return "\xFF\xE1".pack('n', $lengthField).$payload;
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
