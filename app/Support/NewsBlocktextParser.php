<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Zerlegt Vorlagen-Blocktext (Zeilen „Titel:“, „Dachzeile:“ usw.) wie im Admin-JS-Parser.
 */
class NewsBlocktextParser
{
    /**
     * Optional Markdown **: oft „**Titel:** Wert“ (= Titel + : + ** + Wert) oder „**Titel** : Wert“.
     * Nach dem Doppelpunkt optional schließende **, damit nicht fälschlich der erste : in „:**“ das Ende markiert.
     */
    private const LABEL_LINE = '/^\s*\*{0,2}\s*(Titel|Dachzeile|Unterzeile|Web-Text|Webtext|Web\s+Text|Schlagwörter|Schlagwoerter|Metadaten|Bundesland|Stadt|Straße|Strasse|Status|Veröffentlichungsdatum|Veroeffentlichungsdatum|Embargo|Byline|Land)\s*\*{0,2}\s*:\s*\*{0,2}\s*(.*)$/u';

    /** @var array<string, string|null> */
    private const FIELD_BY_LABEL = [
        'titel' => 'title',
        'dachzeile' => 'teaser',
        'unterzeile' => 'subheadline',
        'webtext' => 'body',
        'schlagwoerter' => 'keywords',
        'metadaten' => null,
        'bundesland' => 'federal_state',
        'stadt' => 'city',
        'strasse' => 'street',
        'status' => 'status',
        'veroeffentlichungsdatum' => 'published_at',
        'embargo' => 'embargo_at',
        'byline' => 'author_credit',
        'land' => 'country',
    ];

    /**
     * @return array<string, string>
     */
    public static function parse(string $raw): array
    {
        $text = self::normalizeBlockText($raw);
        $lines = explode("\n", $text);
        $acc = [];
        $currentField = null;

        foreach ($lines as $line) {
            if (preg_match(self::LABEL_LINE, $line, $m)) {
                $norm = self::normalizeLabelKey($m[1]);
                $rest = $m[2] ?? '';
                if ($norm === 'metadaten') {
                    $currentField = null;

                    continue;
                }
                $field = self::FIELD_BY_LABEL[$norm] ?? null;
                if ($field === null) {
                    continue;
                }
                $currentField = $field;
                if ($rest !== '') {
                    $acc[$field] = isset($acc[$field]) ? $acc[$field]."\n".$rest : $rest;
                }

                continue;
            }
            if ($currentField !== null) {
                $acc[$currentField] = isset($acc[$currentField]) ? $acc[$currentField]."\n".$line : $line;
            }
        }

        $out = [];
        foreach ($acc as $k => $v) {
            $t = self::trimValue($v);
            if ($t !== '') {
                $out[$k] = $t;
            }
        }

        return $out;
    }

    /**
     * Formularwerte für Admin-Maske (inkl. Status-Select, datetime-local).
     *
     * @param  array<string, string>  $parsed
     * @return array<string, string>
     */
    public static function toFormFields(array $parsed): array
    {
        $out = [];

        if (! empty($parsed['title'])) {
            $out['title'] = Str::limit($parsed['title'], 265, '');
        }
        if (! empty($parsed['teaser'])) {
            $out['teaser'] = Str::limit($parsed['teaser'], 255, '');
        }
        if (! empty($parsed['subheadline'])) {
            $out['subheadline'] = Str::limit($parsed['subheadline'], 512, '');
        }
        if (! empty($parsed['keywords'])) {
            $out['keywords'] = Str::limit(preg_replace('/\s+/', ' ', str_replace("\n", ' ', $parsed['keywords'])) ?? '', 512, '');
        }
        if (! empty($parsed['body'])) {
            $out['body'] = $parsed['body'];
        }
        if (! empty($parsed['country'])) {
            $out['country'] = Str::limit($parsed['country'], 255, '');
        }
        if (! empty($parsed['federal_state'])) {
            $out['federal_state'] = Str::limit($parsed['federal_state'], 255, '');
        }
        if (! empty($parsed['city'])) {
            $out['city'] = Str::limit($parsed['city'], 255, '');
        }
        if (! empty($parsed['street'])) {
            $out['street'] = Str::limit($parsed['street'], 255, '');
        }

        if (! empty($parsed['status'])) {
            $sel = self::mapStatusToSelect($parsed['status']);
            if ($sel !== null) {
                $out['status'] = $sel;
            }
        }

        if (! empty($parsed['published_at'])) {
            $pub = self::parsePublishedAtForInput($parsed['published_at']);
            if ($pub !== null) {
                $out['published_at'] = $pub;
            }
        }

        if (array_key_exists('embargo_at', $parsed)) {
            $out['embargo_at'] = self::parseEmbargoForInput($parsed['embargo_at']);
        }

        if (! empty($parsed['author_credit'])) {
            $out['author_credit'] = Str::limit($parsed['author_credit'], 255, '');
        }

        return $out;
    }

