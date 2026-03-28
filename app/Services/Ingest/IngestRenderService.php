<?php

namespace App\Services\Ingest;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class IngestRenderService
{
    public function __construct(
        protected IngestFfprobeService $ffprobe,
    ) {}

    /**
     * Normalisiert jeden Clip und concat zu einer MP4 (H.264 + AAC, sendefähig).
     *
     * @param  list<string>  $absoluteInputPaths  Reihenfolge = Schnittreihenfolge
     * @return array{ok: bool, error?: string}
     */
    public function renderConcatToMp4(array $absoluteInputPaths, string $outputAbsolutePath): array
    {
        if ($absoluteInputPaths === []) {
            return ['ok' => false, 'error' => 'Keine Eingabedateien.'];
        }

        $ffmpeg = config('media.ffmpeg_path', 'ffmpeg');
        if (str_contains($ffmpeg, DIRECTORY_SEPARATOR) && ! file_exists($ffmpeg)) {
            return ['ok' => false, 'error' => 'ffmpeg nicht gefunden: '.$ffmpeg];
        }

        $tmp = rtrim(config('ingest.paths.tmp'), '/');
        File::ensureDirectoryExists($tmp);
        File::ensureDirectoryExists(dirname($outputAbsolutePath));

        $w = (int) config('ingest.output.max_width', 1920);
        $h = (int) config('ingest.output.max_height', 1080);
        $fps = (string) config('ingest.output.fps', '25');
        $vbr = (string) config('ingest.output.video_bitrate', '12M');
        $abr = (string) config('ingest.output.audio_bitrate', '256k');
        $ar = (int) config('ingest.output.audio_sample_rate', 48000);
        $movflags = (string) config('ingest.output.movflags', '+faststart');

        $segments = [];
        foreach ($absoluteInputPaths as $i => $inPath) {
            if (! is_file($inPath)) {
                return ['ok' => false, 'error' => 'Eingabe fehlt: '.$inPath];
            }

            $segPath = $tmp.'/ingest_seg_'.uniqid('', true).'_'.$i.'.mp4';
            $vf = 'fps='.$fps.',scale='.$w.':'.$h.':force_original_aspect_ratio=decrease,pad='.$w.':'.$h.':(ow-iw)/2:(oh-ih)/2,format=yuv420p';

            $hasAudio = $this->ffprobe->hasAudioStream($inPath);
            if ($hasAudio) {
                $cmd = [
                    $ffmpeg, '-y', '-i', $inPath,
                    '-vf', $vf,
                    '-c:v', 'libx264', '-b:v', $vbr, '-maxrate', $vbr, '-bufsize', '24M',
                    '-c:a', 'aac', '-b:a', $abr, '-ar', (string) $ar, '-ac', '2',
                    '-movflags', $movflags,
                    $segPath,
                ];
            } else {
                $cmd = [
                    $ffmpeg, '-y', '-i', $inPath,
                    '-f', 'lavfi', '-i', 'anullsrc=channel_layout=stereo:sample_rate='.$ar,
                    '-vf', $vf,
                    '-c:v', 'libx264', '-b:v', $vbr, '-maxrate', $vbr, '-bufsize', '24M',
                    '-c:a', 'aac', '-b:a', $abr, '-ar', (string) $ar, '-ac', '2',
                    '-map', '0:v:0', '-map', '1:a:0', '-shortest',
                    '-movflags', $movflags,
                    $segPath,
                ];
            }

            $p = new Process($cmd);
            $p->setTimeout(3600);
            $p->run();
            if (! $p->isSuccessful()) {
                Log::warning('ingest.render.normalize_failed', ['i' => $i, 'err' => $p->getErrorOutput()]);

                return ['ok' => false, 'error' => 'Normalisieren Clip '.($i + 1).': '.$p->getErrorOutput()];
            }

            $segments[] = $segPath;
        }

        $listFile = $tmp.'/ingest_concat_'.uniqid('', true).'.txt';
        $lines = [];
        foreach ($segments as $seg) {
            $lines[] = "file '".str_replace("'", "'\\''", $seg)."'";
        }
        File::put($listFile, implode("\n", $lines));

        $cmd2 = [
            $ffmpeg, '-y', '-f', 'concat', '-safe', '0', '-i', $listFile,
            '-c', 'copy',
            $outputAbsolutePath,
        ];
        $p2 = new Process($cmd2);
        $p2->setTimeout(3600);
        $p2->run();

        foreach ($segments as $seg) {
            @unlink($seg);
        }
        @unlink($listFile);

        if (! $p2->isSuccessful()) {
            Log::warning('ingest.render.concat_failed', ['err' => $p2->getErrorOutput()]);

            return ['ok' => false, 'error' => 'Concat: '.$p2->getErrorOutput()];
        }

        if (! is_file($outputAbsolutePath) || filesize($outputAbsolutePath) < 1024) {
            return ['ok' => false, 'error' => 'Ausgabedatei fehlt oder zu klein.'];
        }

        return ['ok' => true];
    }
}
