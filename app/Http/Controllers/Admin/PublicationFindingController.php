<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\BuildPublicationFindingEvidenceDossierJob;
use App\Models\MediaPublicationFinding;
use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use App\Models\Organization;
use App\Services\MediaStorage;
use App\Services\Publication\PublicationFindingManualEvidenceService;
use App\Support\PublicationDomainMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicationFindingController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (! Schema::hasTable('media_publication_findings')) {
            return redirect()->route('admin.backoffice.index')
                ->with('error', 'Tabelle für Fundstellen fehlt. Bitte Migrationen ausführen.');
        }

        $query = MediaPublicationFinding::query()
            ->with(['newsItem', 'media', 'mediaItems', 'organization', 'creator'])
            ->orderByDesc('found_at')
            ->orderByDesc('id');

        if ($request->filled('kind')) {
            $query->where('kind', $request->string('kind'));
        }

        if ($request->filled('media_id')) {
            $mediaId = $request->integer('media_id');
            $query->where(function ($q) use ($mediaId): void {
                $q->where('news_item_media_id', $mediaId)
                    ->orWhereHas('mediaItems', fn ($mq) => $mq->where('news_item_media.id', $mediaId));
            });
        }

        if ($request->boolean('unconfirmed')) {
            $query->where('confirmed', false);
        }

        $findings = $query->paginate(30)->withQueryString();

        return view('admin.backoffice.publication-findings.index', [
            'findings' => $findings,
            'kindLabels' => MediaPublicationFinding::kindLabels(),
            'monitorEnabled' => (bool) config('publication_monitor.enabled', false),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        if (! Schema::hasTable('media_publication_findings')) {
            return redirect()->route('admin.backoffice.index')
                ->with('error', 'Tabelle für Fundstellen fehlt. Bitte Migrationen ausführen.');
        }

        $media = null;
        $newsItem = null;
        $mediaId = $request->integer('news_item_media_id');
        $newsId = $request->integer('news_item_id');

        if ($mediaId > 0) {
            $media = NewsItemMedia::query()->with('newsItem')->find($mediaId);
            $newsItem = $media?->newsItem;
        } elseif ($newsId > 0) {
            $newsItem = NewsItem::query()->find($newsId);
        }

        $suggestedKind = MediaPublicationFinding::KIND_UNKNOWN;
        $suggestedOrg = null;
        $prefillUrl = trim((string) $request->get('url', ''));

        if ($prefillUrl !== '') {
            $suggestedOrg = PublicationDomainMatcher::findLicensedOrganization($prefillUrl);
            if ($suggestedOrg !== null) {
                $suggestedKind = MediaPublicationFinding::KIND_LICENSED;
            }
        }

        return view('admin.backoffice.publication-findings.create', [
            'finding' => new MediaPublicationFinding([
                'news_item_id' => $newsItem?->id,
                'news_item_media_id' => $media?->id,
                'kind' => $suggestedKind,
                'organization_id' => $suggestedOrg?->id,
                'url' => $prefillUrl,
                'found_at' => now(),
                'scan_source' => 'manual',
            ]),
            'media' => $media,
            'newsItem' => $newsItem,
            'kindLabels' => MediaPublicationFinding::kindLabels(),
            'organizations' => Organization::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
            'newsImages' => $this->formatMediaForPicker($this->mediaForNewsItem($newsItem?->id)),
            'newsImagesTitle' => $newsItem?->title,
            'selectedMediaIds' => array_filter([
                $media?->id,
            ]),
        ]);
    }

    public function newsImages(Request $request): JsonResponse
    {
        $newsId = $request->integer('news_item_id');
        if ($newsId <= 0) {
            return response()->json(['images' => [], 'title' => null]);
        }

        $newsItem = NewsItem::query()->find($newsId);
        if ($newsItem === null) {
            return response()->json(['images' => [], 'title' => null, 'error' => 'Meldung nicht gefunden.'], 404);
        }

        return response()->json([
            'news_item_id' => $newsId,
            'title' => $newsItem->title,
            'images' => $this->formatMediaForPicker($this->mediaForNewsItem($newsId)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('media_publication_findings')) {
            return redirect()->route('admin.backoffice.index')
                ->with('error', 'Tabelle für Fundstellen fehlt. Bitte Migrationen ausführen.');
        }

        $data = $this->validated($request);
        $data['url_hash'] = MediaPublicationFinding::hashUrl($data['url']);
        $data['created_by'] = Auth::id();
        $data['auto_detected'] = false;
        $data['confirmed'] = $request->boolean('confirmed');
        $data['scan_source'] = $data['scan_source'] ?? 'manual';

        if (empty($data['organization_id']) && $data['kind'] === MediaPublicationFinding::KIND_LICENSED) {
            $matched = PublicationDomainMatcher::findLicensedOrganization($data['url']);
            if ($matched !== null) {
                $data['organization_id'] = $matched->id;
            }
        }

        $mediaIds = $this->extractMediaIds($request);
        unset($data['news_item_media_ids']);

        $finding = MediaPublicationFinding::create($data);
        $this->syncMediaItems($finding, $mediaIds);
        $this->maybeQueueEvidenceDossier($finding->fresh());

        $message = 'Fundstelle wurde gespeichert.';
        if ($this->shouldAutoBuildEvidenceDossier($finding)) {
            $message .= ' Die Beweismittelmappe wird im Hintergrund erstellt.';
        }

        return redirect()
            ->route('admin.backoffice.publication-findings.index', [
                'media_id' => $finding->news_item_media_id,
            ])
            ->with('status', $message);
    }

    public function edit(MediaPublicationFinding $publicationFinding): View
    {
        $publicationFinding->load(['newsItem', 'media', 'mediaItems', 'organization', 'authorityDownloads']);

        return view('admin.backoffice.publication-findings.edit', [
            'finding' => $publicationFinding,
            'kindLabels' => MediaPublicationFinding::kindLabels(),
            'organizations' => Organization::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
            'newsImages' => $this->formatMediaForPicker($this->mediaForNewsItem($publicationFinding->news_item_id)),
            'newsImagesTitle' => $publicationFinding->newsItem?->title,
            'selectedMediaIds' => $publicationFinding->mediaItems->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, MediaPublicationFinding $publicationFinding): RedirectResponse
    {
        $data = $this->validated($request);
        $data['url_hash'] = MediaPublicationFinding::hashUrl($data['url']);

        if (empty($data['organization_id']) && $data['kind'] === MediaPublicationFinding::KIND_LICENSED) {
            $matched = PublicationDomainMatcher::findLicensedOrganization($data['url']);
            if ($matched !== null) {
                $data['organization_id'] = $matched->id;
            }
        }

        $mediaIds = $this->extractMediaIds($request);
        unset($data['news_item_media_ids']);

        $publicationFinding->update($data);
        $this->syncMediaItems($publicationFinding, $mediaIds);

        return redirect()
            ->route('admin.backoffice.publication-findings.index')
            ->with('status', 'Fundstelle wurde aktualisiert.');
    }

    public function destroy(MediaPublicationFinding $publicationFinding): RedirectResponse
    {
        $publicationFinding->delete();

        return redirect()
            ->route('admin.backoffice.publication-findings.index')
            ->with('status', 'Fundstelle wurde gelöscht.');
    }

    public function confirm(MediaPublicationFinding $publicationFinding): RedirectResponse
    {
        $publicationFinding->update(['confirmed' => true]);

        return back()->with('status', 'Fundstelle wurde bestätigt.');
    }

    public function buildEvidenceDossier(MediaPublicationFinding $publicationFinding): RedirectResponse
    {
        BuildPublicationFindingEvidenceDossierJob::dispatch($publicationFinding->id);

        return back()->with('status', 'Beweismittelmappe wird erstellt (Hintergrundjob).');
    }

    public function downloadEvidenceDossier(MediaPublicationFinding $publicationFinding, MediaStorage $mediaStorage): StreamedResponse|RedirectResponse
    {
        $path = (string) ($publicationFinding->evidence_dossier_path ?? '');
        if ($path === '' || ! $mediaStorage->exists($path)) {
            return back()->with('error', 'Beweismittelmappe ist noch nicht verfügbar.');
        }

        $filename = 'Beweismittelmappe-Fundstelle-'.$publicationFinding->id.'.zip';

        return $mediaStorage->activeDisk()->download($path, $filename);
    }

    public function storeManualEvidence(
        Request $request,
        MediaPublicationFinding $publicationFinding,
        PublicationFindingManualEvidenceService $manualEvidence,
    ): RedirectResponse {
        $types = implode(',', array_keys(PublicationFindingManualEvidenceService::typeLabels()));

        $validated = $request->validate([
            'evidence_type' => ['required', 'string', 'in:'.$types],
            'file' => ['required', 'file', 'max:'.(int) config('publication_evidence.manual_upload_max_kb', 512000)],
        ]);

        $file = $request->file('file');
        if (! $file instanceof \Illuminate\Http\UploadedFile) {
            return back()->with('error', 'Keine Datei empfangen.');
        }

        try {
            $manualEvidence->store($publicationFinding, $validated['evidence_type'], $file);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage() !== '' ? $e->getMessage() : 'Upload fehlgeschlagen.');
        }

        BuildPublicationFindingEvidenceDossierJob::dispatch($publicationFinding->id);

        $label = PublicationFindingManualEvidenceService::typeLabels()[$validated['evidence_type']] ?? 'Datei';

        return back()->with('status', $label.' wurde hochgeladen. Die Beweismittelmappe wird im Hintergrund neu erstellt.');
    }

    public function destroyManualEvidence(
        MediaPublicationFinding $publicationFinding,
        string $evidenceType,
        PublicationFindingManualEvidenceService $manualEvidence,
    ): RedirectResponse {
        if (! array_key_exists($evidenceType, PublicationFindingManualEvidenceService::typeLabels())) {
            return back()->with('error', 'Unbekannter Beweismittel-Typ.');
        }

        $manualEvidence->delete($publicationFinding, $evidenceType);

        return back()->with('status', 'Manuelles Beweismittel wurde entfernt.');
    }

    public function showManualEvidence(
        MediaPublicationFinding $publicationFinding,
        string $evidenceType,
        MediaStorage $mediaStorage,
    ): StreamedResponse|RedirectResponse {
        if (! array_key_exists($evidenceType, PublicationFindingManualEvidenceService::typeLabels())) {
            abort(404);
        }

        $manual = (array) ($publicationFinding->evidence_manual_files ?? []);
        $path = (string) ($manual[$evidenceType]['path'] ?? '');
        $name = (string) ($manual[$evidenceType]['original_name'] ?? $evidenceType);

        if ($path === '' || ! $mediaStorage->exists($path)) {
            return back()->with('error', 'Datei nicht gefunden.');
        }

        return $mediaStorage->activeDisk()->response($path, $name);
    }

    public function createAuthorityAccess(Request $request, MediaPublicationFinding $publicationFinding): RedirectResponse
    {
        if ((string) ($publicationFinding->evidence_dossier_path ?? '') === '') {
            return back()->with('error', 'Bitte zuerst eine Beweismittelmappe (ZIP) erstellen, bevor ein Behördenzugang freigegeben wird.');
        }

        $validated = $request->validate([
            'authority_access_recipient' => ['nullable', 'string', 'max:255'],
            'authority_access_file_reference' => ['nullable', 'string', 'max:128'],
            'authority_access_password' => ['nullable', 'string', 'min:8', 'max:64'],
            'authority_link_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $days = (int) ($validated['authority_link_days']
            ?? config('publication_evidence.authority_link_days', 30));

        $publicationFinding->update([
            'authority_access_token' => Str::lower(Str::random(48)),
            'authority_access_expires_at' => now()->addDays($days),
            'authority_access_password' => ! empty($validated['authority_access_password'])
                ? Hash::make($validated['authority_access_password'])
                : null,
            'authority_access_recipient' => trim((string) ($validated['authority_access_recipient'] ?? '')) ?: null,
            'authority_access_file_reference' => trim((string) ($validated['authority_access_file_reference'] ?? '')) ?: null,
            'authority_access_created_by' => Auth::id(),
            'authority_access_created_at' => now(),
            'authority_access_revoked_at' => null,
        ]);

        $redirect = back()->with('status', 'Behördenzugang wurde erstellt. Link und Zugangscode unten kopieren. Aktenzeichen kann später ergänzt werden – der Link bleibt gültig.');

        if (! empty($validated['authority_access_password'])) {
            $redirect->with('authority_access_plain_password', $validated['authority_access_password']);
        }

        return $redirect;
    }

    public function updateAuthorityAccessMeta(Request $request, MediaPublicationFinding $publicationFinding): RedirectResponse
    {
        if (! $publicationFinding->hasActiveAuthorityAccess()) {
            return back()->with('error', 'Es gibt keinen aktiven Behördenzugang.');
        }

        $validated = $request->validate([
            'authority_access_recipient' => ['nullable', 'string', 'max:255'],
            'authority_access_file_reference' => ['nullable', 'string', 'max:128'],
        ]);

        $publicationFinding->update([
            'authority_access_recipient' => trim((string) ($validated['authority_access_recipient'] ?? '')) ?: null,
            'authority_access_file_reference' => trim((string) ($validated['authority_access_file_reference'] ?? '')) ?: null,
        ]);

        return back()->with('status', 'Behördenangaben wurden aktualisiert (Link unverändert).');
    }

    public function revokeAuthorityAccess(MediaPublicationFinding $publicationFinding): RedirectResponse
    {
        $publicationFinding->update([
            'authority_access_revoked_at' => now(),
        ]);

        return back()->with('status', 'Behördenzugang wurde widerrufen.');
    }

    public function triggerScan(Request $request): RedirectResponse
    {
        if (! (bool) config('publication_monitor.enabled', false)) {
            return back()->with(
                'error',
                'Automatischer TinEye-Scan ist deaktiviert (PUBLICATION_MONITOR_ENABLED=false). Bitte Google Lens / Google News in der Bild-Mediathek nutzen und Treffer manuell erfassen.'
            );
        }

        return back()->with('error', 'Automatischer Scan ist konfiguriert, aber noch nicht angebunden. Bitte Fundstellen manuell erfassen.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $kinds = array_keys(MediaPublicationFinding::kindLabels());

        $data = $request->validate([
            'news_item_id' => ['nullable', 'integer', 'exists:news_items,id'],
            'news_item_media_ids' => ['nullable', 'array'],
            'news_item_media_ids.*' => ['integer', 'exists:news_item_media,id'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'kind' => ['required', 'string', 'in:'.implode(',', $kinds)],
            'url' => ['required', 'url', 'max:2048'],
            'page_title' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'found_at' => ['nullable', 'date'],
            'scan_source' => ['nullable', 'string', 'max:32'],
            'confirmed' => ['nullable', 'boolean'],
        ]);

        $mediaIds = array_values(array_unique(array_map('intval', $data['news_item_media_ids'] ?? [])));
        if ($mediaIds !== [] && empty($data['news_item_id'])) {
            $first = NewsItemMedia::query()->find($mediaIds[0]);
            if ($first !== null) {
                $data['news_item_id'] = $first->news_item_id;
            }
        }
        $data['news_item_media_id'] = $mediaIds[0] ?? null;

        $data['found_at'] = isset($data['found_at'])
            ? \Carbon\Carbon::parse($data['found_at'])
            : now();

        return $data;
    }

    private function maybeQueueEvidenceDossier(MediaPublicationFinding $finding): void
    {
        if (! $this->shouldAutoBuildEvidenceDossier($finding)) {
            return;
        }

        BuildPublicationFindingEvidenceDossierJob::dispatch($finding->id);
    }

    private function shouldAutoBuildEvidenceDossier(MediaPublicationFinding $finding): bool
    {
        if (! (bool) config('publication_evidence.auto_build_on_store', true)) {
            return false;
        }

        $kinds = (array) config('publication_evidence.auto_build_kinds', ['infringement']);

        return in_array($finding->kind, $kinds, true);
    }

    /**
     * @return list<int>
     */
    private function extractMediaIds(Request $request): array
    {
        $ids = $request->input('news_item_media_ids', []);
        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    }

    /**
     * @param  list<int>  $mediaIds
     */
    private function syncMediaItems(MediaPublicationFinding $finding, array $mediaIds): void
    {
        $mediaIds = $this->mergeAutoVideoMediaIds($finding, $mediaIds);

        if ($mediaIds === []) {
            $finding->mediaItems()->detach();
            $finding->update(['news_item_media_id' => null]);

            return;
        }

        $validIds = NewsItemMedia::query()
            ->whereIn('id', $mediaIds)
            ->when($finding->news_item_id, fn ($q) => $q->where('news_item_id', $finding->news_item_id))
            ->pluck('id')
            ->all();

        $finding->mediaItems()->sync($validIds);
        $primaryId = NewsItemMedia::query()
            ->whereIn('id', $validIds)
            ->where('type', 'image')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->value('id');

        $finding->update(['news_item_media_id' => $primaryId ?? ($validIds[0] ?? null)]);
    }

    /**
     * Alle Videos der Meldung werden automatisch mitverknüpft (S3-Beweismittelmappe).
     *
     * @param  list<int>  $mediaIds
     * @return list<int>
     */
    private function mergeAutoVideoMediaIds(MediaPublicationFinding $finding, array $mediaIds): array
    {
        $newsId = $finding->news_item_id;
        if ($newsId === null || $newsId <= 0) {
            return $mediaIds;
        }

        $videoIds = NewsItemMedia::query()
            ->where('news_item_id', $newsId)
            ->where('type', 'video')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($videoIds === []) {
            return $mediaIds;
        }

        return array_values(array_unique(array_merge($mediaIds, $videoIds)));
    }

    /**
     * @return Collection<int, NewsItemMedia>
     */
    private function mediaForNewsItem(?int $newsId): Collection
    {
        if ($newsId === null || $newsId <= 0) {
            return collect();
        }

        return NewsItemMedia::query()
            ->where('news_item_id', $newsId)
            ->whereIn('type', ['image', 'video'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, NewsItemMedia>  $mediaItems
     * @return list<array{id: int, thumb_url: string|null, label: string, type: string}>
     */
    private function formatMediaForPicker(Collection $mediaItems): array
    {
        return $mediaItems->map(static function (NewsItemMedia $media): array {
            $thumb = $media->thumb_url ?: ($media->preview_url ?: $media->url);
            $label = $media->display_name ?: ($media->original_name ?: ($media->isVideo() ? 'Video' : 'Bild'));

            return [
                'id' => $media->id,
                'thumb_url' => $thumb,
                'label' => $label,
                'type' => (string) $media->type,
                'auto_link' => $media->isVideo(),
            ];
        })->values()->all();
    }
}
