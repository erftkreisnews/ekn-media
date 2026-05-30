<?php

namespace App\Services\Ingest;

/**
 * Plant Final-Render in mehrere MP4-Teile, wenn die geschätzte Ausgabegröße ein Limit überschreitet.
 *
 * @phpstan-type IngestClipForChunk array{id: int, path: string, duration_s: ?float}
 */
final class IngestRenderChunkPlanner
{
    public function __construct(
        protected IngestFfprobeService $ffprobe,
    ) {}

    /**
     * @param  list<IngestClipForChunk>  $clipsOrdered
     * @return list<list<IngestClipForChunk>>
     */
    public function planChunks(array $clipsOrdered, ?int $maxOutputBytes = null): array
    {
        if ($clipsOrdered === []) {
            return [];
        }

        $maxBytes = $maxOutputBytes ?? $this->maxOutputBytes();
        if ($maxBytes <= 0) {
            return [$clipsOrdered];
        }

        $bytesPerSecond = $this->estimateOutputBytesPerSecond();
        $chunks = [];
        $current = [];
        $currentBytes = 0;

        foreach ($clipsOrdered as $clip) {
            $clipBytes = $this->estimateClipOutputBytes($clip, $bytesPerSecond);

            if ($current !== [] && ($currentBytes + $clipBytes) > $maxBytes) {
                $chunks[] = $current;
                $current = [];
                $currentBytes = 0;
            }

            $current[] = $clip;
            $currentBytes += $clipBytes;
        }

        if ($current !== []) {
            $chunks[] = $current;
        }

        return $chunks !== [] ? $chunks : [$clipsOrdered];
    }

    public function maxOutputBytes(): int
    {
        $gb = (float) config('ingest.output.max_output_gigabytes', 1.0);

        return max(64 * 1024 * 1024, (int) round($gb * 1024 * 1024 * 1024));
    }

    public function estimateOutputBytesPerSecond(): float
    {
        $videoBps = $this->parseBitrateToBitsPerSecond((string) config('ingest.output.video_bitrate', '12.4M'));
        $audioBps = $this->parseBitrateToBitsPerSecond((string) config('ingest.output.audio_bitrate', '256k'));
        $muxOverhead = max(1.0, (float) config('ingest.output.size_estimate_overhead', 1.08));

        return (($videoBps + $audioBps) / 8.0) * $muxOverhead;
    }

    /**
     * @param  IngestClipForChunk  $clip
     */
    public function estimateClipOutputBytes(array $clip, ?float $bytesPerSecond = null): int
    {
        $bytesPerSecond ??= $this->estimateOutputBytesPerSecond();
        $duration = $clip['duration_s'] ?? null;
        if ($duration === null || $duration <= 0) {
            $probe = $this->ffprobe->analyze((string) $clip['path']);
            if ($probe['ok'] && isset($probe['data']['duration_s'])) {
                $duration = (float) $probe['data']['duration_s'];
            }
        }

        $duration = max(1.0, (float) ($duration ?? 30.0));

        return (int) ceil($duration * $bytesPerSecond);
    }

    public function parseBitrateToBitsPerSecond(string $bitrate): float
    {
        $bitrate = trim(str_replace(' ', '', $bitrate));
        if ($bitrate === '') {
            return 0.0;
        }

        if (preg_match('/^([\d.]+)\s*([kKmMgG])?$/', $bitrate, $m) !== 1) {
            return (float) $bitrate;
        }

        $value = (float) $m[1];
        $unit = strtolower($m[2] ?? '');

        return match ($unit) {
            'k' => $value * 1_000,
            'm' => $value * 1_000_000,
            'g' => $value * 1_000_000_000,
            default => $value,
        };
    }
}
