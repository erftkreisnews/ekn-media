<?php

namespace App\Console\Commands;

use App\Services\VideoMetadataXmpService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class GenerateMetadataCommand extends Command
{
    protected $signature = 'generate-metadata
                            {video? : Pfad zur MP4-Datei}
                            {metadata? : Pfad zur JSON-Metadatei}
                            {--batch= : Verzeichnis mit MP4-Dateien}
                            {--metadata-dir= : Verzeichnis mit JSON-Dateien (Dateiname muss passen)}
                            {--embed : MP4 mit FFmpeg-Metadaten neu schreiben}
                            {--ffmpeg=ffmpeg : FFmpeg-Binary}
                            {--overwrite : Vorhandene XMP/Output-Dateien überschreiben}';

    protected $description = 'Erzeugt IPTC-kompatible XMP-Sidecar-Dateien für MP4 und optional eingebettete FFmpeg-Metadaten.';

    public function handle(VideoMetadataXmpService $service): int
    {
        $batchDir = $this->option('batch');
        if (is_string($batchDir) && trim($batchDir) !== '') {
            return $this->runBatch($service, $batchDir, (string) $this->option('metadata-dir'));
        }

        $videoPath = (string) $this->argument('video');
        $metadataPath = (string) $this->argument('metadata');
        if ($videoPath === '' || $metadataPath === '') {
            $this->error('Einzelmodus: Bitte video und metadata angeben, z. B. generate-metadata video.mp4 metadata.json');

            return self::FAILURE;
        }

        return $this->processOne($service, $videoPath, $metadataPath);
    }

    private function runBatch(VideoMetadataXmpService $service, string $batchDir, string $metadataDir): int
    {
        $batchDir = rtrim($batchDir, '/');
        if (! File::isDirectory($batchDir)) {
            $this->error('Batch-Verzeichnis nicht gefunden: '.$batchDir);

            return self::FAILURE;
        }

        $metaBase = trim($metadataDir) !== '' ? rtrim($metadataDir, '/') : $batchDir;
        if (! File::isDirectory($metaBase)) {
            $this->error('Metadata-Verzeichnis nicht gefunden: '.$metaBase);

            return self::FAILURE;
        }

        $videos = File::files($batchDir);
        $targets = array_values(array_filter($videos, fn ($file) => strtolower($file->getExtension()) === 'mp4'));
        if ($targets === []) {
            $this->warn('Keine MP4-Dateien im Batch-Verzeichnis gefunden.');

            return self::SUCCESS;
        }

        $ok = 0;
        $failed = 0;
        foreach ($targets as $video) {
            $videoPath = $video->getPathname();
            $baseName = pathinfo($videoPath, PATHINFO_FILENAME);
            $jsonPath = $metaBase.'/'.$baseName.'.json';
            $this->line('Verarbeite: '.$videoPath);
            $result = $this->processOne($service, $videoPath, $jsonPath);
            if ($result === self::SUCCESS) {
                $ok++;
            } else {
                $failed++;
            }
        }

        $this->newLine();
        $this->info('Batch abgeschlossen: '.$ok.' erfolgreich, '.$failed.' fehlgeschlagen.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function processOne(VideoMetadataXmpService $service, string $videoPath, string $metadataPath): int
    {
        if (! File::exists($videoPath)) {
            $this->error('Video nicht gefunden: '.$videoPath);

            return self::FAILURE;
        }
        if (strtolower((string) pathinfo($videoPath, PATHINFO_EXTENSION)) !== 'mp4') {
            $this->error('Nur MP4 wird unterstützt: '.$videoPath);

            return self::FAILURE;
        }
        if (! File::exists($metadataPath)) {
            $this->error('Metadata-JSON nicht gefunden: '.$metadataPath);

            return self::FAILURE;
        }

        $json = File::get($metadataPath);
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            $this->error('Ungültiges JSON: '.$metadataPath);

            return self::FAILURE;
        }

        try {
            $normalized = $service->validateAndNormalize($decoded);
            $xmp = $service->generateXmp($normalized);
        } catch (\InvalidArgumentException $e) {
            $this->error('Validierungsfehler: '.$e->getMessage());

            return self::FAILURE;
        }

        $xmpPath = preg_replace('/\.mp4$/i', '.xmp', $videoPath) ?: ($videoPath.'.xmp');
        if (File::exists($xmpPath) && ! $this->option('overwrite')) {
            $this->error('XMP existiert bereits (nutze --overwrite): '.$xmpPath);

            return self::FAILURE;
        }

        File::put($xmpPath, $xmp);
        $this->info('XMP erzeugt: '.$xmpPath);

        if ($this->option('embed')) {
            $embeddedPath = preg_replace('/\.mp4$/i', '.tagged.mp4', $videoPath) ?: ($videoPath.'.tagged.mp4');
            if (File::exists($embeddedPath) && ! $this->option('overwrite')) {
                $this->error('Ausgabedatei existiert bereits (nutze --overwrite): '.$embeddedPath);

                return self::FAILURE;
            }

            try {
                $this->embedWithFfmpeg(
                    (string) $this->option('ffmpeg'),
                    $videoPath,
                    $embeddedPath,
                    $service->ffmpegMetadataArguments($normalized)
                );
            } catch (\Throwable $e) {
                $this->error('FFmpeg-Embedding fehlgeschlagen: '.$e->getMessage());

                return self::FAILURE;
            }

            $this->info('MP4 mit Basis-Metadaten erzeugt: '.$embeddedPath);
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $metadataArgs
     */
    private function embedWithFfmpeg(string $ffmpeg, string $input, string $output, array $metadataArgs): void
    {
        $cmd = array_merge(
            [$ffmpeg, '-y', '-i', $input],
            $metadataArgs,
            ['-map', '0', '-c', 'copy', $output]
        );

        $process = new Process($cmd);
        $process->setTimeout(180);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }
}
