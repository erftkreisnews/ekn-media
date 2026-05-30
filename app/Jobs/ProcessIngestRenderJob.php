<?php

namespace App\Jobs;

use App\Models\IngestFile;
use App\Models\IngestRenderJob;
use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use App\Services\Ingest\IngestDirectoryService;
use App\Services\Ingest\IngestPostRenderCleanupService;
use App\Services\Ingest\IngestRenderChunkPlanner;
use App\Services\Ingest\IngestRenderService;
use App\Services\Ingest\IngestSendefassungFilenameService;
use App\Services\MediaStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ProcessIngestRenderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Bei vielen Clips kann das sequentielle Normalisieren/Concat deutlich länger dauern.
    // Sonst wird der Job aus Queue-Sicht abgebrochen, bevor er erfolgreich in Upload kommt.
    public int $timeout = 14400;

    public function __construct(
        protected int $ingestRenderJobId
    ) {}

    /**
     * Wenn der Queue-Job abbricht (Timeout, OOM/SIGKILL, Exception außerhalb unserer Returns),
     * darf der IngestRenderJob nicht dauerhaft auf „rendering“ stehen bleiben.
     */
    public function failed(?\Throwable $e = null): void
    {
        $job = IngestRenderJob::find($this->ingestRenderJobId);
        if (! $job || $job->status === IngestRenderJob::STATUS_COMPLETED) {
            return;
        }

        $msg = $e !== null ? $e->getMessage() : 'Render-Job fehlgeschlagen.';
        $job->update([
            'status' => IngestRenderJob::STATUS_FAILED,
            'error_message' => $msg,
        ]);
    }

    public function handle(
        IngestRenderService $render,
        MediaStorage $mediaStorage,
        IngestDirectoryService $directories,
        IngestSendefassungFilenameService $sendefassungNames,
        IngestRenderChunkPlanner $chunkPlanner,
        IngestPostRenderCleanupService $postRenderCleanup,
    ): void
    {
        $job = IngestRenderJob::find($this->ingestRenderJobId);
        if (! $job) {
            return;
        }

        $job->update(['status' => IngestRenderJob::STATUS_RENDERING, 'error_message' => null]);

        $ids = $job->ingest_file_ids;
        if (! is_array($ids) || $ids === []) {
            $job->update(['status' => IngestRenderJob::STATUS_FAILED, 'error_message' => 'Keine Clip-IDs.']);

            return;
        }

        $newsItem = NewsItem::find($job->news_item_id);
        if (! $newsItem) {
            $job->update(['status' => IngestRenderJob::STATUS_FAILED, 'error_message' => 'Meldung nicht gefunden.']);

            return;
        }

        $clipsOrdered = [];
        foreach ($ids as $id) {
            $f = IngestFile::find((int) $id);
            if (! $f || $f->status !== IngestFile::STATUS_ASSIGNED || (int) $f->news_item_id !== (int) $newsItem->id) {
                $job->update(['status' => IngestRenderJob::STATUS_FAILED, 'error_message' => 'Ungültige oder nicht zugeordnete Clips.']);

                return;
            }
            if (! is_file($f->absolute_path)) {
                $job->update(['status' => IngestRenderJob::STATUS_FAILED, 'error_message' => 'Clip-Datei fehlt: #'.$f->id]);

                return;
            }
            $clipsOrdered[] = [
                'id' => (int) $f->id,
                'path' => (string) $f->absolute_path,
                'duration_s' => $f->duration_s !== null ? (float) $f->duration_s : null,
            ];
        }

        $chunks = $chunkPlanner->planChunks($clipsOrdered);
        if (count($chunks) > 1) {
            Log::info('ingest.render.split_into_parts', [
                'ingest_render_job_id' => $job->id,
                'news_item_id' => $newsItem->id,
                'parts' => count($chunks),
                'clips' => count($clipsOrdered),
                'max_output_bytes' => $chunkPlanner->maxOutputBytes(),
            ]);
        }

        $renderedDir = $directories->path('rendered');
        $createdMediaIds = [];
        $firstMedia = null;

        foreach ($chunks as $chunkIndex => $chunk) {
            $chunkIds = array_map(static fn (array $c): int => (int) $c['id'], $chunk);
            $paths = array_map(static fn (array $c): string => (string) $c['path'], $chunk);

            $naming = $sendefassungNames->assignForNewSendefassung($newsItem);
            $outName = $naming['local_basename'];
            $outPath = rtrim($renderedDir, '/').'/'.$outName;

            $result = $render->renderConcatToMp4($paths, $outPath);
            if (! $result['ok']) {
                $job->update([
                    'status' => IngestRenderJob::STATUS_FAILED,
                    'error_message' => ($result['error'] ?? 'Render fehlgeschlagen.')
                        .(count($chunks) > 1 ? ' (Teil '.($chunkIndex + 1).'/'.count($chunks).')' : ''),
                ]);

                return;
            }

            $job->update(['local_output_path' => $outPath, 'status' => IngestRenderJob::STATUS_UPLOADING]);

            $media = null;

            try {
                DB::beginTransaction();

                $sortOrder = (int) ($newsItem->media()->max('sort_order') ?? 0);
                $sortOrder++;
                $originalName = $naming['original_name'];

                $media = $newsItem->media()->create([
                    'type' => 'video',
                    'path' => 'news-media/.pending',
                    'original_name' => $originalName,
                    'sort_order' => $sortOrder,
                    'is_visible' => true,
                    'versand' => true,
                ]);

                $targetPath = $mediaStorage->generateMediaPath(
                    $newsItem,
                    $media,
                    $originalName,
                    'sendefassung'
                );

                $ok = $mediaStorage->putFromLocalFile($targetPath, $outPath);
                if (! $ok) {
                    throw new \RuntimeException('S3-/Storage-Upload fehlgeschlagen.');
                }

                $media->update(['path' => $targetPath]);

                foreach ($chunkIds as $chunkId) {
                    IngestFile::where('id', $chunkId)->update([
                        'status' => IngestFile::STATUS_USED,
                        'final_news_item_media_id' => $media->id,
                        'is_selected' => false,
                        'selection_order' => null,
                        'trim_in_seconds' => null,
                        'trim_out_seconds' => null,
                    ]);
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('ingest.finalize_failed', [
                    'e' => $e->getMessage(),
                    'part' => $chunkIndex + 1,
                    'parts' => count($chunks),
                ]);
                $job->update([
                    'status' => IngestRenderJob::STATUS_FAILED,
                    'error_message' => $e->getMessage()
                        .(count($chunks) > 1 ? ' (Teil '.($chunkIndex + 1).'/'.count($chunks).')' : ''),
                ]);

                return;
            }

            if ($media instanceof NewsItemMedia) {
                $createdMediaIds[] = $media->id;
                $firstMedia ??= $media;
                ExtractVideoMetadata::dispatch($media);
                GenerateVideoPoster::dispatch($media);
                GenerateVideoStills::dispatch($media);
            }

            if (is_file($outPath)) {
                @unlink($outPath);
            }
        }

        $job->update([
            'status' => IngestRenderJob::STATUS_COMPLETED,
            'final_news_item_media_id' => $firstMedia?->id,
            'local_output_path' => null,
            'error_message' => count($createdMediaIds) > 1
                ? 'Sendefassung in '.count($createdMediaIds).' Teile aufgeteilt (je max. '
                .number_format($chunkPlanner->maxOutputBytes() / (1024 * 1024 * 1024), 1, ',', '')
                .' GB geschätzt). Medien-IDs: '.implode(', ', $createdMediaIds)
                : null,
        ]);

        if (config('ingest.archive_raw_after_success', false)) {
            $archive = $directories->path('archive');
            File::ensureDirectoryExists($archive);
        }

        foreach (IngestFile::whereIn('id', $ids)->get() as $f) {
            $absPath = $f->absolute_path;
            if (! is_string($absPath) || $absPath === '') {
                continue;
            }

            if (! $directories->isManagedAbsoluteFile($absPath)) {
                continue;
            }

            if (config('ingest.archive_raw_after_success', false)) {
                $dest = rtrim($directories->path('archive'), '/').'/'.basename($absPath);
                @rename($absPath, $dest);
            } else {
                @unlink($absPath);
            }
        }

        $postRenderCleanup->cleanupAfterSuccessfulRender(array_map('intval', $ids));
    }
}
