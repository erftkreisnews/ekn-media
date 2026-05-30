<?php

namespace App\Services\Ingest;

use App\Models\IngestFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Process;

class IngestPreviewService
{
    public function __construct(
        protected IngestFfprobeService $ffprobe,
    ) {}

    /**
     * Leichte Browser-Vorschau (klein, kurz) — nicht identisch mit der Sendefassung.
     *
     * @return array{ok: bool, local_path?: string, error?: string}
     */
    public function generateForIngestFile(IngestFile $file): array
    {
        $input = (string) $file->absolute_path;
        if ($input === '' || ! is_file($input)) {
            return ['ok' => false, 'error' => 'Quelldatei fehlt.'];
        }

        if (! $file->isIngestVideoCandidate()) {
            return ['ok' => false, 'error' => 'Kein Video-Clip.'];
        }

        $tmp = rtrim((string) config('ingest.paths.tmp'), '/');
        File::ensureDirectoryExists($tmp);
        $outPath = $tmp.'/ingest_preview_'.uniqid('', true).'_'.$file->id.'.mp4';

        return $this->generateBrowserPreviewMp4(
            $input,
            $outPath,
            $file->trim_in_seconds !== null ? (float) $file->trim_in_seconds : null,
            $file->trim_out_seconds !== null ? (float) $file->trim_out_seconds : null,
            $file->duration_s !== null ? (float) $file->duration_s : null,
        );
    }

    /**
     * @return array{ok: bool, local_path?: string, error?: string}
     */
    public function generateBrowserPreviewMp4(
        string $inputAbsolutePath,
        string $outputAbsolutePath,
        ?float $trimInSeconds = null,
        ?float $trimOutSeconds = null,
        ?float $knownDurationSeconds = null,
    ): array {
        $ffmpeg = (string) config('media.ffmpeg_path', 'ffmpeg');
        if (str_contains($ffmpeg, DIRECTORY_SEPARATOR) && ! file_exists($ffmpeg)) {
            return ['ok' => false, 'error' => 'ffmpeg nicht gefunden: '.$ffmpeg];
        }

        File::ensureDirectoryExists(dirname($outputAbsolutePath));

        $maxW = max(320, (int) config('ingest.preview.max_width', 960));
        $maxH = max(180, (int) config('ingest.preview.max_height', 540));
        $maxSeconds = max(5, (int) config('ingest.preview.max_seconds', 60));
        $vbr = (string) config('ingest.preview.video_bitrate', '2M');
        $abr = (string) config('ingest.preview.audio_bitrate', '128k');
        $preset = (string) config('ingest.preview.x264_preset', 'veryfast');
        $threads = max(1, (int) config('ingest.preview.ffmpeg_threads', 1));

        $trimIn = $trimInSeconds !== null ? max(0.0, $trimInSeconds) : 0.0;
        $duration = $knownDurationSeconds;
        if ($duration === null || $duration <= 0) {
            $probe = $this->ffprobe->analyze($inputAbsolutePath);
            if ($probe['ok'] && isset($probe['data']['duration_s'])) {
                $duration = (float) $probe['data']['duration_s'];
            }
        }

        $trimOut = $trimOutSeconds;
        if ($trimOut !== null && $duration !== null && $duration > 0) {
            $trimOut = min((float) $trimOut, (float) $duration);
        }

        $segmentDuration = (float) $maxSeconds;
        if ($duration !== null && $duration > 0) {
            $available = $duration - $trimIn;
            if ($trimOut !== null && $trimOut > $trimIn) {
                $available = min($available, $trimOut - $trimIn);
            }
            $segmentDuration = min($segmentDuration, max(0.5, $available));
        }

        $vf = 'scale='.$maxW.':'.$maxH.':force_original_aspect_ratio=decrease:flags=fast_bilinear,format=yuv420p';

        $cmd = [$ffmpeg, '-y', '-nostdin', '-threads', (string) $threads, '-filter_threads', '1'];
        if ($trimIn > 0) {
            $cmd = array_merge($cmd, ['-ss', (string) $trimIn]);
        }
        $cmd = array_merge($cmd, [
            '-i', $inputAbsolutePath,
            '-t', (string) $segmentDuration,
            '-vf', $vf,
            '-c:v', 'libx264',
            '-preset', $preset,
            '-b:v', $vbr,
            '-maxrate', $vbr,
            '-bufsize', $vbr,
            '-pix_fmt', 'yuv420p',
            '-movflags', '+faststart',
        ]);

        if ($this->ffprobe->hasAudioStream($inputAbsolutePath)) {
            $cmd = array_merge($cmd, [
                '-map', '0:v:0',
                '-map', '0:a:0',
                '-c:a', 'aac',
                '-b:a', $abr,
                '-ac', '2',
            ]);
        } else {
            $cmd = array_merge($cmd, ['-an']);
        }

        $cmd[] = $outputAbsolutePath;

        $p = new Process($cmd);
        $p->setTimeout(max(120, (int) config('ingest.preview.timeout_seconds', 600)));
        try {
            $p->run();
        } catch (ProcessSignaledException $e) {
            Log::warning('ingest.preview.signaled', ['signal' => $e->getSignal()]);

            return ['ok' => false, 'error' => 'Vorschau: ffmpeg beendet (Signal '.(string) $e->getSignal().', oft Speicherlimit).'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Vorschau: '.$e->getMessage()];
        }

        if (! $p->isSuccessful()) {
            return ['ok' => false, 'error' => 'Vorschau: '.$p->getErrorOutput()];
        }

        if (! is_file($outputAbsolutePath) || filesize($outputAbsolutePath) < 1024) {
            return ['ok' => false, 'error' => 'Vorschau-Ausgabe fehlt oder zu klein.'];
        }

        return ['ok' => true, 'local_path' => $outputAbsolutePath];
    }
}
