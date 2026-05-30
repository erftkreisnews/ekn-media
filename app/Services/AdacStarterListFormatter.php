<?php

namespace App\Services;

use App\Support\ImportTextNormalizer;

/**
 * Formatiert aus PDF/Word kopierte ADAC-Starterlisten-Zeilen für Team-Import (Box | Startnr. | …).
 */
final class AdacStarterListFormatter
{
    /**
     * @return array{0: string, 1: string} [preamble, body]
     */
    public function splitPreambleAndBody(string $raw): array
    {
        $lines = preg_split("/\r\n|\r|\n/u", $raw) ?: [];
        $preamble = [];
        $rest = [];
        $seenEntry = false;

        foreach ($lines as $line) {
            $t = $this->normalizeLine($line);
            if (! $seenEntry && $this->tryParseEntryHeader($t) !== null) {
                $seenEntry = true;
            }
            if ($seenEntry) {
                $rest[] = $line;
            } elseif ($t !== '' && ! $this->isSectionHeaderLine($t)) {
                $preamble[] = $t;
            }
        }

        return [implode("\n", $preamble), implode("\n", $rest)];
    }

    public function format(string $raw, ?string $preamble = null): string
    {
        $lines = preg_split("/\r\n|\r|\n/u", $raw) ?: [];
        $blocks = [];
        $current = null;

        foreach ($lines as $rawLine) {
            $line = $this->normalizeLine($rawLine);
            if ($line === '') {
                continue;
            }
            if ($this->isSectionHeaderLine($line)) {
                continue;
            }

            $parsed = $this->tryParseEntryHeader($line);
            if ($parsed !== null) {
                if ($current !== null) {
                    $blocks[] = $current;
                }
                $current = $parsed;

                continue;
            }

            if ($current !== null) {
                $current['details'][] = $line;
            }
        }
        if ($current !== null) {
            $blocks[] = $current;
        }

        $out = [];
        if ($preamble !== null && trim($preamble) !== '') {
            $out[] = trim($preamble);
            $out[] = '';
        }

        foreach ($blocks as $b) {
            $out[] = $this->renderBlock($b);
            $out[] = '';
        }

        return rtrim(implode("\n", $out))."\n";
    }

    private function isSectionHeaderLine(string $line): bool
    {
        return (bool) preg_match('/^#\s*Box\s+/ui', $line);
    }

    private function normalizeLine(string $line): string
    {
        return ImportTextNormalizer::normalize($line);
    }

    /**
     * @return array{box: string, startnr: string, team_class: string, details: string[], triple_note: string|null}|null
     */
    private function tryParseEntryHeader(string $line): ?array
    {
        if (preg_match('/^(\d{1,4})\s+(\d{1,3})\s+(\d{1,3})\s+(.+)$/u', $line, $m)) {
            return [
                'box' => $m[1],
                'startnr' => $m[3],
                'team_class' => trim($m[4]),
                'details' => [],
                'triple_note' => $m[2],
            ];
        }

        // Zwei Zahlen am Zeilenanfang: viele PDFs liefern Startnummer zuerst, dann Box
        // (z. B. „3 9 Mercedes-AMG Team …“ = Startnr. 3, Box 9).
        if (preg_match('/^(\d{1,4})\s+(\d{1,3})\s+(.+)$/u', $line, $m)) {
            $tail = trim($m[3]);
            if ($tail === '' || preg_match('/^\d+$/u', $tail)) {
                return null;
            }

            return [
                'box' => $m[2],
                'startnr' => $m[1],
                'team_class' => $tail,
                'details' => [],
                'triple_note' => null,
            ];
        }

        return null;
    }

    /**
     * @param  array{box: string, startnr: string, team_class: string, details: string[], triple_note: string|null}  $b
     */
    private function renderBlock(array $b): string
    {
        $head = 'Box '.$b['box'].' | Startnr. '.$b['startnr'].' | '.$b['team_class'];
        if (! empty($b['triple_note'])) {
            $head .= ' (Listenfeld '.$b['triple_note'].')';
        }
        $lines = [$head];
        if ($b['details'] !== []) {
            $joined = implode('; ', array_map(static fn (string $d) => trim(preg_replace('/\s+/u', ' ', $d) ?: ''), $b['details']));
            $lines[] = 'Fahrer / Fahrzeugdetails: '.$joined;
        }

        return implode("\n", $lines);
    }
}
