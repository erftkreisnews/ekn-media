<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessIngestRenderJob;
use App\Models\IngestFile;
use App\Models\IngestRenderJob;
use App\Models\IngestSource;
use App\Models\NewsItem;
use App\Services\Ingest\IngestDirectoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

        $files = $q->paginate(30)->withQueryString();
        $sources = IngestSource::orderBy('name')->get();

        return view('admin.ingest.index', compact('files', 'sources'));
    }

    public function show(IngestFile $ingestFile): View
    {
        $ingestFile->load(['source', 'newsItem']);
        $newsChoices = NewsItem::query()
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get(['id', 'title', 'status']);

        return view('admin.ingest.show', compact('ingestFile', 'newsChoices'));
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

        if ($ingestFile->status !== IngestFile::STATUS_VALIDATED && $ingestFile->status !== IngestFile::STATUS_ASSIGNED) {
            return back()->with('error', 'Nur validierte Clips können einer Meldung zugeordnet werden.');
        }

        if (empty($data['news_item_id'])) {
            $ingestFile->update([
                'news_item_id' => null,
                'selection_order' => null,
                'status' => IngestFile::STATUS_VALIDATED,
            ]);

            return back()->with('status', 'Zuordnung entfernt.');
        }

        $ingestFile->update([
            'news_item_id' => (int) $data['news_item_id'],
            'selection_order' => isset($data['selection_order']) ? (int) $data['selection_order'] : null,
            'status' => IngestFile::STATUS_ASSIGNED,
        ]);

        return back()->with('status', 'Meldung zugeordnet.');
    }

    public function newsWorkspace(NewsItem $newsItem): View
    {
        $clips = IngestFile::query()
            ->where('news_item_id', $newsItem->id)
            ->whereIn('status', [IngestFile::STATUS_ASSIGNED, IngestFile::STATUS_USED])
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

        return view('admin.ingest.news-workspace', compact('newsItem', 'clips', 'activeJob'));
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

        foreach ($files as $f) {
            if ((int) $f->news_item_id !== (int) $newsItem->id || $f->status !== IngestFile::STATUS_ASSIGNED) {
                return back()->with('error', 'Alle Clips müssen dieser Meldung zugeordnet und im Status „zugeordnet“ sein.');
            }
            if (! is_file($f->absolute_path)) {
                return back()->with('error', 'Datei fehlt: #'.$f->id);
            }
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
            ->with('status', 'Sendefassung wurde in die Warteschlange gelegt (Job #'.$job->id.').');
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
