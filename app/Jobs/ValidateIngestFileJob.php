<?php

namespace App\Jobs;

use App\Jobs\GenerateIngestPreviewJob;
use App\Models\IngestFile;
use App\Services\Ingest\IngestFfprobeService;
use App\Services\Ingest\IngestImageThumbnailService;
use App\Services\Ingest\IngestVideoPosterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ValidateIngestFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected int $ingestFileId
    ) {}

    public function handle(IngestFfprobeService $ffprobe, IngestImageThumbnailService $thumbs, IngestVideoPosterService $posters): void
    {
        $file = IngestFile::find($this->ingestFileId);
        if (! $file) {
            return;
        }

        $file->update(['status' => IngestFile::STATUS_VALIDATING]);

        $path = $file->absolute_path;
        if (! is_file($path)) {
            $file->update([
                'status' => IngestFile::STATUS_REJECTED,
                'error_message' => 'Datei nach Import nicht mehr vorhanden.',
            ]);

            return;
        }

        $result = $ffprobe->analyze($path);
        if (! $result['ok']) {
            $file->update([
                'status' => IngestFile::STATUS_REJECTED,
                'error_message' => $result['error'] ?? 'Validierung fehlgeschlagen.',
            ]);

            return;
        }

        $d = $result['data'];
        $file->update([
            'status' => IngestFile::STATUS_VALIDATED,
            'duration_s' => $d['duration_s'] ?? null,
            'width' => $d['width'] ?? null,
            'height' => $d['height'] ?? null,
            'fps' => $d['fps'] ?? null,
            'codec' => $d['codec'] ?? null,
            'ffprobe_json' => $d['ffprobe_json'] ?? null,
            'error_message' => null,
        ]);

        if ($file->isIngestImageFile()) {
            $thumbs->ensureThumbnail($file->fresh(), force: true);

            return;
        }

        if ($file->fresh()?->isIngestVideoFile()) {
            $posters->ensurePoster($file->fresh(), force: false);
        }

        if ($file->fresh()?->isIngestVideoCandidate() && config('ingest.preview.auto_after_validation', true)) {
            $file->update([
                'status' => IngestFile::STATUS_PREVIEW_GENERATING,
                'preview_status' => IngestFile::PREVIEW_STATUS_GENERATING,
                'preview_error_message' => null,
            ]);
            GenerateIngestPreviewJob::dispatch($file->id);
        }
    }
}
