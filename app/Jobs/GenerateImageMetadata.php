<?php

namespace App\Jobs;

use App\Exceptions\MediaAiRateLimitException;
use App\Models\NewsItemMedia;
use App\Services\ImageMetadataWriter;
use App\Services\MediaAi\ImageMetadataGenerator;
use App\Services\MediaStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GenerateImageMetadata implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Job maximal 30 Sekunden (API-Timeout + Lock) */
    public int $timeout = 30;

    /**
     * Max. Versuche für die KI-Generierung.
     * Bei OpenAI 429/Rate-Limits wollen wir nicht nach kurzer Zeit final „KI-Fehler“ anzeigen.
     */
    public int $tries = 20;

    public array $backoff = [30, 60, 120, 240, 480, 900];

    public function __construct(
        protected NewsItemMedia $media
    ) {
        $this->onQueue('ai');
        // Property-Defaults dürfen keine Funktionsaufrufe enthalten, daher Env erst hier setzen.
        $this->tries = (int) env('MEDIA_AI_JOB_MAX_TRIES', $this->tries);
    }

    public function handle(ImageMetadataGenerator $generator): void
    {
        $media = $this->media;
        if ($media->type !== 'image') {
            Log::info('GenerateImageMetadata: skip, not image', ['media_id' => $media->id]);

            return;
        }

        $lock = Cache::lock('ai:media:'.$media->id, 600);
        if (! $lock->get()) {
            Log::info('GenerateImageMetadata: lock busy, release for retry', ['media_id' => $media->id]);
            $this->release(60);

            return;
        }

        try {
            $media->markAiRunning();
        } catch (QueryException $e) {
            Log::warning('GenerateImageMetadata: could not mark running', [
                'media_id' => $media->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $result = $generator->generate($media);

            // Nur leere Felder befüllen
            if (! $this->hasValue($media->image_title) && ! empty($result['image_title'] ?? null)) {
                $media->image_title = $result['image_title'];
            }
            if (! $this->hasValue($media->photographer) && array_key_exists('photographer', $result)) {
                $media->photographer = $result['photographer'];
            }
            if (! $this->hasValue($media->caption) && ! empty($result['caption'] ?? null)) {
                $media->caption = $result['caption'];
            }
            if (! $this->hasValue($media->media_keywords) && ! empty($result['keywords'] ?? [])) {
                $media->media_keywords = implode(', ', $result['keywords']);
            }
            if (! $this->hasValue($media->description) && array_key_exists('description', $result)) {
                $media->description = $result['description'];
            }

            $media->markAiDone([
                'ai_payload' => $result,
                'ai_model' => config('media_ai.vision_model', 'gpt-4.1-mini'),
                'ai_suggested_at' => now(),
            ]);

            $resolved = app(MediaStorage::class)->resolveReadableLocalPath($media->path);
            $fullPath = $resolved['path'] ?? null;
            if (is_string($fullPath) && is_file($fullPath)) {
                ImageMetadataWriter::write($fullPath, [
                    'image_title' => $media->image_title,
                    'photographer' => $media->photographer,
                    'caption' => $media->caption,
                    'credit' => config('newsdesk.iptc_credit', 'Erftkreis News'),
                    'copyright' => config('newsdesk.iptc_copyright', '© Erftkreis News. Alle Rechte vorbehalten.'),
                ]);
                if (($resolved['temporary'] ?? false) === true) {
                    app(MediaStorage::class)->putFromLocalFile($media->path, $fullPath, ['visibility' => 'public']);
                }
            }
            app(MediaStorage::class)->cleanupResolvedPath($resolved);

            Log::info('GenerateImageMetadata: done', ['media_id' => $media->id]);
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
                        'media_id' => $media->id,
                        'attempt' => $this->attempts(),
                        'error' => $msg,
                    ]);
                    $lock->release();
                    $media->markAiError(mb_substr($msg, 0, 2000));

                    return;
                }

                Log::warning('GenerateImageMetadata: rate limit, will retry later', [
                    'media_id' => $media->id,
                    'attempt' => $this->attempts(),
                    'error' => $msg,
                ]);
                $lock->release();
                $this->release(60);

                return;
            }
            $isLastAttempt = $this->attempts() >= $this->tries;
            if ($isLastAttempt) {
                try {
                    $media->markAiError(mb_substr($e->getMessage(), 0, 2000));
                } catch (QueryException $qe) {
                    Log::warning('GenerateImageMetadata: could not mark error', ['media_id' => $media->id, 'error' => $qe->getMessage()]);
                }
            }

            Log::error('GenerateImageMetadata: failed', [
                'media_id' => $media->id,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($isLastAttempt) {
                return;
            }
            throw $e;
        } finally {
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
}
