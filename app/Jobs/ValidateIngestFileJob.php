<?php

namespace App\Jobs;

use App\Models\IngestFile;
use App\Services\Ingest\IngestFfprobeService;
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

    public function handle(IngestFfprobeService $ffprobe): void
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
            'duration_s' => $d['duration_s'],
            'width' => $d['width'],
            'height' => $d['height'],
            'fps' => $d['fps'],
            'codec' => $d['codec'],
            'ffprobe_json' => $d['ffprobe_json'] ?? null,
            'error_message' => null,
        ]);
    }
}
