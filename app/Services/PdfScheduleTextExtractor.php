<?php

namespace App\Services;

use App\Support\PlannedEventScheduleFileLocator;
use Smalot\PdfParser\Parser;
use Throwable;

class PdfScheduleTextExtractor
{
    /**
     * @return array{ok: bool, text: string, message: ?string}
     */
    public function extractFromLocalDisk(string $relativePath): array
    {
        $fullPath = PlannedEventScheduleFileLocator::absolutePath($relativePath);
        if ($fullPath === null || ! is_readable($fullPath)) {
            return ['ok' => false, 'text' => '', 'message' => 'Die PDF-Datei wurde nicht gefunden.'];
        }

        try {
            $parser = new Parser;
            $pdf = $parser->parseFile($fullPath);
            $text = $this->normalizeText($pdf->getText());
            if ($text === '') {
                return [
                    'ok' => false,
                    'text' => '',
                    'message' => 'Im PDF wurde kein lesbarer Text gefunden (häufig bei reinen Scan-Bildern). Bitte ein durchsuchbares PDF nutzen oder den Ablauf im Feld „Programmtext“ manuell einfügen.',
                ];
            }

            return ['ok' => true, 'text' => $text, 'message' => null];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'text' => '',
                'message' => 'PDF konnte nicht gelesen werden: '.$e->getMessage(),
            ];
        }
    }

    protected function normalizeText(string $raw): string
    {
        $t = preg_replace("/\r\n|\r/", "\n", $raw) ?? '';
        $t = preg_replace('/[ \t]+/u', ' ', $t) ?? '';
        $t = preg_replace("/\n{3,}/", "\n\n", $t) ?? '';

        return trim($t);
    }
}
