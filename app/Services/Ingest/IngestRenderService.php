<?php

namespace App\Services\Ingest;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessSignaledException;
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
        $h = (int) config('ingest.output.max_height', 1080); // Sende-Minimum 1920×1080
        $fps = (string) config('ingest.output.fps', '50');
        $vbr = (string) config('ingest.output.video_bitrate', '14M');
        $abr = (string) config('ingest.output.audio_bitrate', '256k');
        $ar = (int) config('ingest.output.audio_sample_rate', 48000);
        $channels = (int) config('ingest.output.audio_channels', 4);
        $normalizeAc = config('ingest.output.normalize_audio_channels');
        $ac = is_int($normalizeAc) ? max(1, $normalizeAc) : $channels;
        $channelLayout = (string) config('ingest.output.audio_channel_layout', 'quad');
        $forceInterlaced = (bool) config('ingest.output.force_interlaced', true);
        $movflags = (string) config('ingest.output.movflags', '+faststart');
        $x264Params = $this->x264ParamsForOutput();
        $audioLayoutForAformat = $this->ffmpegAudioLayoutLabel($ac, $channelLayout);
        $anullsrcLayout = $this->anullsrcChannelLayout($ac, $channelLayout);

        $segments = [];
        foreach ($absoluteInputPaths as $i => $inPath) {
            if (! is_file($inPath)) {
                return ['ok' => false, 'error' => 'Eingabe fehlt: '.$inPath];
            }

            $segPath = $tmp.'/ingest_seg_'.uniqid('', true).'_'.$i.'.mp4';
            $scaleFlags = trim((string) config('ingest.output.scale_flags', 'fast_bilinear'));
            $scalePart = $scaleFlags !== ''
                ? 'scale='.$w.':'.$h.':force_original_aspect_ratio=decrease:flags='.$scaleFlags
                : 'scale='.$w.':'.$h.':force_original_aspect_ratio=decrease';
            $vfBase = $scalePart.',pad='.$w.':'.$h.':(ow-iw)/2:(oh-ih)/2,format=yuv420p';
            // Zuerst skalieren (u. a. 4K→1080p), dann fps — spart RAM gegenüber fps auf voller Quellauflösung.
            // Standard: 1080p50; bei force_interlaced: Felder setzen + x264 interlaced.
            $vf = $vfBase.',fps='.$fps.($forceInterlaced ? ',setfield=mode=tff,fieldorder=tff' : '');

            $hasAudio = $this->ffprobe->hasAudioStream($inPath);
            // AAC: fltp; channel_layouts in aformat nur so viele Kanäle wie nötig (RAM).
            $audioAf = 'aformat=sample_fmts=fltp:channel_layouts='.$audioLayoutForAformat.':sample_rates='.$ar;
            if ($hasAudio) {
                $cmd = array_merge(
                    [$ffmpeg, '-y'],
                    $this->ffmpegLowMemoryGlobalOpts(),
                    ['-i', $inPath],
                    array_merge(
                        ['-vf', $vf],
                        $this->libx264VideoOpts($vbr, $x264Params),
                        array_merge(
                            [
                                '-map', '0:v:0', '-map', '0:a:0',
                                '-c:a', 'aac', '-b:a', $abr, '-ar', (string) $ar, '-ac', (string) $ac,
                                '-af', $audioAf,
                                '-movflags', $movflags,
                            ],
                            $this->ffmpegVideoMuxerRateOpts($fps),
                            [$segPath]
                        )
                    )
                );
            } else {
                $cmd = array_merge(
                    [$ffmpeg, '-y'],
                    $this->ffmpegLowMemoryGlobalOpts(),
                    ['-i', $inPath],
                    ['-f', 'lavfi', '-i', 'anullsrc=channel_layout='.$anullsrcLayout.':sample_rate='.$ar],
                    array_merge(
                        ['-vf', $vf],
                        $this->libx264VideoOpts($vbr, $x264Params),
                        array_merge(
                            [
                                '-c:a', 'aac', '-b:a', $abr, '-ar', (string) $ar, '-ac', (string) $ac,
                                '-af', $audioAf,
                                '-map', '0:v:0', '-map', '1:a:0', '-shortest',
                                '-movflags', $movflags,
                            ],
                            $this->ffmpegVideoMuxerRateOpts($fps),
                            [$segPath]
                        )
                    )
                );
            }

            $p = new Process($cmd);
            $p->setTimeout(3600);
            try {
                $p->run();
            } catch (ProcessSignaledException $e) {
                $sig = $e->getSignal();
                Log::warning('ingest.render.normalize_signaled', ['i' => $i, 'signal' => $sig]);

                return ['ok' => false, 'error' => 'Normalisieren Clip '.($i + 1).': ffmpeg wurde beendet (Signal '.(string) $sig.'). Typisch: Speicherlimit/OOM.'];
            } catch (\Throwable $e) {
                Log::warning('ingest.render.normalize_exception', ['i' => $i, 'err' => $e->getMessage()]);

                return ['ok' => false, 'error' => 'Normalisieren Clip '.($i + 1).': '.$e->getMessage()];
            }
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

        $cmdCopy = [
            $ffmpeg, '-y', '-f', 'concat', '-safe', '0', '-i', $listFile,
            '-c', 'copy',
            $outputAbsolutePath,
        ];
        $p2 = new Process($cmdCopy);
        $p2->setTimeout(3600);
        $p2->run();

        $concatOk = $p2->isSuccessful();
        $concatErr = $p2->getErrorOutput();

        if (! $concatOk) {
            Log::warning('ingest.render.concat_copy_failed', ['err' => $concatErr]);
            // Zweiter Versuch: neu encodieren (hilft z. B. bei leicht abweichenden Stream-Metadaten nach libx264)
            $cmdRe = array_merge(
                [$ffmpeg, '-y'],
                $this->ffmpegLowMemoryGlobalOpts(),
                ['-f', 'concat', '-safe', '0', '-i', $listFile],
                array_merge(
                    ['-vf', $vf],
                    $this->libx264VideoOpts($vbr, $x264Params),
                    array_merge(
                        [
                            '-c:a', 'aac', '-b:a', $abr, '-ar', (string) $ar, '-ac', (string) $ac,
                            '-af', $audioAf,
                            '-movflags', $movflags,
                        ],
                        $this->ffmpegVideoMuxerRateOpts($fps),
                        [$outputAbsolutePath]
                    )
                )
            );
            $p2b = new Process($cmdRe);
            $p2b->setTimeout(3600);
            $p2b->run();
            $concatOk = $p2b->isSuccessful();
            $concatErr = $concatErr."\n--- re-encode ---\n".$p2b->getErrorOutput();
            if (! $concatOk) {
                Log::warning('ingest.render.concat_reencode_failed', ['err' => $p2b->getErrorOutput()]);
            }
        }

        foreach ($segments as $seg) {
            @unlink($seg);
        }
        @unlink($listFile);

        if (! $concatOk) {
            return ['ok' => false, 'error' => 'Zusammenfügen (Concat): '.$concatErr];
        }

        if (! is_file($outputAbsolutePath) || filesize($outputAbsolutePath) < 1024) {
            return ['ok' => false, 'error' => 'Ausgabedatei fehlt oder zu klein.'];
        }

        return ['ok' => true];
    }

    /**
     * @return list<string>
     */
    private function ffmpegLowMemoryGlobalOpts(): array
    {
        $threads = max(1, (int) config('ingest.output.ffmpeg_threads', 1));
        $filterThreads = max(1, (int) config('ingest.output.ffmpeg_filter_threads', 1));

        return ['-nostdin', '-threads', (string) $threads, '-filter_threads', (string) $filterThreads];
    }

    /**
     * Ohne diese Optionen melden manche Tools ~49,95 fps statt exakt 50 (Zeitbasis/Muxer).
     * FFmpeg 4.x: -vsync cfr; zusätzlich -r = nominelle Ausgabe-Fps.
     *
     * @return list<string>
     */
    private function ffmpegVideoMuxerRateOpts(string $fps): array
    {
        return ['-vsync', 'cfr', '-r', $fps];
    }

    /**
     * @return list<string>
     */
    private function libx264VideoOpts(string $vbr, string $x264Params): array
    {
        $preset = (string) config('ingest.output.x264_preset', 'veryfast');
        $buf = (string) config('ingest.output.vbv_bufsize', '12.4M');
        $interlaced = (bool) config('ingest.output.force_interlaced', true);

        $opts = [
            '-c:v', 'libx264',
            '-preset', $preset,
            '-b:v', $vbr, '-maxrate', $vbr, '-bufsize', $buf,
        ];
        if ($interlaced) {
            $opts = array_merge($opts, ['-flags', '+ilme+ildct']);
        }
        $opts = array_merge($opts, ['-x264-params', $x264Params]);

        return $opts;
    }

    /**
     * Bei force_interlaced=false: kein interlaced=1 in libx264 (weniger RAM als 1080i-Pfad).
     */
    private function x264ParamsForOutput(): string
    {
        $encThreads = max(1, (int) config('ingest.output.x264_threads', 1));
        $lookahead = max(0, (int) config('ingest.output.x264_rc_lookahead', 0));
        $ref = max(1, (int) config('ingest.output.x264_ref', 1));
        $bframes = max(0, (int) config('ingest.output.x264_bframes', 0));
        $interlaced = (bool) config('ingest.output.force_interlaced', true);

        if ($interlaced) {
            $base = 'interlaced=1:tff=1:threads='.$encThreads.':ref='.$ref.':bframes='.$bframes;
        } else {
            // force-cfr: konstante Bildrate (zusammen mit -vsync cfr / -r am Muxer)
            $base = 'threads='.$encThreads.':ref='.$ref.':bframes='.$bframes.':force-cfr=1';
        }
        if ($lookahead > 0) {
            $base .= ':rc_lookahead='.$lookahead;
        }

        return $base;
    }

    private function ffmpegAudioLayoutLabel(int $ac, string $fallbackMultichannel): string
    {
        return match (true) {
            $ac <= 1 => 'mono',
            $ac === 2 => 'stereo',
            $ac === 4 => 'quad',
            $ac === 6 => '5.1',
            default => $fallbackMultichannel,
        };
    }

    private function anullsrcChannelLayout(int $ac, string $fallbackMultichannel): string
    {
        return $this->ffmpegAudioLayoutLabel($ac, $fallbackMultichannel);
    }
}
