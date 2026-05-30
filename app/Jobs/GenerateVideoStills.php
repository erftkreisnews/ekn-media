<?php

namespace App\Jobs;

use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use App\Services\ImageMetadataWriter;
use App\Services\MediaQualityCheck;
use App\Services\MediaStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Process;

#[DeleteWhenMissingModels]
class GenerateVideoStills implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    /** Verhindert parallele Doppel-Jobs für dasselbe Quellvideo (3 Queue-Worker). */
    public int $uniqueFor = 3600;

    public function __construct(
        protected NewsItemMedia $videoMedia,
        protected bool $replaceExistingStills = false,
    ) {
        $this->timeout = max(120, (int) config('media.video_stills.queue_timeout_seconds', 900));
    }

    public function uniqueId(): string
    {
        return 'video-stills-'.$this->videoMedia->id.($this->replaceExistingStills ? '-replace' : '');
    }

    public function handle(MediaStorage $mediaStorage): void
    {
        if (! config('media.video_stills.enabled', true)) {
            return;
        }

        $this->videoMedia->refresh();
        if (! $this->videoMedia->isVideo()) {
            return;
        }

        $newsItem = $this->videoMedia->newsItem;
        if (! $newsItem) {
            return;
        }

        if ($this->replaceExistingStills) {
            $this->deleteExistingStillsForSourceVideo($newsItem, $mediaStorage);
        } elseif ($this->countExistingStillsForSourceVideo($newsItem) > 0
            && config('media.video_stills.skip_if_existing_for_source_video', true)) {
            Log::info('GenerateVideoStills: skipped, stills already exist for source video', [
                'media_id' => $this->videoMedia->id,
                'news_item_id' => $newsItem->id,
                'existing' => $this->countExistingStillsForSourceVideo($newsItem),
            ]);

            return;
        }

        $autoRedactionForNewsItem = $this->shouldAutoRedactForNewsItem($newsItem);
        $forceSaveMin = max(0, (int) config('media.video_stills.force_save_min_count', 1));

        $videoPath = $this->videoMedia->path;
        if (! is_string($videoPath) || $videoPath === '') {
            return;
        }

        $resolved = $mediaStorage->resolveReadableLocalPath($videoPath);
        $absVideo = $resolved['path'] ?? null;
        if (! is_string($absVideo) || ! is_file($absVideo) || ! is_readable($absVideo)) {
            Log::warning('GenerateVideoStills: video not readable', [
                'media_id' => $this->videoMedia->id,
                'path' => $videoPath,
            ]);
            throw new \RuntimeException('Standbilder: Videodatei ist nicht lesbar (Pfad/Storage prüfen).');
        }

        $ffmpegPath = $this->resolveFfmpegBinary((string) config('media.ffmpeg_path', 'ffmpeg'));

        $longEdge = max(1500, (int) config('media.video_stills.long_edge', 2560));
        $samplingSeconds = max(1, (int) config('media.video_stills.sampling_seconds', 1));
        $candidateLimit = max(20, (int) config('media.video_stills.candidate_limit', 40));
        $durationSeconds = $this->resolveVideoDurationSeconds($absVideo, $ffmpegPath);
        $this->maybePersistVideoDuration($durationSeconds);
        // Ohne bekannte Gesamtdauer bleibt extractIntervalSeconds ≈ sampling_seconds → fps≈1/sampling und -frames:v K
        // liefert nur K·sampling Sekunden vom Anfang (z. B. 80s bei 80 Kandidaten und 1s). Dauer daher aus Datei messen, nicht nur aus DB.
        if ($durationSeconds === null || $durationSeconds <= 0) {
            Log::warning('GenerateVideoStills: duration unknown — candidates only from an early segment', [
                'media_id' => $this->videoMedia->id,
                'candidate_limit' => $candidateLimit,
                'sampling_seconds' => $samplingSeconds,
            ]);
        }
        $extractIntervalSeconds = $this->computeExtractIntervalSeconds($samplingSeconds, $candidateLimit, $durationSeconds);
        $targetCount = max(1, (int) config('media.video_stills.count', 12));
        $minGapSeconds = max(0, (int) config('media.video_stills.min_seconds_between', 2));
        $minSharpness = max(0.0, (float) config('media.video_stills.min_sharpness', 12.0));
        $maxBytes = max(256 * 1024, (int) config('media.video_stills.max_bytes', 2 * 1024 * 1024));
        $phashDistanceThreshold = max(0, (int) config('media.video_stills.duplicate_phash_distance_max', 4));
        $duplicateFrameDiffMax = max(0.0, (float) config('media.video_stills.duplicate_frame_diff_max', 2.5));
        $maxMotionDelta = max(0.0, (float) config('media.video_stills.max_motion_delta', 18.0));
        $motionPenaltyWeight = max(0.0, (float) config('media.video_stills.motion_penalty_weight', 0.35));
        $lookExpr = $this->buildStillLookFilterExpression();

        $tmpDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'video_stills_'.uniqid('', true);
        if (! @mkdir($tmpDir, 0775, true) && ! is_dir($tmpDir)) {
            $mediaStorage->cleanupResolvedPath($resolved);
            throw new \RuntimeException('Standbilder: Temporäres Verzeichnis konnte nicht angelegt werden.');
        }

        try {
            $chunkMax = max(4, (int) config('media.video_stills.extract_chunk_max_frames', 12));
            $extractTimeout = max(60, (int) config('media.video_stills.extract_timeout_seconds', 600));
            $ffmpegThreads = max(1, min(16, (int) config('media.video_stills.ffmpeg_threads', 1)));

            $extractResult = $this->runFfmpegCandidateExtractWithOomRetry(
                $ffmpegPath,
                $absVideo,
                $tmpDir,
                $samplingSeconds,
                $longEdge,
                $lookExpr,
                $candidateLimit,
                $durationSeconds,
                $chunkMax,
                $extractTimeout,
                $ffmpegThreads
            );
            $extractErr = $extractResult['error'];
            $candidateLimit = $extractResult['candidate_limit'];
            $chunkMax = $extractResult['chunk_max'];
            $extractIntervalSeconds = $extractResult['extract_interval_seconds'];
            $scaleExpr = $extractResult['scale_expr'];
            $lookExpr = $extractResult['look_expr'];
            $ffmpegThreads = $extractResult['ffmpeg_threads'];

            if ($extractErr !== null) {
                Log::error('GenerateVideoStills: FFmpeg extraction failed', [
                    'media_id' => $this->videoMedia->id,
                    'detail' => $extractErr,
                ]);
                throw new \RuntimeException('Standbilder: FFmpeg-Extraktion fehlgeschlagen. '.$extractErr);
            }

            $files = glob($tmpDir.DIRECTORY_SEPARATOR.'cand_*.jpg') ?: [];
            sort($files);
            if ($files === []) {
                throw new \RuntimeException('Standbilder: FFmpeg hat keine Roh-Kandidaten-JPEGs erzeugt.');
            }

            $scored = $this->buildScoredCandidates(
                $files,
                $extractIntervalSeconds,
                $minSharpness,
                $maxMotionDelta,
                $motionPenaltyWeight,
                $durationSeconds,
                $candidateLimit,
                $chunkMax
            );
            if ($scored === [] && config('media.video_stills.fallback_on_empty_scored', true)) {
                $relaxedMin = (float) config('media.video_stills.fallback_min_sharpness', 3.0);
                $relaxedMax = (float) config('media.video_stills.fallback_max_motion_delta', 56.0);
                Log::warning('GenerateVideoStills: no frames passed strict sharpness/motion filters, retrying relaxed', [
                    'media_id' => $this->videoMedia->id,
                    'strict_min_sharpness' => $minSharpness,
                    'strict_max_motion' => $maxMotionDelta,
                    'relaxed_min_sharpness' => $relaxedMin,
                    'relaxed_max_motion' => $relaxedMax,
                ]);
                $scored = $this->buildScoredCandidates(
                    $files,
                    $extractIntervalSeconds,
                    $relaxedMin,
                    $relaxedMax,
                    $motionPenaltyWeight,
                    $durationSeconds,
                    $candidateLimit,
                    $chunkMax
                );
            }

            usort($scored, fn (array $a, array $b): int => $b['score'] <=> $a['score']);
            if ($scored === []) {
                throw new \RuntimeException(
                    'Standbilder: Kein Kandidat hat Schärfe-/Bewegungsfilter passiert (auch nicht im toleranteren Fallback). '
                    .'In .env z. B. VIDEO_STILLS_MIN_SHARPNESS senken oder VIDEO_STILLS_MAX_MOTION_DELTA erhöhen.'
                );
            }

            // Zuerst optisch verschiedene Motive (score-sortiert), dann zeitlich verteilt — verhindert 10× dasselbe Bild.
            $distinctPool = $this->filterVisuallyDistinctCandidates(
                $scored,
                $phashDistanceThreshold,
                $duplicateFrameDiffMax
            );
            $candidatePool = $distinctPool !== [] ? $distinctPool : $scored;

            $selected = $this->selectCandidatesWithMinGap(
                $candidatePool,
                $targetCount,
                $minGapSeconds,
                $phashDistanceThreshold,
                $duplicateFrameDiffMax
            );

            if ($selected === []) {
                throw new \RuntimeException('Standbilder: Nach Abstands-/Duplikatfilter blieb kein Bild übrig.');
            }

            $existingDuplicateRefs = $this->buildExistingImageDuplicateRefs($newsItem, $mediaStorage);

            $sortOrder = (int) ($newsItem->media()->max('sort_order') ?? 0);
            $credit = (string) config('newsdesk.iptc_credit', 'Erftkreis News');
            $copyright = (string) config('newsdesk.iptc_copyright', '© Erftkreis News. Alle Rechte vorbehalten.');
            $watermarkPath = public_path('images/erftkreis-news-logo.png');
            $baseStem = preg_replace('/[^a-z0-9]+/i', '-', (string) ($newsItem->slug ?: 'news'));
            $baseStem = trim((string) $baseStem, '-');
            if ($baseStem === '') {
                $baseStem = 'news';
            }
            $datePrefix = now()->format('Ymd');

            $savedThisRunRefs = [];
            $savedOrdinal = 0;
            $skippedAsManualDuplicate = 0;
            $skippedAsRunDuplicate = 0;
            $failedUploadCount = 0;
            $exactFrameExtract = (bool) config('media.video_stills.exact_frame_extract', true);

            foreach ($selected as $item) {
                if ($this->persistVideoStillCandidate(
                    $newsItem,
                    $mediaStorage,
                    $item,
                    $absVideo,
                    $tmpDir,
                    $savedOrdinal,
                    $sortOrder,
                    $baseStem,
                    $datePrefix,
                    $credit,
                    $copyright,
                    $watermarkPath,
                    $maxBytes,
                    $existingDuplicateRefs,
                    $savedThisRunRefs,
                    $phashDistanceThreshold,
                    $duplicateFrameDiffMax,
                    $autoRedactionForNewsItem,
                    $exactFrameExtract,
                    $ffmpegPath,
                    $scaleExpr,
                    $lookExpr,
                    $extractTimeout,
                    $ffmpegThreads,
                    $skippedAsManualDuplicate,
                    $skippedAsRunDuplicate,
                    $failedUploadCount,
                    ignoreManualDuplicateCheck: false
                )) {
                    $savedOrdinal++;
                }
            }

            if ($savedOrdinal < $forceSaveMin && count($selected) > 0) {
                Log::info('GenerateVideoStills: saving minimum stills despite manual press-photo duplicates', [
                    'video_media_id' => $this->videoMedia->id,
                    'force_save_min' => $forceSaveMin,
                    'saved_so_far' => $savedOrdinal,
                ]);
                foreach ($selected as $item) {
                    if ($savedOrdinal >= $forceSaveMin) {
                        break;
                    }
                    if ($this->persistVideoStillCandidate(
                        $newsItem,
                        $mediaStorage,
                        $item,
                        $absVideo,
                        $tmpDir,
                        $savedOrdinal,
                        $sortOrder,
                        $baseStem,
                        $datePrefix,
                        $credit,
                        $copyright,
                        $watermarkPath,
                        $maxBytes,
                        $existingDuplicateRefs,
                        $savedThisRunRefs,
                        $phashDistanceThreshold,
                        $duplicateFrameDiffMax,
                        $autoRedactionForNewsItem,
                        $exactFrameExtract,
                        $ffmpegPath,
                        $scaleExpr,
                        $lookExpr,
                        $extractTimeout,
                        $ffmpegThreads,
                        $skippedAsManualDuplicate,
                        $skippedAsRunDuplicate,
                        $failedUploadCount,
                        ignoreManualDuplicateCheck: true
                    )) {
                        $savedOrdinal++;
                    }
                }
            }

            if ($savedOrdinal === 0 && count($selected) > 0) {
                Log::warning('GenerateVideoStills: no images persisted', [
                    'news_item_id' => $newsItem->id,
                    'video_media_id' => $this->videoMedia->id,
                    'candidates_selected' => count($selected),
                    'skipped_manual_duplicate' => $skippedAsManualDuplicate,
                    'skipped_run_duplicate' => $skippedAsRunDuplicate,
                    'failed_upload' => $failedUploadCount,
                    'disk' => $mediaStorage->activeDiskName(),
                ]);
                if ($failedUploadCount > 0) {
                    throw new \RuntimeException(
                        'Standbilder: Upload auf Disk „'.$mediaStorage->activeDiskName().'“ fehlgeschlagen: '
                        .$failedUploadCount.' (S3/Credentials/Quota prüfen).'
                    );
                }

                return;
            }
        } finally {
            $this->cleanupTmpDirectory($tmpDir);
            if (isset($existingDuplicateRefs) && is_array($existingDuplicateRefs)) {
                foreach ($existingDuplicateRefs as $ref) {
                    if (is_array($ref['resolved'] ?? null)) {
                        $mediaStorage->cleanupResolvedPath($ref['resolved']);
                    }
                }
            }
            $mediaStorage->cleanupResolvedPath($resolved);
        }
    }

    /**
     * @return array{error: ?string, candidate_limit: int, chunk_max: int, extract_interval_seconds: float, scale_expr: string, look_expr: ?string, ffmpeg_threads: int}
     */
    private function runFfmpegCandidateExtractWithOomRetry(
        string $ffmpegPath,
        string $absVideo,
        string $tmpDir,
        int $samplingSeconds,
        int $longEdge,
        ?string $lookExpr,
        int $candidateLimit,
        ?float $durationSeconds,
        int $chunkMax,
        int $extractTimeout,
        int $ffmpegThreads,
    ): array {
        $scaleFlags = config('media.video_stills.apply_look_filters', false) ? 'lanczos' : 'bicubic';

        $profiles = [
            [
                'label' => 'standard',
                'long_edge' => $longEdge,
                'candidate_limit' => $candidateLimit,
                'chunk_max' => $chunkMax,
                'threads' => $ffmpegThreads,
                'look' => $lookExpr,
                'scale_flags' => $scaleFlags,
            ],
        ];

        if (config('media.video_stills.fallback_on_oom', true)) {
            $profiles[] = [
                'label' => 'oom_safe',
                'long_edge' => min($longEdge, max(960, (int) config('media.video_stills.oom_long_edge', 1920))),
                'candidate_limit' => min($candidateLimit, max(8, (int) config('media.video_stills.oom_candidate_limit', 16))),
                'chunk_max' => min($chunkMax, max(4, (int) config('media.video_stills.oom_chunk_max_frames', 8))),
                'threads' => 1,
                'look' => null,
                'scale_flags' => 'bilinear',
            ];
        }

        $lastErr = null;
        $lastProfile = $profiles[0];
        $lastInterval = $this->computeExtractIntervalSeconds($samplingSeconds, $candidateLimit, $durationSeconds);
        $lastScaleExpr = $this->buildStillScaleFilterExpression($longEdge, $scaleFlags);
        $lastLook = $lookExpr;
        $lastThreads = $ffmpegThreads;

        foreach ($profiles as $i => $profile) {
            if ($i > 0) {
                $this->cleanupTmpJpegCandidates($tmpDir);
                Log::warning('GenerateVideoStills: retrying candidate extract after memory failure', [
                    'media_id' => $this->videoMedia->id,
                    'profile' => $profile['label'],
                    'previous_error' => $lastErr,
                ]);
            }

            $limit = max(4, (int) $profile['candidate_limit']);
            $chunk = max(4, (int) $profile['chunk_max']);
            $interval = $this->computeExtractIntervalSeconds($samplingSeconds, $limit, $durationSeconds);
            $fpsExpr = 'fps='.sprintf('%.6F', max(0.02, 1.0 / $interval));
            $scaleExpr = $this->buildStillScaleFilterExpression((int) $profile['long_edge'], (string) $profile['scale_flags']);
            $threads = max(1, (int) $profile['threads']);

            $lastProfile = $profile;
            $lastInterval = $interval;
            $lastScaleExpr = $scaleExpr;
            $lastLook = $profile['look'];
            $lastThreads = $threads;

            $lastErr = $this->runFfmpegCandidateExtract(
                $ffmpegPath,
                $absVideo,
                $tmpDir,
                $fpsExpr,
                $scaleExpr,
                $profile['look'],
                $limit,
                $durationSeconds,
                $chunk,
                $extractTimeout,
                $threads,
            );

            if ($lastErr === null) {
                if ($i > 0) {
                    Log::info('GenerateVideoStills: oom_safe profile succeeded', [
                        'media_id' => $this->videoMedia->id,
                        'profile' => $profile['label'],
                        'candidate_limit' => $limit,
                        'chunk_max' => $chunk,
                    ]);
                }

                return [
                    'error' => null,
                    'candidate_limit' => $limit,
                    'chunk_max' => $chunk,
                    'extract_interval_seconds' => $interval,
                    'scale_expr' => $scaleExpr,
                    'look_expr' => $profile['look'],
                    'ffmpeg_threads' => $threads,
                ];
            }

            if ($i === 0 && ! $this->isFfmpegMemoryFailure($lastErr)) {
                break;
            }
        }

        return [
            'error' => $lastErr,
            'candidate_limit' => max(4, (int) ($lastProfile['candidate_limit'] ?? $candidateLimit)),
            'chunk_max' => max(4, (int) ($lastProfile['chunk_max'] ?? $chunkMax)),
            'extract_interval_seconds' => $lastInterval,
            'scale_expr' => $lastScaleExpr,
            'look_expr' => $lastLook,
            'ffmpeg_threads' => $lastThreads,
        ];
    }

    private function computeExtractIntervalSeconds(int $samplingSeconds, int $candidateLimit, ?float $durationSeconds): float
    {
        $extractIntervalSeconds = (float) max(1, $samplingSeconds);
        if ($durationSeconds !== null && $durationSeconds > 0) {
            $spreadInterval = $durationSeconds / (float) max(1, $candidateLimit);
            $extractIntervalSeconds = max($extractIntervalSeconds, $spreadInterval);
        }

        return max($extractIntervalSeconds, 0.04);
    }

    private function isFfmpegMemoryFailure(?string $err): bool
    {
        if ($err === null || trim($err) === '') {
            return false;
        }

        $hay = strtolower($err);
        if (preg_match('/signal\s*9\b/', $hay) === 1) {
            return true;
        }

        return str_contains($hay, 'killed')
            || str_contains($hay, 'oom')
            || str_contains($hay, 'out of memory')
            || str_contains($hay, 'cannot allocate memory');
    }

    private function cleanupTmpJpegCandidates(string $tmpDir): void
    {
        foreach (glob($tmpDir.DIRECTORY_SEPARATOR.'cand_*.jpg') ?: [] as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
    }

    /**
     * Extrahiert JPEG-Kandidaten; bei vielen Frames mehrere FFmpeg-Läufe (weniger RAM/Timeout-Risiko).
     *
     * @return null bei Erfolg, sonst kurze Fehlerbeschreibung für Log/Exception
     */
    private function runFfmpegCandidateExtract(
        string $ffmpegPath,
        string $absVideo,
        string $tmpDir,
        string $fpsExpr,
        string $scaleExpr,
        ?string $lookExpr,
        int $candidateLimit,
        ?float $durationSeconds,
        int $chunkMaxFrames,
        int $extractTimeoutSeconds,
        int $ffmpegThreads,
    ): ?string {
        $vfPrimary = $fpsExpr.','.$scaleExpr;
        if (is_string($lookExpr) && trim($lookExpr) !== '') {
            $vfPrimary .= ','.$lookExpr;
        }
        $vfFallback = $this->buildFallbackVideoFilter($fpsExpr);

        $duration = ($durationSeconds !== null && $durationSeconds > 0.5) ? $durationSeconds : null;
        $numChunks = 1;
        if ($duration !== null && $candidateLimit > $chunkMaxFrames) {
            $numChunks = (int) max(1, (int) ceil($candidateLimit / $chunkMaxFrames));
        }

        if ($numChunks === 1) {
            return $this->runOneFfmpegExtractWithFallback(
                $ffmpegPath,
                $absVideo,
                $tmpDir,
                $vfPrimary,
                $vfFallback,
                $candidateLimit,
                null,
                null,
                'cand_%05d.jpg',
                $extractTimeoutSeconds,
                $ffmpegThreads
            );
        }

        $framesPerChunk = [];
        $base = intdiv($candidateLimit, $numChunks);
        $rem = $candidateLimit % $numChunks;
        for ($c = 0; $c < $numChunks; $c++) {
            $framesPerChunk[$c] = $base + ($c < $rem ? 1 : 0);
        }

        for ($c = 0; $c < $numChunks; $c++) {
            $framesThis = $framesPerChunk[$c] ?? 0;
            if ($framesThis < 1) {
                continue;
            }
            $t0 = $duration * $c / $numChunks;
            $segmentDuration = $duration / $numChunks;
            if ($segmentDuration < 0.08) {
                continue;
            }
            $fpsRate = $framesThis / $segmentDuration;
            $fpsExprChunk = 'fps='.sprintf('%.6F', max(0.02, $fpsRate));
            $pattern = 'cand_c'.$c.'_%05d.jpg';
            $vfChunkPrimary = $fpsExprChunk.','.$scaleExpr;
            if (is_string($lookExpr) && trim($lookExpr) !== '') {
                $vfChunkPrimary .= ','.$lookExpr;
            }
            $vfChunkFallback = $this->buildFallbackVideoFilter($fpsExprChunk);

            $err = $this->runOneFfmpegExtractWithFallback(
                $ffmpegPath,
                $absVideo,
                $tmpDir,
                $vfChunkPrimary,
                $vfChunkFallback,
                $framesThis,
                $t0,
                $segmentDuration,
                $pattern,
                $extractTimeoutSeconds,
                $ffmpegThreads
            );
            if ($err !== null) {
                return 'Chunk '.$c.'/'.$numChunks.': '.$err;
            }
        }

        return null;
    }

    /**
     * Presse-Look beim JPEG-Extrakt (Kontrast/Sättigung/Gamma + optional Unsharp).
     */
    private function buildStillLookFilterExpression(): ?string
    {
        if (! config('media.video_stills.apply_look_filters', true)) {
            return null;
        }

        $contrast = (float) config('media.video_stills.look.contrast', 1.10);
        $brightness = (float) config('media.video_stills.look.brightness', 0.02);
        $saturation = (float) config('media.video_stills.look.saturation', 1.10);
        $gamma = (float) config('media.video_stills.look.gamma', 0.94);
        $unsharp = trim((string) config('media.video_stills.look.unsharp', '7:7:0.55:3:3:0.05'));
        $applyUnsharp = (bool) config('media.video_stills.look.apply_unsharp', true);

        $eqParts = [];
        if (abs($contrast - 1.0) > 0.0001) {
            $eqParts[] = 'contrast='.$contrast;
        }
        if (abs($brightness) > 0.0001) {
            $eqParts[] = 'brightness='.$brightness;
        }
        if (abs($saturation - 1.0) > 0.0001) {
            $eqParts[] = 'saturation='.$saturation;
        }
        if (abs($gamma - 1.0) > 0.0001) {
            $eqParts[] = 'gamma='.$gamma;
        }

        $parts = [];
        if ($eqParts !== []) {
            $parts[] = 'eq='.implode(':', $eqParts);
        }
        if ($applyUnsharp && $unsharp !== '') {
            $parts[] = 'unsharp='.$unsharp;
        }

        return $parts !== [] ? implode(',', $parts) : null;
    }

    /**
     * Skalierung für Standbilder: Standard Presse 3:2 (Zentrum-Crop), optional Quellformat.
     *
     * @return array{0: int, 1: int}|null Breite/Höhe bei festem Seitenverhältnis
     */
    private function stillOutputDimensions(int $longEdge): ?array
    {
        $ratio = strtolower(trim((string) config('media.video_stills.aspect_ratio', '3:2')));
        if (in_array($ratio, ['source', 'original', 'video'], true)) {
            return null;
        }
        if (in_array($ratio, ['3:2', '3x2', '3-2'], true)) {
            return [
                max(640, $longEdge),
                max(427, (int) round($longEdge * 2 / 3)),
            ];
        }

        return [
            max(640, $longEdge),
            max(427, (int) round($longEdge * 2 / 3)),
        ];
    }

    private function buildStillScaleFilterExpression(int $longEdge, string $scaleFlags): string
    {
        $dims = $this->stillOutputDimensions($longEdge);
        if ($dims === null) {
            return "scale='if(gte(iw,ih),{$longEdge},-2)':'if(gte(iw,ih),-2,{$longEdge})':flags={$scaleFlags}";
        }

        [$w, $h] = $dims;

        return "scale={$w}:{$h}:force_original_aspect_ratio=increase:flags={$scaleFlags},crop={$w}:{$h}";
    }

    /**
     * Leichtere Filterkette: weniger RAM, robustere Decoder (bilinear, kein eq/unsharp).
     */
    private function buildFallbackVideoFilter(string $fpsExpr): string
    {
        $edge = max(480, (int) config('media.video_stills.fallback_long_edge', 1280));
        $scale = $this->buildStillScaleFilterExpression($edge, 'bilinear');

        return $fpsExpr.','.$scale;
    }

    /**
     * Ein FFmpeg-Lauf mit optionalem Fallback bei Fehler.
     */
    private function runOneFfmpegExtractWithFallback(
        string $ffmpegPath,
        string $absVideo,
        string $tmpDir,
        string $vfPrimary,
        string $vfFallback,
        int $framesV,
        ?float $ss,
        ?float $durationSeg,
        string $outputFilenamePattern,
        int $extractTimeoutSeconds,
        int $ffmpegThreads,
    ): ?string {
        $err = $this->runOneFfmpegExtract(
            $ffmpegPath,
            $absVideo,
            $tmpDir,
            $vfPrimary,
            $framesV,
            $ss,
            $durationSeg,
            $outputFilenamePattern,
            $extractTimeoutSeconds,
            $ffmpegThreads
        );
        if ($err === null) {
            return null;
        }
        if (! config('media.video_stills.fallback_on_ffmpeg_error', true)) {
            return $err;
        }
        Log::warning('GenerateVideoStills: ffmpeg primary failed, retrying fallback pipeline', [
            'media_id' => $this->videoMedia->id,
            'detail' => $err,
        ]);
        $this->deleteMatchingTempJpegs($tmpDir, $outputFilenamePattern);

        $err2 = $this->runOneFfmpegExtract(
            $ffmpegPath,
            $absVideo,
            $tmpDir,
            $vfFallback,
            $framesV,
            $ss,
            $durationSeg,
            $outputFilenamePattern,
            $extractTimeoutSeconds,
            $ffmpegThreads
        );
        if ($err2 === null) {
            return null;
        }

        return $err.' | Fallback: '.$err2;
    }

    /**
     * Entfernt Teil-Ausgaben eines fehlgeschlagenen Laufs (vor Fallback).
     */
    private function deleteMatchingTempJpegs(string $tmpDir, string $outputFilenamePattern): void
    {
        $glob = str_replace('%05d', '*', $outputFilenamePattern);
        $glob = str_replace('%d', '*', $glob);
        foreach (glob($tmpDir.DIRECTORY_SEPARATOR.$glob) ?: [] as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
    }

    /**
     * Ein FFmpeg-Lauf; optional Zeitfenster (chunked).
     *
     * @return null bei Erfolg, sonst Fehlerkurztext (stderr / Signal / Exit)
     */
    private function runOneFfmpegExtract(
        string $ffmpegPath,
        string $absVideo,
        string $tmpDir,
        string $vf,
        int $framesV,
        ?float $ss,
        ?float $durationSeg,
        string $outputFilenamePattern,
        int $extractTimeoutSeconds,
        int $ffmpegThreads,
    ): ?string {
        $outputPattern = $tmpDir.DIRECTORY_SEPARATOR.$outputFilenamePattern;

        $args = [
            $ffmpegPath,
            '-hide_banner',
            '-v', 'error',
            '-nostdin',
            '-y',
        ];
        if ($ffmpegThreads > 0) {
            $args[] = '-threads';
            $args[] = (string) $ffmpegThreads;
        }
        $filterThreads = max(1, (int) config('media.video_stills.ffmpeg_filter_threads', 1));
        if ($filterThreads > 0) {
            $args[] = '-filter_threads';
            $args[] = (string) $filterThreads;
        }
        if ($ss !== null && $durationSeg !== null) {
            $args[] = '-ss';
            $args[] = sprintf('%.4f', $ss);
        }
        $args[] = '-i';
        $args[] = $absVideo;
        if ($durationSeg !== null) {
            $args[] = '-t';
            $args[] = sprintf('%.4f', $durationSeg);
        }
        $args[] = '-vf';
        $args[] = $vf;
        $args[] = '-frames:v';
        $args[] = (string) $framesV;
        $args[] = '-q:v';
        $args[] = '2';
        $args[] = $outputPattern;

        $extract = new Process($args);

        try {
            $extract->setTimeout($extractTimeoutSeconds);
            $extract->run();
        } catch (ProcessSignaledException $e) {
            $sig = method_exists($e, 'getSignal') ? $e->getSignal() : null;
            Log::warning('GenerateVideoStills: ffmpeg signaled', [
                'media_id' => $this->videoMedia->id,
                'signal' => $sig,
            ]);

            return 'Prozess beendet (Signal '.($sig ?? '?').', oft OOM oder Timeout).';
        } catch (\Throwable $e) {
            Log::warning('GenerateVideoStills: ffmpeg process error', [
                'media_id' => $this->videoMedia->id,
                'error' => $e->getMessage(),
            ]);

            return 'Prozess: '.$e->getMessage();
        }

        if (! $extract->isSuccessful()) {
            $detail = $this->describeFfmpegStderr($extract);
            Log::warning('GenerateVideoStills: frame extraction failed', [
                'media_id' => $this->videoMedia->id,
                'detail' => $detail,
            ]);

            return $detail;
        }

        return null;
    }

    private function describeFfmpegStderr(Process $process): string
    {
        $code = $process->getExitCode();
        $err = trim($process->getErrorOutput());
        $err = Str::limit(preg_replace('/\s+/', ' ', $err), 500);

        return 'Exit '.$code.($err !== '' ? ': '.$err : ' (kein stderr)');
    }

    /**
     * @return list<array{hash: string, localPath: string, resolved: ?array}>
     */
    private function countExistingStillsForSourceVideo(NewsItem $newsItem): int
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn((new NewsItemMedia)->getTable(), 'source_video_media_id')) {
            return 0;
        }

        return (int) $newsItem->media()
            ->where('type', 'image')
            ->where('source_video_media_id', $this->videoMedia->id)
            ->count();
    }

    private function deleteExistingStillsForSourceVideo(NewsItem $newsItem, MediaStorage $mediaStorage): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn((new NewsItemMedia)->getTable(), 'source_video_media_id')) {
            return;
        }

        $stills = $newsItem->media()
            ->where('type', 'image')
            ->where('source_video_media_id', $this->videoMedia->id)
            ->get();

        foreach ($stills as $still) {
            $path = $still->path;
            if (is_string($path) && $path !== '' && ! str_contains($path, '.pending')) {
                $mediaStorage->delete($path);
            }
            $preview = $still->preview_path;
            if (is_string($preview) && $preview !== '') {
                $mediaStorage->delete($preview);
            }
            $still->delete();
        }
    }

    /**
     * Wählt bis zu $targetCount Kandidaten mit Mindestabstand in Sekunden und visueller Eindeutigkeit.
     *
     * @param  list<array{path: string, score: float, sharpness: float, motion_delta: float, second: int, hash: string}>  $candidates
     * @return list<array{path: string, score: float, sharpness: float, motion_delta: float, second: int, hash: string}>
     */
    private function selectCandidatesWithMinGap(
        array $candidates,
        int $targetCount,
        int $minGapSeconds,
        int $phashDistanceThreshold,
        float $duplicateFrameDiffMax,
    ): array {
        $selected = [];
        foreach ($candidates as $candidate) {
            if ($this->isTooSimilarToAnySelected($candidate, $selected, $minGapSeconds, $phashDistanceThreshold, $duplicateFrameDiffMax)) {
                continue;
            }
            $selected[] = $candidate;
            if (count($selected) >= $targetCount) {
                break;
            }
        }

        return $selected;
    }

    /**
     * @param  list<array{path: string, score: float, sharpness: float, motion_delta: float, second: int, hash: string}>  $selected
     */
    private function isTooSimilarToAnySelected(
        array $candidate,
        array $selected,
        int $minGapSeconds,
        int $phashDistanceThreshold,
        float $duplicateFrameDiffMax,
    ): bool {
        foreach ($selected as $picked) {
            if (abs((int) ($picked['second'] ?? 0) - (int) ($candidate['second'] ?? 0)) < $minGapSeconds) {
                return true;
            }
            $candHash = (string) ($candidate['hash'] ?? '');
            $pickedHash = (string) ($picked['hash'] ?? '');
            if ($candHash !== '' && $pickedHash !== ''
                && $this->hammingDistance($candHash, $pickedHash) <= $phashDistanceThreshold) {
                return true;
            }
            if ($this->computeFrameDifference((string) $candidate['path'], (string) $picked['path']) <= $duplicateFrameDiffMax) {
                return true;
            }
        }

        return false;
    }

    /**
     * Entfernt optisch nahezu identische Kandidaten (z. B. statische Testmuster / wenig Bewegung im Clip).
     *
     * @param  list<array{path: string, score: float, sharpness: float, motion_delta: float, second: int, hash: string}>  $candidates
     * @return list<array{path: string, score: float, sharpness: float, motion_delta: float, second: int, hash: string}>
     */
    private function filterVisuallyDistinctCandidates(array $candidates, int $phashDistanceThreshold, float $duplicateFrameDiffMax): array
    {
        $picked = [];
        foreach ($candidates as $candidate) {
            $isDup = false;
            foreach ($picked as $existing) {
                $candHash = (string) ($candidate['hash'] ?? '');
                $existHash = (string) ($existing['hash'] ?? '');
                if ($candHash !== '' && $existHash !== ''
                    && $this->hammingDistance($candHash, $existHash) <= $phashDistanceThreshold) {
                    $isDup = true;
                    break;
                }
                if ($this->computeFrameDifference((string) $candidate['path'], (string) $existing['path']) <= $duplicateFrameDiffMax) {
                    $isDup = true;
                    break;
                }
            }
            if (! $isDup) {
                $picked[] = $candidate;
            }
        }

        return $picked;
    }

    private function buildExistingImageDuplicateRefs(NewsItem $newsItem, MediaStorage $mediaStorage): array
    {
        $out = [];
        foreach ($newsItem->images()->get() as $medium) {
            if (! $medium->isImage()) {
                continue;
            }
            // Aus Video erzeugte Bilder nicht als Dubletten-Referenz (sonst blockieren erneute Läufe).
            if ($medium->isVideoDerivedStillImage()) {
                continue;
            }
            $path = $medium->path;
            if (! is_string($path) || $path === '' || str_contains($path, '.pending')) {
                continue;
            }
            $resolved = $mediaStorage->resolveReadableLocalPath($path);
            if (! is_array($resolved)) {
                continue;
            }
            $local = $resolved['path'] ?? null;
            if (! is_string($local) || ! is_file($local)) {
                $mediaStorage->cleanupResolvedPath($resolved);

                continue;
            }
            $out[] = [
                'hash' => $this->perceptualHash($local),
                'localPath' => $local,
                'resolved' => $resolved,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{hash: string, localPath: string, resolved?: ?array}>  $refs
     */
    private function isVisualDuplicateOfAny(
        string $candidatePath,
        string $candidateHash,
        array $refs,
        int $phashDistanceThreshold,
        float $duplicateFrameDiffMax
    ): bool {
        if (! is_file($candidatePath)) {
            return false;
        }

        foreach ($refs as $ref) {
            $refPath = (string) ($ref['localPath'] ?? '');
            if ($refPath === '' || ! is_file($refPath)) {
                continue;
            }
            $refHash = (string) ($ref['hash'] ?? '');
            if ($candidateHash !== '' && $refHash !== ''
                && $this->hammingDistance($candidateHash, $refHash) <= $phashDistanceThreshold) {
                return true;
            }
            if ($this->computeFrameDifference($candidatePath, $refPath) <= $duplicateFrameDiffMax) {
                return true;
            }
        }

        return false;
    }

    /**
     * Spieldauer: zuerst aus der Datei (ffprobe/ffmpeg), damit eine falsche DB-Spalte duration_s
     * nicht die gleichmäßige Verteilung über das ganze Video zerstört.
     */
    private function resolveVideoDurationSeconds(string $absVideo, string $ffmpegPath): ?float
    {
        $fromProbe = $this->probeDurationSecondsWithFfprobeJson($absVideo);
        if ($fromProbe !== null && $fromProbe > 0.5) {
            return $fromProbe;
        }

        $fromFfmpeg = $this->probeDurationSecondsWithFfmpegStderr($absVideo, $ffmpegPath);
        if ($fromFfmpeg !== null && $fromFfmpeg > 0.5) {
            return $fromFfmpeg;
        }

        $fromModel = $this->videoMedia->duration_s;
        if ($fromModel !== null && (float) $fromModel > 0.5) {
            return (float) $fromModel;
        }

        return null;
    }

    /**
     * Konfigurierter absoluter Pfad nutzen; wenn er fehlt, „ffmpeg“ aus PATH (Hosting mit anderem Installationsort).
     */
    private function resolveFfmpegBinary(string $configured): string
    {
        $t = trim($configured);
        if ($t === '') {
            return 'ffmpeg';
        }
        if (! str_contains($t, DIRECTORY_SEPARATOR)) {
            return $t;
        }
        if (is_file($t)) {
            return $t;
        }

        return 'ffmpeg';
    }

    private function maybePersistVideoDuration(?float $resolvedSeconds): void
    {
        if ($resolvedSeconds === null || $resolvedSeconds < 0.5) {
            return;
        }
        $current = $this->videoMedia->duration_s;
        if ($current !== null && abs((float) $current - $resolvedSeconds) < 2.0) {
            return;
        }
        try {
            $this->videoMedia->duration_s = $resolvedSeconds;
            $this->videoMedia->saveQuietly();
        } catch (\Throwable) {
        }
    }

    /**
     * format.duration und ggf. Video-Stream-Duration (JSON zuverlässiger als nur format=duration).
     */
    private function probeDurationSecondsWithFfprobeJson(string $absVideo): ?float
    {
        $ffprobePath = (string) config('media.ffprobe_path', 'ffprobe');
        if (str_contains($ffprobePath, DIRECTORY_SEPARATOR) && ! is_file($ffprobePath)) {
            return null;
        }

        $process = new Process([
            $ffprobePath,
            '-v', 'error',
            '-show_format',
            '-show_streams',
            '-of', 'json',
            $absVideo,
        ]);
        $process->setTimeout(90);
        $process->run();
        if (! $process->isSuccessful()) {
            return null;
        }

        $json = json_decode($process->getOutput(), true);
        if (! is_array($json)) {
            return null;
        }

        $candidates = [];
        if (isset($json['format']['duration'])) {
            $fd = (float) $json['format']['duration'];
            if ($fd > 0.05) {
                $candidates[] = $fd;
            }
        }
        if (isset($json['streams']) && is_array($json['streams'])) {
            foreach ($json['streams'] as $stream) {
                if (($stream['codec_type'] ?? '') !== 'video') {
                    continue;
                }
                if (isset($stream['duration'])) {
                    $sd = (float) $stream['duration'];
                    if ($sd > 0.05) {
                        $candidates[] = $sd;
                    }
                }
            }
        }
        if ($candidates === []) {
            return null;
        }

        $duration = max($candidates);

        return $duration > 0.5 ? $duration : null;
    }

    /**
     * Fallback: Dauer aus ffmpeg -i … (steht in stderr, funktioniert oft wenn ffprobe format=N/A).
     */
    private function probeDurationSecondsWithFfmpegStderr(string $absVideo, string $ffmpegPath): ?float
    {
        $ffmpegPath = $this->resolveFfmpegBinary($ffmpegPath);

        $process = new Process([
            $ffmpegPath,
            '-hide_banner',
            '-i', $absVideo,
            '-f', 'null',
            '-',
        ]);
        $process->setTimeout(120);
        $process->run();

        $combined = $process->getErrorOutput().$process->getOutput();
        if (preg_match('/Duration:\\s*(\\d+):(\\d+):(\\d+(?:\\.\\d+)?)/', $combined, $m)) {
            $total = ((int) $m[1]) * 3600 + ((int) $m[2]) * 60 + (float) $m[3];

            return $total > 0.5 ? $total : null;
        }

        return null;
    }

    /**
     * Roh-Kandidaten nach Schärfe und Bewegung filtern (Laplacian / Nachbarframes).
     *
     * @param  list<string>  $files
     * @return list<array{path: string, score: float, sharpness: float, motion_delta: float, second: int, hash: string}>
     */
    private function buildScoredCandidates(
        array $files,
        float $extractIntervalSeconds,
        float $minSharpness,
        float $maxMotionDelta,
        float $motionPenaltyWeight,
        ?float $durationSeconds,
        int $candidateLimit,
        int $chunkMaxFrames,
    ): array {
        $scored = [];
        foreach ($files as $idx => $file) {
            $sharpness = $this->estimateSharpness($file);
            if ($sharpness < $minSharpness) {
                continue;
            }
            $prev = $idx > 0 ? ($files[$idx - 1] ?? null) : null;
            $next = $idx < count($files) - 1 ? ($files[$idx + 1] ?? null) : null;
            $motionDelta = $this->estimateMotionDelta($file, is_string($prev) ? $prev : null, is_string($next) ? $next : null);
            if ($motionDelta > $maxMotionDelta) {
                continue;
            }
            $second = $this->estimateCandidateSecond(
                $file,
                $extractIntervalSeconds,
                $durationSeconds,
                $candidateLimit,
                $chunkMaxFrames
            );
            $score = $sharpness - ($motionDelta * $motionPenaltyWeight);
            $meanLuma = $this->estimateMeanLuminance($file);
            if ($meanLuma > 200.0) {
                $score -= ($meanLuma - 200.0) * 0.2;
            }
            $scored[] = [
                'path' => $file,
                'score' => $score,
                'sharpness' => $sharpness,
                'motion_delta' => $motionDelta,
                'second' => $second,
                'hash' => $this->perceptualHash($file),
            ];
        }

        return $scored;
    }

    private function estimateCandidateSecond(
        string $filePath,
        float $extractIntervalSeconds,
        ?float $durationSeconds,
        int $candidateLimit,
        int $chunkMaxFrames,
    ): int {
        $base = basename($filePath);
        if (preg_match('/cand_c(\d+)_(\d+)\.jpg$/i', $base, $m) === 1) {
            $chunk = (int) $m[1];
            $frameIdx = max(1, (int) $m[2]);
            if ($durationSeconds !== null && $durationSeconds > 0.5) {
                $chunkMax = max(8, $chunkMaxFrames);
                $numChunks = max(1, (int) ceil($candidateLimit / $chunkMax));
                $segmentDuration = $durationSeconds / $numChunks;
                $framesThis = max(1, (int) ceil($candidateLimit / $numChunks));
                $fpsRate = max(0.02, $framesThis / $segmentDuration);
                $t0 = $durationSeconds * $chunk / $numChunks;

                return (int) round(max(0.0, $t0 + ($frameIdx - 1) / $fpsRate));
            }
        }
        if (preg_match('/cand_(\d+)\.jpg$/i', $base, $m) === 1) {
            return (int) round(max(0, ((int) $m[1] - 1) * $extractIntervalSeconds));
        }

        return 0;
    }

    /**
     * Exaktes Standbild per Seek (1:1 zum Quellvideo, optional ohne Look-Filter).
     */
    private function extractExactStillAtSecond(
        string $ffmpegPath,
        string $absVideo,
        string $outputPath,
        float $second,
        string $scaleExpr,
        ?string $lookExpr,
        int $extractTimeoutSeconds,
        int $ffmpegThreads,
    ): ?string {
        $vf = $scaleExpr;
        if (is_string($lookExpr) && trim($lookExpr) !== '') {
            $vf .= ','.$lookExpr;
        }

        $args = [
            $ffmpegPath,
            '-hide_banner',
            '-v', 'error',
            '-nostdin',
            '-y',
        ];
        if ($ffmpegThreads > 0) {
            $args[] = '-threads';
            $args[] = (string) $ffmpegThreads;
        }
        $filterThreads = max(1, (int) config('media.video_stills.ffmpeg_filter_threads', 1));
        if ($filterThreads > 0) {
            $args[] = '-filter_threads';
            $args[] = (string) $filterThreads;
        }
        $args[] = '-ss';
        $args[] = sprintf('%.3f', max(0.0, $second));
        $args[] = '-i';
        $args[] = $absVideo;
        $args[] = '-frames:v';
        $args[] = '1';
        $args[] = '-vf';
        $args[] = $vf;
        $args[] = '-q:v';
        $args[] = '2';
        $args[] = $outputPath;

        $extract = new Process($args);
        try {
            $extract->setTimeout(min($extractTimeoutSeconds, 120));
            $extract->run();
        } catch (\Throwable $e) {
            return 'Exact frame: '.$e->getMessage();
        }
        if (! $extract->isSuccessful() || ! is_file($outputPath)) {
            return $this->describeFfmpegStderr($extract);
        }

        return null;
    }

    private function estimateMeanLuminance(string $filePath): float
    {
        if (! function_exists('imagecreatefromjpeg') || ! is_file($filePath)) {
            return 128.0;
        }
        $img = @imagecreatefromjpeg($filePath);
        if ($img === false) {
            return 128.0;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        if ($w < 1 || $h < 1) {
            imagedestroy($img);

            return 128.0;
        }
        $step = max(1, (int) floor(max($w, $h) / 80));
        $sum = 0.0;
        $count = 0;
        for ($y = 0; $y < $h; $y += $step) {
            for ($x = 0; $x < $w; $x += $step) {
                $c = imagecolorat($img, $x, $y);
                $sum += (($c >> 16) & 0xFF) * 0.299 + (($c >> 8) & 0xFF) * 0.587 + ($c & 0xFF) * 0.114;
                $count++;
            }
        }
        imagedestroy($img);

        return $count > 0 ? $sum / $count : 128.0;
    }

    private function estimateSharpness(string $filePath): float
    {
        if (! function_exists('imagecreatefromjpeg')) {
            return 0.0;
        }

        if (! is_file($filePath)) {
            return 0.0;
        }
        $img = @imagecreatefromjpeg($filePath);
        if ($img === false) {
            return 0.0;
        }

        $w = imagesx($img);
        $h = imagesy($img);
        if ($w < 3 || $h < 3) {
            imagedestroy($img);

            return 0.0;
        }

        $targetLongEdge = 600;
        $scale = max($w, $h) > $targetLongEdge ? ($targetLongEdge / max($w, $h)) : 1.0;
        $dw = max(3, (int) round($w * $scale));
        $dh = max(3, (int) round($h * $scale));

        $work = imagecreatetruecolor($dw, $dh);
        imagecopyresampled($work, $img, 0, 0, 0, 0, $dw, $dh, $w, $h);
        imagedestroy($img);

        $sum = 0.0;
        $count = 0;
        for ($y = 1; $y < $dh - 1; $y++) {
            for ($x = 1; $x < $dw - 1; $x++) {
                $c = imagecolorat($work, $x, $y);
                $l = imagecolorat($work, $x - 1, $y);
                $r = imagecolorat($work, $x + 1, $y);
                $u = imagecolorat($work, $x, $y - 1);
                $d = imagecolorat($work, $x, $y + 1);

                $gc = (($c >> 16) & 0xFF) * 0.299 + (($c >> 8) & 0xFF) * 0.587 + ($c & 0xFF) * 0.114;
                $gl = (($l >> 16) & 0xFF) * 0.299 + (($l >> 8) & 0xFF) * 0.587 + ($l & 0xFF) * 0.114;
                $gr = (($r >> 16) & 0xFF) * 0.299 + (($r >> 8) & 0xFF) * 0.587 + ($r & 0xFF) * 0.114;
                $gu = (($u >> 16) & 0xFF) * 0.299 + (($u >> 8) & 0xFF) * 0.587 + ($u & 0xFF) * 0.114;
                $gd = (($d >> 16) & 0xFF) * 0.299 + (($d >> 8) & 0xFF) * 0.587 + ($d & 0xFF) * 0.114;

                $lap = abs((4.0 * $gc) - $gl - $gr - $gu - $gd);
                $sum += $lap;
                $count++;
            }
        }
        imagedestroy($work);

        if ($count <= 0) {
            return 0.0;
        }

        return $sum / $count;
    }

    /**
     * @param  list<array{hash: string, localPath: string, resolved?: ?array}>  $existingDuplicateRefs
     * @param  list<array{hash: string, localPath: string, resolved?: ?array}>  $savedThisRunRefs
     */
    private function persistVideoStillCandidate(
        NewsItem $newsItem,
        MediaStorage $mediaStorage,
        array $item,
        string $absVideo,
        string $tmpDir,
        int $savedOrdinal,
        int &$sortOrder,
        string $baseStem,
        string $datePrefix,
        string $credit,
        string $copyright,
        string $watermarkPath,
        int $maxBytes,
        array &$existingDuplicateRefs,
        array &$savedThisRunRefs,
        int $phashDistanceThreshold,
        float $duplicateFrameDiffMax,
        bool $autoRedactionForNewsItem,
        bool $exactFrameExtract,
        string $ffmpegPath,
        string $scaleExpr,
        ?string $lookExpr,
        int $extractTimeout,
        int $ffmpegThreads,
        int &$skippedAsManualDuplicate,
        int &$skippedAsRunDuplicate,
        int &$failedUploadCount,
        bool $ignoreManualDuplicateCheck,
    ): bool {
        $candPath = (string) $item['path'];
        if ($exactFrameExtract) {
            $exactPath = $tmpDir.DIRECTORY_SEPARATOR.'exact_'.str_pad((string) $savedOrdinal, 4, '0', STR_PAD_LEFT).'.jpg';
            $ts = max(0.0, (float) ($item['second'] ?? 0));
            $exactErr = $this->extractExactStillAtSecond(
                $ffmpegPath,
                $absVideo,
                $exactPath,
                $ts,
                $scaleExpr,
                $lookExpr,
                $extractTimeout,
                $ffmpegThreads
            );
            if ($exactErr === null && is_file($exactPath)) {
                $candPath = $exactPath;
                $item['path'] = $exactPath;
                $item['hash'] = $this->perceptualHash($exactPath);
            }
        }
        $candHash = (string) ($item['hash'] ?? '');

        if ($this->isVisualDuplicateOfAny($candPath, $candHash, $savedThisRunRefs, $phashDistanceThreshold, $duplicateFrameDiffMax)) {
            $skippedAsRunDuplicate++;

            return false;
        }

        if (! $ignoreManualDuplicateCheck
            && $this->isVisualDuplicateOfAny($candPath, $candHash, $existingDuplicateRefs, $phashDistanceThreshold, $duplicateFrameDiffMax)) {
            $skippedAsManualDuplicate++;

            return false;
        }

        $sortOrder++;
        $ordinal = $savedOrdinal + 1;
        $originalName = $datePrefix.'-'.$baseStem.'-'.str_pad((string) $ordinal, 2, '0', STR_PAD_LEFT).'.jpg';
        $stillCaptureTime = $this->videoMedia->capture_time ?? $this->videoMedia->created_at;

        $imageMedia = $newsItem->media()->create([
            'type' => 'image',
            'path' => 'news-media/.pending',
            'original_name' => $originalName,
            'sort_order' => $sortOrder,
            'source_video_media_id' => $this->videoMedia->id,
            'capture_time' => $stillCaptureTime,
            'metadata_recorded_at' => $stillCaptureTime?->toDateString(),
            'caption' => '',
            'image_title' => '',
            'photographer' => $credit,
            'versand' => true,
            'redaction_status' => $autoRedactionForNewsItem
                ? NewsItemMedia::REDACTION_PENDING
                : NewsItemMedia::REDACTION_DISABLED,
        ]);

        ImageMetadataWriter::write((string) $item['path'], [
            'image_title' => $imageMedia->image_title,
            'photographer' => $imageMedia->photographer,
            'caption' => $imageMedia->caption,
            'credit' => $credit,
            'copyright' => $copyright,
        ]);

        $this->ensureMaxFileSize((string) $item['path'], $maxBytes);

        $targetPath = $mediaStorage->generateMediaPath($newsItem, $imageMedia, $originalName, 'gallery');
        $uploaded = $mediaStorage->putFromLocalFile($targetPath, (string) $item['path'], [
            'visibility' => 'public',
            'ContentType' => 'image/jpeg',
        ]);
        if (! $uploaded) {
            $failedUploadCount++;
            Log::error('GenerateVideoStills: putFromLocalFile failed', [
                'news_item_id' => $newsItem->id,
                'video_media_id' => $this->videoMedia->id,
                'image_media_id' => $imageMedia->id,
                'target_path' => $targetPath,
                'disk' => $mediaStorage->activeDiskName(),
                'local_candidate' => $item['path'],
            ]);
            $imageMedia->delete();
            $sortOrder--;

            return false;
        }

        $imageMedia->update([
            'path' => $targetPath,
            'capture_time' => $imageMedia->capture_time ?? $stillCaptureTime,
            'metadata_recorded_at' => ($imageMedia->capture_time ?? $stillCaptureTime)?->toDateString(),
        ]);
        Log::info('GenerateVideoStills: image stored', [
            'news_item_id' => $newsItem->id,
            'image_media_id' => $imageMedia->id,
            'disk' => $mediaStorage->activeDiskName(),
            'path' => $targetPath,
            'public_url' => $mediaStorage->url($targetPath),
        ]);

        try {
            app(MediaQualityCheck::class)->runAndSave($imageMedia);
        } catch (\Throwable $e) {
            Log::warning('GenerateVideoStills: quality check failed', [
                'media_id' => $imageMedia->id,
                'error' => $e->getMessage(),
            ]);
        }

        $previewPath = $mediaStorage->generateDerivedMediaPath($newsItem, $imageMedia, $originalName.'.webp', 'preview');
        $imageMedia->update(['preview_path' => $previewPath]);
        if (is_file($watermarkPath)) {
            GenerateNewsMediaPreview::dispatchSync($mediaStorage->activeDiskName(), $targetPath, $previewPath, $watermarkPath);
        }

        try {
            $imageMedia->markAiQueued();
            GenerateImageMetadata::dispatch($imageMedia);
        } catch (QueryException $e) {
            Log::warning('GenerateVideoStills: AI columns missing', [
                'media_id' => $imageMedia->id,
                'error' => $e->getMessage(),
            ]);
        }

        if ($autoRedactionForNewsItem) {
            ProcessMediaRedaction::dispatch($imageMedia);
        }

        $savedThisRunRefs[] = [
            'hash' => $candHash,
            'localPath' => $candPath,
            'resolved' => null,
        ];

        return true;
    }

    private function cleanupTmpDirectory(string $tmpDir): void
    {
        $files = glob($tmpDir.DIRECTORY_SEPARATOR.'*') ?: [];
        foreach ($files as $file) {
            @unlink($file);
        }
        @rmdir($tmpDir);
    }

    private function shouldAutoRedactForNewsItem(NewsItem $newsItem): bool
    {
        $brand = $newsItem->relationLoaded('brand')
            ? $newsItem->brand
            : $newsItem->brand()->first();

        return $brand?->key !== 'koelnimage';
    }

    private function perceptualHash(string $filePath): string
    {
        if (! function_exists('imagecreatefromjpeg') || ! is_file($filePath)) {
            return '';
        }

        $img = @imagecreatefromjpeg($filePath);
        if ($img === false) {
            return '';
        }

        $small = imagecreatetruecolor(8, 8);
        imagecopyresampled($small, $img, 0, 0, 0, 0, 8, 8, imagesx($img), imagesy($img));
        imagedestroy($img);

        $values = [];
        $sum = 0.0;
        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $c = imagecolorat($small, $x, $y);
                $g = (($c >> 16) & 0xFF) * 0.299 + (($c >> 8) & 0xFF) * 0.587 + ($c & 0xFF) * 0.114;
                $values[] = $g;
                $sum += $g;
            }
        }
        imagedestroy($small);

        $avg = $sum / 64.0;
        $bits = '';
        foreach ($values as $v) {
            $bits .= $v >= $avg ? '1' : '0';
        }

        return $bits;
    }

    private function hammingDistance(string $a, string $b): int
    {
        $len = min(strlen($a), strlen($b));
        if ($len === 0) {
            return PHP_INT_MAX;
        }
        $distance = 0;
        for ($i = 0; $i < $len; $i++) {
            if ($a[$i] !== $b[$i]) {
                $distance++;
            }
        }

        return $distance + abs(strlen($a) - strlen($b));
    }

    private function estimateMotionDelta(string $centerPath, ?string $prevPath, ?string $nextPath): float
    {
        $values = [];
        if (is_string($prevPath) && $prevPath !== '') {
            $values[] = $this->computeFrameDifference($centerPath, $prevPath);
        }
        if (is_string($nextPath) && $nextPath !== '') {
            $values[] = $this->computeFrameDifference($centerPath, $nextPath);
        }
        if ($values === []) {
            return 0.0;
        }

        return array_sum($values) / count($values);
    }

    private function computeFrameDifference(string $aPath, string $bPath): float
    {
        if (! function_exists('imagecreatefromjpeg') || ! is_file($aPath) || ! is_file($bPath)) {
            return PHP_FLOAT_MAX;
        }
        $a = @imagecreatefromjpeg($aPath);
        $b = @imagecreatefromjpeg($bPath);
        if ($a === false || $b === false) {
            if ($a !== false) {
                imagedestroy($a);
            }
            if ($b !== false) {
                imagedestroy($b);
            }

            return PHP_FLOAT_MAX;
        }

        $dw = 64;
        $dh = 36;
        $sa = imagecreatetruecolor($dw, $dh);
        $sb = imagecreatetruecolor($dw, $dh);
        imagecopyresampled($sa, $a, 0, 0, 0, 0, $dw, $dh, imagesx($a), imagesy($a));
        imagecopyresampled($sb, $b, 0, 0, 0, 0, $dw, $dh, imagesx($b), imagesy($b));
        imagedestroy($a);
        imagedestroy($b);

        $sum = 0.0;
        $count = 0;
        for ($y = 0; $y < $dh; $y++) {
            for ($x = 0; $x < $dw; $x++) {
                $ca = imagecolorat($sa, $x, $y);
                $cb = imagecolorat($sb, $x, $y);
                $ga = (($ca >> 16) & 0xFF) * 0.299 + (($ca >> 8) & 0xFF) * 0.587 + ($ca & 0xFF) * 0.114;
                $gb = (($cb >> 16) & 0xFF) * 0.299 + (($cb >> 8) & 0xFF) * 0.587 + ($cb & 0xFF) * 0.114;
                $sum += abs($ga - $gb);
                $count++;
            }
        }
        imagedestroy($sa);
        imagedestroy($sb);

        if ($count <= 0) {
            return 0.0;
        }

        return $sum / $count;
    }

    private function ensureMaxFileSize(string $filePath, int $maxBytes): void
    {
        if (! function_exists('imagecreatefromjpeg') || ! is_file($filePath)) {
            return;
        }
        $currentSize = filesize($filePath);
        if (! is_int($currentSize) || $currentSize <= 0 || $currentSize <= $maxBytes) {
            return;
        }

        $img = @imagecreatefromjpeg($filePath);
        if ($img === false) {
            return;
        }

        $quality = 90;
        while ($quality >= 70) {
            if (! @imagejpeg($img, $filePath, $quality)) {
                break;
            }
            $newSize = filesize($filePath);
            if (is_int($newSize) && $newSize > 0 && $newSize <= $maxBytes) {
                break;
            }
            $quality -= 4;
        }
        imagedestroy($img);
    }
}
