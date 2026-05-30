<?php

namespace App\Support;

class MediaKeywordNormalizer
{
    /**
     * @param  list<string>  $keywords
     * @return list<string>
     */
    public static function normalizeKeywordList(array $keywords, int $maxKeywords = 14): array
    {
        $year = date('Y');
        $result = [];
        $seen = [];

        if ($year !== '') {
            $result[] = $year;
            $seen[mb_strtolower($year, 'UTF-8')] = true;
        }

        foreach ($keywords as $keyword) {
            $normalized = self::normalizeKeyword($keyword);
            if ($normalized === null) {
                continue;
            }

            $dedupeKey = mb_strtolower($normalized, 'UTF-8');
            if (isset($seen[$dedupeKey])) {
                continue;
            }

            $seen[$dedupeKey] = true;
            $result[] = $normalized;

            if (count($result) >= $maxKeywords) {
                break;
            }
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    public static function normalizeCommaSeparated(?string $rawKeywords, int $maxKeywords = 14): array
    {
        if (! is_string($rawKeywords) || trim($rawKeywords) === '') {
            return [];
        }

        $parts = preg_split('/\s*,\s*/u', trim($rawKeywords), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return self::normalizeKeywordList(array_values($parts), $maxKeywords);
    }

    public static function normalizeCommaSeparatedString(?string $rawKeywords, int $maxKeywords = 14): ?string
    {
        $normalized = self::normalizeCommaSeparated($rawKeywords, $maxKeywords);

        return $normalized === [] ? null : implode(', ', $normalized);
    }

    private static function normalizeKeyword(string $keyword): ?string
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return null;
        }

        $keyword = preg_replace('/\s+/u', ' ', $keyword) ?? $keyword;

        if (preg_match('/^\#\s*(\d{1,4})$/u', $keyword, $matches) === 1) {
            return '#'.$matches[1];
        }

        if (preg_match('/^(?:startnummer|startnr\.?|nummer|nr\.?)\s*#?\s*(\d{1,4})$/iu', $keyword, $matches) === 1) {
            return '#'.$matches[1];
        }

        return $keyword;
    }
}
