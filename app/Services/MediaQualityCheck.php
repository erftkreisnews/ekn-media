<?php

namespace App\Services;

use App\Models\NewsItemMedia;

class MediaQualityCheck
{
    /** Mindestlänge lange Kante für Presse (Ziel). */
    public const LONG_EDGE_PRESSE = 4500;

    /**
     * Führt die Qualitätsprüfung für ein Medium durch und gibt Status + Hinweise zurück.
     * Bild: lange Kante, Fotograf, Caption. Audio: ÖRR-Mindestbitrate (Sendefähigkeit).
     * Video: null (keine Prüfung).
     *
     * @return array{status: string, notes: string}|null
     */
    public function check(NewsItemMedia $media): ?array
    {
        if ($media->isAudio()) {
            return $this->checkAudio($media);
        }
        if (! $media->isImage()) {
            return null;
        }

        if ($media->isVideoDerivedStillImage()) {
            return null;
        }

        $notes = [];
        $longEdge = $this->getLongEdge($media);
        $status = 'ok';

        if ($longEdge === null) {
            $notes[] = 'Bildmaße konnten nicht gelesen werden.';
            $status = 'fail';
        } else {
            $minLongEdge = (int) config('media.image_long_edge_min', 3500);
            $maxLongEdge = (int) config('media.image_master_long_edge_max', 6048);
            $maxShortEdge = (int) config('media.image_master_short_edge_max', 4024);
            $shortEdge = $this->getShortEdge($media);

            if ($longEdge < $minLongEdge) {
                $notes[] = "Lange Kante {$longEdge} px – unter Mindestwert {$minLongEdge} px.";
                $status = 'fail';
            } elseif ($longEdge < self::LONG_EDGE_PRESSE) {
                $notes[] = "Lange Kante {$longEdge} px – unter Presse-Ziel ".self::LONG_EDGE_PRESSE.' px (Dokumentationsbild).';
                $status = 'warning';
            }

            if ($longEdge > $maxLongEdge) {
                $notes[] = "Lange Kante {$longEdge} px – über Master-Maximum {$maxLongEdge} px.";
                $status = 'fail';
            }

            if ($shortEdge !== null && $shortEdge > $maxShortEdge) {
                $notes[] = "Kurze Kante {$shortEdge} px – über Master-Maximum {$maxShortEdge} px.";
                $status = 'fail';
            }
        }

        if (trim((string) ($media->photographer ?? '')) === '') {
            $notes[] = 'Fotograf (Creator) fehlt in den Metadaten.';
            if ($status === 'ok') {
                $status = 'warning';
            }
        }
        $hasCaption = trim((string) ($media->caption ?? '')) !== '' || trim((string) ($media->description ?? '')) !== '';
        if (! $hasCaption) {
            $notes[] = 'Bildunterschrift oder Beschreibung fehlt.';
            if ($status === 'ok') {
                $status = 'warning';
            }
        }

        return [
            'status' => $status,
            'notes' => implode(' ', $notes),
        ];
    }

    /**
     * ÖRR-taugliche Audio-Qualität: Mindest-Bitrate (z. B. 128 kbit/s).
     */
    protected function checkAudio(NewsItemMedia $media): array
    {
        $notes = [];
        $status = 'ok';
        $minKbps = (int) config('media.audio_min_bitrate_kbps', 128);

        if ($media->duration_s === null && $media->bitrate_bps === null) {
            $notes[] = 'Metadaten werden noch ausgewertet (Analyse läuft oder fehlgeschlagen).';

            return [
                'status' => 'warning',
                'notes' => implode(' ', $notes),
            ];
        }

        $bitrateBps = $media->bitrate_bps !== null ? (int) $media->bitrate_bps : null;
        if ($bitrateBps !== null) {
            $kbps = round($bitrateBps / 1000);
            if ($kbps < $minKbps) {
                $notes[] = "Bitrate {$kbps} kbit/s – unter ÖRR-Mindestwert {$minKbps} kbit/s.";
                $status = 'warning';
            }
        } else {
            $notes[] = 'Bitrate konnte nicht ausgelesen werden.';
            $status = 'warning';
        }

        return [
            'status' => $status,
            'notes' => implode(' ', $notes),
        ];
    }

    /**
     * Prüfung durchführen und am Medium speichern.
     */
    public function runAndSave(NewsItemMedia $media): void
    {
        $result = $this->check($media);
        if ($result === null) {
            $media->update(['quality_status' => null, 'quality_notes' => null]);

            return;
        }
        $media->update([
            'quality_status' => $result['status'],
            'quality_notes' => $result['notes'] ?: null,
        ]);
    }

    protected function getLongEdge(NewsItemMedia $media): ?int
    {
        try {
            $resolved = app(MediaStorage::class)->resolveReadableLocalPath($media->path);
            $path = $resolved['path'] ?? null;
            if (! is_readable($path)) {
                app(MediaStorage::class)->cleanupResolvedPath($resolved);

                return null;
            }
            $info = @getimagesize($path);
            if ($info === false || ! isset($info[0], $info[1])) {
                app(MediaStorage::class)->cleanupResolvedPath($resolved);

                return null;
            }
            app(MediaStorage::class)->cleanupResolvedPath($resolved);

            return max((int) $info[0], (int) $info[1]);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function getShortEdge(NewsItemMedia $media): ?int
    {
        try {
            $resolved = app(MediaStorage::class)->resolveReadableLocalPath($media->path);
            $path = $resolved['path'] ?? null;
            if (! is_readable($path)) {
                app(MediaStorage::class)->cleanupResolvedPath($resolved);

                return null;
            }
            $info = @getimagesize($path);
            if ($info === false || ! isset($info[0], $info[1])) {
                app(MediaStorage::class)->cleanupResolvedPath($resolved);

                return null;
            }
            app(MediaStorage::class)->cleanupResolvedPath($resolved);

            return min((int) $info[0], (int) $info[1]);
        } catch (\Throwable) {
            return null;
        }
    }
}
