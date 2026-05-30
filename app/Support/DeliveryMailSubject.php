<?php

namespace App\Support;

use Illuminate\Support\Str;

class DeliveryMailSubject
{
    public static function forNewsDelivery(string $phase, string $title, ?string $author = null, ?string $updateScanLine = null): string
    {
        $normalizedPhase = self::normalizePhase($phase);
        $normalizedTitle = self::normalizeTitle($title);
        $normalizedAuthor = self::normalizeAuthor($author);
        $scan = trim((string) $updateScanLine);

        if ($normalizedPhase !== 'ERSTMELDUNG' && $scan !== '') {
            return $normalizedPhase.' | '.Str::limit($scan, 55, '…').' | '.$normalizedTitle.' | von '.$normalizedAuthor;
        }

        $content = $normalizedPhase === 'ERSTMELDUNG'
            ? 'Medienangebot: '.$normalizedTitle
            : $normalizedTitle;

        return $normalizedPhase.' | '.$content.' | von '.$normalizedAuthor;
    }

    public static function forQuickMedia(string $title, ?string $author = null): string
    {
        $normalizedTitle = self::normalizeTitle($title);
        $normalizedAuthor = self::normalizeAuthor($author);

        return 'SOFORTVERSAND | Medienangebot: '.$normalizedTitle.' | von '.$normalizedAuthor;
    }

    private static function normalizePhase(string $phase): string
    {
        $normalized = mb_strtoupper(trim($phase));

        return match ($normalized) {
            'ABSCHLUSS' => 'ABSCHLUSSMELDUNG',
            default => $normalized !== '' ? $normalized : 'ERSTMELDUNG',
        };
    }

    private static function normalizeTitle(string $title): string
    {
        $normalized = trim($title);

        if ($normalized === '') {
            return 'Mediathek';
        }

        return Str::limit($normalized, 90, '');
    }

    private static function normalizeAuthor(?string $author): string
    {
        $normalized = trim((string) $author);

        return $normalized !== '' ? $normalized : 'Alexander Franz';
    }
}
