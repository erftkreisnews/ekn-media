<?php

namespace App\Services\Presseportal;

/**
 * Bereinigt Meldungstexte aus der Presseportal-API für Medienpaket/Mail (weniger Leerfläche, kein Polizei-Web-Boilerplate).
 */
final class PresseportalBodyNormalizer
{
    /**
     * Zeilenumbrüche direkt nach „… (ots) -“ entfernen – ein Leerzeichen, dann Fließtext.
     * Gilt für gängige dpa/OTS-Zeilen (Ort variabel).
     */
    public static function collapseOtsDashWhitespace(string $body): string
    {
        if ($body === '') {
            return $body;
        }

        return (string) preg_replace(
            '/(\S+(?:\s+\S+)*\s*\(\s*ots\s*\)\s*-)(\s+)/iu',
            '$1 ',
            $body
        );
    }

    /**
     * Alles ab der ersten URL auf bonn.polizei.nrw streichen; optional die Herkunftszeile behalten.
     */
    public static function stripBonnPolizeiNrwTail(string $body, bool $keepOriginalContentLine = true): string
    {
        if ($body === '') {
            return $body;
        }

        $preserve = $keepOriginalContentLine ? self::extractPolizeiBonnOriginalContentLine($body) : null;

        if (preg_match('~https?://(?:www\.)?bonn\.polizei\.nrw~i', $body, $m, PREG_OFFSET_CAPTURE)) {
            $body = substr($body, 0, $m[0][1]);
            $body = rtrim($body);
        }

        if ($preserve !== null && ! str_contains($body, $preserve)) {
            $body .= "\n\n".$preserve;
        }

        return $body;
    }

    public static function normalize(string $body): string
    {
        $body = self::collapseOtsDashWhitespace($body);

        return self::stripBonnPolizeiNrwTail($body, true);
    }

    private static function extractPolizeiBonnOriginalContentLine(string $body): ?string
    {
        if (preg_match(
            '/Original-Content\s+von:\s*Polizei\s+Bonn,\s*übermittelt\s+durch\s+news\s+aktuell\.?/iu',
            $body,
            $m
        )) {
            return trim($m[0]);
        }

        return null;
    }
}
