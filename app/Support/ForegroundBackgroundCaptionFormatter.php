<?php

namespace App\Support;

class ForegroundBackgroundCaptionFormatter
{
    /**
     * Fügt keine starre „Im Vordergrund / Im Hintergrund“-Schablone mehr ein.
     * Gibt die KI-Caption unverändert zurück, sobald sie Text enthält; nur wenn leer, ein sachlicher Kurzsatz.
     */
    public static function compose(string $existingCaption, string $foregroundSubject, string $backgroundSubject): string
    {
        $foreground = trim($foregroundSubject);
        $background = trim($backgroundSubject);
        if ($foreground === '' || $background === '') {
            return trim($existingCaption);
        }

        $existing = trim($existingCaption);
        if ($existing !== '') {
            return $existing;
        }

        $tail = self::extractLocationDateTail($existingCaption);
        $caption = $foreground.', dahinter '.$background.'.';
        if ($tail !== null) {
            $caption .= ' '.$tail;
        }

        return trim($caption);
    }

    private static function extractLocationDateTail(string $caption): ?string
    {
        $text = trim($caption);
        if ($text === '') {
            return null;
        }

        if (preg_match('/([A-ZÄÖÜa-zäöüß\-\s\(\)]+,\s*\d{2}\.\d{2}\.\d{4}\.?)$/u', $text, $matches) !== 1) {
            return null;
        }

        $tail = trim((string) $matches[1]);
        if ($tail === '') {
            return null;
        }
        if (! preg_match('/[.!?]$/u', $tail)) {
            $tail .= '.';
        }

        return $tail;
    }
}
