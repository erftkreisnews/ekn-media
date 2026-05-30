<?php

namespace App\Services\Ingest;

class IngestTrimValidator
{
    private const EPS = 0.05;

    /**
     * @return array{ok: true, trim_in: ?string, trim_out: ?string}|array{ok: false, error: string}
     */
    public function validate(?string $trimIn, ?string $trimOut, ?float $duration): array
    {
        $in = $this->parseToSeconds($trimIn);
        $out = $this->parseToSeconds($trimOut);

        if ($in === false || $out === false) {
            return [
                'ok' => false,
                'error' => 'Trim: bitte Sekunden (z. B. 12.5) oder Zeit MM:SS bzw. HH:MM:SS verwenden.',
            ];
        }

        if ($in === null && $out === null) {
            return ['ok' => true, 'trim_in' => null, 'trim_out' => null];
        }

        $start = $in ?? 0.0;
        $end = $out;

        if ($end === null) {
            if ($duration === null || $duration <= 0) {
                return [
                    'ok' => false,
                    'error' => 'Ohne Trim-Out wird die Clip-Dauer (Metadaten) benötigt.',
                ];
            }
            $end = (float) $duration;
        }

        if ($end <= $start) {
            return [
                'ok' => false,
                'error' => 'Trim-Out muss größer als Trim-In sein.',
            ];
        }

        if ($duration !== null && $duration > 0) {
            if ($start + self::EPS < 0 || $start > $duration + self::EPS) {
                return [
                    'ok' => false,
                    'error' => 'Trim-In liegt außerhalb der Clip-Dauer.',
                ];
            }
            if ($end > $duration + self::EPS) {
                return [
                    'ok' => false,
                    'error' => 'Trim-Out übersteigt die Clip-Dauer.',
                ];
            }
        }

        $trimInStored = ($in === null && abs($start) < 1e-6) ? null : $this->formatSeconds($start);
        $trimOutStored = ($out === null && $duration !== null && $duration > 0 && abs($end - (float) $duration) < self::EPS)
            ? null
            : $this->formatSeconds($end);

        return [
            'ok' => true,
            'trim_in' => $trimInStored,
            'trim_out' => $trimOutStored,
        ];
    }

    /**
     * @return float|null|false null = leer, false = ungültig
     */
    private function parseToSeconds(?string $raw): float|null|false
    {
        if ($raw === null) {
            return null;
        }

        $s = trim($raw);
        if ($s === '') {
            return null;
        }

        if (preg_match('/^(\d+):(\d{2}):(\d{2}(?:\.\d+)?)$/', $s, $m)) {
            return (float) $m[1] * 3600 + (float) $m[2] * 60 + (float) $m[3];
        }

        if (preg_match('/^(\d+):(\d{2}(?:\.\d+)?)$/', $s, $m)) {
            return (float) $m[1] * 60 + (float) $m[2];
        }

        if (preg_match('/^\d+(?:\.\d+)?$/', $s)) {
            return (float) $s;
        }

        return false;
    }

    private function formatSeconds(float $seconds): string
    {
        $rounded = round(max(0.0, $seconds), 4);
        $t = rtrim(rtrim(sprintf('%.4f', $rounded), '0'), '.');

        return $t === '' ? '0' : $t;
    }
}
