<?php

namespace App\Services\Publication;

use App\Models\MediaPublicationFinding;
use App\Models\NewsItemMedia;
use App\Services\MediaStorage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use ZipArchive;

class PublicationFindingEvidenceDossierService
{
    private ?string $lastYtDlpUserMessage = null;

    public const STATUS_PENDING = 'pending';

    public const STATUS_BUILDING = 'building';

    public const STATUS_READY = 'ready';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_FAILED = 'failed';

    public function __construct(
        private readonly MediaStorage $mediaStorage,
        private readonly PublicationFindingManualEvidenceService $manualEvidence,
    ) {}

    public function build(MediaPublicationFinding $finding): void
    {
        $finding->update([
            'evidence_dossier_status' => self::STATUS_BUILDING,
            'evidence_dossier_manifest' => null,
            'evidence_dossier_built_at' => null,
        ]);

        $workDir = storage_path('app/temp/evidence-dossier/'.$finding->id.'-'.Str::uuid());
        File::ensureDirectoryExists($workDir);

        $manifest = [
            'finding_id' => $finding->id,
            'built_at' => now()->toIso8601String(),
            'found_at' => $finding->found_at?->toIso8601String(),
            'url' => $finding->url,
            'kind' => $finding->kind,
            'checklist' => [],
            'files' => [],
            'warnings' => [],
        ];

        try {
            $this->writeFeststellung($workDir, $finding, $manifest);
            $this->writeAuthorityHandoverNotice($workDir, $finding, $manifest);
            $this->writeFundUrl($workDir, $finding, $manifest);
            $this->copyOriginalPhotos($workDir, $finding, $manifest);
            $this->copyOwnVideos($workDir, $finding, $manifest);
            $this->collectYoutubeEvidence($workDir, $finding, $manifest);
            $this->runOptionalPlaywrightScreenshots($workDir, $finding, $manifest);
            $this->copyManualEvidenceFiles($workDir, $finding, $manifest);
            $this->sanitizeWarnings($finding, $manifest);
            $this->persistArchiveToStorage($finding, $workDir, $manifest);

            $zipLocal = $workDir.'/Beweismittelmappe.zip';
            if (! $this->createZip($workDir, $zipLocal, $manifest)) {
                throw new \RuntimeException('ZIP-Erstellung fehlgeschlagen.');
            }

            $storagePath = trim((string) config('publication_evidence.storage_directory'), '/')
                .'/'.$finding->id.'/Beweismittelmappe-'.now()->format('Ymd-His').'.zip';

            if (! $this->mediaStorage->putFromLocalFile($storagePath, $zipLocal, [
                'visibility' => 'private',
                'ContentType' => 'application/zip',
            ])) {
                throw new \RuntimeException('Upload der Beweismittelmappe fehlgeschlagen.');
            }

            $status = $this->resolveStatus($manifest);
            $finding->update([
                'evidence_dossier_status' => $status,
                'evidence_dossier_path' => $storagePath,
                'evidence_dossier_manifest' => $manifest,
                'evidence_dossier_built_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('publication_evidence.build_failed', [
                'finding_id' => $finding->id,
                'error' => $e->getMessage(),
            ]);

            $manifest['error'] = $e->getMessage();
            $finding->update([
                'evidence_dossier_status' => self::STATUS_FAILED,
                'evidence_dossier_manifest' => $manifest,
            ]);

            throw $e;
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function writeFeststellung(string $workDir, MediaPublicationFinding $finding, array &$manifest): void
    {
        $ts = $finding->found_at ?? now();
        $content = "Datum und Uhrzeit der Feststellung\n"
            .$ts->timezone(config('app.timezone'))->format('d.m.Y H:i:s T')."\n"
            ."Fundstellen-ID: {$finding->id}\n";

        $name = '00-feststellung-datum-uhrzeit.txt';
        file_put_contents($workDir.'/'.$name, $content);
        $manifest['files'][] = $name;
        $manifest['checklist']['datum_uhrzeit'] = true;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function writeAuthorityHandoverNotice(string $workDir, MediaPublicationFinding $finding, array &$manifest): void
    {
        $content = "Hinweise zur Beweismittelübergabe (Polizei / Staatsanwaltschaft)\n\n"
            ."Anbieter: ".config('app.name', 'EKN Media')."\n"
            ."Fundstellen-ID: {$finding->id}\n"
            ."Erstellt am: ".now()->timezone(config('app.timezone'))->format('d.m.Y H:i:s T')."\n\n"
            ."Diese ZIP wurde zur Beweissicherung zusammengestellt. Ein gesonderter Behördenzugang kann\n"
            ."über ein zeitlich begrenztes Portal mit Protokollierung bereitgestellt werden.\n"
            ."Dateiliste und Checkliste siehe README.txt in diesem Archiv.\n";

        $name = '00-hinweis-behoerdenuebergabe.txt';
        file_put_contents($workDir.'/'.$name, $content);
        $manifest['files'][] = $name;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function writeFundUrl(string $workDir, MediaPublicationFinding $finding, array &$manifest): void
    {
        $name = '01-fund-url.txt';
        file_put_contents($workDir.'/'.$name, (string) $finding->url);
        $manifest['files'][] = $name;
        $manifest['checklist']['fund_url'] = true;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function copyOriginalPhotos(string $workDir, MediaPublicationFinding $finding, array &$manifest): void
    {
        $finding->loadMissing('mediaItems');
        $mediaItems = $finding->mediaItems->isNotEmpty()
            ? $finding->mediaItems
            : ($finding->news_item_media_id
                ? NewsItemMedia::query()->whereKey($finding->news_item_media_id)->get()
                : collect());

        if ($mediaItems->isEmpty()) {
            $manifest['checklist']['originalfoto_s3'] = false;
            $manifest['warnings'][] = 'Kein Bild verknüpft – Originalfoto fehlt.';

            return;
        }

        $copied = 0;
        $manifest['original_s3_paths'] = [];

        foreach ($mediaItems as $index => $media) {
            if (! $media->isImage()) {
                continue;
            }

            $path = (string) ($media->path ?? '');
            if ($path === '') {
                continue;
            }

            $resolved = $this->mediaStorage->resolveReadableLocalPath($path);
            if ($resolved === null) {
                $manifest['warnings'][] = 'Originalfoto #'.$media->id.' nicht von S3 lesbar.';

                continue;
            }

            $ext = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
            $name = sprintf('02-originalfoto-s3-%d.%s', $media->id, $ext);
            copy($resolved['path'], $workDir.'/'.$name);
            $this->mediaStorage->cleanupResolvedPath($resolved);

            $manifest['files'][] = $name;
            $manifest['original_s3_paths'][] = $path;
            $copied++;
        }

        $manifest['checklist']['originalfoto_s3'] = $copied > 0;
        if ($copied === 0 && $mediaItems->contains(fn (NewsItemMedia $m): bool => $m->isImage())) {
            $manifest['warnings'][] = 'Keines der verknüpften Bilder konnte als Originalfoto kopiert werden.';
        }
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function copyOwnVideos(string $workDir, MediaPublicationFinding $finding, array &$manifest): void
    {
        $finding->loadMissing('mediaItems');
        $videos = $finding->mediaItems->filter(fn (NewsItemMedia $m): bool => $m->isVideo());

        if ($videos->isEmpty() && $finding->news_item_id) {
            $videos = NewsItemMedia::query()
                ->where('news_item_id', $finding->news_item_id)
                ->where('type', 'video')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        }

        if ($videos->isEmpty()) {
            $manifest['checklist']['eigenes_video_s3'] = false;

            return;
        }

        $maxBytes = (int) config('publication_evidence.max_own_video_bytes', 838860800);
        $copied = 0;
        $manifest['own_video_s3_paths'] = [];

        foreach ($videos as $media) {
            $path = (string) ($media->path ?? '');
            if ($path === '') {
                continue;
            }

            $size = $this->mediaStorage->size($path);
            if ($maxBytes > 0 && $size > $maxBytes) {
                $manifest['warnings'][] = 'Eigenes Video #'.$media->id.' zu groß für ZIP ('.number_format($size / 1048576, 0).' MB).';

                continue;
            }

            $resolved = $this->mediaStorage->resolveReadableLocalPath($path);
            if ($resolved === null) {
                $manifest['warnings'][] = 'Eigenes Video #'.$media->id.' nicht von S3 lesbar.';

                continue;
            }

            $ext = pathinfo($path, PATHINFO_EXTENSION) ?: 'mp4';
            $name = sprintf('03-eigenes-video-s3-%d.%s', $media->id, $ext);
            copy($resolved['path'], $workDir.'/'.$name);
            $this->mediaStorage->cleanupResolvedPath($resolved);

            $manifest['files'][] = $name;
            $manifest['own_video_s3_paths'][] = $path;
            $copied++;
        }

        $manifest['checklist']['eigenes_video_s3'] = $copied > 0;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function collectYoutubeEvidence(string $workDir, MediaPublicationFinding $finding, array &$manifest): void
    {
        $videoId = $this->extractYoutubeVideoId((string) $finding->url);
        if ($videoId === null) {
            $manifest['checklist']['youtube_url'] = false;
            $manifest['checklist']['youtube_video_lokal'] = false;
            $manifest['checklist']['screenshot_video'] = false;
            $manifest['checklist']['screenshot_einblendung'] = false;
            $manifest['checklist']['screenshot_kanal'] = false;

            return;
        }

        $manifest['checklist']['youtube_url'] = true;
        $urlName = '09-youtube-url.txt';
        file_put_contents($workDir.'/'.$urlName, (string) $finding->url);
        $manifest['files'][] = $urlName;

        $manual = (array) ($finding->evidence_manual_files ?? []);
        $hasManualVideo = isset($manual['youtube_video']);

        $videoFile = $workDir.'/10-youtube-video.mp4';
        $downloaded = $this->downloadYoutubeVideo((string) $finding->url, $videoFile);
        $manifest['checklist']['youtube_video_lokal'] = $downloaded;
        if ($downloaded) {
            $manifest['files'][] = '10-youtube-video.mp4';
            $this->writeYoutubeInfoJson($workDir, (string) $finding->url, $manifest);
        } elseif (! $hasManualVideo) {
            $manifest['warnings'][] = $this->lastYtDlpUserMessage
                ?? 'YouTube-Video konnte nicht lokal gespeichert werden (yt-dlp prüfen: YT_DLP_PATH).';
        }

        $screenshotSeconds = array_values(array_unique(array_filter(array_merge(
            [(float) config('publication_evidence.video_screenshot_seconds', 3)],
            (array) config('publication_evidence.video_screenshot_extra_seconds', [])
        ), static fn (float $s): bool => $s >= 0)));

        $screenshotOk = false;
        foreach ($screenshotSeconds as $index => $seconds) {
            $screenshotPath = $index === 0
                ? $workDir.'/11-screenshot-video.jpg'
                : $workDir.'/11-screenshot-video-'.str_replace('.', '-', (string) $seconds).'s.jpg';

            $frameOk = false;
            if ($downloaded && is_file($videoFile)) {
                $frameOk = $this->extractVideoFrame($videoFile, $screenshotPath, $seconds);
            }
            if (! $frameOk && $index === 0) {
                $frameOk = $this->downloadYoutubeMaxresThumbnail($videoId, $screenshotPath);
                if ($frameOk && ! $hasManualVideo && ! $downloaded) {
                    $manifest['warnings'][] = 'Video-Screenshot aus YouTube-Vorschaubild, nicht aus Abspielstand.';
                }
            }
            if ($frameOk) {
                $screenshotOk = true;
                $manifest['files'][] = basename($screenshotPath);
            }
        }

        $manifest['checklist']['screenshot_video'] = $screenshotOk;

        $manifest['checklist']['screenshot_einblendung'] = false;
        $manifest['checklist']['screenshot_kanal'] = false;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function copyManualEvidenceFiles(string $workDir, MediaPublicationFinding $finding, array &$manifest): void
    {
        $manual = (array) ($finding->evidence_manual_files ?? []);
        if ($manual === []) {
            return;
        }

        foreach ($manual as $type => $meta) {
            if (! is_array($meta)) {
                continue;
            }

            $path = (string) ($meta['path'] ?? '');
            if ($path === '') {
                continue;
            }

            $resolved = $this->mediaStorage->resolveReadableLocalPath($path);
            if ($resolved === null) {
                $manifest['warnings'][] = 'Manuelles Beweismittel „'.$type.'“ nicht lesbar.';

                continue;
            }

            $basename = $this->manualEvidence->zipBasename((string) $type);
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            if ($ext !== '' && ! str_ends_with($basename, '.'.$ext)) {
                $basename = pathinfo($basename, PATHINFO_FILENAME).'.'.$ext;
            }

            copy($resolved['path'], $workDir.'/'.$basename);
            $this->mediaStorage->cleanupResolvedPath($resolved);

            $manifest['files'][] = $basename;
            $checkKey = $this->manualEvidence->checklistKey((string) $type);
            $manifest['checklist'][$checkKey] = true;
            $manifest['manual_evidence'][$type] = $basename;
        }
    }

    /**
     * Dauerhafte Ablage einzelner Beweisdateien auf S3 (zusätzlich zur ZIP).
     *
     * @param  array<string, mixed>  $manifest
     */
    private function persistArchiveToStorage(MediaPublicationFinding $finding, string $workDir, array &$manifest): void
    {
        if (! (bool) config('publication_evidence.archive_youtube_to_storage', true)) {
            return;
        }

        $base = trim((string) config('publication_evidence.storage_directory'), '/').'/'.$finding->id.'/archive';
        $manifest['archive_storage'] = [];

        $candidates = [
            '10-youtube-video.mp4' => $base.'/youtube/video.mp4',
            '12-youtube-metadaten.json' => $base.'/youtube/metadaten.json',
            '11-screenshot-video.jpg' => $base.'/youtube/screenshot-video.jpg',
            '06-screenshot-einblendung.png' => $base.'/youtube/screenshot-einblendung.png',
            '07-screenshot-kanal.png' => $base.'/youtube/screenshot-kanal.png',
        ];

        foreach ($candidates as $localName => $storagePath) {
            $local = $workDir.'/'.$localName;
            if (! is_file($local)) {
                continue;
            }

            $mime = match (pathinfo($localName, PATHINFO_EXTENSION)) {
                'mp4' => 'video/mp4',
                'json' => 'application/json',
                'png' => 'image/png',
                default => 'image/jpeg',
            };

            if ($this->mediaStorage->putFromLocalFile($storagePath, $local, [
                'visibility' => 'private',
                'ContentType' => $mime,
            ])) {
                $manifest['archive_storage'][$localName] = $storagePath;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function writeYoutubeInfoJson(string $workDir, string $url, array &$manifest): void
    {
        $ytDlp = (string) config('publication_evidence.yt_dlp_path', 'yt-dlp');
        $jsonPath = $workDir.'/12-youtube-metadaten.json';

        $process = new Process([
            $ytDlp,
            '--no-playlist',
            '--skip-download',
            '--write-info-json',
            '--output', $workDir.'/yt-meta',
            $url,
        ]);
        $process->setTimeout(120);
        $process->run();

        $written = false;
        foreach (glob($workDir.'/yt-meta*.info.json') ?: [] as $infoFile) {
            rename($infoFile, $jsonPath);
            $written = true;
            break;
        }

        if (! $written) {
            $meta = [
                'url' => $url,
                'archived_at' => now()->toIso8601String(),
                'note' => 'Metadaten per yt-dlp nicht abrufbar',
            ];
            file_put_contents($jsonPath, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        $manifest['files'][] = '12-youtube-metadaten.json';
        $manifest['checklist']['youtube_metadaten'] = true;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function runOptionalPlaywrightScreenshots(string $workDir, MediaPublicationFinding $finding, array &$manifest): void
    {
        $manual = (array) ($finding->evidence_manual_files ?? []);
        $needsEinblendung = ! isset($manual['screenshot_einblendung']);
        $needsKanal = ! isset($manual['screenshot_kanal']);

        if (! $needsEinblendung && ! $needsKanal) {
            return;
        }

        $script = config('publication_evidence.playwright_script');
        if (! is_string($script) || $script === '') {
            if ($needsEinblendung || $needsKanal) {
                $manifest['warnings'][] = 'Screenshots Einblendung/Kanal: Playwright-Skript nicht konfiguriert (PUBLICATION_EVIDENCE_PLAYWRIGHT_SCRIPT).';
            }

            return;
        }

        $scriptPath = base_path($script);
        if (! is_file($scriptPath)) {
            if ($needsEinblendung || $needsKanal) {
                $manifest['warnings'][] = 'Playwright-Skript nicht gefunden: '.$script;
            }

            return;
        }

        $overlayOut = $workDir.'/06-screenshot-einblendung.png';
        $channelOut = $workDir.'/07-screenshot-kanal.png';

        $process = new Process([
            'node',
            $scriptPath,
            '--url='.(string) $finding->url,
            '--overlay='.$overlayOut,
            '--channel='.$channelOut,
        ]);
        $process->setTimeout(120);
        $process->run();

        if ($process->isSuccessful() && is_file($overlayOut)) {
            $manifest['files'][] = '06-screenshot-einblendung.png';
            $manifest['checklist']['screenshot_einblendung'] = true;
        }

        if ($process->isSuccessful() && is_file($channelOut)) {
            $manifest['files'][] = '07-screenshot-kanal.png';
            $manifest['checklist']['screenshot_kanal'] = true;
        }

        if ($needsEinblendung && ! ($manifest['checklist']['screenshot_einblendung'] ?? false)) {
            $manifest['warnings'][] = 'Screenshot Einblendung fehlgeschlagen: '.$process->getErrorOutput();
        }
        if ($needsKanal && ! ($manifest['checklist']['screenshot_kanal'] ?? false)) {
            $manifest['warnings'][] = 'Screenshot Kanal fehlgeschlagen.';
        }
    }

    /**
     * Entfernt Warnungen, die durch manuelle Uploads oder erfolgreiche Checklisten-Punkte erledigt sind.
     *
     * @param  array<string, mixed>  $manifest
     */
    private function sanitizeWarnings(MediaPublicationFinding $finding, array &$manifest): void
    {
        $checklist = (array) ($manifest['checklist'] ?? []);
        $manual = (array) ($finding->evidence_manual_files ?? []);

        $filtered = [];
        foreach ((array) ($manifest['warnings'] ?? []) as $warning) {
            if (! is_string($warning) || $warning === '') {
                continue;
            }

            if (($checklist['screenshot_kanal'] ?? false) && $this->warningRelatesTo($warning, ['kanal', 'playwright'])) {
                continue;
            }

            if (($checklist['screenshot_einblendung'] ?? false) && $this->warningRelatesTo($warning, ['einblendung', 'playwright'])) {
                continue;
            }

            if (($checklist['youtube_video_lokal'] ?? false) && $this->warningRelatesTo($warning, ['youtube:', 'yt-dlp', 'nicht lokal'])) {
                continue;
            }

            if (isset($manual['youtube_video']) && $this->warningRelatesTo($warning, ['youtube:', 'unavailable', 'copyright', 'vorschaubild', 'nicht lokal', 'yt-dlp'])) {
                continue;
            }

            if (($checklist['screenshot_video'] ?? false) && $this->warningRelatesTo($warning, ['vorschaubild'])) {
                continue;
            }

            $filtered[] = $warning;
        }

        $manifest['warnings'] = array_values(array_unique($filtered));

        if (isset($manual['youtube_video']) && ! ($checklist['youtube_video_lokal'] ?? false)) {
            $manifest['notes'][] = 'YouTube-Automatik: Video bei YouTube nicht verfügbar; manuell hochgeladenes Video wird verwendet.';
        }
    }

    /**
     * @param  list<string>  $keywords
     */
    private function warningRelatesTo(string $warning, array $keywords): bool
    {
        $lower = strtolower($warning);
        foreach ($keywords as $keyword) {
            if (str_contains($lower, strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function resolveStatus(array $manifest): string
    {
        $checklist = (array) ($manifest['checklist'] ?? []);
        $required = [
            'datum_uhrzeit',
            'fund_url',
        ];
        foreach ($required as $key) {
            if (empty($checklist[$key])) {
                return self::STATUS_FAILED;
            }
        }

        $optionalKeys = [
            'originalfoto_s3',
            'eigenes_video_s3',
            'youtube_url',
            'youtube_video_lokal',
            'youtube_metadaten',
            'screenshot_video',
            'screenshot_einblendung',
            'screenshot_kanal',
        ];
        $allOptional = true;
        foreach ($optionalKeys as $key) {
            if (array_key_exists($key, $checklist) && $checklist[$key] !== true) {
                $allOptional = false;
                break;
            }
        }

        return $allOptional ? self::STATUS_READY : self::STATUS_PARTIAL;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function createZip(string $workDir, string $zipPath, array &$manifest): bool
    {
        $readme = "Beweismittelmappe – Fundstelle #".($manifest['finding_id'] ?? '?')."\n\n";
        foreach ((array) ($manifest['checklist'] ?? []) as $item => $ok) {
            $readme .= ($ok ? '✅' : '❌').' '.$item."\n";
        }
        foreach ((array) ($manifest['warnings'] ?? []) as $warning) {
            $readme .= "\nWarnung: ".$warning;
        }
        foreach ((array) ($manifest['notes'] ?? []) as $note) {
            $readme .= "\nInfo: ".$note;
        }
        file_put_contents($workDir.'/README.txt', $readme);
        $manifest['files'][] = 'README.txt';

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        foreach ((array) ($manifest['files'] ?? []) as $basename) {
            $abs = $workDir.'/'.$basename;
            if (is_file($abs)) {
                $zip->addFile($abs, $basename);
            }
        }

        $zip->close();

        return is_file($zipPath);
    }

    private function extractYoutubeVideoId(string $url): ?string
    {
        if (preg_match('~(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/shorts/)([a-zA-Z0-9_-]{11})~', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    private function downloadYoutubeVideo(string $url, string $targetFile): bool
    {
        $this->lastYtDlpUserMessage = null;
        $ytDlp = (string) config('publication_evidence.yt_dlp_path', 'yt-dlp');
        $dir = dirname($targetFile);
        File::ensureDirectoryExists($dir);

        $command = array_merge([
            $ytDlp,
            '--no-playlist',
            '--no-warnings',
            '-f', 'bv*[ext=mp4]+ba[ext=m4a]/bv*+ba/b[ext=mp4]/b',
            '--merge-output-format', 'mp4',
            '--output', $dir.'/yt-dl-%(id)s.%(ext)s',
        ], (array) config('publication_evidence.yt_dlp_extra_args', []), [$url]);

        $process = new Process($command);
        $process->setTimeout(900);
        $process->run();

        $candidates = array_merge(
            glob($dir.'/yt-dl-*.*') ?: [],
            glob($dir.'/*.mp4') ?: []
        );

        foreach ($candidates as $file) {
            if (! is_file($file) || filesize($file) < 1024) {
                continue;
            }
            if (realpath($file) === realpath($targetFile)) {
                return true;
            }
            @unlink($targetFile);
            rename($file, $targetFile);

            return true;
        }

        $stderr = $process->getErrorOutput();
        $this->lastYtDlpUserMessage = $this->parseYtDlpErrorMessage($stderr);

        Log::warning('publication_evidence.yt_dlp_failed', [
            'url' => $url,
            'exit' => $process->getExitCode(),
            'stderr' => $stderr,
        ]);

        return false;
    }

    private function parseYtDlpErrorMessage(string $stderr): string
    {
        if (preg_match('/ERROR:\s*\[youtube\][^\n]*/', $stderr, $m)) {
            return trim(str_replace('ERROR: [youtube]', 'YouTube:', $m[0]));
        }

        if (str_contains($stderr, 'not found') || str_contains($stderr, 'No such file')) {
            return 'yt-dlp nicht gefunden – YT_DLP_PATH in .env prüfen.';
        }

        return 'YouTube-Video konnte nicht heruntergeladen werden. Ggf. bereits gelöscht/gesperrt – Wayback oder manuellen Export anfügen.';
    }

    private function extractVideoFrame(string $videoPath, string $imagePath, ?float $seconds = null): bool
    {
        $seconds ??= (float) config('publication_evidence.video_screenshot_seconds', 3);
        $ffmpeg = (string) config('publication_evidence.ffmpeg_path', 'ffmpeg');
        $process = new Process([
            $ffmpeg,
            '-y',
            '-ss', (string) max(0, $seconds),
            '-i', $videoPath,
            '-frames:v', '1',
            '-q:v', '2',
            $imagePath,
        ]);
        $process->setTimeout(60);
        $process->run();

        return $process->isSuccessful() && is_file($imagePath);
    }

    private function downloadYoutubeMaxresThumbnail(string $videoId, string $imagePath): bool
    {
        foreach (['hqdefault', 'sddefault', 'maxresdefault'] as $quality) {
            $url = 'https://img.youtube.com/vi/'.$videoId.'/'.$quality.'.jpg';
            try {
                $response = Http::timeout(20)->get($url);
                if (! $response->successful()) {
                    continue;
                }
                $data = $response->body();
                if (strlen($data) > 1000) {
                    file_put_contents($imagePath, $data);

                    return true;
                }
            } catch (\Throwable $e) {
                Log::warning('publication_evidence.thumbnail_failed', [
                    'video_id' => $videoId,
                    'quality' => $quality,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return false;
    }
}
