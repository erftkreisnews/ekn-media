<?php

namespace App\Support;

/**
 * Entfernt Marshal-/LED-Anzeigen an der Windschutzscheibe aus der Startnummern-Erkennung.
 * Diese Ziffern sind für Streckenposten, nicht die offizielle Renn-Startnummer.
 */
final class RaceStartNumberSanitizer
{
    /**
     * @param  array<string, mixed>  $result  Vision-/KI-Metadaten (detected_start_number, caption, …)
     * @return array<string, mixed>
     */
    public function sanitizeVisionResult(array $result): array
    {
        $text = $this->aggregateDetectionText($result);

        $primary = $this->toPositiveInt($result['detected_start_number'] ?? null);
        if ($primary !== null && $this->shouldIgnoreAsMarshalDisplay($primary, $text)) {
            $result['detected_start_number'] = null;
            $result['number_readability'] = null;
        }

        $filtered = [];
        foreach ($this->toIntList($result['detected_car_numbers'] ?? []) as $num) {
            if (! $this->shouldIgnoreAsMarshalDisplay($num, $text)) {
                $filtered[] = $num;
            }
        }
        $result['detected_car_numbers'] = array_values($filtered);

        if (($result['detected_start_number'] ?? null) === null && $filtered !== []) {
            $result['detected_start_number'] = $filtered[0];
        }

        return $this->stripMarshalFromResult($result);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    public function stripMarshalFromResult(array $result): array
    {
        if (isset($result['caption']) && is_string($result['caption'])) {
            $result['caption'] = $this->stripMarshalMentionsFromCaption($result['caption']);
        }
        if (isset($result['image_title']) && is_string($result['image_title'])) {
            $result['image_title'] = $this->stripMarshalMentionsFromCaption($result['image_title']);
        }
        if (isset($result['description']) && is_string($result['description'])) {
            $result['description'] = $this->stripMarshalMentionsFromCaption($result['description']);
        }

        return $result;
    }

    public function stripMarshalMentionsFromCaption(string $caption): string
    {
        $text = ImportTextNormalizer::normalize($caption);
        if ($text === '') {
            return '';
        }

        $sentences = preg_split('/(?<=[.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $kept = [];
        foreach ($sentences as $sentence) {
            if ($this->sentenceIsMarshalDisplayOnly($sentence)) {
                continue;
            }
            $cleaned = $this->stripMarshalInlinePhrases($sentence);
            if ($this->isOrphanFragmentAfterMarshalStrip($cleaned, $text)) {
                continue;
            }
            $kept[] = $cleaned;
        }

        $out = trim(implode(' ', array_filter($kept, static fn (string $s): bool => trim($s) !== '')));
        if ($out === '') {
            $fallback = trim($this->stripMarshalInlinePhrases($text));
            if ($fallback !== '' && $this->hasMarshalWindshieldContext(mb_strtolower($text, 'UTF-8'))
                && $this->isOrphanFragmentAfterMarshalStrip($fallback, $text)) {
                return '';
            }

            return $fallback;
        }

        $out = preg_replace('/\s{2,}/u', ' ', $out) ?? $out;
        $out = preg_replace('/\s+([,.])/u', '$1', $out) ?? $out;
        $out = preg_replace('/\s+und\s+und\s+/iu', ' und ', $out) ?? $out;
        $out = preg_replace('/,\s*,/u', ',', $out) ?? $out;
        $out = preg_replace('/\s+,\s+/u', ', ', $out) ?? $out;
        $out = trim(preg_replace('/\s*\.\s*\./u', '.', $out) ?? $out);

        if ($out !== '' && $this->hasMarshalWindshieldContext(mb_strtolower($text, 'UTF-8'))) {
            if (preg_match('/^[a-zäöüß\-]+\.\s*$/iu', $out) === 1) {
                return '';
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function sanitizeBeforeEnrichment(array $result): array
    {
        return $this->sanitizeVisionResult($result);
    }

    private function sentenceIsMarshalDisplayOnly(string $sentence): bool
    {
        $s = mb_strtolower(trim($sentence), 'UTF-8');
        if ($s === '') {
            return false;
        }

        if (preg_match(
            '/\b(?:beleuchtet|led|marshal)[^.]{0,80}(?:nummer|ziffer|\d{2,3})[^.]{0,40}(?:scheibe|windschutzscheibe|frontscheibe)\b/iu',
            $s
        ) === 1) {
            return true;
        }

        if (! $this->hasMarshalWindshieldContext($s)) {
            return false;
        }

        return preg_match(
            '/\b(?:zeigt|mit|eine|einer|sichtbar|beleuchtet|leuchtet|anzeige|display|nummer|ziffer)\b/iu',
            $s
        ) === 1;
    }

    private function isOrphanFragmentAfterMarshalStrip(string $fragment, string $original): bool
    {
        $trimmed = trim($fragment, " \t\n\r\0\x0B.");
        if ($trimmed === '') {
            return true;
        }
        if (! $this->hasMarshalWindshieldContext(mb_strtolower($original, 'UTF-8'))) {
            return false;
        }

        if (preg_match('/\(#\s*\d{1,4}\)|\bstartnummer\b|\bstartnr\.?/iu', $fragment) === 1) {
            return false;
        }

        if (preg_match('/\d{1,2}\.\d{1,2}\.\d{2,4}/u', $fragment) === 1) {
            return false;
        }

        return mb_strlen($trimmed, 'UTF-8') < 24;
    }

    private function stripMarshalInlinePhrases(string $text): string
    {
        $patterns = [
            '/,?\s*mit\s+beleuchtet[^.]*?(?:start)?nummer[^.]*?(?:scheibe|frontscheibe|windschutzscheibe)[^.]*/iu',
            '/,?\s*mit\s+(?:einer\s+)?(?:marshal|led)[\s-]*(?:marshal[\s-]*)?(?:anzeige|display)[^.]*?(?:scheibe|frontscheibe|windschutzscheibe)[^.]*/iu',
            '/,?\s*(?:die\s+)?(?:led[\s-]*)?marshal[\s-]*anzeige[^.]*?(?:scheibe|frontscheibe|windschutzscheibe)[^.]*/iu',
            '/,?\s*marshal[\s-]*anzeige\s+an\s+der\s+(?:front|wind)?scheibe[^.]*?(?=\.|$)/iu',
            '/,?\s*(?:die\s+)?led[\s-]*marshal[\s-]*anzeige[^.]*?(?=\.|$)/iu',
            '/,?\s*beleuchtete\s+(?:start)?nummer\s*\(?0?\d{1,3}\)?[^.]*?(?:scheibe|frontscheibe|windschutzscheibe)[^.]*/iu',
            '/\s*(?:die\s+)?(?:led[\s-]*)?anzeige\s+an\s+der\s+(?:front|wind)?scheibe\s+zeigt[^.]*?(?=\.|$)/iu',
        ];

        $out = $text;
        foreach ($patterns as $pattern) {
            $out = preg_replace($pattern, '', $out) ?? $out;
        }

        return trim($out);
    }

    /**
     * @param  array<int, array{team:string, drivers:list<string>}>  $starterMapping
     * @param  array<string, mixed>  $result
     */
    /**
     * Karosserie-Startnummer nur dann „sicher“, wenn Vision sie klar am Fahrzeugkörper belegt.
     *
     * @param  array<string, mixed>  $result
     */
    public function isBodyStartNumberClearlyVisible(?int $number, array $result): bool
    {
        if ($number === null || $number <= 0) {
            return false;
        }

        $text = $this->aggregateTextFromResult($result);
        if ($this->shouldIgnoreAsMarshalDisplay($number, $text)) {
            return false;
        }

        if ($this->numberMentionedOnBody($number, $text)) {
            return true;
        }

        $readability = mb_strtolower(trim((string) ($result['number_readability'] ?? '')), 'UTF-8');

        return $readability === 'high';
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    public function clearDetectedStartNumbers(array $result): array
    {
        $result['detected_start_number'] = null;
        $result['number_readability'] = null;
        $result['detected_car_numbers'] = [];

        return $result;
    }

    public function shouldDiscardMisreadStartNumber(int $number, array $starterMapping, array $result): bool
    {
        if ($number <= 0 || isset($starterMapping[$number])) {
            return false;
        }

        $text = $this->aggregateTextFromResult($result);
        if ($this->shouldIgnoreAsMarshalDisplay($number, $text)) {
            return true;
        }

        return ! $this->numberMentionedOnBody($number, $text);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function aggregateTextFromResult(array $result): string
    {
        return $this->aggregateDetectionText($result);
    }

    public function shouldIgnoreAsMarshalDisplay(int $number, string $text): bool
    {
        if ($number <= 0) {
            return false;
        }

        $text = ImportTextNormalizer::normalize($text);
        if ($text === '') {
            return false;
        }

        if ($this->numberMentionedOnBody($number, $text)) {
            return false;
        }

        if ($this->numberUsesMarshalLedDisplayForm($number, $text)) {
            return true;
        }

        if (! $this->hasMarshalWindshieldContext($text)) {
            return false;
        }

        return $this->numberMentionedNearMarshalContext($number, $text);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function aggregateDetectionText(array $result): string
    {
        $chunks = [
            (string) ($result['caption'] ?? ''),
            (string) ($result['description'] ?? ''),
            (string) ($result['image_title'] ?? ''),
            implode("\n", $this->toStringList($result['keywords'] ?? [])),
            implode("\n", $this->toStringList($result['livery_cues'] ?? [])),
            implode("\n", $this->toStringList($result['needs_review'] ?? [])),
        ];

        return trim(implode("\n", array_filter($chunks, fn ($c) => trim($c) !== '')));
    }

    private function hasMarshalWindshieldContext(string $text): bool
    {
        return preg_match(
            '/\b('
            .'windschutzscheibe|frontscheibe|scheibenanzeige|scheiben-display|'
            .'led[\s-]*(anzeige|display|nummer|zahl)?|'
            .'marshal[\s-]*(anzeige|display|nummer|aufkleber|sticker)?|'
            .'streckenposten[\s-]*(anzeige|display|nummer)?|'
            .'beleuchtet(?:e|er|es|en)?\s+(?:start)?nummer|'
            .'nummer\s+an\s+der\s+(?:front)?scheibe|'
            .'anzeige\s+an\s+der\s+scheibe|'
            .'scheibe\s+mit\s+(?:der\s+)?nummer|'
            .'display\s+an\s+der\s+scheibe|'
            .'posten[\s-]*nummer'
            .')\b/iu',
            $text
        ) === 1;
    }

    private function numberMentionedNearMarshalContext(int $number, string $text): bool
    {
        $n = (string) $number;
        $marshal = '(?:windschutzscheibe|frontscheibe|scheibenanzeige|led[\s-]*anzeige|marshal|streckenposten|beleuchtet)';
        $patterns = [
            '/\b'.$n.'\b.{0,80}\b'.$marshal.'/iu',
            '/\b'.$marshal.'.{0,80}\b'.$n.'\b/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }

    private function numberUsesMarshalLedDisplayForm(int $number, string $text): bool
    {
        if ($number > 999) {
            return false;
        }

        $threeDigit = str_pad((string) $number, 3, '0', STR_PAD_LEFT);

        if (preg_match('/\b'.preg_quote($threeDigit, '/').'\b/u', $text) !== 1) {
            return false;
        }

        if ($this->hasMarshalWindshieldContext($text)) {
            return true;
        }

        return preg_match(
            '/\b(?:beleuchtet|led|scheibe|display|anzeige|marshal|streckenposten).{0,100}\b'.preg_quote($threeDigit, '/').'\b/iu',
            $text
        ) === 1
            || preg_match(
                '/\b'.preg_quote($threeDigit, '/').'\b.{0,100}\b(?:beleuchtet|led|scheibe|display|anzeige|marshal|streckenposten)/iu',
                $text
            ) === 1;
    }

    private function numberMentionedOnBody(int $number, string $text): bool
    {
        $n = (string) $number;

        if (preg_match(
            '/\b(?:startnummer|startnr\.?|rennnummer|startplatz)\s*#?\s*'.$n.'\b/iu',
            $text
        ) === 1) {
            return true;
        }

        if ($this->numberAttributedToBodyPart($number, $text)) {
            return true;
        }

        return preg_match(
            '/\b(?:groß(?:e|en)?\s+(?:start)?nummer|nummer\s+am\s+fahrzeug|startnummer\s+am)\b.{0,45}\b'.$n.'\b/iu',
            $text
        ) === 1;
    }

    private function numberAttributedToBodyPart(int $number, string $text): bool
    {
        $n = (string) $number;
        $body = '(?:tür|seite|seitlich|flanke|kotflügel|heck|motorhaube|dach|lackierung)';

        if (preg_match('/\b'.$n.'\b[^.]{0,80}\b(?:nicht\s+sichtbar|nicht\s+erkennbar|ohne\s+sichtbare)\b/iu', $text) === 1) {
            return false;
        }

        return preg_match('/\b'.$n.'\s*(?:am|auf)\s+(?:dem\s+)?'.$body.'\b/iu', $text) === 1
            || preg_match('/\b'.$body.'\b[^.]{0,35}\b'.$n.'\b/iu', $text) === 1;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function appendNeedsReview(array $result, string $message): array
    {
        $needsReview = array_values(array_filter(
            array_map(static fn ($item) => trim((string) $item), (array) ($result['needs_review'] ?? [])),
            static fn ($item) => $item !== ''
        ));
        if (! in_array($message, $needsReview, true)) {
            $needsReview[] = $message;
        }
        $result['needs_review'] = $needsReview;

        return $result;
    }

    private function toPositiveInt(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }
        if (is_string($value) && preg_match('/^\s*(\d{1,4})\s*$/u', $value, $match) === 1) {
            $n = (int) $match[1];

            return $n > 0 ? $n : null;
        }

        return null;
    }

    /**
     * @return list<int>
     */
    private function toIntList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            $n = $this->toPositiveInt($item);
            if ($n !== null && ! in_array($n, $out, true)) {
                $out[] = $n;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function toStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            if (! is_string($item)) {
                continue;
            }
            $clean = trim($item);
            if ($clean !== '' && ! in_array($clean, $out, true)) {
                $out[] = $clean;
            }
        }

        return $out;
    }
}
