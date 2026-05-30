<?php

namespace App\Services\Ingest;

use App\Models\IngestFile;
use App\Services\MediaStorage;
use Illuminate\Support\Facades\Log;

/**
 * Räumt Ingest nach erfolgreichem Finalrender auf — damit kein neues Material mit altem vermischt wird.
 */
final class IngestPostRenderCleanupService
{
    public function __construct(
        protected MediaStorage $mediaStorage,
        protected IngestImageThumbnailService $imageThumbs,
        protected IngestVideoPosterService $videoPosters,
    ) {}

    /**
     * @param  list<int>  $ingestFileIds
     */
    public function cleanupAfterSuccessfulRender(array $ingestFileIds): void
    {
        if (! config('ingest.auto_clear_after_render.enabled', true)) {
            return;
        }

        foreach ($ingestFileIds as $id) {
            $file = IngestFile::find((int) $id);
            if (! $file) {
                continue;
            }

            if (config('ingest.auto_clear_after_render.purge_previews', true)) {
                $this->purgePreview($file);
            }

            if (config('ingest.auto_clear_after_render.purge_posters', true)) {
                if ($file->isIngestImageFile()) {
                    $this->imageThumbs->removeThumbnail($file);
                } elseif ($file->isIngestVideoFile()) {
                    $this->videoPosters->removePoster($file);
                }
            }

            if (config('ingest.auto_clear_after_render.detach_news_item_link', true)) {
                $file->forceFill([
                    'news_item_id' => null,
                    'is_selected' => false,
                    'selection_order' => null,
                ])->save();
            }
        }
    }

    private function purgePreview(IngestFile $file): void
    {
        $previewPath = $file->preview_path;
        if (! is_string($previewPath) || trim($previewPath) === '') {
            return;
        }

        try {
            $this->mediaStorage->delete($previewPath);
        } catch (\Throwable $e) {
            Log::warning('ingest.post_render.preview_delete_failed', [
                'ingest_file_id' => $file->id,
                'path' => $previewPath,
                'error' => $e->getMessage(),
            ]);
        }

        $file->forceFill([
            'preview_path' => null,
            'preview_status' => null,
            'preview_error_message' => null,
        ])->save();
    }
}
