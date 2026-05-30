<?php

namespace App\Http\Controllers\Admin;

use App\Console\Commands\IngestPurgeAllCommand;
use App\Http\Controllers\Controller;
use App\Jobs\ExtractVideoMetadata;
use App\Jobs\GenerateImageMetadata;
use App\Jobs\GenerateIngestPreviewJob;
use App\Jobs\GenerateNewsMediaPreview;
use App\Jobs\GenerateVideoPoster;
use App\Jobs\GenerateVideoStills;
use App\Jobs\ProcessIngestRenderJob;
use App\Jobs\ProcessMediaRedaction;
use App\Models\Brand;
use App\Models\IngestFile;
use App\Models\IngestRenderJob;
use App\Models\IngestSource;
use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use App\Models\Organization;
use App\Services\Ingest\IngestDirectoryService;
use App\Services\Ingest\IngestImageThumbnailService;
use App\Services\Ingest\IngestImageToNewsMediaService;
use App\Services\Ingest\IngestPostRenderCleanupService;
use App\Services\Ingest\IngestSendefassungFilenameService;
use App\Services\Ingest\IngestTrimValidator;
use App\Services\Ingest\IngestVideoPosterService;
use App\Services\MediaQualityCheck;
use App\Services\MediaStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class VideoIngestController extends Controller
{
    public function index(Request $request): View
    {
        $ingestListOnly = $request->routeIs('admin.ingest.entries');
        $sichtungImplicitToday = $this->ingestSichtungShouldApplyImplicitTodayScope();

        $q = IngestFile::query()
            ->with(['source', 'newsItem'])
            ->orderByDesc('id');

        if ($sichtungImplicitToday) {
            $q->whereDate('created_at', today());
        }

        if ($request->routeIs('admin.ingest.index') && ! $ingestListOnly) {
            $q->where('status', '!=', IngestFile::STATUS_USED);
        }

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

        $files = $q->paginate(60)->withQueryString();
        $sources = IngestSource::orderBy('name')->get();

        $ingestTotalInDb = IngestFile::query()->count();
        $filterActive = $this->ingestIndexAnyFilterActive($request);

        $browserPlayableClips = collect($files->items())
            ->filter(fn (IngestFile $f) => $this->ingestIndexSupportsCardPreview($f))
            ->values();

        $ingestNewsChoices = $this->ingestNewsChoicesForAssignment();

        return view('admin.ingest.index', compact(
            'files',
            'sources',
            'ingestTotalInDb',
            'filterActive',
            'browserPlayableClips',
            'ingestNewsChoices',
            'ingestListOnly',
            'sichtungImplicitToday',
        ));
    }

    public function statistics(): View
    {
        $stats = $this->ingestIndexStats();

        return view('admin.ingest.statistics', compact('stats'));
    }

    /**
     * Früher: ohne Filter nur „heute“. Redaktion wünscht die volle Liste (alle Videos) als Standard.
     */
    private function ingestSichtungShouldApplyImplicitTodayScope(): bool
    {
        return false;
    }

    /**
     * Meldungen für Ingest-Zuordnung: früher nur die 200 zuletzt geänderten Einträge insgesamt –
     * damit verschwanden Kölnimage-Meldungen oft komplett aus den Dropdowns. Stattdessen pro aktiver
     * Marke ein Kontingent plus ohne Marke, dann zusammenführen und nach Aktualität sortieren.
     *
     * @return EloquentCollection<int, NewsItem>
     */
    private function ingestNewsChoicesForAssignment(): EloquentCollection
    {
        if (! Schema::hasTable('news_items')) {
            return new EloquentCollection;
        }

        $select = ['id', 'title', 'status', 'updated_at'];
        if (Schema::hasColumn('news_items', 'brand_id')) {
            $select[] = 'brand_id';
        }

        $base = NewsItem::query()->select($select);

        if (! Schema::hasColumn('news_items', 'brand_id')) {
            $items = (clone $base)->orderByDesc('updated_at')->limit(250)->get();

            return $this->ingestNewsChoicesWithBrandsLoaded($items);
        }

        $perBrandLimit = 120;
        $unbrandedLimit = 80;
        $maxTotal = 400;
        $byId = [];

        if (Schema::hasTable('brands')) {
            $brandIds = Brand::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->pluck('id');

            if ($brandIds->isEmpty()) {
                return $this->ingestNewsChoicesWithBrandsLoaded(
                    (clone $base)->orderByDesc('updated_at')->limit(250)->get()
                );
            }

            foreach ($brandIds as $brandId) {
                foreach (
                    (clone $base)->where('brand_id', (int) $brandId)
                        ->orderByDesc('updated_at')
                        ->limit($perBrandLimit)
                        ->get() as $row
                ) {
                    $byId[$row->id] = $row;
                }
            }
        } else {
            return $this->ingestNewsChoicesWithBrandsLoaded(
                (clone $base)->orderByDesc('updated_at')->limit(250)->get()
            );
        }

        foreach (
            (clone $base)->whereNull('brand_id')
                ->orderByDesc('updated_at')
                ->limit($unbrandedLimit)
                ->get() as $row
        ) {
            $byId[$row->id] = $row;
        }

        $merged = new EloquentCollection(array_values($byId));

        $items = $merged
            ->sortByDesc(fn (NewsItem $n) => $n->updated_at?->getTimestamp() ?? 0)
            ->values()
            ->take($maxTotal);

        return $this->ingestNewsChoicesWithBrandsLoaded($items);
    }

    /**
     * @param  EloquentCollection<int, NewsItem>  $items
     * @return EloquentCollection<int, NewsItem>
     */
    private function ingestNewsChoicesWithBrandsLoaded(EloquentCollection $items): EloquentCollection
    {
        if ($items->isEmpty()) {
            return $items;
        }

        if (Schema::hasTable('brands')) {
            $items->load(['brand' => function ($q) {
                $q->select('id', 'name', 'key');
            }]);
        }

        return $items;
    }

    /**
     * Video oder JPEG – für die Index-Karten-Vorschau (Poster, Kurz-MP4 oder Original-Stream).
     */
    private function ingestIndexSupportsCardPreview(IngestFile $f): bool
    {
        if ($f->isIngestImageFile()) {
            return true;
        }

        if (! $f->isIngestVideoFile()) {
            return false;
        }

        return ! in_array($f->status, [
            IngestFile::STATUS_IMPORTED,
            IngestFile::STATUS_VALIDATING,
            IngestFile::STATUS_REJECTED,
            IngestFile::STATUS_FAILED,
        ], true);
    }

    /**
     * Zusätzliche GET-Scope-Filter (nur lesend, bestehende Felder).
     *
     * @param  Builder<IngestFile>  $query
     */
    private function applyIngestIndexScopeFilters(Builder $query, Request $request): void
    {
        if ($request->filled('news_item_id')) {
            $v = (string) $request->input('news_item_id');
            if ($v === 'none') {
                $query->whereNull('news_item_id');
            } elseif ($v === 'any') {
                $query->whereNotNull('news_item_id');
            } else {
                $query->where('news_item_id', (int) $v);
            }
        } else {
            if ($request->boolean('with_news')) {
                $query->whereNotNull('news_item_id');
            }
            if ($request->boolean('without_news')) {
                $query->whereNull('news_item_id');
            }
        }

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

        if ($request->boolean('no_preview') && $this->ingestFilesTableHasColumn('preview_path')) {
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

        if ($request->boolean('selected') && $this->ingestFilesTableHasColumn('is_selected')) {
            $query->where('is_selected', true);
        }

        if ($request->boolean('not_selected') && $this->ingestFilesTableHasColumn('is_selected')) {
            $query->where('is_selected', false);
        }
    }

    private function ingestIndexAnyFilterActive(Request $request): bool
    {
        return $request->filled('status')
            || $request->filled('source_id')
            || $request->filled('from')
            || $request->filled('to')
            || $request->filled('news_item_id')
            || $request->boolean('today')
            || $request->boolean('validated')
            || ($request->boolean('no_preview') && $this->ingestFilesTableHasColumn('preview_path'))
            || $request->boolean('errors')
            || $request->boolean('with_news')
            || $request->boolean('without_news')
            || ($request->boolean('selected') && $this->ingestFilesTableHasColumn('is_selected'))
            || ($request->boolean('not_selected') && $this->ingestFilesTableHasColumn('is_selected'));
    }

    /**
     * Kennzahlen für die Ingest-Übersicht (nur lesende Aggregationen auf ingest_files).
     *
     * @return array<string, int>
     */
    private function ingestIndexStats(): array
    {
        $base = IngestFile::query();

        $withoutPreview = 0;
        if ($this->ingestFilesTableHasColumn('preview_path')) {
            $withoutPreview = (clone $base)
                ->whereNull('preview_path')
                ->whereNotIn('status', [
                    IngestFile::STATUS_IMPORTED,
                    IngestFile::STATUS_VALIDATING,
                    IngestFile::STATUS_REJECTED,
                    IngestFile::STATUS_FAILED,
                ])
                ->count();
        }

        $isSelectedCount = 0;
        if ($this->ingestFilesTableHasColumn('is_selected')) {
            $isSelectedCount = (clone $base)->where('is_selected', true)->count();
        }

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
            'without_preview' => $withoutPreview,
            'rejected_or_failed' => (clone $base)->whereIn('status', [
                IngestFile::STATUS_REJECTED,
                IngestFile::STATUS_FAILED,
            ])->count(),
            'is_selected' => $isSelectedCount,
            'with_news' => (clone $base)->whereNotNull('news_item_id')->count(),
        ];
    }

    /** @param  non-empty-string  $column */
    private function ingestFilesTableHasColumn(string $column): bool
    {
        static $cache = [];

        if (! array_key_exists($column, $cache)) {
            $cache[$column] = Schema::hasTable('ingest_files')
                && Schema::hasColumn('ingest_files', $column);
        }

        return $cache[$column];
    }

    /**
     * Speichert assign-Payload; is_selected per forceFill, falls Spalte existiert (nicht im Model-$fillable).
     *
     * @param  array<string, mixed>  $payload
     */
    private function persistIngestAssignPayload(IngestFile $ingestFile, array $payload): void
    {
        $isSel = null;
        if ($this->ingestFilesTableHasColumn('is_selected') && array_key_exists('is_selected', $payload)) {
            $isSel = (bool) $payload['is_selected'];
            unset($payload['is_selected']);
        }

        $ingestFile->update($payload);

        if ($isSel !== null) {
            $ingestFile->forceFill(['is_selected' => $isSel])->save();
        }
    }

    /**
     * Nach erfolgreicher Zuordnung: nicht nur „back()“ (lange Seiten → grüner Balken oben leicht zu übersehen),
     * sondern klare URL inkl. Anker + verständliche Meldung.
     */
    private function redirectAfterIngestAssign(Request $request, IngestFile $ingestFile, string $message): RedirectResponse
    {
        $ingestFile->refresh();

        $target = $request->input('_ingest_return');
        $query = $request->query->all();

        $base = match ($target) {
            'show' => route('admin.ingest.show', $ingestFile),
            'entries' => route('admin.ingest.entries', $query),
            default => route('admin.ingest.index', $query),
        };

        // Anker auf den grünen Hinweis, damit er bei langen Seiten nicht „oben versteckt“ bleibt
        return redirect()->to($base.'#ingest-flash-status')->with('status', $message);
    }

    public function show(Request $request, IngestFile $ingestFile): View
    {
        $ingestFile->load(['source', 'newsItem', 'batch']);
        $newsChoices = $this->ingestNewsChoicesForAssignment();

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

        $previousIngestFile = IngestFile::query()
            ->where('id', '<', (int) $ingestFile->id)
            ->orderByDesc('id')
            ->first(['id']);
        $nextIngestFile = IngestFile::query()
            ->where('id', '>', (int) $ingestFile->id)
            ->orderBy('id')
            ->first(['id']);

        $originalCreatedAtInfo = $this->resolveIngestOriginalCreatedAt($ingestFile);
        $deliveryOrganizations = $this->directMarketingOrganizations($request);

        return view('admin.ingest.show', [
            'ingestFile' => $ingestFile,
            'newsChoices' => $newsChoices,
            'activeRenderJobForClip' => $activeRenderJobForClip,
            'ingestPreviewMaxSeconds' => (int) config('ingest.preview.max_seconds', 60),
            'ingestStatusLabel' => $ingestStatusLabel,
            'ingestPreviewStatusLabel' => $ingestPreviewStatusLabel,
            'previousIngestFile' => $previousIngestFile,
            'nextIngestFile' => $nextIngestFile,
            'originalCreatedAtInfo' => $originalCreatedAtInfo,
            'deliveryOrganizations' => $deliveryOrganizations,
        ]);
    }

    /**
     * JPEG aus Ingest direkt in die Bild-Mediathek für Vermarktung übernehmen (ohne redaktionelle News-Zuordnung).
     */
    public function directMarketingFromImage(
        Request $request,
        IngestFile $ingestFile,
        IngestDirectoryService $directories,
        IngestImageThumbnailService $thumbs
    ): RedirectResponse {
        if (! $ingestFile->isIngestImageFile()) {
            return back()->with('error', 'Direktvermarktung ohne Meldung ist nur für JPEG-Bilder vorgesehen.');
        }

        $err = $this->ingestDestroyErrorReason($ingestFile);
        if ($err !== null) {
            return back()->with('error', $err);
        }

        $data = $request->validate([
            'delivery_org_ids' => ['nullable', 'array'],
            'delivery_org_ids.*' => ['integer', 'exists:organizations,id'],
        ]);

        $selectedBrandId = $this->selectedAdminBrandId($request);
        $allowedOrgIds = $this->directMarketingOrganizations($request)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $requestedOrgIds = array_values(array_unique(array_map(
            'intval',
            is_array($data['delivery_org_ids'] ?? null) ? $data['delivery_org_ids'] : []
        )));
        $deliveryOrgIds = array_values(array_filter(
            $requestedOrgIds,
            fn (int $id): bool => in_array($id, $allowedOrgIds, true)
        ));

        try {
            $mediaStorage = app(MediaStorage::class);
            $originalName = (string) ($ingestFile->original_name ?? 'ingest-image.jpg');

            $media = NewsItemMedia::query()->create([
                'news_item_id' => null,
                'type' => 'image',
                'path' => 'news-media/.pending',
                'original_name' => $originalName,
                'sort_order' => 0,
                'is_visible' => false,
                'versand' => true,
                'is_teaser' => false,
                'channel_type' => 'direct_marketing',
                'visibility' => 'customer_only',
                'publish_state' => 'ready',
                'brand_id' => $selectedBrandId,
            ]);

            $targetPath = $mediaStorage->generateMediaPath(
                null,
                $media,
                $originalName,
                'direct-marketing'
            );
            if (! $mediaStorage->putFromLocalFile($targetPath, (string) $ingestFile->absolute_path)) {
                throw new \RuntimeException('Speichern im Medien-Speicher fehlgeschlagen.');
            }

            $mediaUpdates = [
                'path' => $targetPath,
                'is_visible' => false,
                'versand' => true,
                'is_teaser' => false,
                'channel_type' => 'direct_marketing',
                'visibility' => 'customer_only',
                'publish_state' => 'ready',
            ];
            if ($this->newsItemMediaTableHasColumn('delivery_visible_for_organization_ids')) {
                $mediaUpdates['delivery_visible_for_organization_ids'] = $deliveryOrgIds;
            }
            if ($this->newsItemMediaTableHasColumn('brand_id') && $selectedBrandId !== null) {
                $mediaUpdates['brand_id'] = (int) $selectedBrandId;
            }
            $media->update($mediaUpdates);

            $media->update([
                'redaction_status' => $media->shouldAutoRedact()
                    ? NewsItemMedia::REDACTION_PENDING
                    : NewsItemMedia::REDACTION_DISABLED,
            ]);
            app(MediaQualityCheck::class)->runAndSave($media);
            $previewPath = $mediaStorage->generateDerivedMediaPath(null, $media, $originalName.'.webp', 'preview');
            $media->update(['preview_path' => $previewPath]);
            $watermarkPath = public_path('images/erftkreis-news-logo.png');
            if (is_file($watermarkPath)) {
                GenerateNewsMediaPreview::dispatchSync($mediaStorage->activeDiskName(), $targetPath, $previewPath, $watermarkPath);
            }
            try {
                $media->markAiQueued();
                GenerateImageMetadata::dispatch($media);
            } catch (QueryException $e) {
                report($e);
            }
            if ($media->shouldAutoRedact()) {
                ProcessMediaRedaction::dispatch($media);
            }
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Direktvermarktung-Übernahme fehlgeschlagen: '.$e->getMessage());
        }

        $absPath = $ingestFile->absolute_path;
        if (is_string($absPath) && $absPath !== '' && $directories->isManagedAbsoluteFile($absPath) && is_file($absPath)) {
            @unlink($absPath);
        }
        $thumbs->removeThumbnail($ingestFile);

        $ingestFile->update([
            'status' => IngestFile::STATUS_USED,
            'news_item_id' => null,
            'final_news_item_media_id' => $media->id,
            'is_selected' => false,
            'selection_order' => null,
            'trim_in_seconds' => null,
            'trim_out_seconds' => null,
        ]);

        return redirect()
            ->route('admin.images.index')
            ->with('status', 'Bild wurde ohne News in die Direktvermarktungs-Mediathek übernommen.');
    }

    /**
     * @return array{at: Carbon|null, source: string|null}
     */
    private function resolveIngestOriginalCreatedAt(IngestFile $ingestFile): array
    {
        $fromFfprobe = $this->ingestOriginalCreatedAtFromFfprobe($ingestFile);
        if ($fromFfprobe !== null) {
            return ['at' => $fromFfprobe, 'source' => 'ffprobe'];
        }

        $fromExif = $this->ingestOriginalCreatedAtFromExif($ingestFile);
        if ($fromExif !== null) {
            return ['at' => $fromExif, 'source' => 'EXIF'];
        }

        $path = (string) ($ingestFile->absolute_path ?? '');
        if ($path !== '' && is_file($path)) {
            $mtime = @filemtime($path);
            if (is_int($mtime) && $mtime > 0) {
                return ['at' => Carbon::createFromTimestamp($mtime), 'source' => 'Datei-Mtime'];
            }
        }

        return ['at' => null, 'source' => null];
    }

    private function ingestOriginalCreatedAtFromFfprobe(IngestFile $ingestFile): ?Carbon
    {
        $json = $ingestFile->ffprobe_json;
        if (! is_string($json) || trim($json) === '') {
            return null;
        }

        $data = json_decode($json, true);
        if (! is_array($data)) {
            return null;
        }

        $candidates = [];
        $fmtTags = $data['format']['tags'] ?? null;
        if (is_array($fmtTags) && isset($fmtTags['creation_time'])) {
            $candidates[] = (string) $fmtTags['creation_time'];
        }
        foreach ($data['streams'] ?? [] as $stream) {
            $tags = is_array($stream) ? ($stream['tags'] ?? null) : null;
            if (is_array($tags) && isset($tags['creation_time'])) {
                $candidates[] = (string) $tags['creation_time'];
            }
        }

        foreach ($candidates as $raw) {
            $parsed = $this->parseIngestDateTimeString($raw);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return null;
    }

    private function ingestOriginalCreatedAtFromExif(IngestFile $ingestFile): ?Carbon
    {
        if (! $ingestFile->isIngestImageFile() || ! function_exists('exif_read_data')) {
            return null;
        }

        $path = (string) ($ingestFile->absolute_path ?? '');
        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            return null;
        }

        try {
            $exif = @exif_read_data($path, null, true, false);
        } catch (\Throwable $e) {
            $exif = false;
        }
        if (! is_array($exif)) {
            return null;
        }

        $candidates = [
            $exif['EXIF']['DateTimeOriginal'] ?? null,
            $exif['EXIF']['CreateDate'] ?? null,
            $exif['IFD0']['DateTime'] ?? null,
        ];

        foreach ($candidates as $raw) {
            if (! is_string($raw) || trim($raw) === '') {
                continue;
            }

            $parsed = $this->parseIngestDateTimeString($raw);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return null;
    }

    private function parseIngestDateTimeString(string $raw): ?Carbon
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $formats = [
            'Y-m-d\\TH:i:s.u\\Z',
            'Y-m-d\\TH:i:s\\Z',
            'Y-m-d\\TH:i:sP',
            'Y-m-d H:i:s',
            'Y:m:d H:i:s',
        ];

        foreach ($formats as $format) {
            try {
                $dt = Carbon::createFromFormat($format, $raw, 'UTC');
                if ($dt instanceof Carbon) {
                    return $dt;
                }
            } catch (\Throwable $e) {
                // nächstes Format probieren
            }
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function assign(Request $request, IngestFile $ingestFile, IngestImageThumbnailService $thumbs): RedirectResponse
    {
        $data = $request->validate([
            'news_item_id' => ['nullable', 'exists:news_items,id'],
            'selection_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_selected' => ['sometimes'],
            '_ingest_return' => ['nullable', 'string', 'in:index,entries,show'],
        ]);

        if ($ingestFile->status === IngestFile::STATUS_USED) {
            return back()->with('error', 'Clip wurde bereits in einen Sendeschnitt übernommen.');
        }

        if ($ingestFile->status === IngestFile::STATUS_RENDERING) {
            return back()->with('error', 'Sendefassung wird gerade gerendert – bitte warten.');
        }

        $assignable = in_array($ingestFile->status, [
            IngestFile::STATUS_VALIDATED,
            IngestFile::STATUS_PREVIEW_GENERATING,
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
                'status' => $ingestFile->preview_path
                    ? IngestFile::STATUS_PREVIEW_READY
                    : ($ingestFile->status === IngestFile::STATUS_PREVIEW_GENERATING
                        ? IngestFile::STATUS_PREVIEW_GENERATING
                        : IngestFile::STATUS_VALIDATED),
            ];
            if (filled($ingestFile->preview_path)) {
                $payload['preview_status'] = IngestFile::PREVIEW_STATUS_READY;
                $payload['preview_error_message'] = null;
            }
            $this->persistIngestAssignPayload($ingestFile, $payload);

            return $this->redirectAfterIngestAssign(
                $request,
                $ingestFile,
                'Zuordnung entfernt. Der Clip ist wieder ohne Meldung.'
            );
        }

        $newId = (int) $data['news_item_id'];

        if ($ingestFile->isIngestImageFile()) {
            $newsItem = NewsItem::findOrFail($newId);
            $directories = app(IngestDirectoryService::class);

            try {
                $media = app(IngestImageToNewsMediaService::class)->finalize($ingestFile, $newsItem);
            } catch (\Throwable $e) {
                report($e);

                return back()->with('error', 'Bild konnte nicht übernommen werden: '.$e->getMessage());
            }

            $absPath = $ingestFile->absolute_path;
            if (is_string($absPath) && $absPath !== '' && $directories->isManagedAbsoluteFile($absPath) && is_file($absPath)) {
                @unlink($absPath);
            }
            $thumbs->removeThumbnail($ingestFile);

            $ingestFile->update([
                'status' => IngestFile::STATUS_USED,
                'news_item_id' => $newsItem->id,
                'final_news_item_media_id' => $media->id,
                'is_selected' => false,
                'selection_order' => null,
                'trim_in_seconds' => null,
                'trim_out_seconds' => null,
            ]);

            return redirect()
                ->route('admin.news.edit', $newsItem)
                ->with('status', 'Das Bild wurde der Meldung in der Galerie hinzugefügt und im Ingest als übernommen geführt (kein Finalschnitt).');
        }

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

        if (
            $request->has('is_selected')
            && $this->ingestFilesTableHasColumn('is_selected')
            && ($oldId === null || $oldId === $newId)
        ) {
            $payload['is_selected'] = $request->boolean('is_selected');
        }

        $this->persistIngestAssignPayload($ingestFile, $payload);

        // Auto-Auswahl: neu zugeordnete Video-Clips sollen im Workspace bereits als "Nutzen" markiert sein,
        // damit beim Rendern die sendefähige MP4 die neuen Clips direkt mitnimmt.
        if (
            (bool) config('ingest.auto_select_assigned_videos', true)
            && ! $ingestFile->isIngestImageFile()
            && $this->ingestFilesTableHasColumn('is_selected')
            && $this->ingestFilesTableHasColumn('selection_order')
        ) {
            $ingestFile->refresh();

            $maxOrder = IngestFile::query()
                ->where('news_item_id', $newId)
                ->where('status', IngestFile::STATUS_ASSIGNED)
                ->where('is_selected', true)
                ->whereNotNull('selection_order')
                ->max('selection_order');

            $maxOrder = is_numeric($maxOrder) ? (int) $maxOrder : -1;
            $desiredOrder = $ingestFile->selection_order !== null
                ? (int) $ingestFile->selection_order
                : ($maxOrder + 1);

            if (! $ingestFile->is_selected || $ingestFile->selection_order === null) {
                $ingestFile->forceFill([
                    'is_selected' => true,
                    'selection_order' => $desiredOrder,
                ])->save();
            }
        }

        $newsTitle = NewsItem::where('id', $newId)->value('title');
        $titlePart = $newsTitle !== null && $newsTitle !== ''
            ? '„'.Str::limit((string) $newsTitle, 70).'“'
            : 'der gewählten Meldung';

        $message = 'Clip #'.$ingestFile->id.' ist '.$titlePart.' zugeordnet. '
            .'Wenn dies bereits die fertige MP4 ist: in der Clip-Detailseite „Fertige MP4 ohne Render übernehmen“ nutzen. '
            .'Nur für eine gemeinsame Sendefassung aus mehreren Clips: zur Meldung wechseln, „Schnitt / Sendefassung“ öffnen, gewünschte Clips unter „Nutzen“ anhaken und „Sendefähige MP4 erzeugen“ wählen (Queue-Worker muss laufen).';

        return $this->redirectAfterIngestAssign($request, $ingestFile, $message);
    }

    /**
     * Bereits fertige MP4 direkt als News-Video übernehmen (ohne erneuten Ingest-Render).
     */
    public function finalizeAssignedVideo(
        Request $request,
        IngestFile $ingestFile,
        IngestDirectoryService $directories,
        MediaStorage $mediaStorage
    ): RedirectResponse {
        if ($ingestFile->isIngestImageFile()) {
            return back()->with('error', 'Diese Aktion ist nur für Video-Dateien verfügbar.');
        }

        if (in_array($ingestFile->status, [
            IngestFile::STATUS_USED,
            IngestFile::STATUS_RENDERING,
            IngestFile::STATUS_PREVIEW_GENERATING,
        ], true)) {
            return back()->with('error', 'In diesem Status kann der Clip nicht direkt übernommen werden.');
        }

        if (! in_array($ingestFile->status, [
            IngestFile::STATUS_VALIDATED,
            IngestFile::STATUS_PREVIEW_READY,
            IngestFile::STATUS_ASSIGNED,
        ], true)) {
            return back()->with('error', 'Clip muss mindestens validiert sein, um ohne Render übernommen zu werden.');
        }

        $data = $request->validate([
            'news_item_id' => ['nullable', 'exists:news_items,id'],
            '_ingest_return' => ['nullable', 'string', 'in:index,entries,show'],
        ]);

        $newsId = (int) ($data['news_item_id'] ?? $ingestFile->news_item_id ?? 0);
        if ($newsId < 1) {
            return back()->with('error', 'Bitte zuerst eine Meldung zuordnen.');
        }

        $newsItem = NewsItem::findOrFail($newsId);

        $sourcePath = (string) ($ingestFile->absolute_path ?? '');
        if ($sourcePath === '' || ! is_file($sourcePath)) {
            return back()->with('error', 'Quelldatei fehlt auf dem Server.');
        }

        if (! $directories->isManagedAbsoluteFile($sourcePath)) {
            return back()->with('error', 'Sicherheitsprüfung fehlgeschlagen: Datei liegt außerhalb des Ingest-Bereichs.');
        }

        $media = null;

        try {
            DB::beginTransaction();

            $sortOrder = (int) ($newsItem->media()->max('sort_order') ?? 0);
            $sortOrder++;
            $naming = app(IngestSendefassungFilenameService::class)->assignForNewSendefassung($newsItem);
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

            if (! $mediaStorage->putFromLocalFile($targetPath, $sourcePath)) {
                throw new \RuntimeException('Upload in den Medien-Speicher fehlgeschlagen.');
            }

            $media->update(['path' => $targetPath]);

            $ingestFile->update([
                'status' => IngestFile::STATUS_USED,
                'news_item_id' => $newsItem->id,
                'final_news_item_media_id' => $media->id,
                'is_selected' => false,
                'selection_order' => null,
                'trim_in_seconds' => null,
                'trim_out_seconds' => null,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->with('error', 'Direkte Übernahme ohne Render fehlgeschlagen: '.$e->getMessage());
        }

        ExtractVideoMetadata::dispatch($media);
        GenerateVideoPoster::dispatch($media);
        GenerateVideoStills::dispatch($media);

        if (config('ingest.archive_raw_after_success', false)) {
            $archive = $directories->path('archive');
            if (is_string($archive) && $archive !== '') {
                if (! is_dir($archive)) {
                    @mkdir($archive, 0775, true);
                }
                @rename($sourcePath, rtrim($archive, '/').'/'.basename($sourcePath));
            }
        } else {
            @unlink($sourcePath);
        }

        app(IngestPostRenderCleanupService::class)->cleanupAfterSuccessfulRender([(int) $ingestFile->id]);

        return $this->redirectAfterIngestAssign(
            $request,
            $ingestFile->fresh(),
            'Fertige MP4 wurde ohne Render direkt als News-Video übernommen.'
        );
    }

    public function destroy(Request $request, IngestFile $ingestFile, IngestDirectoryService $directories, IngestImageThumbnailService $thumbs): RedirectResponse
    {
        $err = $this->ingestDestroyErrorReason($ingestFile);
        if ($err !== null) {
            return back()->with('error', $err);
        }

        $fallbackUrl = route('admin.ingest.index');
        $targetUrl = $this->ingestSafeRedirectTarget(url()->previous(), $fallbackUrl);
        $showUrl = route('admin.ingest.show', $ingestFile);
        if ($this->ingestUrlsSharePath($targetUrl, $showUrl)) {
            $targetUrl = $fallbackUrl;
        }

        $this->ingestDeleteFileAndRecord($ingestFile, $directories, $thumbs);

        return redirect()->to($targetUrl)->with('status', 'Ingest-Eintrag wurde gelöscht.');
    }

    /**
     * Notfall-Reset für den kompletten Ingest-Bestand.
     * Absicherung: feste Sicherheitsphrase + aktueller DB-Zähler + "ja".
     */
    public function emergencyPurge(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'confirm_phrase' => ['required', 'string', 'max:128'],
            'confirm_count' => ['required', 'integer', 'min:0'],
            'confirmation' => ['required', 'string', 'max:32'],
        ]);

        if (trim((string) $data['confirm_phrase']) !== IngestPurgeAllCommand::CONFIRM_PHRASE) {
            return redirect()
                ->route('admin.ingest.index')
                ->with('error', 'Notfall-Löschung abgebrochen: Sicherheitsphrase stimmt nicht.');
        }

        if (mb_strtolower(trim((string) $data['confirmation']), 'UTF-8') !== 'ja') {
            return redirect()
                ->route('admin.ingest.index')
                ->with('error', 'Notfall-Löschung abgebrochen: Bitte „ja“ bestätigen.');
        }

        $currentCount = IngestFile::query()->count();
        if ((int) $data['confirm_count'] !== $currentCount) {
            return redirect()
                ->route('admin.ingest.index')
                ->with('error', 'Notfall-Löschung abgebrochen: Anzahl stimmt nicht mehr. Aktuell: '.$currentCount.'.');
        }

        $exitCode = Artisan::call('ingest:purge-all', [
            '--force' => true,
            '--confirm' => IngestPurgeAllCommand::CONFIRM_PHRASE,
        ]);

        $output = trim((string) Artisan::output());
        if ($exitCode !== 0) {
            $msg = 'Notfall-Löschung fehlgeschlagen.';
            if ($output !== '') {
                $msg .= ' '.Str::limit(str_replace(["\r", "\n"], ' ', $output), 240);
            }

            return redirect()->route('admin.ingest.index')->with('error', $msg);
        }

        return redirect()
            ->route('admin.ingest.index')
            ->with('status', 'Notfall-Löschung ausgeführt. '.$currentCount.' Ingest-Einträge wurden bereinigt.');
    }

    /**
     * Mehrfachauswahl: löschen oder gemeinsame Meldungs-Zuordnung (JPEG → Galerie, Video → zugeordnet).
     */
    public function bulk(Request $request, IngestDirectoryService $directories, IngestImageToNewsMediaService $imageToNews, IngestImageThumbnailService $thumbs): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:delete,assign_news,render_final'],
            'ids' => ['required', 'array', 'min:1', 'max:2000'],
            'ids.*' => ['integer', 'exists:ingest_files,id'],
            'news_item_id' => ['nullable', 'exists:news_items,id'],
            'confirmation' => ['nullable', 'string'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ], [
            'ids.required' => 'Bitte mindestens einen Eintrag auswählen (Mehrfachauswahl-Häkchen).',
            'ids.min' => 'Bitte mindestens einen Eintrag auswählen (Mehrfachauswahl-Häkchen).',
            'ids.max' => 'Maximal 2000 Einträge pro Aktion. Bitte in mehreren Schritten löschen oder Auswahl reduzieren.',
        ]);

        $ids = array_values(array_unique(array_map('intval', $data['ids'])));
        $files = IngestFile::query()->whereIn('id', $ids)->orderBy('id')->get();

        if ($files->count() !== count($ids)) {
            return $this->redirectAfterIngestBulk($request)->with('error', 'Auswahl ungültig oder veraltet.');
        }

        if ($data['action'] === 'delete') {
            if (mb_strtolower(trim((string) ($data['confirmation'] ?? '')), 'UTF-8') !== 'ja') {
                return $this->redirectAfterIngestBulk($request)->with('error', 'Löschen nicht bestätigt. Bitte „ja“ eingeben bzw. Dialog bestätigen.');
            }

            $deleted = 0;
            $skipped = [];
            foreach ($files as $ingestFile) {
                $reason = $this->ingestDestroyErrorReason($ingestFile);
                if ($reason !== null) {
                    $skipped[] = '#'.$ingestFile->id.' ('.$reason.')';

                    continue;
                }
                $this->ingestDeleteFileAndRecord($ingestFile, $directories, $thumbs);
                $deleted++;
            }

            $msg = $deleted.' Eintrag/Einträge gelöscht.';
            if ($skipped !== []) {
                $msg .= ' Übersprungen: '.implode(', ', $skipped);
            }

            return $this->redirectAfterIngestBulk($request)->with('status', $msg);
        }

        if ($data['action'] === 'render_final') {
            $newsId = (int) ($data['news_item_id'] ?? 0);
            if ($newsId < 1) {
                return $this->redirectAfterIngestBulk($request)->with('error', 'Bitte eine Meldung für den Finalrender wählen.');
            }

            $newsItem = NewsItem::findOrFail($newsId);

            app(IngestRenderJobReleaseService::class)->releaseStaleForNewsItem($newsItem->id);

            if (IngestRenderJob::query()
                ->where('news_item_id', $newsItem->id)
                ->whereIn('status', [
                    IngestRenderJob::STATUS_QUEUED,
                    IngestRenderJob::STATUS_RENDERING,
                    IngestRenderJob::STATUS_UPLOADING,
                ])
                ->exists()) {
                return $this->redirectAfterIngestBulk($request)->with('error', 'Für diese Meldung läuft bereits ein Render- oder Upload-Job.');
            }

            $trimValidator = app(IngestTrimValidator::class);

            $validated = collect();
            foreach ($files as $ingestFile) {
                if ($ingestFile->isIngestImageFile()) {
                    return $this->redirectAfterIngestBulk($request)->with('error', 'JPEG-Bilder gehören nicht in die sendefähige MP4 – bitte nur Video-Clips auswählen.');
                }
                if ($ingestFile->status !== IngestFile::STATUS_ASSIGNED) {
                    return $this->redirectAfterIngestBulk($request)->with('error', 'Alle Clips müssen dieser Meldung zugeordnet und im Status „zugeordnet“ sein (Clip #'.$ingestFile->id.').');
                }
                if ((int) $ingestFile->news_item_id !== (int) $newsItem->id) {
                    return $this->redirectAfterIngestBulk($request)->with('error', 'Alle Clips müssen zur gewählten Meldung passen (Clip #'.$ingestFile->id.').');
                }
                if (! is_file($ingestFile->absolute_path)) {
                    return $this->redirectAfterIngestBulk($request)->with('error', 'Datei fehlt: #'.$ingestFile->id);
                }

                $duration = $ingestFile->duration_s !== null ? (float) $ingestFile->duration_s : null;
                $tr = $trimValidator->validate(
                    $ingestFile->trim_in_seconds !== null ? (string) $ingestFile->trim_in_seconds : null,
                    $ingestFile->trim_out_seconds !== null ? (string) $ingestFile->trim_out_seconds : null,
                    $duration
                );
                if (! $tr['ok']) {
                    return $this->redirectAfterIngestBulk($request)->with('error', 'Clip #'.$ingestFile->id.': '.($tr['error'] ?? 'Trim ungültig.'));
                }

                $validated->push($ingestFile);
            }

            // Schnittreihenfolge = Eingangszeit (ältester Clip zuerst), bei Gleichstand nach ID.
            $filesSorted = $validated->sortBy(function (IngestFile $f) {
                return [(int) ($f->created_at?->timestamp ?? 0), (int) $f->id];
            })->values();

            $idsOrdered = $filesSorted->pluck('id')->map(fn ($id) => (int) $id)->all();

            foreach ($filesSorted as $i => $ingestFile) {
                $row = ['selection_order' => (int) $i];
                if ($this->ingestFilesTableHasColumn('is_selected')) {
                    $row['is_selected'] = true;
                }
                IngestFile::where('id', (int) $ingestFile->id)->update($row);
            }

            $job = IngestRenderJob::create([
                'news_item_id' => $newsItem->id,
                'ingest_file_ids' => $idsOrdered,
                'status' => IngestRenderJob::STATUS_QUEUED,
            ]);

            ProcessIngestRenderJob::dispatch($job->id);

            return $this->redirectAfterIngestBulk($request)
                ->with(
                    'status',
                    'Sendefähige MP4 wurde in die Warteschlange gelegt (Job #'.$job->id.'). Der Render läuft jetzt im Hintergrund. Du kannst hier weiterarbeiten.'
                );
        }

        $newsId = (int) ($data['news_item_id'] ?? 0);
        if ($newsId < 1) {
            return $this->redirectAfterIngestBulk($request)->with('error', 'Bitte eine Meldung für die Zuordnung wählen.');
        }

        $newsItem = NewsItem::findOrFail($newsId);

        $assignedVideos = 0;
        $finalizedImages = 0;
        $failures = [];
        $autoSelect = (bool) config('ingest.auto_select_assigned_videos', true);
        $hasIsSelected = $this->ingestFilesTableHasColumn('is_selected');
        $hasSelectionOrder = $this->ingestFilesTableHasColumn('selection_order');
        $nextOrder = 0;
        if ($autoSelect && $hasIsSelected && $hasSelectionOrder) {
            $maxOrder = IngestFile::query()
                ->where('news_item_id', $newsItem->id)
                ->where('status', IngestFile::STATUS_ASSIGNED)
                ->where('is_selected', true)
                ->whereNotNull('selection_order')
                ->max('selection_order');
            $nextOrder = is_numeric($maxOrder) ? ((int) $maxOrder + 1) : 0;
        }

        foreach ($files as $ingestFile) {
            if (! $this->ingestIsAssignableForBulk($ingestFile)) {
                $failures[] = '#'.$ingestFile->id.' (nicht zuordenbar)';

                continue;
            }

            try {
                if ($ingestFile->isIngestImageFile()) {
                    $media = $imageToNews->finalize($ingestFile, $newsItem);
                    $absPath = $ingestFile->absolute_path;
                    if (is_string($absPath) && $absPath !== '' && $directories->isManagedAbsoluteFile($absPath) && is_file($absPath)) {
                        @unlink($absPath);
                    }
                    $thumbs->removeThumbnail($ingestFile);
                    $ingestFile->update([
                        'status' => IngestFile::STATUS_USED,
                        'news_item_id' => $newsItem->id,
                        'final_news_item_media_id' => $media->id,
                    ]);
                    $finalizedImages++;
                } else {
                    $payload = [
                        'news_item_id' => $newsItem->id,
                        'selection_order' => $ingestFile->selection_order,
                        'status' => IngestFile::STATUS_ASSIGNED,
                    ];

                    $oldId = $ingestFile->news_item_id !== null ? (int) $ingestFile->news_item_id : null;
                    if ($oldId !== null && $oldId !== (int) $newsItem->id) {
                        // Clip bekommt bei Wechsel der Meldung neue Final-Reihenfolge.
                        $payload['selection_order'] = null;
                        $payload['trim_in_seconds'] = null;
                        $payload['trim_out_seconds'] = null;
                    }

                    if ($hasIsSelected) {
                        $payload['is_selected'] = $autoSelect;
                        if (
                            $autoSelect
                            && $payload['selection_order'] === null
                            && $hasSelectionOrder
                        ) {
                            $payload['selection_order'] = $nextOrder;
                            $nextOrder++;
                        }
                    }

                    $this->persistIngestAssignPayload($ingestFile, $payload);
                    $assignedVideos++;
                }
            } catch (\Throwable $e) {
                report($e);
                $failures[] = '#'.$ingestFile->id.' ('.$e->getMessage().')';
            }
        }

        $parts = [];
        if ($finalizedImages > 0) {
            $parts[] = $finalizedImages.' Bild(er) in die Galerie übernommen';
        }
        if ($assignedVideos > 0) {
            $parts[] = $assignedVideos.' Video(s) der Meldung zugeordnet';
        }
        $msg = $parts !== [] ? implode('; ', $parts).'.' : 'Keine Änderungen.';
        if ($failures !== []) {
            $msg .= ' Fehler: '.implode(', ', $failures);
        }

        return $this->redirectAfterIngestBulk($request)
            ->with($failures !== [] && ($finalizedImages + $assignedVideos) === 0 ? 'error' : 'status', $msg);
    }

    private function ingestIsAssignableForBulk(IngestFile $ingestFile): bool
    {
        if ($ingestFile->status === IngestFile::STATUS_USED) {
            return false;
        }

        if (in_array($ingestFile->status, [
            IngestFile::STATUS_PREVIEW_GENERATING,
            IngestFile::STATUS_RENDERING,
        ], true)) {
            return false;
        }

        return in_array($ingestFile->status, [
            IngestFile::STATUS_VALIDATED,
            IngestFile::STATUS_PREVIEW_READY,
            IngestFile::STATUS_ASSIGNED,
        ], true);
    }

    private function ingestDestroyErrorReason(IngestFile $ingestFile): ?string
    {
        if ($ingestFile->status === IngestFile::STATUS_USED) {
            return 'Bereits übernommene Einträge können nicht gelöscht werden.';
        }

        if (in_array($ingestFile->status, [
            IngestFile::STATUS_RENDERING,
            IngestFile::STATUS_PREVIEW_GENERATING,
        ], true)) {
            return 'Löschen ist während Verarbeitung nicht möglich.';
        }

        return null;
    }

    private function ingestDeleteFileAndRecord(IngestFile $ingestFile, IngestDirectoryService $directories, IngestImageThumbnailService $thumbs): void
    {
        $thumbs->removeThumbnail($ingestFile);

        $path = $ingestFile->absolute_path;
        if (is_string($path) && $path !== '' && $directories->isManagedAbsoluteFile($path) && is_file($path)) {
            @unlink($path);
        }

        $ingestFile->delete();
    }

    private function selectedAdminBrandId(Request $request): ?int
    {
        $raw = $request->session()->get('admin.brand_filter');
        if (is_int($raw)) {
            return $raw;
        }
        if (is_string($raw) && ctype_digit($raw)) {
            return (int) $raw;
        }

        return null;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Organization>
     */
    private function directMarketingOrganizations(Request $request): \Illuminate\Support\Collection
    {
        if (! Schema::hasTable('organizations')) {
            return collect();
        }

        $q = Organization::query()->orderBy('name');

        if (Schema::hasColumn('organizations', 'active')) {
            $q->where('active', true);
        }

        $selectedBrandId = $this->selectedAdminBrandId($request);
        if ($selectedBrandId !== null && Schema::hasColumn('organizations', 'brand_id')) {
            $q->where('brand_id', $selectedBrandId);
        }

        return $q->get(['id', 'name']);
    }

    private function newsItemMediaTableHasColumn(string $column): bool
    {
        static $cache = [];
        $k = 'news_item_media:'.$column;
        if (! array_key_exists($k, $cache)) {
            $cache[$k] = Schema::hasTable('news_item_media') && Schema::hasColumn('news_item_media', $column);
        }

        return $cache[$k];
    }

    private function redirectAfterIngestBulk(Request $request): RedirectResponse
    {
        $to = $request->input('return_to');
        if (is_string($to) && $to !== '') {
            $appUrl = rtrim((string) config('app.url'), '/');
            if (str_starts_with($to, $appUrl) || (str_starts_with($to, '/') && ! str_starts_with($to, '//'))) {
                return redirect()->to($to);
            }
        }

        return redirect()->route('admin.ingest.index');
    }

    private function ingestSafeRedirectTarget(?string $candidate, string $fallback): string
    {
        if (! is_string($candidate) || $candidate === '') {
            return $fallback;
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        if (str_starts_with($candidate, $appUrl) || (str_starts_with($candidate, '/') && ! str_starts_with($candidate, '//'))) {
            return $candidate;
        }

        return $fallback;
    }

    private function ingestUrlsSharePath(string $left, string $right): bool
    {
        $leftPath = parse_url($left, PHP_URL_PATH);
        $rightPath = parse_url($right, PHP_URL_PATH);

        if (! is_string($leftPath) || ! is_string($rightPath)) {
            return false;
        }

        return rtrim($leftPath, '/') === rtrim($rightPath, '/');
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
        if ($duration === null && $ingestFile->isIngestImageFile()) {
            $duration = (float) config('ingest.image_still_seconds', 5);
        }
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
        if ($ingestFile->isIngestImageFile()) {
            return back()->with('error', 'Bilder werden nicht für den Finalschnitt markiert.');
        }

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
        if (! is_string($path) || $path === '') {
            abort(404);
        }

        $resolved = $mediaStorage->resolveReadableLocalPath($path);
        $localPath = $resolved['path'] ?? null;
        if (! is_string($localPath) || ! is_file($localPath) || ! is_readable($localPath)) {
            abort(404);
        }

        $headers = [
            'Accept-Ranges' => 'bytes',
            'Content-Type' => 'video/mp4',
        ];

        if ($request->boolean('download')) {
            $response = response()->download($localPath, 'ingest-'.$ingestFile->id.'-preview.mp4', $headers);
        } else {
            $response = response()->file($localPath, $headers);
        }

        if (! empty($resolved['temporary'])) {
            $response->deleteFileAfterSend(true);
        }

        return $response;
    }

    public function newsWorkspace(Request $request, NewsItem $newsItem): View
    {
        app(IngestRenderJobReleaseService::class)->releaseStaleForNewsItem($newsItem->id);

        $clips = IngestFile::query()
            ->where('news_item_id', $newsItem->id)
            ->whereIn('status', [
                IngestFile::STATUS_ASSIGNED,
                'rendering',
            ])
            ->where(function (Builder $q) {
                $q->whereRaw('LOWER(original_name) NOT LIKE ?', ['%.jpg'])
                    ->orWhereRaw('LOWER(original_name) NOT LIKE ?', ['%.jpeg'])
                    ->orWhereRaw('LOWER(original_name) NOT LIKE ?', ['%.jpe']);
            })
            ->orderByRaw('selection_order IS NULL')
            ->orderBy('selection_order')
            ->orderBy('id')
            ->get();

        $usedClipsCount = (int) IngestFile::query()
            ->where('status', IngestFile::STATUS_USED)
            ->whereIn('final_news_item_media_id', function ($q) use ($newsItem) {
                $q->select('id')
                    ->from('news_item_media')
                    ->where('news_item_id', $newsItem->id)
                    ->where('type', 'video');
            })
            ->count();

        $activeJob = IngestRenderJob::query()
            ->where('news_item_id', $newsItem->id)
            ->whereIn('status', [
                IngestRenderJob::STATUS_QUEUED,
                IngestRenderJob::STATUS_RENDERING,
                IngestRenderJob::STATUS_UPLOADING,
            ])
            ->latest('id')
            ->first();

        // Diagnose: bei Fehlschlag (STATUS_FAILED) ist der Job sonst unsichtbar und der Button wirkt „ohne Effekt“.
        $lastJob = IngestRenderJob::query()
            ->where('news_item_id', $newsItem->id)
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

        $newsStatusKey = 'news.status.'.$newsItem->status;
        $newsStatusLabel = __($newsStatusKey);
        if ($newsStatusLabel === $newsStatusKey) {
            $newsStatusLabel = (string) $newsItem->status;
        }

        $workspaceSummary = [
            'total' => $clips->count(),
            'assigned' => $clips->where('status', IngestFile::STATUS_ASSIGNED)->count(),
            'rendering' => $clips->where('status', 'rendering')->count(),
            'used' => $usedClipsCount,
            'marked_for_final' => $clips->filter(fn (IngestFile $c): bool => (bool) ($c->is_selected ?? false))->count(),
            'with_preview_file' => $clips->filter(fn (IngestFile $c): bool => $c->hasIngestPreviewVideo())->count(),
        ];

        return view('admin.ingest.news-workspace', compact(
            'newsItem',
            'clips',
            'usedClipsCount',
            'activeJob',
            'lastJob',
            'ingestRenderPoll',
            'ingestRenderMonitorOptions',
            'newsStatusLabel',
            'workspaceSummary',
        ));
    }

    private function ingestRenderJobProgress(IngestRenderJob $job): array
    {
        // Fortschritt ist nicht 1:1 ffmpeg-progress, sondern eine stabile Heuristik, damit die UI 0-100 % zeigen kann.
        // Prozess-Timeout ist in ProcessIngestRenderJob definiert.
        $timeoutSeconds = 14400;
        $elapsedSeconds = $job->created_at ? max(0, $job->created_at->diffInSeconds(now())) : 0;
        $ratio = $timeoutSeconds > 0 ? min(1, $elapsedSeconds / $timeoutSeconds) : 0;

        return match ($job->status) {
            IngestRenderJob::STATUS_QUEUED => [
                'progress' => 0,
                'stage' => 'Wartet',
                'stageKey' => 'queued',
            ],
            IngestRenderJob::STATUS_RENDERING => [
                'progress' => (int) min(79, floor($ratio * 79)),
                'stage' => 'Rendern',
                'stageKey' => 'rendering',
            ],
            IngestRenderJob::STATUS_UPLOADING => [
                'progress' => (int) min(99, 80 + floor($ratio * 19)),
                'stage' => 'Upload',
                'stageKey' => 'uploading',
            ],
            IngestRenderJob::STATUS_COMPLETED => [
                'progress' => 100,
                'stage' => 'Fertig',
                'stageKey' => 'completed',
            ],
            IngestRenderJob::STATUS_FAILED => [
                'progress' => 100,
                'stage' => 'Fehlgeschlagen',
                'stageKey' => 'failed',
            ],
            default => [
                'progress' => 0,
                'stage' => (string) $job->status,
                'stageKey' => 'unknown',
            ],
        };
    }

    /**
     * Admin-Übersicht: offene Render-Jobs (inkl. failed) mit Progress 0-100.
     */
    public function renderJobs(): View
    {
        $jobs = IngestRenderJob::query()
            ->whereIn('status', [
                IngestRenderJob::STATUS_QUEUED,
                IngestRenderJob::STATUS_RENDERING,
                IngestRenderJob::STATUS_UPLOADING,
                IngestRenderJob::STATUS_FAILED,
            ])
            ->with(['newsItem:id,title,status'])
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();

        $jobsData = $jobs->map(function (IngestRenderJob $j) {
            $p = $this->ingestRenderJobProgress($j);

            return [
                'id' => (int) $j->id,
                'news_item_id' => (int) $j->news_item_id,
                'status' => (string) $j->status,
                'stage' => (string) $p['stage'],
                'progress' => (int) $p['progress'],
                'error_message' => filled($j->error_message) ? (string) $j->error_message : null,
                'updated_at' => $j->updated_at?->toIso8601String(),
                'news_title' => $j->newsItem?->title ?? null,
            ];
        })->values();

        return view('admin.ingest.render-jobs', [
            'jobsData' => $jobsData,
        ]);
    }

    /**
     * JSON-Endpoint für Polling im Render-Jobs-UI.
     */
    public function renderJobsStatus(): JsonResponse
    {
        $jobs = IngestRenderJob::query()
            ->whereIn('status', [
                IngestRenderJob::STATUS_QUEUED,
                IngestRenderJob::STATUS_RENDERING,
                IngestRenderJob::STATUS_UPLOADING,
                IngestRenderJob::STATUS_FAILED,
            ])
            ->with(['newsItem:id,title,status'])
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();

        $jobsData = $jobs->map(function (IngestRenderJob $j) {
            $p = $this->ingestRenderJobProgress($j);

            return [
                'id' => (int) $j->id,
                'news_item_id' => (int) $j->news_item_id,
                'status' => (string) $j->status,
                'stage' => (string) $p['stage'],
                'progress' => (int) $p['progress'],
                'error_message' => filled($j->error_message) ? (string) $j->error_message : null,
                'updated_at' => $j->updated_at?->toIso8601String(),
                'news_title' => $j->newsItem?->title ?? null,
            ];
        })->values();

        return response()->json([
            'jobs' => $jobsData,
            'generated_at' => now()->toIso8601String(),
        ]);
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
        app(IngestRenderJobReleaseService::class)->releaseStaleForNewsItem($newsItem->id);

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
            if ($f->isIngestImageFile()) {
                return back()->with('error', 'JPEG-Bilder gehören nicht in die sendefähige MP4 – sie werden bei der Zuordnung zur Meldung direkt in die Bildergalerie übernommen.');
            }
            if (! is_file($f->absolute_path)) {
                return back()->with('error', 'Datei fehlt: #'.$f->id);
            }

            $duration = $f->duration_s !== null ? (float) $f->duration_s : null;
            if ($duration === null && $f->isIngestImageFile()) {
                $duration = (float) config('ingest.image_still_seconds', 5);
            }
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

        // Checkbox „Nutzen“ = redaktionelle Auswahl für diesen Render; DB-Flag für Anzeige/Konsistenz setzen
        IngestFile::whereIn('id', $ids)->update(['is_selected' => true]);

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
            ->with(
                'status',
                'Sendefassung wurde in die Warteschlange gelegt (Job #'.$job->id.'). Der Render läuft jetzt im Hintergrund. Du kannst hier weiterarbeiten.'
            );
    }

    /**
     * Aktiven Render-Job manuell beenden, damit ein neuer Job für dieselbe Meldung angelegt werden kann.
     */
    public function cancelRenderJob(NewsItem $newsItem, IngestRenderJob $job): RedirectResponse
    {
        if ((int) $job->news_item_id !== (int) $newsItem->id) {
            abort(404);
        }

        if (! in_array($job->status, [
            IngestRenderJob::STATUS_QUEUED,
            IngestRenderJob::STATUS_RENDERING,
            IngestRenderJob::STATUS_UPLOADING,
        ], true)) {
            return back()->with('error', 'Dieser Job ist nicht mehr abbrechbar (Status: '.$job->status.').');
        }

        $job->update([
            'status' => IngestRenderJob::STATUS_FAILED,
            'error_message' => 'Manuell im Schnitt-Workspace abgebrochen.',
        ]);

        return redirect()
            ->route('admin.ingest.news-workspace', $newsItem)
            ->with('status', 'Render-Job #'.$job->id.' wurde beendet. Du kannst jetzt erneut „Sendefähige MP4 erzeugen“ wählen.');
    }

    public function playback(IngestFile $ingestFile, IngestDirectoryService $directories): BinaryFileResponse
    {
        $path = $ingestFile->absolute_path;
        if (! $directories->isManagedAbsoluteFile($path)) {
            abort(404);
        }

        $mime = $ingestFile->mime;
        if (! is_string($mime) || $mime === '') {
            $mime = $ingestFile->isIngestImageFile() ? 'image/jpeg' : 'video/mp4';
        }

        return response()->file($path, [
            'Content-Type' => $mime,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function thumbnail(IngestFile $ingestFile, IngestDirectoryService $directories, IngestImageThumbnailService $thumbs, IngestVideoPosterService $posters): BinaryFileResponse
    {
        if ($ingestFile->isIngestImageFile()) {
            $thumbPath = $thumbs->resolveStoredThumbPath($ingestFile);
            if (! is_string($thumbPath) || ! is_file($thumbPath)) {
                $thumbPath = $thumbs->ensureThumbnail($ingestFile, force: false);
            }

            if (is_string($thumbPath) && $thumbPath !== '' && $directories->isManagedAbsoluteFile($thumbPath)) {
                return response()->file($thumbPath, [
                    'Content-Type' => 'image/webp',
                    'Cache-Control' => 'private, max-age=86400',
                ]);
            }

            return $this->playback($ingestFile, $directories);
        }

        if ($ingestFile->isIngestVideoFile()) {
            $posterPath = $posters->resolveStoredThumbPath($ingestFile);
            if (! is_string($posterPath) || ! is_file($posterPath)) {
                $posterPath = $posters->ensurePoster($ingestFile, force: false);
            }

            if (is_string($posterPath) && $posterPath !== '' && is_file($posterPath)) {
                return response()->file($posterPath, [
                    'Content-Type' => 'image/webp',
                    'Cache-Control' => 'private, max-age=86400',
                ]);
            }

            if ($ingestFile->isBrowserPlayableOriginal()) {
                return $this->playback($ingestFile, $directories);
            }
        }

        abort(404);
    }
}