    public static function looksStructured(string $raw): bool
    {
        $raw = self::normalizeBlockText($raw);
        if (mb_strlen($raw) < 8) {
            return false;
        }
        if (! preg_match('/^\s*\*{0,2}\s*Titel\s*\*{0,2}\s*:\s*\*{0,2}\s*/mi', $raw)) {
            return false;
        }
        if (preg_match('/^\s*\*{0,2}\s*(Web-Text|Webtext|Web\s+Text)\s*\*{0,2}\s*:\s*\*{0,2}\s*/mi', $raw)) {
            return true;
        }
        $markers = [
            '/^\s*\*{0,2}\s*Dachzeile\s*\*{0,2}\s*:\s*\*{0,2}\s*/mi',
            '/^\s*\*{0,2}\s*Unterzeile\s*\*{0,2}\s*:\s*\*{0,2}\s*/mi',
            '/^\s*\*{0,2}\s*Schlagwörter\s*\*{0,2}\s*:\s*\*{0,2}\s*/mi',
            '/^\s*\*{0,2}\s*Schlagwoerter\s*\*{0,2}\s*:\s*\*{0,2}\s*/mi',
            '/^\s*\*{0,2}\s*Metadaten\s*\*{0,2}\s*:\s*\*{0,2}\s*/mi',
            '/^\s*\*{0,2}\s*Bundesland\s*\*{0,2}\s*:\s*\*{0,2}\s*/mi',
            '/^\s*\*{0,2}\s*Stadt\s*\*{0,2}\s*:\s*\*{0,2}\s*/mi',
            '/^\s*\*{0,2}\s*Straße\s*\*{0,2}\s*:\s*\*{0,2}\s*/mi',
            '/^\s*\*{0,2}\s*Strasse\s*\*{0,2}\s*:\s*\*{0,2}\s*/mi',
            '/^\s*\*{0,2}\s*Status\s*\*{0,2}\s*:\s*\*{0,2}\s*/mi',
        ];
        foreach ($markers as $re) {
            if (preg_match($re, $raw)) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeBlockText(string $raw): string
    {
        $s = preg_replace('/^\x{FEFF}/u', '', $raw) ?? $raw;
        $s = preg_replace('/[\x{200B}-\x{200D}\x{2060}]/u', '', $s) ?? $s;
        $s = str_replace('：', ':', $s);
        $s = str_replace(["\r\n", "\r"], "\n", $s);

        return $s;
    }

    private static function normalizeLabelKey(string $label): string
    {
        $s = mb_strtolower(trim($label));
        $s = preg_replace('/\s+/u', '', $s) ?? $s;
        $s = str_replace(['ä', 'ö', 'ü', 'ß', '-'], ['ae', 'oe', 'ue', 'ss', ''], $s);

        return $s;
    }

    private static function trimValue(string $s): string
    {
        $s = preg_replace('/^\s+|\s+$/u', '', $s) ?? '';
        $s = preg_replace("/\n{3,}/", "\n\n", $s) ?? '';

        return $s;
    }

    private static function mapStatusToSelect(string $val): ?string
    {
        $v = Str::ascii(mb_strtolower(trim($val)));
        if ($v === '') {
            return null;
        }
        if (str_contains($v, 'entwurf')) {
            return 'draft';
        }
        if (str_contains($v, 'review')) {
            return 'review';
        }
        if (str_contains($v, 'archiv')) {
            return 'archived';
        }
        if (str_contains($v, 'ready') || str_contains($v, 'veroffentlich') || str_contains($v, 'published') || str_contains($v, 'freigabe')) {
            return 'published';
        }

        return null;
    }

    private static function parsePublishedAtForInput(string $val): ?string
    {
        $t = trim($val);
        $low = mb_strtolower($t);
        if ($t === '' || in_array($low, ['sofort', 'jetzt', 'n/v', '—', '-'], true)) {
            return now()->format('Y-m-d\TH:i');
        }
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})(?:\s+(\d{1,2}):(\d{2}))?$/u', $t, $m)) {
            $day = (int) $m[1];
            $month = (int) $m[2];
            $year = (int) $m[3];
            $hour = isset($m[4]) ? (int) $m[4] : (int) now()->format('G');
            $min = isset($m[5]) ? (int) $m[5] : (int) now()->format('i');

            try {
                return Carbon::create($year, $month, $day, $hour, $min, 0)->format('Y-m-d\TH:i');
            } catch (\Throwable) {
                return null;
            }
        }
        try {
            return Carbon::parse($t)->format('Y-m-d\TH:i');
        } catch (\Throwable) {
            return null;
        }
    }

    private static function parseEmbargoForInput(string $val): string
    {
        $t = trim($val);
        $low = mb_strtolower($t);
        if ($t === '' || in_array($low, ['-', '—', 'keine', 'nein'], true)) {
            return '';
        }
        $parsed = self::parsePublishedAtForInput($t);

        return $parsed ?? '';
    }
}
