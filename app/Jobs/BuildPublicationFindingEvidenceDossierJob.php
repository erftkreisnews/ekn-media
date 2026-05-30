<?php

namespace App\Jobs;

use App\Models\MediaPublicationFinding;
use App\Services\Publication\PublicationFindingEvidenceDossierService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class BuildPublicationFindingEvidenceDossierJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 900;

    public function __construct(
        public readonly int $findingId,
    ) {}

    public function handle(PublicationFindingEvidenceDossierService $service): void
    {
        $finding = MediaPublicationFinding::query()->find($this->findingId);
        if ($finding === null) {
            return;
        }

        try {
            $service->build($finding);
        } catch (\Throwable $e) {
            Log::error('BuildPublicationFindingEvidenceDossierJob.failed', [
                'finding_id' => $this->findingId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
