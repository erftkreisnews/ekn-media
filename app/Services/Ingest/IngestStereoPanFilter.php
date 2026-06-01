<?php

namespace App\Services\Ingest;

/**
 * Stereo-Ausgabe nach Sendespezifikation: links O-Ton, rechts Atmo (aus Mehrkanal-Quelle).
 */
final class IngestStereoPanFilter
{
    /**
     * @return list<string> ffmpeg-audiofilter-Fragmente (ohne abschließendes aformat)
     */
    public static function preFormatFilters(?int $sourceChannels, int $outputChannels): array
    {
        if (! (bool) config('ingest.output.stereo_broadcast_map', true)) {
            return [];
        }

        if ($outputChannels !== 2 || $sourceChannels === null || $sourceChannels < 1) {
            return [];
        }

        if ($sourceChannels === 2) {
            return [];
        }

        if ($sourceChannels === 1) {
            // FFmpeg 4.4: „c1=0“ ist ungültig (kein Kanalname) — rechte Spur stumm via 0*c0.
            return ['pan=stereo|c0=c0|c1=0*c0'];
        }

        $oton = max(0, (int) config('ingest.output.stereo_oton_channel_index', 0));
        if ($oton >= $sourceChannels) {
            return [];
        }

        if ($sourceChannels >= 4) {
            $atmo = trim((string) config('ingest.output.stereo_atmo_pan_expression', '0.25*c1+0.25*c2+0.5*c3'));

            return ['pan=stereo|c0=c'.$oton.'|c1='.$atmo];
        }

        $atmoCh = max(0, (int) config('ingest.output.stereo_atmo_channel_index', $sourceChannels - 1));
        $atmoCh = min($atmoCh, $sourceChannels - 1);

        return ['pan=stereo|c0=c'.$oton.'|c1=c'.$atmoCh];
    }

    public static function buildAudioFilterChain(?int $sourceChannels, int $outputChannels, int $sampleRate): string
    {
        $layout = match (true) {
            $outputChannels <= 1 => 'mono',
            $outputChannels === 2 => 'stereo',
            $outputChannels === 4 => 'quad',
            $outputChannels === 6 => '5.1',
            default => (string) config('ingest.output.audio_channel_layout', 'quad'),
        };

        $parts = self::preFormatFilters($sourceChannels, $outputChannels);
        $parts[] = 'aformat=sample_fmts=fltp:channel_layouts='.$layout.':sample_rates='.$sampleRate;

        return implode(',', $parts);
    }
}
