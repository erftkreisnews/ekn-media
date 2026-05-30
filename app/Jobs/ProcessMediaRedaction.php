<?php

namespace App\Jobs;

use App\Models\NewsItemMedia;
use App\Services\MediaStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

#[DeleteWhenMissingModels]
class ProcessMediaRedaction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected NewsItemMedia $media
    ) {}

    public function handle(): void
    {
        if (! $this->media->isImage()) {
            return;
        }

        $mediaStorage = app(MediaStorage::class);
        $disk = $mediaStorage->activeDisk();
        $basePath = $this->media->path;
        $resolvedBase = $mediaStorage->resolveReadableLocalPath($basePath);
        $fullPath = $resolvedBase['path'] ?? null;
        if (! is_string($fullPath) || ! is_file($fullPath) || ! is_readable($fullPath)) {
            Log::warning('ProcessMediaRedaction: original not readable', ['media_id' => $this->media->id, 'path' => $basePath]);
            $this->media->update([
                'redaction_status' => NewsItemMedia::REDACTION_FAILED,
                'redacted_at' => null,
                'redaction_error' => 'Originalbild nicht lesbar oder Datei fehlt.',
            ]);

            return;
        }

        $prefixOriginal = config('redaction.original_prefix', 'media/original');
        $prefixRedacted = config('redaction.redacted_prefix', 'media/redacted');
        $useStructuredDerived = $mediaStorage->isStructuredNewsMediaPath($basePath);
        $originalRel = $this->media->original_path;
        $resolvedOriginal = null;
        if (empty($originalRel)) {
            $ext = pathinfo($basePath, PATHINFO_EXTENSION) ?: 'jpg';
            $originalRel = $useStructuredDerived
                ? $mediaStorage->generateDerivedMediaPath($this->media->newsItem, $this->media, $this->media->original_name ?: basename($basePath), 'original')
                : sprintf('%s/%s/%s.%s', $prefixOriginal, $this->media->news_item_id, $this->media->id, $ext);
            $mediaStorage->putFromLocalFile($originalRel, $fullPath, ['visibility' => 'public']);
            $this->media->update(['original_path' => $originalRel]);
            $originalRel = $this->media->fresh()->original_path;
        }
        $resolvedOriginal = $mediaStorage->resolveReadableLocalPath($originalRel);
        $originalInputPath = $resolvedOriginal['path'] ?? null;
        if (! is_string($originalInputPath) || ! is_file($originalInputPath) || ! is_readable($originalInputPath)) {
            $this->media->update([
                'redaction_status' => NewsItemMedia::REDACTION_FAILED,
                'redacted_at' => null,
                'redaction_error' => 'Originalbild nicht lesbar oder Datei fehlt.',
            ]);

            return;
        }

        $extOut = pathinfo($basePath, PATHINFO_EXTENSION) ?: 'jpg';
        $redactedRel = $useStructuredDerived
            ? $mediaStorage->generateDerivedMediaPath($this->media->newsItem, $this->media, $this->media->original_name ?: basename($basePath), 'redacted')
            : sprintf('%s/%s/%s.%s', $prefixRedacted, $this->media->news_item_id, $this->media->id, $extOut);
        $redactedFull = tempnam(sys_get_temp_dir(), 'redacted_');
        if ($redactedFull === false) {
            return;
        }
        $redactedFull .= '.'.$extOut;

        $python = config('redaction.python_bin') ?: (rtrim(config('redaction.ai_worker_path'), '/').'/env/bin/python');
        $scriptPath = rtrim(config('redaction.ai_worker_path'), '/').'/scripts/'.config('redaction.script_name', 'detect_and_redact.py');
        $modelPath = config('redaction.model_path') ?: (rtrim(config('redaction.ai_worker_path'), '/').'/models/plate_detector.onnx');
        $faceModelPath = config('redaction.face_model_path', '');
        $method = $this->media->redaction_method ?: config('redaction.default_method', 'blur');
        $boxesJson = $this->media->redaction_boxes ? json_encode($this->media->redaction_boxes) : '';

        $blurStrength = (int) config('redaction.blur_strength', 151);
        $args = [
            $python,
            $scriptPath,
            '--input', $originalInputPath,
            '--output', $redactedFull,
            '--method', $method,
            '--blur-strength', (string) $blurStrength,
        ];
        if ($modelPath && is_file($modelPath)) {
            $args[] = '--model';
            $args[] = $modelPath;
        }
        if ($faceModelPath !== '' && is_file($faceModelPath)) {
            $args[] = '--face-model';
            $args[] = $faceModelPath;
        }
        if ($boxesJson !== '' && $boxesJson !== '[]') {
            $args[] = '--boxes-json';
            $args[] = $boxesJson;
        }

        $process = new Process($args);
        $process->setTimeout(120);
        $process->run();
        $stdout = $process->getOutput();
        $stderr = $process->getErrorOutput();

        $decoded = null;
        $lines = array_filter(array_map('trim', explode("\n", $stdout)));
        foreach (array_reverse($lines) as $line) {
            if ($line !== '' && $line[0] === '{') {
                $decoded = json_decode($line, true);
                if (is_array($decoded)) {
                    break;
                }
            }
        }
        if ($decoded === null && preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $stdout, $m)) {
            $decoded = json_decode($m[0], true);
        }

        $userError = null;
        $isNoDetection = false;
        if (is_array($decoded) && ! empty($decoded['error'])) {
            $err = $decoded['error'];
            $isNoDetection = str_contains(strtolower($err), 'no boxes') || str_contains(strtolower($err), 'no detection');
            $userError = $isNoDetection
                ? null
                : $err;
        } elseif ($stderr !== '') {
            $userError = strlen($stderr) > 500 ? substr($stderr, 0, 497).'…' : $stderr;
        }
        $userError = $userError ?: 'Skript-Fehler (Exit-Code '.$process->getExitCode().'). Details: storage/logs/laravel.log';

        if ($isNoDetection) {
            Log::info('ProcessMediaRedaction: nothing to redact (no detection)', [
                'media_id' => $this->media->id,
            ]);
            $this->media->update([
                'redaction_status' => NewsItemMedia::REDACTION_DONE,
                'redacted_path' => null,
                'redaction_error' => null,
                'redacted_at' => now(),
            ]);

            return;
        }

        if ($process->getExitCode() !== 0 || empty($decoded['success'])) {
            Log::warning('ProcessMediaRedaction: auto-redaction failed (fail-safe)', [
                'media_id' => $this->media->id,
                'exit_code' => $process->getExitCode(),
                'stdout' => $stdout,
                'stderr' => $stderr,
                'decoded_success' => $decoded['success'] ?? null,
                'decoded_error' => $decoded['error'] ?? null,
                'boxes_count' => $decoded['boxes'] ?? 0,
                'method' => $method,
            ]);
            $this->media->update([
                'redaction_status' => NewsItemMedia::REDACTION_FAILED,
                'redacted_path' => null,
                'redacted_at' => null,
                'redaction_error' => $userError,
            ]);

            return;
        }

        $boxes = $decoded['boxes'] ?? [];
        $mediaStorage->putFromLocalFile($redactedRel, $redactedFull, ['visibility' => 'public']);
        $this->media->update([
            'redaction_status' => NewsItemMedia::REDACTION_DONE,
            'redacted_path' => $redactedRel,
            'redaction_method' => $decoded['redaction_method'] ?? $method,
            'redaction_boxes' => $boxes,
            'redacted_at' => now(),
            'redaction_error' => null,
        ]);

        // Vorschau (WebP) aus der redigierten Datei neu erzeugen, damit überall geblurte Version erscheint
        $previewPath = $this->media->preview_path;
        if ($previewPath !== null && $previewPath !== '') {
            $watermarkPath = public_path('images/erftkreis-news-logo.png');
            if (is_file($watermarkPath)) {
                try {
                    GenerateNewsMediaPreview::dispatchSync($mediaStorage->activeDiskName(), $redactedRel, $previewPath, $watermarkPath);
                } catch (\Throwable $e) {
                    Log::warning('ProcessMediaRedaction: preview regeneration failed', [
                        'media_id' => $this->media->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Log::info('ProcessMediaRedaction: auto-redaction completed', [
            'media_id' => $this->media->id,
            'boxes_count' => count($boxes),
            'method' => $this->media->redaction_method,
        ]);

        @unlink($redactedFull);
        $mediaStorage->cleanupResolvedPath($resolvedBase);
        $mediaStorage->cleanupResolvedPath($resolvedOriginal);
    }
}
