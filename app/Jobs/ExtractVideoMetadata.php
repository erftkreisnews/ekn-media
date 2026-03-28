<?php

namespace App\Jobs;

use App\Models\NewsItemMedia;
use App\Services\MediaStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class ExtractVideoMetadata implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected NewsItemMedia $media
    ) {}

    public function handle(): void
    {
        if (! $this->media->isVideo()) {
            Log::info('ExtractVideoMetadata: skip, not video', ['media_id' => $this->media->id]);

            return;
        }

        $resolved = app(MediaStorage::class)->resolveReadableLocalPath($this->media->path);
        $absPath = $resolved['path'] ?? null;
        if (! is_string($absPath) || ! file_exists($absPath)) {
            Log::warning('ExtractVideoMetadata: file does not exist', [
                'media_id' => $this->media->id,
                'abs_path' => $absPath,
                'path' => $this->media->path,
            ]);

            return;
        }

        $ffprobePath = config('media.ffprobe_path', 'ffprobe');
        if (str_contains($ffprobePath, DIRECTORY_SEPARATOR) && ! file_exists($ffprobePath)) {
            Log::warning('ExtractVideoMetadata: ffprobe binary not found', [
                'media_id' => $this->media->id,
                'ffprobe_path' => $ffprobePath,
                'abs_path' => $absPath,
            ]);

            return;
        }

        $process = new Process([
            $ffprobePath,
            '-v', 'error',
            '-print_format', 'json',
            '-show_streams',
            '-show_format',
            $absPath,
        ]);
        $process->setTimeout(20);
        $process->run();

        if (! $process->isSuccessful()) {
            Log::warning('ExtractVideoMetadata: ffprobe failed', [
                'media_id' => $this->media->id,
                'abs_path' => $absPath,
                'ffprobe_path' => $ffprobePath,
                'stderr' => $process->getErrorOutput(),
                'stdout' => $process->getOutput(),
            ]);

            return;
        }

        $json = $process->getOutput();
        $data = json_decode($json, true);
        if (! is_array($data)) {
            Log::warning('ExtractVideoMetadata: invalid json', [
                'media_id' => $this->media->id,
                'abs_path' => $absPath,
                'json_excerpt' => Str::limit($json, 500),
            ]);

            return;
        }

        $videoStream = null;
        foreach ($data['streams'] ?? [] as $stream) {
            if (($stream['codec_type'] ?? '') === 'video') {
                $videoStream = $stream;
                break;
            }
        }

        if ($videoStream === null) {
            Log::warning('ExtractVideoMetadata: no video stream in file', [
                'media_id' => $this->media->id,
                'abs_path' => $absPath,
                'stream_count' => count($data['streams'] ?? []),
            ]);

            return;
        }

        $format = $data['format'] ?? [];
        $width = isset($videoStream['width']) ? (int) $videoStream['width'] : null;
        $height = isset($videoStream['height']) ? (int) $videoStream['height'] : null;
        $codec = isset($videoStream['codec_name']) ? (string) $videoStream['codec_name'] : null;
        $fieldOrder = isset($videoStream['field_order']) ? (string) $videoStream['field_order'] : null;

        $fps = null;
        $rate = $videoStream['avg_frame_rate'] ?? $videoStream['r_frame_rate'] ?? null;
        if (is_string($rate) && preg_match('#^(\d+)/(\d+)$#', $rate, $m)) {
            $den = (int) $m[2];
            $fps = $den > 0 ? round((int) $m[1] / $den, 4) : null;
        }

        $durationS = null;
        if (isset($format['duration']) && is_numeric($format['duration'])) {
            $durationS = round((float) $format['duration'], 4);
        } elseif (isset($videoStream['duration']) && is_numeric($videoStream['duration'])) {
            $durationS = round((float) $videoStream['duration'], 4);
        }

        $bitrateBps = null;
        if (isset($videoStream['bit_rate']) && (int) $videoStream['bit_rate'] > 0) {
            $bitrateBps = (int) $videoStream['bit_rate'];
        } elseif (isset($format['bit_rate']) && (int) $format['bit_rate'] > 0) {
            $bitrateBps = (int) $format['bit_rate'];
        }

        $extracted = [
            'width' => $width,
            'height' => $height,
            'fps' => $fps,
            'bitrate_bps' => $bitrateBps,
            'codec' => $codec,
            'field_order' => $fieldOrder,
            'duration_s' => $durationS,
        ];

        $missing = array_keys(array_filter($extracted, fn ($v) => $v === null));
        if ($missing !== []) {
            Log::info('ExtractVideoMetadata: some values missing', [
                'media_id' => $this->media->id,
                'abs_path' => $absPath,
                'missing' => $missing,
                'json_stream_excerpt' => $videoStream ? array_intersect_key($videoStream, array_flip(['width', 'height', 'codec_name', 'avg_frame_rate', 'r_frame_rate', 'bit_rate', 'duration', 'field_order'])) : null,
                'json_format_excerpt' => array_intersect_key($format, array_flip(['duration', 'bit_rate'])),
            ]);
        }

        $this->media->width = $width;
        $this->media->height = $height;
        $this->media->fps = $fps;
        $this->media->bitrate_bps = $bitrateBps;
        $this->media->codec = $codec;
        $this->media->field_order = $fieldOrder;
        $this->media->duration_s = $durationS;
        $this->media->save();

        Log::info('ExtractVideoMetadata: saved', [
            'media_id' => $this->media->id,
            'abs_path' => $absPath,
            'extracted' => $extracted,
        ]);

        app(MediaStorage::class)->cleanupResolvedPath($resolved);
    }
}
