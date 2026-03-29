<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateIngestPreviewJob;
use App\Jobs\ProcessIngestRenderJob;
use App\Models\IngestFile;
use App\Models\IngestRenderJob;
use App\Models\IngestSource;
use App\Models\NewsItem;
use App\Services\Ingest\IngestDirectoryService;
use App\Services\Ingest\IngestTrimValidator;
use App\Services\MediaStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class VideoIngestController extends Controller
{
    public function index(Request $request): View
    {
        $q = IngestFile::query()
            ->with(['source', 'newsItem'])
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }

        if ($request->filled('source_id')) {
            $q->where('ingest_source_id', (int) $request->input('source_id'));
        }

        if ($request->filled('from')) {
            $q->whereDate('created_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $q->whereDate('created_at', '<=', $request->date('to'));
        }

        $this->applyIngestIndexScopeFilters($q, $request);

        $files = $q->paginate(30)->withQueryString();
        $sources = IngestSource::orderBy('name')->get();

        $stats = $this->ingestIndexStats();
        $filterActive = $this->ingestIndexAnyFilterActive($request);

        return view('admin.ingest.index', compact('files', 'sources', 'stats', 'filterActive'));
    }

    /**
     * Zusätzliche GET-Scope-Filter (nur lesend, bestehende Felder).
     *
     * @param  Builder<IngestFile>  $query
     */
    private function applyIngestIndexScopeFilters(Builder $query, Request $request): void
    {
        if ($request->boolean('today')) {
            $query->whereDate('created_at', today());
        }

        if ($request->boolean('validated')) {
            $query->whereIn('status', [
                IngestFile::STATUS_VALIDATED,
                'preview_generating',
                'preview_ready',
                IngestFile::STATUS_ASSIGNED,
                'rendering',
                IngestFile::STATUS_USED,
            ]);
        }

        if ($request->boolean('no_preview')) {
            $query->whereNull('preview_path')
                ->whereNotIn('status', [
                    IngestFile::STATUS_IMPORTED,
                    IngestFile::STATUS_VALIDATING,
                    IngestFile::STATUS_REJECTED,
                    IngestFile::STATUS_FAILED,
                ]);
        }

        if ($request->boolean('errors')) {
            $query->whereIn('status', [
                IngestFile::STATUS_REJECTED,
                IngestFile::STATUS_FAILED,
            ]);
        }

        if ($request->boolean('with_news')) {
            $query->whereNotNull('news_item_id');
        }

        if ($request->boolean('without_news')) {
            $query->whereNull('news_item_id');
        }

        if ($request->boolean('selected')) {
            $query->where('is_selected', true);
        }

        if ($request->boolean('not_selected')) {
            $query->where('is_selected', false);
        }
    }

    private function ingestIndexAnyFilterActive(Request $request): bool
    {
        return $request->filled('status')
            || $request->filled('source_id')
            || $request->filled('from')
            || $request->filled('to')
            || $request->boolean('today')
            || $request->boolean('validated')
            || $request->boolean('no_preview')
            || $request->boolean('errors')
            || $request->boolean('with_news')
            || $request->boolean('without_news')
            || $request->boolean('selected')
            || $request->boolean('not_selected');
    }

    /**
     * Kennzahlen für die Ingest-Übersicht (nur lesende Aggregationen auf ingest_files).
     *
     * @return array<string, int>
     */
    private function ingestIndexStats(): array
    {
        $base = IngestFile::query();

        return [
            'total' => (clone $base)->count(),
            'today' => (clone $base)->whereDate('created_at', today())->count(),
            'validated' => (clone $base)->whereIn('status', [
                IngestFile::STATUS_VALIDATED,
                'preview_generating',
                'preview_ready',
                IngestFile::STATUS_ASSIGNED,
                'rendering',
                IngestFile::STATUS_USED,
            ])->count(),
            'without_preview' => (clone $base)
                ->whereNull('preview_path')
                ->whereNotIn('status', [
                    IngestFile::STATUS_IMPORTED,
                    IngestFile::STATUS_VALIDATING,
                    IngestFile::STATUS_REJECTED,
                    IngestFile::STATUS_FAILED,
                ])
                ->count(),
            'rejected_or_failed' => (clone $base)->whereIn('status', [
                IngestFile::STATUS_REJECTED,
                IngestFile::STATUS_FAILED,
            ])->count(),
            'is_selected' => (clone $base)->where('is_selected', true)->count(),
            'with_news' => (clone $base)->whereNotNull('news_item_id')->count(),
        ];
    }

    public function show(IngestFile $ingestFile): View
    {
        $ingestFile->load(['source', 'newsItem', 'batch']);
        $newsChoices = NewsItem::query()
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get(['id', 'title', 'status']);

        $activeRenderJobForClip = null;
        if ($ingestFile->news_item_id) {
            $activeRenderJobForClip = IngestRenderJob::query()
                ->where('news_item_id', $ingestFile->news_item_id)
                ->whereIn('status', [
                    IngestRenderJob::STATUS_QUEUED,
                    IngestRenderJob::STATUS_RENDERING,
                    IngestRenderJob::STATUS_UPLOADING,
                ])
                ->orderByDesc('id')
                ->get()
                ->first(function (IngestRenderJob $job) use ($ingestFile) {
                    $ids = $job->ingest_file_ids;

                    return is_array($ids) && in_array((int) $ingestFile->id, array_map('intval', $ids), true);
                });
        }

        $statusKey = 'ingest.file_status.'.$ingestFile->status;
        $ingestStatusLabel = __($statusKey);
        if ($ingestStatusLabel === $statusKey) {
            $ingestStatusLabel = (string) $ingestFile->status;
        }

        $ingestPreviewStatusLabel = null;
        if (filled($ingestFile->preview_status ?? null)) {
            $pv = (string) $ingestFile->preview_status;
            $pkey = 'ingest.preview_status.'.$pv;
            $ingestPreviewStatusLabel = __($pkey);
            if ($ingestPreviewStatusLabel === $pkey) {
                $ingestPreviewStatusLabel = $pv;
            }
        }

        return view('admin.ingest.show', [
            'ingestFile' => $ingestFile,
            'newsChoices' => $newsChoices,
            'activeRenderJobForClip' => $activeRenderJobForClip,
            'ingestPreviewMaxSeconds' => (int) config('ingest.preview.max_seconds', 60),
            'ingestStatusLabel' => $ingestStatusLabel,
            'ingestPreviewStatusLabel' => $ingestPreviewStatusLabel,
        ]);
    }

    public function assign(Request $request, IngestFile $ingestFile): RedirectResponse
    {
        $data = $request->validate([
            'news_item_id' => ['nullable', 'exists:news_items,id'],
            'selection_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        if ($ingestFile->status === IngestFile::STATUS_USED) {
            return back()->with('error', 'Clip wurde bereits in einen Sendeschnitt übernommen.');
        }

        if ($ingestFile->status === IngestFile::STATUS_PREVIEW_GENERATING) {
            return back()->with('error', 'Vorschau wird erzeugt – bitte kurz warten.');
        }

        if ($ingestFile->status === IngestFile::STATUS_RENDERING) {
            return back()->with('error', 'Sendefassung wird gerade gerendert – bitte warten.');
        }

        $assignable = in_array($ingestFile->status, [
            IngestFile::STATUS_VALIDATED,
            IngestFile::STATUS_PREVIEW_READY,
            IngestFile::STATUS_ASSIGNED,
        ], true);

        if (! $assignable) {
            return back()->with('error', 'Nur validierte Clips können einer Meldung zugeordnet werden.');
        }

        if (empty($data['news_item_id'])) {
            $payload = [
                'news_item_id' => null,
                'selection_order' => null,
                'is_selected' => false,
                'trim_in_seconds' => null,
                'trim_out_seconds' => null,
                'status' => $ingestFile->preview_path ? IngestFile::STATUS_PREVIEW_READY : IngestFile::STATUS_VALIDATED,
            ];
            if (filled($ingestFile->preview_path)) {
                $payload['preview_status'] = IngestFile::PREVIEW_STATUS_READY;
                $payload['preview_error_message'] = null;
            }
            $ingestFile->update($payload);

            return back()->with('status', 'Zuordnung entfernt.');
        }

        $newId = (int) $data['news_item_id'];
        $oldId = $ingestFile->news_item_id !== null ? (int) $ingestFile->news_item_id : null;

        $payload = [
            'news_item_id' => $newId,
            'selection_order' => isset($data['selection_order']) ? (int) $data['selection_order'] : null,
            'status' => IngestFile::STATUS_ASSIGNED,
        ];

        if ($oldId !== null && $oldId !== $newId) {
            $payload['is_selected'] = false;
            $payload['trim_in_seconds'] = null;
            $payload['trim_out_seconds'] = null;
        }

        $ingestFile->update($payload);

        return back()->with('status', 'Meldung zugeordnet.');
    }

    public function updateTrim(Request $request, IngestFile $ingestFile, IngestTrimValidator $trimValidator): RedirectResponse
    {
        if (in_array($ingestFile->status, [
            IngestFile::STATUS_USED,
            IngestFile::STATUS_RENDERING,
            IngestFile::STATUS_PREVIEW_GENERATING,
        ], true)) {
            return back()->with('error', 'Trim kann in diesem Status nicht geändert werden.');
        }

        if (! in_array($ingestFile->status, [
            IngestFile::STATUS_VALIDATED,
            IngestFile::STATUS_PREVIEW_READY,
            IngestFile::STATUS_ASSIGNED,
        ], true)) {
            return back()->with('error', 'Trim nur für validierte oder zugeordnete Clips.');
        }

        if (! is_file($ingestFile->absolute_path)) {
            return back()->with('error', 'Quelldatei fehlt.');
        }

        if ($request->boolean('reset_trim')) {
            $ingestFile->update([
                'trim_in_seconds' => null,
                'trim_out_seconds' => null,
            ]);

            return back()->with('status', 'Trim zurückgesetzt (ganzer Clip wird gerendert).');
        }

        $data = $request->validate([
            'trim_in_seconds' => ['nullable', 'string', 'max:64'],
            'trim_out_seconds' => ['nullable', 'string', 'max:64'],
            'selection_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $duration = $ingestFile->duration_s !== null ? (float) $ingestFile->duration_s : null;
        $r = $trimValidator->validate(
            $data['trim_in_seconds'] ?? null,
            $data['trim_out_seconds'] ?? null,
            $duration
        );

        if (! $r['ok']) {
            return back()->with('error', $r['error'] ?? 'Trim ungültig.')->withInput();
        }

        $updates = [
            'trim_in_seconds' => $r['trim_in'],
            'trim_out_seconds' => $r['trim_out'],
        ];

        if (array_key_exists('selection_order', $data) && $data['selection_order'] !== null) {
            $updates['selection_order'] = (int) $data['selection_order'];
        }

        $ingestFile->update($updates);

        return back()->with('status', 'Schnitt und Reihenfolge gespeichert. Finalrender nutzt das Originalmaterial mit diesen In/Out-Zeiten.');
    }

    public function toggleFinalSelection(IngestFile $ingestFile): RedirectResponse
    {
        if ($ingestFile->status !== IngestFile::STATUS_ASSIGNED) {
            return back()->with('error', 'Nur zugeordnete Clips können für den Finalschnitt markiert werden.');
        }

        if (! $ingestFile->news_item_id) {
            return back()->with('error', 'Zuerst eine Meldung zuordnen.');
        }

        $ingestFile->update(['is_selected' => ! $ingestFile->is_selected]);

        $msg = $ingestFile->fresh()->is_selected
            ? 'Clip ist für den Finalschnitt markiert (in der Schnittseite auswählbar).'
            : 'Markierung für den Finalschnitt entfernt.';

        return back()->with('status', $msg);
    }

    public function queuePreview(IngestFile $ingestFile): RedirectResponse
    {
        if ($ingestFile->status === IngestFile::STATUS_PREVIEW_GENERATING) {
            return back()->with('error', 'Eine Vorschau wird bereits erzeugt.');
        }

        if ($ingestFile->status === IngestFile::STATUS_RENDERING) {
            return back()->with('error', 'Sendefassung wird gerade gerendert.');
        }

        if (! in_array($ingestFile->status, [
            IngestFile::STATUS_VALIDATED,
            IngestFile::STATUS_PREVIEW_READY,
            IngestFile::STATUS_ASSIGNED,
        ], true)) {
            return back()->with('error', 'Vorschau nur für validierte oder zugeordnete Clips möglich.');
        }

        if (! is_file($ingestFile->absolute_path)) {
            return back()->with('error', 'Quelldatei fehlt auf dem Server.');
        }

        if (! $ingestFile->isIngestVideoCandidate()) {
            return back()->with('error', 'Für diese Datei ist keine Video-Vorschau vorgesehen (kein Video-Ingest).');
        }

        $ingestFile->update([
            'status' => IngestFile::STATUS_PREVIEW_GENERATING,
            'preview_status' => IngestFile::PREVIEW_STATUS_GENERATING,
            'preview_error_message' => null,
        ]);
        GenerateIngestPreviewJob::dispatch($ingestFile->id);

        return back()->with('status', 'Vorschau wurde in die Warteschlange gelegt (Queue-Worker muss laufen).');
    }

    public function previewPlayback(Request $request, IngestFile $ingestFile, MediaStorage $mediaStorage): Response
    {
        $path = $ingestFile->preview_path;
        if (! is_string($path) || $path === '' || ! $mediaStorage->exists($path)) {
            abort(404);
        }

        $disk = $mediaStorage->activeDisk();
        if (! $disk->exists($path) && $mediaStorage->fallbackDiskName() !== $mediaStorage->activeDiskName()) {
            $disk = $mediaStorage->fallbackDisk();
        }
        if (! $disk->exists($path)) {
            abort(404);
        }

        if ($request->boolean('download')) {
            return $disk->download($path, 'ingest-'.$ingestFile->id.'-preview.mp4');
        }

        return $disk->response($path, 'preview.mp4', [
            'Accept-Ranges' => 'bytes',
            'Content-Type' => 'video/mp4',
        ]);
    }

    public function newsWorkspace(Request $request, NewsItem $newsItem): View
    {
        $clips = IngestFile::query()
            ->where('news_item_id', $newsItem->id)
            ->whereIn('status', [
                IngestFile::STATUS_ASSIGNED,
                IngestFile::STATUS_RENDERING,
                IngestFile::STATUS_USED,
            ])
            ->orderByRaw('selection_order IS NULL')
            ->orderBy('selection_order')
            ->orderBy('id')
            ->get();

        $activeJob = IngestRenderJob::query()
            ->where('news_item_id', $newsItem->id)
            ->whereIn('status', [
                IngestRenderJob::STATUS_QUEUED,
                IngestRenderJob::STATUS_RENDERING,
                IngestRenderJob::STATUS_UPLOADING,
            ])
            ->latest('id')
            ->first();

        $watchAfterQueue = (bool) $request->session()->pull('ingest_render_watch', false);
        $ingestRenderPoll = $activeJob !== null || $watchAfterQueue;
        $ingestRenderMonitorOptions = [
            'poll' => $ingestRenderPoll,
            'sawActiveJob' => $activeJob !== null,
            'statusLabel' => $activeJob?->status_label ?? '',
            'jobId' => $activeJob?->id,
        ];

        return view('admin.ingest.news-workspace', compact(
            'newsItem',
            'clips',
            'activeJob',
            'ingestRenderPoll',
            'ingestRenderMonitorOptions',
        ));
    }

    public function renderJobStatus(NewsItem $newsItem): JsonResponse
    {
        $job = IngestRenderJob::query()
            ->where('news_item_id', $newsItem->id)
            ->whereIn('status', [
                IngestRenderJob::STATUS_QUEUED,
                IngestRenderJob::STATUS_RENDERING,
                IngestRenderJob::STATUS_UPLOADING,
            ])
            ->latest('id')
            ->first();

        return response()->json([
            'active' => $job !== null,
            'job' => $job ? [
                'id' => $job->id,
                'status' => $job->status,
                'status_label' => $job->status_label,
            ] : null,
        ]);
    }

    public function queueRender(Request $request, NewsItem $newsItem): RedirectResponse
    {
        $data = $request->validate([
            'selected' => ['required', 'array', 'min:1'],
            'selected.*' => ['integer', 'exists:ingest_files,id'],
            'order' => ['nullable', 'array'],
            'order.*' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $selected = array_map('intval', $data['selected']);
        $selected = array_values(array_unique($selected));
        $orderMap = $data['order'] ?? [];

        $items = [];
        foreach ($selected as $id) {
            $items[] = [
                'id' => $id,
                'order' => isset($orderMap[$id]) ? (int) $orderMap[$id] : 9999,
            ];
        }
        usort($items, fn ($a, $b) => $a['order'] <=> $b['order']);
        $ids = array_column($items, 'id');

        if (IngestRenderJob::query()
            ->where('news_item_id', $newsItem->id)
            ->whereIn('status', [
                IngestRenderJob::STATUS_QUEUED,
                IngestRenderJob::STATUS_RENDERING,
                IngestRenderJob::STATUS_UPLOADING,
            ])
            ->exists()) {
            return back()->with('error', 'Für diese Meldung läuft bereits ein Render- oder Upload-Job.');
        }

        $files = IngestFile::query()
            ->whereIn('id', $ids)
            ->get();

        if ($files->count() !== count($ids)) {
            return back()->with('error', 'Ungültige Clip-Auswahl.');
        }

        $trimValidator = app(IngestTrimValidator::class);

        foreach ($files as $f) {
            if ((int) $f->news_item_id !== (int) $newsItem->id || $f->status !== IngestFile::STATUS_ASSIGNED) {
                return back()->with('error', 'Alle Clips müssen dieser Meldung zugeordnet und im Status „zugeordnet“ sein.');
            }
            if (! $f->is_selected) {
                return back()->with('error', 'Clip #'.$f->id.' ist nicht für den Finalschnitt markiert. Bitte in der Detailansicht „Für Finalschnitt auswählen“ aktivieren.');
            }
            if (! is_file($f->absolute_path)) {
                return back()->with('error', 'Datei fehlt: #'.$f->id);
            }

            $duration = $f->duration_s !== null ? (float) $f->duration_s : null;
            $tr = $trimValidator->validate(
                $f->trim_in_seconds !== null ? (string) $f->trim_in_seconds : null,
                $f->trim_out_seconds !== null ? (string) $f->trim_out_seconds : null,
                $duration
            );
            if (! $tr['ok']) {
                return back()->with('error', 'Clip #'.$f->id.': '.($tr['error'] ?? 'Trim ungültig.'));
            }
        }

        foreach ($items as $item) {
            IngestFile::where('id', $item['id'])->update(['selection_order' => $item['order']]);
        }

        $ordered = $files->sortBy(function (IngestFile $f) use ($ids) {
            $pos = array_search($f->id, $ids, true);

            return $pos !== false ? $pos : 9999;
        })->values();

        $job = IngestRenderJob::create([
            'news_item_id' => $newsItem->id,
            'ingest_file_ids' => $ordered->pluck('id')->all(),
            'status' => IngestRenderJob::STATUS_QUEUED,
        ]);

        ProcessIngestRenderJob::dispatch($job->id);

        return redirect()
            ->route('admin.ingest.news-workspace', $newsItem)
            ->with('ingest_render_watch', true)
            ->with(
                'status',
                'Sendefassung wurde in die Warteschlange gelegt (Job #'.$job->id.'). Unten sehen Sie den Status; die Seite lädt automatisch neu, sobald der Export fertig ist.'
            );
    }

    public function playback(IngestFile $ingestFile, IngestDirectoryService $directories): BinaryFileResponse
    {
        $path = $ingestFile->absolute_path;
        if (! $directories->isManagedAbsoluteFile($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Content-Type' => $ingestFile->mime ?: 'video/mp4',
        ]);
    }
}
