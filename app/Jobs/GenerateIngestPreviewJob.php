<?php

namespace App\Jobs;

use App\Models\IngestFile;
use App\Services\Ingest\IngestPreviewService;
use App\Services\MediaStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GenerateIngestPreviewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public function __construct(
        protected int $ingestFileId
    ) {}

    public function failed(?\Throwable $e = null): void
    {
        $file = IngestFile::find($this->ingestFileId);
        if (! $file || $file->status === IngestFile::STATUS_USED) {
            return;
        }

        if ($file->status !== IngestFile::STATUS_PREVIEW_GENERATING) {
            return;
        }

        $msg = $e !== null ? $e->getMessage() : 'Vorschau-Job fehlgeschlagen.';
        $file->update([
            'status' => IngestFile::STATUS_VALIDATED,
            'preview_status' => null,
            'preview_error_message' => $msg,
        ]);
    }

    public function handle(IngestPreviewService $preview, MediaStorage $mediaStorage): void
    {
        $file = IngestFile::find($this->ingestFileId);
        if (! $file) {
            return;
        }

        if ($file->status === IngestFile::STATUS_USED || $file->status === IngestFile::STATUS_RENDERING) {
            return;
        }

        if (! $file->isIngestVideoCandidate()) {
            return;
        }

        $previewPath = filled($file->preview_path) ? (string) $file->preview_path : null;
        if ($previewPath !== null) {
            $previewDisk = $mediaStorage->ingestPreviewDiskName();
            if ($mediaStorage->ingestPreviewDisk()->exists($previewPath)) {
                $file->update([
                    'status' => IngestFile::STATUS_PREVIEW_READY,
                    'preview_status' => IngestFile::PREVIEW_STATUS_READY,
                    'preview_error_message' => null,
                ]);

                return;
            }

            Log::info('ingest.preview.stale_path_regenerate', [
                'ingest_file_id' => $file->id,
                'preview_path' => $previewPath,
                'expected_disk' => $previewDisk,
            ]);
            $mediaStorage->delete($previewPath);
            $file->update(['preview_path' => null]);
        }

        $file->update([
            'status' => IngestFile::STATUS_PREVIEW_GENERATING,
            'preview_status' => IngestFile::PREVIEW_STATUS_GENERATING,
            'preview_error_message' => null,
        ]);

        $result = $preview->generateForIngestFile($file);
        if (! ($result['ok'] ?? false)) {
            $file->update([
                'status' => IngestFile::STATUS_VALIDATED,
                'preview_status' => null,
                'preview_error_message' => $result['error'] ?? 'Vorschau fehlgeschlagen.',
            ]);

            return;
        }

        $localPath = $result['local_path'] ?? null;
        if (! is_string($localPath) || ! is_file($localPath)) {
            $file->update([
                'status' => IngestFile::STATUS_VALIDATED,
                'preview_error_message' => 'Vorschau-Datei fehlt nach ffmpeg.',
            ]);

            return;
        }

        $storagePath = 'ingest-previews/'.now()->format('Y/m').'/ingest-'.$file->id.'-'.Str::lower(Str::random(8)).'.mp4';
        $previewDisk = $mediaStorage->ingestPreviewDiskName();
        $uploadOptions = [
            'visibility' => 'public',
            'ContentType' => 'video/mp4',
        ];

        try {
            if (! $mediaStorage->putFromLocalFile($storagePath, $localPath, $uploadOptions, $previewDisk)) {
                throw new \RuntimeException('Upload der Vorschau in den Medien-Speicher fehlgeschlagen (Disk: '.$previewDisk.').');
            }

            if (! $mediaStorage->resolveReadableLocalPath($storagePath)) {
                throw new \RuntimeException('Vorschau nach Upload nicht lesbar (Disk: '.$previewDisk.').');
            }

            $file->update([
                'status' => IngestFile::STATUS_PREVIEW_READY,
                'preview_path' => $storagePath,
                'preview_status' => IngestFile::PREVIEW_STATUS_READY,
                'preview_error_message' => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('ingest.preview.upload_failed', ['ingest_file_id' => $file->id, 'e' => $e->getMessage()]);
            $file->update([
                'status' => IngestFile::STATUS_VALIDATED,
                'preview_status' => null,
                'preview_error_message' => $e->getMessage(),
            ]);
        } finally {
            if (is_file($localPath)) {
                @unlink($localPath);
            }
        }
    }
}
