<?php

namespace App\Jobs;

use App\Models\NewsItemMedia;
use App\Services\MediaQualityCheck;
use App\Services\MediaStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class ExtractAudioMetadata implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected NewsItemMedia $media
    ) {}

    public function handle(): void
    {
        if (! $this->media->isAudio()) {
            Log::info('ExtractAudioMetadata: skip, not audio', ['media_id' => $this->media->id]);

            return;
        }

        $resolved = app(MediaStorage::class)->resolveReadableLocalPath($this->media->path);
        $absPath = $resolved['path'] ?? null;
        if (! is_string($absPath) || ! file_exists($absPath)) {
            Log::warning('ExtractAudioMetadata: file does not exist', [
                'media_id' => $this->media->id,
                'abs_path' => $absPath,
                'path' => $this->media->path,
            ]);

            return;
        }

        $ffprobePath = config('media.ffprobe_path', 'ffprobe');
        if (str_contains($ffprobePath, DIRECTORY_SEPARATOR) && ! file_exists($ffprobePath)) {
            Log::warning('ExtractAudioMetadata: ffprobe binary not found', [
                'media_id' => $this->media->id,
                'ffprobe_path' => $ffprobePath,
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
            Log::warning('ExtractAudioMetadata: ffprobe failed', [
                'media_id' => $this->media->id,
                'abs_path' => $absPath,
                'stderr' => $process->getErrorOutput(),
                'stdout' => $process->getOutput(),
            ]);

            return;
        }

        $json = $process->getOutput();
        $data = json_decode($json, true);
        if (! is_array($data)) {
            Log::warning('ExtractAudioMetadata: invalid json', [
                'media_id' => $this->media->id,
                'json_excerpt' => Str::limit($json, 500),
            ]);

            return;
        }

        $audioStream = null;
        foreach ($data['streams'] ?? [] as $stream) {
            if (($stream['codec_type'] ?? '') === 'audio') {
                $audioStream = $stream;
                break;
            }
        }

        $format = $data['format'] ?? [];

        $durationS = null;
        if (isset($format['duration']) && is_numeric($format['duration'])) {
            $durationS = round((float) $format['duration'], 4);
        } elseif ($audioStream && isset($audioStream['duration']) && is_numeric($audioStream['duration'])) {
            $durationS = round((float) $audioStream['duration'], 4);
        }

        $bitrateBps = null;
        if ($audioStream && isset($audioStream['bit_rate']) && (int) $audioStream['bit_rate'] > 0) {
            $bitrateBps = (int) $audioStream['bit_rate'];
        } elseif (isset($format['bit_rate']) && (int) $format['bit_rate'] > 0) {
            $bitrateBps = (int) $format['bit_rate'];
        }

        $codec = $audioStream && isset($audioStream['codec_name']) ? (string) $audioStream['codec_name'] : null;

        $this->media->duration_s = $durationS;
        $this->media->bitrate_bps = $bitrateBps;
        $this->media->codec = $codec;
        $this->media->save();

        app(MediaQualityCheck::class)->runAndSave($this->media);

        Log::info('ExtractAudioMetadata: saved', [
            'media_id' => $this->media->id,
            'duration_s' => $durationS,
            'bitrate_bps' => $bitrateBps,
            'codec' => $codec,
        ]);

        app(MediaStorage::class)->cleanupResolvedPath($resolved);
    }
}
