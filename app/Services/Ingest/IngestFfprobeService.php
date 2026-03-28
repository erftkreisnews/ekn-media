<?php

namespace App\Services\Ingest;

use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class IngestFfprobeService
{
    /**
     * @return array{ok: bool, data?: array, error?: string}
     */
    public function analyze(string $absolutePath): array
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return ['ok' => false, 'error' => 'Datei nicht lesbar.'];
        }

        $ffprobePath = config('media.ffprobe_path', 'ffprobe');
        if (str_contains($ffprobePath, DIRECTORY_SEPARATOR) && ! file_exists($ffprobePath)) {
            return ['ok' => false, 'error' => 'ffprobe nicht gefunden: '.$ffprobePath];
        }

        $process = new Process([
            $ffprobePath,
            '-v', 'error',
            '-print_format', 'json',
            '-show_streams',
            '-show_format',
            $absolutePath,
        ]);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            return ['ok' => false, 'error' => 'ffprobe: '.$process->getErrorOutput()];
        }

        $json = $process->getOutput();
        $data = json_decode($json, true);
        if (! is_array($data)) {
            return ['ok' => false, 'error' => 'Ungültige ffprobe-Ausgabe: '.Str::limit($json, 200)];
        }

        $videoStream = null;
        foreach ($data['streams'] ?? [] as $stream) {
            if (($stream['codec_type'] ?? '') === 'video') {
                $videoStream = $stream;
                break;
            }
        }

        if ($videoStream === null) {
            return ['ok' => false, 'error' => 'Kein Video-Stream.'];
        }

        $format = $data['format'] ?? [];
        $durationS = null;
        if (isset($format['duration']) && is_numeric($format['duration'])) {
            $durationS = round((float) $format['duration'], 4);
        } elseif (isset($videoStream['duration']) && is_numeric($videoStream['duration'])) {
            $durationS = round((float) $videoStream['duration'], 4);
        }

        if ($durationS === null || $durationS <= 0) {
            return ['ok' => false, 'error' => 'Dauer ungültig oder 0.'];
        }

        $width = isset($videoStream['width']) ? (int) $videoStream['width'] : null;
        $height = isset($videoStream['height']) ? (int) $videoStream['height'] : null;
        $codec = isset($videoStream['codec_name']) ? (string) $videoStream['codec_name'] : null;

        $fps = null;
        $rate = $videoStream['avg_frame_rate'] ?? $videoStream['r_frame_rate'] ?? null;
        if (is_string($rate) && preg_match('#^(\d+)/(\d+)$#', $rate, $m)) {
            $den = (int) $m[2];
            $fps = $den > 0 ? round((int) $m[1] / $den, 4) : null;
        }

        $hasAudio = false;
        foreach ($data['streams'] ?? [] as $stream) {
            if (($stream['codec_type'] ?? '') === 'audio') {
                $hasAudio = true;
                break;
            }
        }

        return [
            'ok' => true,
            'data' => [
                'duration_s' => $durationS,
                'width' => $width,
                'height' => $height,
                'fps' => $fps,
                'codec' => $codec,
                'has_audio' => $hasAudio,
                'ffprobe_json' => $json,
            ],
        ];
    }

    public function hasAudioStream(string $absolutePath): bool
    {
        $r = $this->analyze($absolutePath);

        return $r['ok'] && ! empty($r['data']['has_audio']);
    }
}
