<?php

namespace App\Jobs;

use App\Models\IngestFile;
use App\Models\IngestRenderJob;
use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use App\Services\Ingest\IngestDirectoryService;
use App\Services\Ingest\IngestRenderService;
use App\Services\MediaStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessIngestRenderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 7200;

    public function __construct(
        protected int $ingestRenderJobId
    ) {}

    public function handle(IngestRenderService $render, MediaStorage $mediaStorage, IngestDirectoryService $directories): void
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

        $paths = [];
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
            $paths[] = $f->absolute_path;
        }

        $renderedDir = $directories->path('rendered');
        $outName = 'ingest_final_'.$job->id.'_'.now()->format('Ymd_His').'.mp4';
        $outPath = rtrim($renderedDir, '/').'/'.$outName;

        $result = $render->renderConcatToMp4($paths, $outPath);
        if (! $result['ok']) {
            $job->update([
                'status' => IngestRenderJob::STATUS_FAILED,
                'error_message' => $result['error'] ?? 'Render fehlgeschlagen.',
            ]);

            return;
        }

        $job->update(['local_output_path' => $outPath, 'status' => IngestRenderJob::STATUS_UPLOADING]);

        $media = null;

        try {
            DB::beginTransaction();

            $sortOrder = (int) ($newsItem->media()->max('sort_order') ?? 0);
            $sortOrder++;
            $originalName = pathinfo($outName, PATHINFO_FILENAME).'.mp4';

            $media = $newsItem->media()->create([
                'type' => 'video',
                'path' => 'news-media/.pending',
                'original_name' => $originalName,
                'sort_order' => $sortOrder,
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

            $job->update([
                'status' => IngestRenderJob::STATUS_COMPLETED,
                'final_news_item_media_id' => $media->id,
                'error_message' => null,
            ]);

            foreach ($ids as $id) {
                IngestFile::where('id', (int) $id)->update([
                    'status' => IngestFile::STATUS_USED,
                    'final_news_item_media_id' => $media->id,
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ingest.finalize_failed', ['e' => $e->getMessage()]);
            $job->update([
                'status' => IngestRenderJob::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            return;
        }

        if ($media instanceof NewsItemMedia) {
            ExtractVideoMetadata::dispatch($media);
            GenerateVideoPoster::dispatch($media);
        }

        if (is_file($outPath)) {
            @unlink($outPath);
        }
        $job->update(['local_output_path' => null]);

        if (config('ingest.archive_raw_after_success', false)) {
            $archive = $directories->path('archive');
            foreach (IngestFile::whereIn('id', $ids)->get() as $f) {
                if (is_file($f->absolute_path)) {
                    $dest = rtrim($archive, '/').'/'.basename($f->absolute_path);
                    @rename($f->absolute_path, $dest);
                }
            }
        }
    }
}
