<?php

namespace App\Services\Ingest;

use App\Models\IngestRenderJob;

/**
 * Verwaiste oder hängende Render-Jobs freigeben, damit neue Jobs für dieselbe Meldung angelegt werden können.
 */
class IngestRenderJobReleaseService
{
    /**
     * Markiert alte aktive Jobs als fehlgeschlagen (nur für eine Meldung).
     *
     * @return int Anzahl aktualisierter Zeilen
     */
    public function releaseStaleForNewsItem(int $newsItemId): int
    {
        $queuedAfterMin = max(5, (int) config('ingest.stale_job.queued_after_minutes', 30));
        $activeAfterMin = max(15, (int) config('ingest.stale_job.rendering_uploading_after_minutes', 300));

        $now = now();
        $msgQueued = 'Automatisch beendet: zu lange nur „queued“ (Worker/Queue prüfen). Bitte erneut starten.';
        $msgActive = 'Automatisch beendet: kein Abschluss innerhalb des Zeitfensters (hängender Job). Bitte erneut starten.';

        $n = 0;

        $n += IngestRenderJob::query()
            ->where('news_item_id', $newsItemId)
            ->where('status', IngestRenderJob::STATUS_QUEUED)
            ->where('created_at', '<', $now->copy()->subMinutes($queuedAfterMin))
            ->update([
                'status' => IngestRenderJob::STATUS_FAILED,
                'error_message' => $msgQueued,
            ]);

        $n += IngestRenderJob::query()
            ->where('news_item_id', $newsItemId)
            ->whereIn('status', [
                IngestRenderJob::STATUS_RENDERING,
                IngestRenderJob::STATUS_UPLOADING,
            ])
            ->where('updated_at', '<', $now->copy()->subMinutes($activeAfterMin))
            ->update([
                'status' => IngestRenderJob::STATUS_FAILED,
                'error_message' => $msgActive,
            ]);

        return $n;
    }
}
