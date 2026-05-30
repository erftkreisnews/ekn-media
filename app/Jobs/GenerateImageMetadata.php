<?php

namespace App\Jobs;

use App\Exceptions\MediaAiRateLimitException;
use App\Models\NewsItemMedia;
use App\Models\PlannedEvent;
use App\Services\ImageMetadataWriter;
use App\Services\MediaAi\ImageMetadataGenerator;
use App\Services\MediaAi\PlannedEventMetadataEnricher;
use App\Services\MediaStorage;
use App\Support\CaptionDateSanitizer;
use App\Support\MediaCaptionLocationDateTail;
use App\Support\MediaKeywordNormalizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GenerateImageMetadata implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Falls ein Medium zwischen Dispatch und Verarbeitung gelöscht wurde,
     * wird der Job still verworfen statt als "failed" zu enden.
     */
    public bool $deleteWhenMissingModels = true;

    /** Job-Timeout (Vision + Nachbearbeitung; HTTP-Timeout siehe config media_ai.timeout) */
    public int $timeout = 120;

    /**
     * Max. Versuche für die KI-Generierung.
     * Bei OpenAI 429/Rate-Limits wollen wir nicht nach kurzer Zeit final „KI-Fehler“ anzeigen.
     */
    public int $tries = 20;

    public array $backoff = [30, 60, 120, 240, 480, 900];

    public function __construct(
        protected NewsItemMedia $media,
        protected ?string $refinementHint = null,
        protected ?string $captionContext = null,
        protected ?string $keywordsContext = null,
    ) {
        $this->onQueue('ai');
        // Property-Defaults dürfen keine Funktionsaufrufe enthalten, daher Env erst hier setzen.
        $this->tries = (int) env('MEDIA_AI_JOB_MAX_TRIES', $this->tries);
    }

    public function handle(ImageMetadataGenerator $generator, PlannedEventMetadataEnricher $enricher): void
    {
        $media = $this->media;
        $jobId = method_exists($this, 'job') && $this->job ? $this->job->getJobId() : null;
        $logCtx = [
            'media_id' => $media->id,
            'job_id' => $jobId,
            'attempt' => $this->attempts(),
        ];
        Log::info('GenerateImageMetadata: start', $logCtx);
        if ($media->type !== 'image') {
            Log::info('GenerateImageMetadata: skip, not image', $logCtx);

            return;
        }

        Log::debug('GenerateImageMetadata: lock acquire try', $logCtx);
        $lock = Cache::lock('ai:media:'.$media->id, 600);
        if (! $lock->get()) {
            Log::info('GenerateImageMetadata: lock busy, release for retry', $logCtx);
            try {
                $media->markAiQueued();
            } catch (QueryException $e) {
                Log::warning('GenerateImageMetadata: could not mark queued (lock busy)', [
                    ...$logCtx,
                    'error' => $e->getMessage(),
                ]);
            }
            $this->release(60);
            Log::info('GenerateImageMetadata: job released (lock busy)', $logCtx + ['release_seconds' => 60]);

            return;
        }
        Log::debug('GenerateImageMetadata: lock acquired', $logCtx);

        try {
            Log::debug('GenerateImageMetadata: markAiRunning begin', $logCtx);
            $media->markAiRunning();
            Log::debug('GenerateImageMetadata: markAiRunning done', $logCtx);
        } catch (QueryException $e) {
            Log::warning('GenerateImageMetadata: could not mark running', [
                ...$logCtx,
                'error' => $e->getMessage(),
            ]);
        }

        if (method_exists($media, 'syncCaptureTimeFromMasterFile')) {
            $media->syncCaptureTimeFromMasterFile();
            $media->refresh();
        }

        try {
            Log::debug('GenerateImageMetadata: generate begin', $logCtx);
            $dbCaptionBefore = trim((string) ($media->caption ?? ''));
            $refinementHint = $this->refinementHint !== null ? trim($this->refinementHint) : '';
            $isRefinement = $refinementHint !== '';
            $refinementPayload = $isRefinement ? [
                'hint' => $refinementHint,
                'existing_caption' => $this->captionContext !== null
                    ? (string) $this->captionContext
                    : (string) ($media->caption ?? ''),
                'existing_keywords' => $this->keywordsContext !== null
                    ? (string) $this->keywordsContext
                    : (string) ($media->media_keywords ?? ''),
            ] : null;
            $result = $generator->generate($media, $refinementPayload);
            $result = $enricher->enrich($media, $result);
            Log::debug('GenerateImageMetadata: generate done', $logCtx);
            $hasPlannedEventMatch = (bool) Arr::get($result, 'planned_event_enrichment.matched', false);
            $result['caption'] = CaptionDateSanitizer::sanitize(
                (string) ($result['caption'] ?? ''),
                CaptionDateSanitizer::allowedDatesForMedia($media)
            );
            $result['keywords'] = MediaKeywordNormalizer::normalizeKeywordList(
                array_values(array_filter($result['keywords'] ?? [], static fn ($value) => is_string($value))),
                14
            );
            if ($isRefinement) {
                if (! empty($result['keywords'] ?? [])) {
                    $media->media_keywords = implode(', ', $result['keywords']);
                }
                $title = trim((string) ($result['image_title'] ?? ''));
                if ($title !== '') {
                    $media->image_title = $title;
                }
            } else {
                if (! $this->hasValue($media->image_title) && ! empty($result['image_title'] ?? null)) {
                    $media->image_title = $result['image_title'];
                } elseif ($hasPlannedEventMatch && ! empty($result['image_title'] ?? null)) {
                    $media->image_title = $result['image_title'];
                }
                if (! $this->hasValue($media->media_keywords) && ! empty($result['keywords'] ?? [])) {
                    $media->media_keywords = implode(', ', $result['keywords']);
                } elseif ($hasPlannedEventMatch && ! empty($result['keywords'] ?? [])) {
                    $media->media_keywords = implode(', ', $result['keywords']);
                }
                if (! $this->hasValue($media->photographer) && array_key_exists('photographer', $result)) {
                    $media->photographer = $result['photographer'];
                }
            }
            if (! $isRefinement) {
                $description = trim((string) ($result['description'] ?? ''));
                if ($hasPlannedEventMatch && $description !== '') {
                    $media->description = $description;
                } elseif (! $this->hasValue($media->description) && array_key_exists('description', $result)) {
                    $media->description = $result['description'];
                }
            }

            $this->applyPlannedEventVenueIptcDefaults($media, $hasPlannedEventMatch);

            $news = $media->relationLoaded('newsItem') ? $media->newsItem : $media->newsItem()->first();
            if ($media->isImage() && $news && ($result['caption_append_location_tail'] ?? true) !== false) {
                $result['caption'] = MediaCaptionLocationDateTail::appendToCaption(
                    $news,
                    $media,
                    (string) ($result['caption'] ?? '')
                );
            }
            unset($result['caption_append_location_tail']);

            $hadDbCaption = $dbCaptionBefore !== '';
            if ($isRefinement) {
                if (trim((string) ($result['caption'] ?? '')) !== '') {
                    $media->caption = $result['caption'];
                }
            } elseif (trim((string) ($result['caption'] ?? '')) !== '' && (! $hadDbCaption || $hasPlannedEventMatch)) {
                $media->caption = $result['caption'];
            }

            $media->markAiDone([
                'ai_payload' => $result,
                'ai_model' => config('media_ai.vision_model', 'gpt-4.1-mini'),
                'ai_suggested_at' => now(),
            ]);
            Log::info('GenerateImageMetadata: markAiDone', $logCtx);

            $iptcRel = $media->resolveIptcMasterRelativePath();
            $mediaStorage = app(MediaStorage::class);
            if ($iptcRel !== null) {
                $resolved = $mediaStorage->resolveReadableLocalPath($iptcRel);
                $fullPath = $resolved['path'] ?? null;
                if (is_string($fullPath) && is_file($fullPath)) {
                    $written = ImageMetadataWriter::write($fullPath, $media->resolvedIptcForEmbed());
                    if ($written && ($resolved['temporary'] ?? false) === true) {
                        $mediaStorage->putFromLocalFile($iptcRel, $fullPath, ['visibility' => 'public']);
                    }
                }
                $mediaStorage->cleanupResolvedPath($resolved);
            }

            Log::info('GenerateImageMetadata: done', $logCtx);
        } catch (\Throwable $e) {
            // Spezieller Fall: eigenes oder OpenAI-Rate-Limit → nur später erneut versuchen, nicht als „kaputt“ markieren.
            if ($e instanceof MediaAiRateLimitException) {
                // OpenAI 429 kann entweder "Rate limit" oder "Quota/Billing exceeded" sein.
                // Quota/Billing ist i. d. R. dauerhaft → nicht endlos releasen und dann "MaxAttemptsExceeded" anzeigen.
                $msg = $e->getMessage();
                $lower = mb_strtolower($msg);
                $isQuotaBilling =
                    str_contains($lower, 'insufficient_quota')
                    || str_contains($lower, 'current quota')
                    || str_contains($lower, 'exceeded your current quota')
                    || (str_contains($lower, 'quota') && str_contains($lower, 'billing'))
                    || str_contains($lower, 'check your plan and billing details');

                if ($isQuotaBilling) {
                    Log::error('GenerateImageMetadata: openai quota/billing exceeded (no retry)', [
                        ...$logCtx,
                        'error' => $msg,
                    ]);
                    Log::debug('GenerateImageMetadata: lock release before markAiError (quota/billing)', $logCtx);
                    $lock->release();
                    $media->markAiError(mb_substr($msg, 0, 2000));
                    Log::info('GenerateImageMetadata: markAiError (quota/billing)', $logCtx);

                    return;
                }

                Log::warning('GenerateImageMetadata: rate limit, will retry later', [
                    ...$logCtx,
                    'error' => $msg,
                ]);
                Log::debug('GenerateImageMetadata: lock release before retry', $logCtx);
                $lock->release();
                try {
                    $media->markAiQueued();
                } catch (QueryException $e) {
                    Log::warning('GenerateImageMetadata: could not mark queued (rate limit retry)', [
                        ...$logCtx,
                        'error' => $e->getMessage(),
                    ]);
                }
                $this->release(60);
                Log::info('GenerateImageMetadata: job released (rate limit)', $logCtx + ['release_seconds' => 60]);

                return;
            }
            $isLastAttempt = $this->attempts() >= $this->tries;
            if ($isLastAttempt) {
                try {
                    Log::debug('GenerateImageMetadata: markAiError begin', $logCtx);
                    $media->markAiError(mb_substr($e->getMessage(), 0, 2000));
                    Log::info('GenerateImageMetadata: markAiError done', $logCtx);
                } catch (QueryException $qe) {
                    Log::warning('GenerateImageMetadata: could not mark error', [...$logCtx, 'error' => $qe->getMessage()]);
                }
            }

            Log::error('GenerateImageMetadata: failed', [
                ...$logCtx,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'is_last_attempt' => $isLastAttempt,
            ]);

            if ($isLastAttempt) {
                return;
            }
            throw $e;
        } finally {
            Log::debug('GenerateImageMetadata: lock release in finally', $logCtx);
            $lock->release();
        }
    }

    public function failed(\Throwable $e): void
    {
        try {
            $this->media->markAiError(mb_substr($e->getMessage(), 0, 2000));
        } catch (\Throwable $qe) {
            Log::warning('GenerateImageMetadata: failed() could not mark error', ['media_id' => $this->media->id]);
        }
        Log::error('GenerateImageMetadata: job failed after all retries', ['media_id' => $this->media->id, 'error' => $e->getMessage()]);
    }

    protected function hasValue(mixed $value): bool
    {
        return ! ($value === null || $value === '');
    }

    /**
     * Befüllt IPTC-Ortfelder aus der gepflegten Veranstaltungsadresse (wie in der Medienbearbeitung).
     */
    protected function applyPlannedEventVenueIptcDefaults(NewsItemMedia $media, bool $hasPlannedEventMatch): void
    {
        $news = $media->relationLoaded('newsItem') ? $media->newsItem : $media->newsItem()->first();
        if (! $news || ! (int) ($news->planned_event_id ?? 0)) {
            return;
        }

        $event = $news->relationLoaded('plannedEvent')
            ? $news->plannedEvent
            : PlannedEvent::query()->find((int) $news->planned_event_id);
        if (! $event || ! $event->hasCompleteVenueAddress()) {
            return;
        }

        $city = trim((string) $event->venue_city);
        $state = trim((string) $event->venue_state);
        $country = trim((string) ($event->venue_country ?? ''));
        if ($country === '') {
            $country = 'Deutschland';
        }
        $cc = strtoupper(trim((string) ($event->venue_country_code ?? '')));
        if ($cc === '') {
            $cc = 'DE';
        }

        if ($city !== '' && ($hasPlannedEventMatch || ! $this->hasValue($media->city))) {
            $media->city = $city;
        }
        if ($state !== '' && ($hasPlannedEventMatch || ! $this->hasValue($media->state))) {
            $media->state = $state;
        }
        if ($hasPlannedEventMatch || ! $this->hasValue($media->country)) {
            $media->country = $country;
        }
        if ($hasPlannedEventMatch || ! $this->hasValue($media->country_code)) {
            $media->country_code = $cc;
        }
    }
}
