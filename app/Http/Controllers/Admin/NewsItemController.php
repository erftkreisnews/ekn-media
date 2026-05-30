<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNewsItemRequest;
use App\Http\Requests\UpdateNewsItemRequest;
use App\Jobs\ExtractAudioMetadata;
use App\Jobs\ExtractVideoMetadata;
use App\Jobs\GenerateImageMetadata;
use App\Jobs\GenerateVideoPoster;
use App\Jobs\GenerateVideoStills;
use App\Jobs\ProcessMediaRedaction;
use App\Models\Brand;
use App\Models\DeliveryDestination;
use App\Models\NewsItem;
use App\Models\NewsItemDeleteAudit;
use App\Models\NewsItemFieldAudit;
use App\Models\NewsItemMedia;
use App\Models\NewsItemStatement;
use App\Models\NewsItemUpdate;
use App\Models\NewsItemWitnessSubmission;
use App\Models\Organization;
use App\Models\PlannedEvent;
use App\Models\PlannedEventTeam;
use App\Models\PresseportalOffice;
use App\Models\User;
use App\Services\Admin\NewsItemImageIngestService;
use App\Services\ImageMetadataReader;
use App\Services\ImageMetadataWriter;
use App\Services\MediaQualityCheck;
use App\Services\MediaStorage;
use App\Services\PlannedEvents\ScheduleSlotMatcher;
use App\Support\AdminBrandNewsEntry;
use App\Support\MediaCaptionLocationDateTail;
use App\Support\MediaKeywordNormalizer;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NewsItemController extends Controller
{
    /**
     * Medien-Spalten, die in {@see NewsItemMedia::resolvedIptcForEmbed()} einfließen.
     * Nur bei Änderung dieser Felder muss beim Speichern das JPEG erneut beschrieben werden —
     * sonst würde jeder Meldungs-Speichervorgang bei N Bildern N-mal I/O (lokal oder S3→Temp) auslösen.
     *
     * @var list<string>
     */
    private const MEDIA_UPDATE_KEYS_TRIGGERING_IPTC_EMBED = [
        'caption',
        'image_title',
        'photographer',
        'credit',
        'copyright',
        'source',
        'city',
        'state',
        'country',
        'country_code',
        'capture_time',
    ];

    public function index(Request $request)
    {
        $query = NewsItem::query()
            ->with(['author', 'images', 'videos'])
            ->withCount(['images', 'videos']);

        if (! $this->canManageNewsItemBypass()) {
            $query->where('author_id', auth()->id());
        }

        $selectedBrandId = $this->selectedAdminBrandId($request);
        if ($selectedBrandId !== null && $this->newsItemsHasColumn('brand_id')) {
            $query->where('brand_id', $selectedBrandId);
        }

        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            $query->where(function (Builder $b) use ($q) {
                $b->where('title', 'like', '%'.$q.'%');
                if (ctype_digit($q)) {
                    $b->orWhere('id', (int) $q);
                }
                $b->orWhereHas('author', function (Builder $a) use ($q) {
                    $a->where('name', 'like', '%'.$q.'%');
                });
            });
        }

        $sort = (string) $request->query('sort', 'created_at');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = [
            'status',
            'teaser_image',
            'id',
            'title',
            'images_count',
            'videos_count',
            'created_at',
            'author',
        ];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        switch ($sort) {
            case 'status':
                $query->orderBy('status', $direction)->orderByDesc('updated_at');
                break;
            case 'teaser_image':
                // Näherungswert für Teaserbild-Sortierung: vorhandene Bilder zuerst/zuletzt.
                $query->orderBy('images_count', $direction)->orderByDesc('updated_at');
                break;
            case 'id':
                $query->orderBy('id', $direction);
                break;
            case 'title':
                $query->orderBy('title', $direction)->orderByDesc('updated_at');
                break;
            case 'images_count':
                $query->orderBy('images_count', $direction)->orderByDesc('updated_at');
                break;
            case 'videos_count':
                $query->orderBy('videos_count', $direction)->orderByDesc('updated_at');
                break;
            case 'author':
                $query
                    ->leftJoin('users as author_users', 'author_users.id', '=', 'news_items.author_id')
                    ->orderBy('author_users.name', $direction)
                    ->orderByDesc('news_items.created_at')
                    ->select('news_items.*');
                break;
            case 'created_at':
            default:
                $query->orderBy('created_at', $direction);
                break;
        }

        $newsItems = $query
            ->paginate(15)
            ->withQueryString();

        $selectedBrand = AdminBrandNewsEntry::selectedBrand($request);

        return view('admin.news.index', compact('newsItems', 'sort', 'direction', 'selectedBrand'));
    }

    public function create(Request $request): View|RedirectResponse
    {
        if ($redirect = AdminBrandNewsEntry::redirectIfWrongCreateFlow($request, 'erftkreis_news')) {
            return $redirect;
        }

        $createData = $this->newsCreateViewData($request);

        // Immer die volle Nachrichten-Maske: fehlende View „create-koelnimage“ würde sonst
        // bei gewählter Marke Kölnimage einen Fehler auslösen (in Prod. oft als 404 maskiert).
        // Kölnimage blendet WDR/MoID per Alpine aus. Kurz-Maske: admin.news.create.koelnimage.
        return view('admin.news.create', $createData);
    }

    public function createErftkreis(Request $request): View|RedirectResponse
    {
        if ($redirect = AdminBrandNewsEntry::redirectIfWrongCreateFlow($request, 'erftkreis_news')) {
            return $redirect;
        }

        $createData = $this->newsCreateViewData($request, 'erftkreis_news');

        return view('admin.news.create', $createData);
    }

    public function createKoelnimage(Request $request): RedirectResponse
    {
        return redirect()->route('admin.koelnimage.foto.create');
    }

    public function createUpdate(Request $request, NewsItem $newsItem)
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        // Online-first: Updates werden im selben Datensatz erfasst.
        return redirect()
            ->route('admin.news.edit', ['newsItem' => $newsItem, 'tab' => 'ot'])
            ->with('status', 'Updates werden in dieser Nachricht erfasst. Bitte den Reiter „O-Töne & Updates“ verwenden.');
    }

    public function createImages(Request $request): View|RedirectResponse
    {
        if ($redirect = AdminBrandNewsEntry::redirectIfWrongCreateFlow($request, 'erftkreis_news')) {
            return $redirect;
        }

        $brands = $this->brandsForNewsForm();
        $newsBrandDefault = $this->selectedAdminBrandId($request);
        $currentUserId = (int) auth()->id();

        return view('admin.news.create-images', compact('brands', 'newsBrandDefault', 'currentUserId'));
    }

    public function store(StoreNewsItemRequest $request)
    {
        $isKoelnimagePhotoFlow = $request->input('news_entry_flow') === StoreNewsItemRequest::NEWS_FLOW_KOELNIMAGE_PHOTO;

        $data = $request->safe()->except(['images', 'videos', 'audios', 'author_credit_user_id', 'source_news_item_id', 'source_media_ids']);
        $data['author_credit'] = $this->authorCreditNameFromUserId((int) $request->validated('author_credit_user_id'));
        $data['author_id'] = auth()->id();
        $data['is_breaking'] = $request->boolean('is_breaking');
        $data['planned_video_upload'] = $request->boolean('planned_video_upload');
        $data['liveu_on_site'] = $request->boolean('liveu_on_site');
        $data['is_confidential'] = $request->boolean('is_confidential');
        $this->mergeBrandIdIntoNewsData($request, $data);
        $data['is_wdr_job'] = $this->resolveIsWdrJob($request, $data, null, $isKoelnimagePhotoFlow);
        $sourceNewsItemId = (int) $request->validated('source_news_item_id', 0);
        $sourceMediaIds = collect((array) $request->validated('source_media_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        $sourceNewsItem = null;
        if ($sourceNewsItemId > 0 && $this->newsItemsHasColumn('parent_news_item_id') && $this->newsItemsHasColumn('update_revision')) {
            $sourceNewsItem = NewsItem::query()->find($sourceNewsItemId);
            if ($sourceNewsItem) {
                $this->abortIfCannotManageNewsItem($sourceNewsItem);
                $rootNewsItemId = (int) ($sourceNewsItem->parent_news_item_id ?: $sourceNewsItem->id);
                $data['parent_news_item_id'] = $rootNewsItemId;
                $data['update_revision'] = $this->nextUpdateRevisionForRoot($rootNewsItemId);
                if (empty($data['update_type'])) {
                    $data['update_type'] = 'update';
                }
            }
        }

        if (empty($data['published_at'])) {
            $data['published_at'] = now();
        }
        if (empty($data['update_type'])) {
            $data['update_type'] = $isKoelnimagePhotoFlow ? 'final' : 'first_report';
        }

        $newsItem = NewsItem::create($data);

        if ($sourceNewsItem && $sourceMediaIds !== []) {
            $this->copySelectedSourceMediaToNewsItem($newsItem, $sourceNewsItem, $sourceMediaIds);
        }
        $this->processMediaUploads($newsItem, $request);

        if ($request->has('save_images_and_continue')) {
            return redirect()
                ->to(route('admin.news.edit', ['newsItem' => $newsItem]).'?tab=bilder&mode=pre_send#section-media')
                ->with('status', 'Bilder wurden gespeichert. Bitte jetzt Versand je Bild aktivieren.');
        }

        if ($request->has('save_and_redirect_to_send')) {
            $status = $isKoelnimagePhotoFlow
                ? 'Beitrag gespeichert. Bitte Bilder prüfen und „Versand = Ja“ setzen. Versand erfolgt danach in der Bearbeitung über „Speichern & Versenden“.'
                : 'Erstmeldung gespeichert. Bitte zuerst Bilder prüfen und „Versand = Ja“ setzen. Versand erfolgt danach in der Bearbeitung über „Speichern & Versenden“.';

            return redirect()
                ->to(route('admin.news.edit', ['newsItem' => $newsItem]).'?tab=bilder&mode=pre_send#section-media')
                ->with('status', $status);
        }

        if ($isKoelnimagePhotoFlow) {
            return redirect()
                ->route('admin.news.edit', ['newsItem' => $newsItem, 'tab' => 'bilder'])
                ->withFragment('section-media')
                ->with('status', 'Beitrag angelegt. Bearbeite hier Texte, Bilder und Versand.');
        }

        return redirect()
            ->route('admin.news.edit', ['newsItem' => $newsItem, 'tab' => 'ot'])
            ->with('status', 'Erstmeldung erstellt. Ergänze jetzt Updates, Bilder, Videos und PMs im selben Einsatz.');
    }

    public function edit(NewsItem $newsItem)
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        $statuses = $this->statuses();
        // PATCH: add statements and updates support for news items
        if (Schema::hasTable('news_item_statements') && Schema::hasTable('news_item_updates')) {
            $newsItem->load([
                'statements' => fn ($q) => $q->latest('received_at')->latest('id'),
                'updates' => fn ($q) => $q->latest('happened_at')->latest('id'),
            ]);
        } else {
            $newsItem->setRelation('statements', collect());
            $newsItem->setRelation('updates', collect());
        }
        $statementSourceOptions = NewsItemStatement::sourceTypeOptions();
        $statementTypeOptions = NewsItemStatement::statementTypeOptions();
        $updateTypeOptions = NewsItemUpdate::updateTypeOptions();
        $updateSourceOptions = NewsItemUpdate::sourceTypeOptions();

        $presseportalKeySet = ! empty(config('presseportal.api_key'));
        $presseportalWhitelistCount = 0;
        if (Schema::hasTable('presseportal_offices')) {
            $presseportalWhitelistCount = PresseportalOffice::query()->active()->count();
        }

        $organizationsForMediaDelivery = Organization::query()->orderBy('name')->get();

        $witnessSubmissions = collect();
        if (Schema::hasTable('news_item_witness_links')) {
            $newsItem->load([
                'witnessLinks' => fn ($q) => $q->withCount('submissions')->orderByDesc('created_at'),
            ]);
            $witnessSubmissions = NewsItemWitnessSubmission::query()
                ->where('news_item_id', $newsItem->id)
                ->with('link')
                ->orderByDesc('created_at')
                ->limit(100)
                ->get();
        }

        $webTextAiEnabled = ! empty(config('media_ai.api_key'));
        $creditUsers = $this->creditUsersForPicker();
        $plannedEvents = class_exists(PlannedEvent::class) && Schema::hasTable('planned_events')
            ? PlannedEvent::queryForNewsSelect($newsItem->planned_event_id, auth()->user())
            : collect();

        $fieldAuditsForEdit = collect();
        if (Schema::hasTable('news_item_field_audits')) {
            $fieldAuditsForEdit = NewsItemFieldAudit::query()
                ->where('news_item_id', $newsItem->id)
                ->with('user:id,name')
                ->orderByDesc('id')
                ->limit(100)
                ->get();
        }

        $brands = $this->brandsForNewsForm();
        $sourceNewsItem = null;
        $sourceMedia = collect();
        $rootNewsItemId = (int) ($newsItem->parent_news_item_id ?: 0);
        if ($rootNewsItemId > 0) {
            $sourceNewsItem = NewsItem::query()->find($rootNewsItemId);
            if ($sourceNewsItem) {
                $sourceMedia = $sourceNewsItem->media()
                    ->whereIn('type', ['image', 'video', 'audio'])
                    ->orderBy('sort_order')
                    ->get();
            }
        }

        [$bulkEventMotivPicks, $bulkEventMotivLabel] = $this->plannedEventMotivPickerData($newsItem);
        $bulkPlannedEventImageAiEnabled = $webTextAiEnabled
            && Schema::hasColumn('news_items', 'planned_event_id')
            && filled($newsItem->planned_event_id)
            && $this->refinementHintFromPlannedEvent($newsItem) !== '';
        $bulkEventMotivAssignEnabled = Schema::hasColumn('news_items', 'planned_event_id')
            && filled($newsItem->planned_event_id)
            && $bulkEventMotivPicks !== [];

        return view('admin.news.edit', compact(
            'newsItem',
            'sourceNewsItem',
            'sourceMedia',
            'brands',
            'statuses',
            'statementSourceOptions',
            'statementTypeOptions',
            'updateTypeOptions',
            'updateSourceOptions',
            'presseportalKeySet',
            'presseportalWhitelistCount',
            'organizationsForMediaDelivery',
            'witnessSubmissions',
            'webTextAiEnabled',
            'creditUsers',
            'plannedEvents',
            'fieldAuditsForEdit',
            'bulkPlannedEventImageAiEnabled',
            'bulkEventMotivAssignEnabled',
            'bulkEventMotivPicks',
            'bulkEventMotivLabel'
        ));
    }

    /**
     * Streamt Video/Audio für die Admin-Bearbeitung über dieselbe Origin wie die App.
     * Direkte S3-/Object-Storage-URLs in &lt;video&gt;/&lt;audio&gt; scheitern oft (CORS, Range, private Buckets).
     */
    public function playbackMedia(Request $request, NewsItem $newsItem, int $mediaId): StreamedResponse|BinaryFileResponse
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        $medium = $newsItem->media()->where('id', $mediaId)->firstOrFail();
        if (! in_array($medium->type, ['video', 'audio'], true)) {
            abort(404);
        }
        $path = $medium->path;
        if ($path === null || $path === '') {
            abort(404);
        }

        $mediaStorage = app(MediaStorage::class);
        $disk = $mediaStorage->activeDisk();
        if (! $disk->exists($path) && $mediaStorage->fallbackDiskName() !== $mediaStorage->activeDiskName()) {
            $disk = $mediaStorage->fallbackDisk();
        }
        if (! $disk->exists($path)) {
            abort(404);
        }

        $filename = $medium->original_name ?: basename($path);

        if ($request->boolean('download')) {
            return $disk->download($path, $filename);
        }

        return $disk->response($path, $filename, [
            'Accept-Ranges' => 'bytes',
        ]);
    }

    public function update(UpdateNewsItemRequest $request, NewsItem $newsItem)
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        $data = $request->safe()->except(['images', 'videos', 'audios', 'delete_media', 'media', 'teaser_media_id', 'publish_and_save', 'no_wdr_job', 'save_and_redirect_to_send', 'author_credit_user_id', 'source_news_item_id', 'source_media_ids']);
        $data['author_credit'] = $this->authorCreditNameFromUserId((int) $request->validated('author_credit_user_id'));
        if ($request->has('publish_and_save')) {
            $data['status'] = 'published';
            $data['published_at'] = $data['published_at'] ?? now();
        }
        $data['is_breaking'] = $request->boolean('is_breaking');
        $data['planned_video_upload'] = $request->boolean('planned_video_upload');
        $data['liveu_on_site'] = $request->boolean('liveu_on_site');
        $data['is_confidential'] = $request->boolean('is_confidential');
        $this->mergeBrandIdIntoNewsData($request, $data, $newsItem);
        $data['is_wdr_job'] = $this->resolveIsWdrJob($request, $data, $newsItem);
        $newsItem->update($data);

        $sourceNewsItemId = (int) $request->validated('source_news_item_id', 0);
        $sourceMediaIds = collect((array) $request->validated('source_media_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        if ($sourceNewsItemId > 0 && $sourceMediaIds !== []) {
            $sourceNewsItem = NewsItem::query()->find($sourceNewsItemId);
            if ($sourceNewsItem) {
                $this->abortIfCannotManageNewsItem($sourceNewsItem);
                $this->copySelectedSourceMediaToNewsItem($newsItem, $sourceNewsItem, $sourceMediaIds);
            }
        }

        if ($ids = $request->validated('delete_media')) {
            $media = $newsItem->media()->whereIn('id', $ids)->get();
            foreach ($media as $m) {
                $m->delete();
            }
        }

        // Roh-Input nutzen: validated('media') kann je nach Laravel-Nested-Wildcards Teilfelder weglassen.
        $mediaData = $request->input('media', []);
        if (is_array($mediaData) && $mediaData !== []) {
            $requestedMediaIds = [];
            foreach (array_keys($mediaData) as $rawId) {
                $i = (int) $rawId;
                if ($i > 0) {
                    $requestedMediaIds[] = $i;
                }
            }
            $requestedMediaIds = array_values(array_unique($requestedMediaIds));
            $mediaById = $requestedMediaIds === []
                ? collect()
                : $newsItem->media()->whereIn('id', $requestedMediaIds)->get()->keyBy('id');

            foreach ($mediaData as $id => $attrs) {
                $id = (int) $id;
                if ($id <= 0 || ! is_array($attrs)) {
                    continue;
                }
                $medium = $mediaById->get($id);
                if (! $medium) {
                    continue;
                }
                $update = [
                    'is_visible' => isset($attrs['is_visible']) ? (string) $attrs['is_visible'] !== '0' && (string) $attrs['is_visible'] !== 'false' : true,
                    'versand' => ! empty($attrs['versand']),
                ];
                if (! empty($attrs['delivery_visibility_set'])) {
                    $raw = $attrs['delivery_visible_for_organization_ids'] ?? [];
                    $update['delivery_visible_for_organization_ids'] = empty($raw)
                        ? null
                        : array_values(array_unique(array_map('intval', (array) $raw)));
                }
                if (array_key_exists('caption', $attrs)) {
                    $update['caption'] = $attrs['caption'] ?? null;
                }
                if ($medium->isVideo()) {
                    if (array_key_exists('media_keywords', $attrs)) {
                        $v = $attrs['media_keywords'] ?? null;
                        $update['media_keywords'] = is_string($v) && $v !== '' ? $v : null;
                    }
                    if (array_key_exists('description', $attrs)) {
                        $v = $attrs['description'] ?? null;
                        $update['description'] = is_string($v) && $v !== '' ? $v : null;
                    }
                    if (array_key_exists('metadata_location', $attrs)) {
                        $v = $attrs['metadata_location'] ?? null;
                        $update['metadata_location'] = is_string($v) && $v !== '' ? $v : null;
                    }
                    if (array_key_exists('metadata_recorded_at', $attrs)) {
                        $v = $attrs['metadata_recorded_at'] ?? null;
                        $update['metadata_recorded_at'] = is_string($v) && $v !== '' ? $v : null;
                    }
                }
                $dirtyUpdate = [];
                foreach ($update as $key => $value) {
                    $currentValue = $medium->getAttribute($key);
                    if ($this->mediaAttributeValueChanged($key, $currentValue, $value)) {
                        $dirtyUpdate[$key] = $value;
                    }
                }

                if ($dirtyUpdate === []) {
                    continue;
                }

                $medium->update($dirtyUpdate);

                $iptcKeysTouched = array_intersect(array_keys($dirtyUpdate), self::MEDIA_UPDATE_KEYS_TRIGGERING_IPTC_EMBED);
                $iptcRel = $medium->resolveIptcMasterRelativePath();
                if ($iptcRel !== null && $iptcKeysTouched !== []) {
                    $mediaStorage = app(MediaStorage::class);
                    $resolved = $mediaStorage->resolveReadableLocalPath($iptcRel);
                    $fullPath = $resolved['path'] ?? null;
                    if (is_string($fullPath) && is_file($fullPath)) {
                        $written = ImageMetadataWriter::write($fullPath, $medium->resolvedIptcForEmbed());
                        if ($written && ($resolved['temporary'] ?? false) === true) {
                            $mediaStorage->putFromLocalFile($iptcRel, $fullPath, ['visibility' => 'public']);
                        }
                    }
                    $mediaStorage->cleanupResolvedPath($resolved);
                }
            }
        }

        // Pro Beitrag genau ein Teaser-Bild: nur DB schreiben, wenn sich die Auswahl geändert hat (sonst bei vielen Bildern unnötiges Massen-UPDATE).
        $teaserId = $request->validated('teaser_media_id');
        $newTeaserMediaId = null;
        if ($teaserId && $newsItem->media()->where('id', $teaserId)->where('type', 'image')->exists()) {
            $newTeaserMediaId = (int) $teaserId;
        } elseif ($newsItem->images()->exists() && ! $teaserId) {
            $newTeaserMediaId = (int) $newsItem->images()->value('id');
        }
        $currentTeaserMediaId = $newsItem->images()->where('is_teaser', true)->value('id');
        if ((int) $currentTeaserMediaId !== (int) $newTeaserMediaId) {
            $newsItem->images()->update(['is_teaser' => false]);
            if ($newTeaserMediaId) {
                $newsItem->media()->where('id', $newTeaserMediaId)->where('type', 'image')->update(['is_teaser' => true]);
            }
        }

        $this->processMediaUploads($newsItem, $request);

        if ($request->has('save_and_redirect_to_send')) {
            $sendUrl = route('admin.news.send', $newsItem);
            if ($newsItem->hasPriorDeliveries()) {
                $sendUrl .= '?context=update';
            }

            return redirect()
                ->to($sendUrl)
                ->with('status', $newsItem->hasPriorDeliveries()
                    ? 'Änderungen gespeichert. Weiter zum Update-Versand.'
                    : 'Änderungen gespeichert. Weiter zum Versand.');
        }

        $message = $request->has('publish_and_save')
            ? 'Die Nachricht wurde gespeichert und veröffentlicht.'
            : 'Die Nachricht wurde aktualisiert.';

        if ($request->expectsJson()) {
            if ($request->has('save_and_redirect_to_send')) {
                $sendUrl = route('admin.news.send', $newsItem);
                if ($newsItem->hasPriorDeliveries()) {
                    $sendUrl .= '?context=update';
                }

                return response()->json([
                    'redirect' => $sendUrl,
                    'message' => $newsItem->hasPriorDeliveries()
                        ? 'Änderungen gespeichert. Weiter zum Update-Versand.'
                        : 'Änderungen gespeichert. Weiter zum Versand.',
                ]);
            }

            $tab = (string) $request->input('ekn_redirect_tab', '');
            $editParams = ['newsItem' => $newsItem];
            if ($tab !== '') {
                $editParams['tab'] = $tab;
            }

            return response()->json([
                'redirect' => route('admin.news.edit', $editParams),
                'message' => $message,
            ]);
        }

        return redirect()
            ->route('admin.news.edit', $newsItem)
            ->with('status', $message);
    }

    public function destroy(NewsItem $newsItem)
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        // PATCH: add delete audit for news items
        NewsItemDeleteAudit::create([
            'news_item_id' => (int) $newsItem->id,
            'user_id' => auth()->id(),
            'title' => $newsItem->title,
            'slug' => $newsItem->slug,
            'status' => $newsItem->status,
            'published_at' => $newsItem->published_at,
            'deleted_at' => now(),
            'request_ip' => request()->ip(),
            'user_agent' => (string) request()->userAgent(),
        ]);

        $newsItem->delete();

        return redirect()
            ->route('admin.news.index')
            ->with('status', 'Die Nachricht wurde gelöscht.');
    }

    public function destroyMedia(NewsItem $newsItem, int $mediaId): RedirectResponse
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        $medium = $newsItem->media()->findOrFail($mediaId);
        $type = (string) $medium->type;
        $medium->delete();

        $tab = match ($type) {
            'image' => 'bilder',
            'video' => 'videos',
            'audio' => 'audios',
            default => 'nachricht',
        };

        return redirect()
            ->to(route('admin.news.edit', $newsItem).'?tab='.$tab)
            ->with('status', 'Medium wurde gelöscht.');
    }

    /**
     * Mehrere Bild-Medien auf einmal löschen (Auswahl im Tab „Bilder“ mit Shift-Bereich).
     */
    public function bulkDestroyImages(Request $request, NewsItem $newsItem): RedirectResponse
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        $validated = $request->validate([
            'delete_media' => ['required', 'array', 'min:1'],
            'delete_media.*' => ['integer', 'exists:news_item_media,id'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $validated['delete_media'])));
        $deleted = 0;
        foreach ($ids as $id) {
            $medium = $newsItem->media()->whereKey($id)->where('type', 'image')->first();
            if ($medium) {
                $medium->delete();
                $deleted++;
            }
        }

        $msg = $deleted === 0
            ? 'Keine Bilder gelöscht (Auswahl ungültig oder bereits entfernt).'
            : ($deleted === 1 ? '1 Bild wurde gelöscht.' : $deleted.' Bilder wurden gelöscht.');

        return redirect()
            ->to(route('admin.news.edit', $newsItem).'?tab=bilder')
            ->with('status', $msg);
    }

    /**
     * KI-Nachbearbeitung (Unterschrift & Schlagwörter) für mehrere Bilder: je Bild mit gespeichertem Caption/keywords,
     * Veranstaltungskontext der Meldung; optional Fokus auf ein Team/Künstler. Fotograf bleibt unverändert (Refinement-Modus).
     */
    public function bulkRequestPlannedEventImageAi(Request $request, NewsItem $newsItem): RedirectResponse
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        if (empty(config('media_ai.api_key'))) {
            return redirect()
                ->to(route('admin.news.edit', $newsItem).'?tab=bilder')
                ->with('error', 'Bild-KI ist nicht konfiguriert (API-Schlüssel fehlt).');
        }

        $newsItem->loadMissing('plannedEvent');

        $validated = $request->validate([
            'bulk_ai_media_ids' => ['required', 'array', 'min:1', 'max:25'],
            'bulk_ai_media_ids.*' => ['integer', 'exists:news_item_media,id'],
            'event_motiv_focus' => ['nullable', 'string', 'max:255'],
        ]);

        [$motivPicks] = $this->plannedEventMotivPickerData($newsItem);
        $allowedMotivNames = $this->motivPickerValues($motivPicks);
        $focus = trim((string) ($validated['event_motiv_focus'] ?? ''));
        if ($focus !== '' && ! in_array($focus, $allowedMotivNames, true)) {
            return redirect()
                ->to(route('admin.news.edit', $newsItem).'?tab=bilder')
                ->with('error', 'Ungültige Auswahl für Künstler/Team.');
        }

        $ids = array_values(array_unique(array_map('intval', $validated['bulk_ai_media_ids'])));

        if ($this->refinementHintFromPlannedEvent($newsItem) === '') {
            return redirect()
                ->to(route('admin.news.edit', $newsItem).'?tab=bilder')
                ->with('error', 'Für diese Meldung fehlt nutzbarer Veranstaltungs-Kontext (geplante Veranstaltung prüfen).');
        }

        $queued = 0;
        foreach ($ids as $id) {
            $medium = $newsItem->media()->whereKey($id)->where('type', 'image')->first();
            if (! $medium) {
                continue;
            }
            $medium->refresh();
            $hint = $this->plannedEventImageAiHint($newsItem, $focus, $medium);
            if ($hint === null) {
                continue;
            }
            $captionCtx = (string) ($medium->caption ?? '');
            $keywordsCtx = (string) ($medium->media_keywords ?? '');
            try {
                $medium->markAiQueued();
                GenerateImageMetadata::dispatch($medium, $hint, $captionCtx, $keywordsCtx)->afterResponse();
                $queued++;
            } catch (QueryException $e) {
                Log::warning('bulkRequestPlannedEventImageAi: AI columns missing, skip medium', [
                    'news_item_id' => $newsItem->id,
                    'media_id' => $medium->id,
                    'error' => $e->getMessage(),
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($queued === 0) {
            return redirect()
                ->to(route('admin.news.edit', $newsItem).'?tab=bilder')
                ->with('error', 'Keine gültigen Bilder in der Auswahl (nur Bilder dieser Meldung möglich).');
        }

        $msg = $queued === 1
            ? 'KI-Nachbearbeitung wurde für 1 Bild in die Warteschlange gelegt. Pro Bild gelten die gespeicherte Unterschrift und die Schlagwörter; der Fotograf bleibt unverändert.'
            : 'KI-Nachbearbeitung wurde für '.$queued.' Bilder in die Warteschlange gelegt. Pro Bild gelten die gespeicherte Unterschrift und die Schlagwörter; der Fotograf bleibt unverändert.';

        return redirect()
            ->to(route('admin.news.edit', $newsItem).'?tab=bilder')
            ->with('status', $msg);
    }

    /**
     * Für mehrere Bilder den Künstler-/Teamnamen aus der Veranstaltungsliste vor den Motiv-Teil der Unterschrift setzen
     * (entspricht der Einzel-Auswahl auf der Medienseite). Optional anschließend KI wie bei der Bulk-KI-Veranstaltung.
     */
    public function bulkAssignEventMotivToCaptions(Request $request, NewsItem $newsItem): RedirectResponse
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        if (! Schema::hasColumn('news_items', 'planned_event_id') || ! filled($newsItem->planned_event_id)) {
            return redirect()
                ->to(route('admin.news.edit', $newsItem).'?tab=bilder')
                ->with('error', 'Bitte dieser Meldung zuerst eine geplante Veranstaltung zuweisen.');
        }

        $newsItem->loadMissing('plannedEvent');

        [$motivPicks] = $this->plannedEventMotivPickerData($newsItem);
        $allowedMotivNames = $this->motivPickerValues($motivPicks);
        if ($allowedMotivNames === []) {
            return redirect()
                ->to(route('admin.news.edit', $newsItem).'?tab=bilder')
                ->with('error', 'An der Veranstaltung sind keine Künstler/Teams hinterlegt (Planung unter Einstellungen → Veranstaltung).');
        }

        $validated = $request->validate([
            'bulk_assign_media_ids' => ['required', 'array', 'min:1', 'max:25'],
            'bulk_assign_media_ids.*' => ['integer', 'exists:news_item_media,id'],
            'event_motiv_assign' => ['required', 'string', 'max:255'],
            'bulk_motiv_action' => ['required', 'string', 'in:caption_only,caption_and_ai'],
        ]);

        $motiv = trim((string) $validated['event_motiv_assign']);
        if (! in_array($motiv, $allowedMotivNames, true)) {
            return redirect()
                ->to(route('admin.news.edit', $newsItem).'?tab=bilder')
                ->with('error', 'Ungültige Auswahl für Künstler/Team.');
        }

        $runAi = ($validated['bulk_motiv_action'] ?? '') === 'caption_and_ai';
        if ($runAi) {
            if (empty(config('media_ai.api_key'))) {
                return redirect()
                    ->to(route('admin.news.edit', $newsItem).'?tab=bilder')
                    ->with('error', 'Bild-KI ist nicht konfiguriert („Unterschrift + KI“ nicht möglich).');
            }
            if ($this->refinementHintFromPlannedEvent($newsItem) === '') {
                return redirect()
                    ->to(route('admin.news.edit', $newsItem).'?tab=bilder')
                    ->with('error', 'Für diese Meldung fehlt nutzbarer Veranstaltungs-Kontext für die KI.');
            }
        }

        $ids = array_values(array_unique(array_map('intval', $validated['bulk_assign_media_ids'])));
        $updatedCaptions = 0;
        $queuedAi = 0;

        foreach ($ids as $id) {
            $medium = $newsItem->media()->whereKey($id)->where('type', 'image')->first();
            if (! $medium) {
                continue;
            }
            $medium->refresh();
            $before = (string) ($medium->caption ?? '');
            $newCaption = MediaCaptionLocationDateTail::prependMotivName($newsItem, $medium, $before, $motiv);
            $changed = $newCaption !== $before;
            if ($changed) {
                $medium->caption = $newCaption === '' ? null : $newCaption;
                $medium->save();
                $updatedCaptions++;
            }

            $medium->refresh();

            if ($medium->isImage() && filled($medium->path)) {
                try {
                    app(MediaQualityCheck::class)->runAndSave($medium);
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            if ($changed) {
                $medium->refresh();
                $this->embedIptcFromMediumIntoMasterFile($medium);
            }

            if ($runAi) {
                $medium->refresh();
                $hintForMedium = $this->plannedEventImageAiHint($newsItem, $motiv, $medium);
                if ($hintForMedium === null) {
                    continue;
                }
                try {
                    $medium->markAiQueued();
                    GenerateImageMetadata::dispatch(
                        $medium,
                        $hintForMedium,
                        (string) ($medium->caption ?? ''),
                        (string) ($medium->media_keywords ?? '')
                    )->afterResponse();
                    $queuedAi++;
                } catch (QueryException $e) {
                    Log::warning('bulkAssignEventMotivToCaptions: AI columns missing, skip medium', [
                        'news_item_id' => $newsItem->id,
                        'media_id' => $medium->id,
                        'error' => $e->getMessage(),
                    ]);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        if ($updatedCaptions === 0 && ! $runAi) {
            return redirect()
                ->to(route('admin.news.edit', $newsItem).'?tab=bilder')
                ->with('status', 'Keine Unterschrift geändert (Name stand vermutlich schon im Motiv-Text aller ausgewählten Bilder).');
        }

        $parts = [];
        if ($updatedCaptions > 0) {
            $parts[] = $updatedCaptions === 1
                ? 'Unterschrift bei 1 Bild angepasst (Künstler/Team vorangestellt).'
                : "Unterschrift bei {$updatedCaptions} Bildern angepasst (Künstler/Team vorangestellt).";
        }
        if ($runAi) {
            if ($queuedAi === 0) {
                return redirect()
                    ->to(route('admin.news.edit', $newsItem).'?tab=bilder')
                    ->with('error', 'KI konnte nicht gestartet werden (keine gültigen Bilder in der Auswahl).');
            }
            $parts[] = $queuedAi === 1
                ? 'KI wurde für 1 Bild in die Warteschlange gelegt.'
                : "KI wurde für {$queuedAi} Bilder in die Warteschlange gelegt.";
        }

        return redirect()
            ->to(route('admin.news.edit', $newsItem).'?tab=bilder')
            ->with('status', implode(' ', $parts));
    }

    public function unlinkMedia(NewsItem $newsItem, int $mediaId): RedirectResponse
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        $medium = $newsItem->media()->findOrFail($mediaId);
        $type = (string) $medium->type;
        $medium->delete();

        $tab = match ($type) {
            'image' => 'bilder',
            'video' => 'videos',
            'audio' => 'audios',
            default => 'nachricht',
        };

        return redirect()
            ->to(route('admin.news.edit', $newsItem).'?tab='.$tab)
            ->with('status', 'Verknüpfung wurde entfernt.');
    }

    public function editMedia(Request $request, NewsItem $newsItem, int $mediaId)
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        $newsItem->loadMissing('plannedEvent');

        $medium = $newsItem->media()->findOrFail($mediaId);
        $metadataFromFile = [];
        if ($medium->isImage()) {
            $metadataFromFile = $medium->readImageMetadataFromMasterFile() ?? [];
            $medium->syncCaptureTimeFromMetadata($metadataFromFile);
            $medium->refresh();
        }

        $galleryImages = $newsItem->images->map(fn ($m) => [
            'id' => $m->id,
            'url' => $m->url,
            'display_name' => $m->display_name,
        ])->values();
        $galleryIndex = $galleryImages->search(fn ($m) => $m['id'] === $medium->id);
        if ($galleryIndex === false) {
            $galleryIndex = 0;
        }

        $imagesOrdered = $newsItem->images->values();
        $currentPos = $imagesOrdered->search(fn ($m) => $m->id === $medium->id);
        $prevMedia = $currentPos > 0 ? $imagesOrdered->get($currentPos - 1) : null;
        $nextMedia = $currentPos !== false && $currentPos < $imagesOrdered->count() - 1 ? $imagesOrdered->get($currentPos + 1) : null;

        $creditUsers = $this->creditUsersForPicker();
        $quickSendDestinations = $this->quickSendDestinationsForMedia($request);
        [$plannedEventMotivPicks, $plannedEventMotivLabel] = $this->plannedEventMotivPickerData($newsItem);
        $refineCaptionFromEventAi = $medium->isImage()
            && filled(config('media_ai.api_key'))
            && Schema::hasColumn('news_items', 'planned_event_id')
            && filled($newsItem->planned_event_id)
            && $this->refinementHintFromPlannedEvent($newsItem) !== '';

        return view('admin.news.media.edit', compact(
            'newsItem',
            'medium',
            'metadataFromFile',
            'galleryImages',
            'galleryIndex',
            'prevMedia',
            'nextMedia',
            'creditUsers',
            'quickSendDestinations',
            'plannedEventMotivPicks',
            'plannedEventMotivLabel',
            'refineCaptionFromEventAi',
        ));
    }

    public function editMediaImage(Request $request, NewsItem $newsItem, int $mediaId): View
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        $medium = $newsItem->media()->where('type', 'image')->findOrFail($mediaId);

        $imagesOrdered = $newsItem->images()->orderBy('sort_order')->orderBy('id')->get();
        $currentPos = $imagesOrdered->search(fn ($m) => $m->id === $medium->id);
        $prevMedia = $currentPos !== false && $currentPos > 0 ? $imagesOrdered->get($currentPos - 1) : null;
        $nextMedia = $currentPos !== false && $currentPos < $imagesOrdered->count() - 1
            ? $imagesOrdered->get($currentPos + 1)
            : null;

        $filmstripImages = $imagesOrdered->map(static function ($m) use ($newsItem) {
            return [
                'id' => $m->id,
                'url' => route('admin.news.media.image-editor', [$newsItem, $m->id]),
                'thumb' => $m->thumb_url ?? $m->preview_url,
                'label' => $m->original_name ?: 'Bild #'.$m->id,
            ];
        })->values();

        $editorSource = $medium->editorSourceConfig();
        $prefetchEditorSources = collect([$prevMedia, $nextMedia])
            ->filter()
            ->map(static fn (NewsItemMedia $m) => $m->editorSourceConfig())
            ->values()
            ->all();

        return view('admin.news.media.image-editor', compact(
            'newsItem',
            'medium',
            'prevMedia',
            'nextMedia',
            'filmstripImages',
            'editorSource',
            'prefetchEditorSources',
        ));
    }

    public function updateMedia(Request $request, NewsItem $newsItem, int $mediaId): RedirectResponse|JsonResponse
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        $medium = $newsItem->media()->findOrFail($mediaId);
        $rules = [
            'image_title' => ['nullable', 'string', 'max:255'],
            'photographer' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:1800'],
            'media_keywords' => ['nullable', 'string', 'max:512'],
            'description' => ['nullable', 'string', 'max:65535'],
            'is_visible' => ['sometimes'],
            'versand' => ['sometimes'],
        ];
        if ($medium->isVideo()) {
            $rules['metadata_location'] = ['nullable', 'string', 'max:512'];
            $rules['metadata_recorded_at'] = ['nullable', 'date'];
        }
        if ($medium->isImage()) {
            $rules['city'] = ['nullable', 'string', 'max:255'];
            $rules['state'] = ['nullable', 'string', 'max:255'];
            $rules['country'] = ['nullable', 'string', 'max:255'];
            $rules['country_code'] = ['nullable', 'string', 'max:2', 'regex:/^[A-Za-z]{0,2}$/'];
            $rules['capture_time'] = ['nullable', 'date'];
            $rules['credit'] = ['nullable', 'string', 'max:255'];
            $rules['copyright'] = ['nullable', 'string', 'max:512'];
            $rules['source'] = ['nullable', 'string', 'max:255'];
        }
        $validated = $request->validate($rules);
        $normalizedMediaKeywords = MediaKeywordNormalizer::normalizeCommaSeparatedString($validated['media_keywords'] ?? null);
        $update = [
            'image_title' => $validated['image_title'] ?? null,
            'photographer' => isset($validated['photographer']) && trim((string) $validated['photographer']) !== '' ? trim($validated['photographer']) : null,
            'caption' => $validated['caption'] ?? null,
            'media_keywords' => $normalizedMediaKeywords,
            'description' => $validated['description'] ?? null,
            'is_visible' => $request->boolean('is_visible'),
            'versand' => $request->boolean('versand'),
        ];
        if ($medium->isVideo()) {
            $update['metadata_location'] = isset($validated['metadata_location']) && trim((string) $validated['metadata_location']) !== ''
                ? trim((string) $validated['metadata_location'])
                : null;
            $update['metadata_recorded_at'] = $validated['metadata_recorded_at'] ?? null;
        }
        if ($medium->isImage()) {
            $cc = isset($validated['country_code']) ? strtoupper(trim((string) $validated['country_code'])) : '';
            $update['city'] = self::nullableTrimmedString($validated['city'] ?? null);
            $update['state'] = self::nullableTrimmedString($validated['state'] ?? null);
            $update['country'] = self::nullableTrimmedString($validated['country'] ?? null);
            $update['country_code'] = strlen($cc) === 2 ? $cc : null;
            $photoLine = $update['photographer'] ?? null;
            if (is_string($photoLine) && trim($photoLine) !== '') {
                $photoLine = trim($photoLine);
                $update['credit'] = $photoLine;
                $brand = trim((string) config('newsdesk.iptc_credit', 'Erftkreis News'));
                $suffix = ' / '.$brand;
                $maxPhoto = max(0, 255 - mb_strlen($suffix, 'UTF-8'));
                $update['source'] = mb_substr($photoLine, 0, $maxPhoto, 'UTF-8').$suffix;
            } else {
                $update['credit'] = self::nullableTrimmedString($validated['credit'] ?? null);
                $update['source'] = self::nullableTrimmedString($validated['source'] ?? null);
            }
            $update['copyright'] = self::nullableTrimmedString($validated['copyright'] ?? null);
            // Nur ändern, wenn das Feld wirklich mitgeschickt wurde — sonst wurde bisher
            // die gespeicherte Aufnahmezeit fälschlich auf null gesetzt (Galerie zeigt dann Meldungsdatum).
            if (array_key_exists('capture_time', $validated)) {
                $cap = $validated['capture_time'];
                if ($cap === null || $cap === '' || (is_string($cap) && trim($cap) === '')) {
                    $update['capture_time'] = null;
                } elseif ($cap instanceof \DateTimeInterface) {
                    $update['capture_time'] = Carbon::parse($cap);
                } elseif (is_string($cap)) {
                    try {
                        $update['capture_time'] = Carbon::parse($cap);
                    } catch (\Throwable) {
                        $update['capture_time'] = null;
                    }
                }
            }
            // Aufnahmezeit aus der Master-Datei nachziehen, wenn das Formular kein capture_time sendet
            // (Teil-Speichern). Explizit mitgeschickte Werte (readonly/hidden oder manuell) haben Vorrang.
            if (! array_key_exists('capture_time', $validated)) {
                $fromFile = $medium->resolveCaptureTimeCarbonFromMasterFile();
                if ($fromFile !== null) {
                    $update['capture_time'] = $fromFile;
                    $update['metadata_recorded_at'] = $fromFile->toDateString();
                }
            }
            $ghost = new NewsItemMedia;
            $ghost->forceFill([
                'city' => array_key_exists('city', $update) ? $update['city'] : $medium->city,
                'capture_time' => array_key_exists('capture_time', $update) ? $update['capture_time'] : $medium->capture_time,
            ]);
            $ghost->setRelation('newsItem', $newsItem);
            $captionInput = isset($update['caption']) && is_string($update['caption']) ? $update['caption'] : '';
            $merged = MediaCaptionLocationDateTail::appendToCaption($newsItem, $ghost, $captionInput);
            $update['caption'] = $merged === '' ? null : $merged;
        }
        $medium->update($update);
        $medium->refresh();

        if ($medium->isImage() && filled($medium->path)) {
            app(MediaQualityCheck::class)->runAndSave($medium);
        }
        $iptcRel = $medium->resolveIptcMasterRelativePath();
        if ($iptcRel !== null) {
            try {
                $mediaStorage = app(MediaStorage::class);
                $resolved = $mediaStorage->resolveReadableLocalPath($iptcRel);
                $fullPath = $resolved['path'] ?? null;
                if (is_string($fullPath) && is_file($fullPath)) {
                    $written = ImageMetadataWriter::write($fullPath, $medium->resolvedIptcForEmbed());
                    if ($written && ($resolved['temporary'] ?? false) === true) {
                        $mediaStorage->putFromLocalFile($iptcRel, $fullPath, ['visibility' => 'public']);
                    }
                }
                $mediaStorage->cleanupResolvedPath($resolved);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($request->boolean('autosave')) {
            return response()->json(['ok' => true]);
        }

        $redirectAfter = $request->input('redirect_after');
        if ($redirectAfter && is_string($redirectAfter) && trim($redirectAfter) !== '') {
            $url = trim($redirectAfter);
            $allowedHost = parse_url(config('app.url', ''), PHP_URL_HOST);
            $redirectHost = parse_url($url, PHP_URL_HOST);
            if ($redirectHost === $allowedHost || $redirectHost === null) {
                return redirect($url);
            }
        }

        return redirect()
            ->route('admin.news.media.edit', [$newsItem, $medium])
            ->with('status', 'Metadaten wurden gespeichert.');
    }

    public function mediaAiStatus(NewsItem $newsItem, int $mediaId): JsonResponse
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        $medium = $newsItem->media()->findOrFail($mediaId);

        return response()->json([
            'ai_status' => $medium->ai_status ?? null,
            'ai_last_error' => $medium->ai_last_error ?? $medium->ai_error ?? null,
            'ai_finished_at' => $medium->ai_finished_at?->toIso8601String(),
        ]);
    }

    public function requestMediaAi(Request $request, NewsItem $newsItem, int $mediaId): RedirectResponse
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        $medium = $newsItem->media()->findOrFail($mediaId);
        if ($medium->type !== 'image') {
            return redirect()
                ->route('admin.news.media.edit', [$newsItem, $medium])
                ->with('status', 'KI-Vorschlag ist nur für Bilder möglich.');
        }

        if (empty(config('media_ai.api_key'))) {
            return redirect()
                ->route('admin.news.media.edit', [$newsItem, $medium])
                ->with('error', 'Bild-KI ist nicht konfiguriert (API-Schlüssel fehlt).');
        }

        $validated = $request->validate([
            'ai_refinement_from_planned_event' => ['nullable', 'in:1'],
            'ai_refinement_hint' => ['nullable', 'string', 'max:1500'],
            'caption_context' => ['nullable', 'string', 'max:1800'],
            'keywords_context' => ['nullable', 'string', 'max:512'],
        ]);

        $fromPlannedEvent = ($validated['ai_refinement_from_planned_event'] ?? null) === '1';
        $hint = $fromPlannedEvent
            ? $this->refinementHintFromPlannedEvent($newsItem, $medium)
            : trim((string) ($validated['ai_refinement_hint'] ?? ''));
        if ($fromPlannedEvent && $hint === '') {
            return redirect()
                ->route('admin.news.media.edit', [$newsItem, $medium])
                ->with('error', 'Für diese Meldung fehlt nutzbarer Veranstaltungs-Kontext (Einstellungen → Veranstaltung prüfen).');
        }

        $captionCtx = array_key_exists('caption_context', $validated)
            ? (string) $validated['caption_context']
            : null;
        $keywordsCtx = array_key_exists('keywords_context', $validated)
            ? (string) $validated['keywords_context']
            : null;

        $statusMessage = 'KI-Analyse wurde gestartet und läuft nach dem Absenden im Hintergrund. Bitte die Seite in Kürze neu laden.';
        if ($fromPlannedEvent) {
            $statusMessage = 'KI-Nachbearbeitung (Unterschrift & Schlagwörter) läuft im Hintergrund. Seite in Kürze neu laden, bis die KI fertig ist.';
        } elseif ($hint !== '') {
            $statusMessage = 'KI-Nachbearbeitung (Unterschrift & Schlagwörter) läuft im Hintergrund. Bitte die Seite in Kürze neu laden.';
        }

        try {
            $medium->markAiQueued();
            if ($fromPlannedEvent) {
                $hintForJob = Str::limit($hint, 8000, "\n…");
                GenerateImageMetadata::dispatch($medium, $hintForJob, $captionCtx, $keywordsCtx)->afterResponse();
            } elseif ($hint !== '') {
                GenerateImageMetadata::dispatch($medium, $hint, $captionCtx, $keywordsCtx)->afterResponse();
            } else {
                GenerateImageMetadata::dispatch($medium)->afterResponse();
            }
        } catch (QueryException $e) {
            Log::warning('requestMediaAi: AI columns missing, continue without AI', [
                'media_id' => $medium->id,
                'path' => $medium->path,
                'error' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.news.media.edit', [$newsItem, $medium])
                ->with('error', 'Bild-KI ist fehlgeschlagen: '.Str::limit($e->getMessage(), 200));
        }

        return redirect()
            ->route('admin.news.media.edit', [$newsItem, $medium])
            ->with('status', $statusMessage);
    }

    /**
     * Einstieg „Unkenntlich machen“ → einheitlich zur Anonymisierungs-Karte auf der Bearbeitungsseite.
     * Es gibt nur noch ein System: Redaction (Kennzeichen + Gesichter + manuelle Boxen).
     */
    public function showUnkenntlichEditor(NewsItem $newsItem, int $mediaId): RedirectResponse
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        $medium = $newsItem->media()->findOrFail($mediaId);
        if (! $medium->isImage()) {
            return redirect()->route('admin.news.media.edit', [$newsItem, $medium])
                ->with('status', 'Nur bei Bildern möglich.');
        }
        if ($this->redactionLockedForNewsItem($newsItem)) {
            return $this->redirectRedactionLockedByPlannedEvent($newsItem, $medium);
        }

        return redirect()->route('admin.news.media.edit', [$newsItem, $medium])
            ->withFragment('redaction')
            ->with('status', 'Nutzen Sie die Karte „Bereiche unkenntlich machen“ für Kennzeichen, Gesichter und weitere Bereiche.');
    }

    /**
     * Legacy: POST vom alten „Unkenntlich“-Formular.
     * Nutzt jetzt die Redaction-Pipeline (Original bleibt erhalten, redigierte Version wird erzeugt).
     */
    public function applyUnkenntlich(Request $request, NewsItem $newsItem, int $mediaId): RedirectResponse
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        $medium = $newsItem->media()->findOrFail($mediaId);
        if (! $medium->isImage()) {
            return redirect()->route('admin.news.media.edit', [$newsItem, $medium])
                ->with('status', 'Nur bei Bildern möglich.');
        }
        if ($this->redactionLockedForNewsItem($newsItem)) {
            return $this->redirectRedactionLockedByPlannedEvent($newsItem, $medium);
        }
        $regions = $request->input('regions');
        if (is_string($regions)) {
            $regions = json_decode($regions, true) ?: [];
        }
        $request->merge(['regions' => $regions]);
        $request->validate([
            'regions' => ['required', 'array', 'min:1'],
            'regions.*.x' => ['required', 'numeric', 'min:0', 'max:100'],
            'regions.*.y' => ['required', 'numeric', 'min:0', 'max:100'],
            'regions.*.w' => ['required', 'numeric', 'min:1', 'max:100'],
            'regions.*.h' => ['required', 'numeric', 'min:1', 'max:100'],
            'mode' => ['nullable', 'string', 'in:blur,pixelate'],
        ]);
        $mode = $request->input('mode', 'blur');
        $redactionMethod = $mode === 'pixelate' ? 'black_box' : 'blur';

        $resolved = app(MediaStorage::class)->resolveReadableLocalPath($medium->path);
        $fullPath = $resolved['path'] ?? null;
        if (! is_file($fullPath) || ! is_readable($fullPath)) {
            app(MediaStorage::class)->cleanupResolvedPath($resolved);

            return redirect()->route('admin.news.media.edit', [$newsItem, $medium])
                ->withFragment('redaction')
                ->with('status', 'Bilddatei nicht lesbar. Bitte Anonymisierung auf der Bearbeitungsseite nutzen.');
        }
        $width = 0;
        $height = 0;
        $info = @getimagesize($fullPath);
        if ($info && isset($info[0], $info[1])) {
            $width = (int) $info[0];
            $height = (int) $info[1];
        }
        if ($width < 2 || $height < 2) {
            app(MediaStorage::class)->cleanupResolvedPath($resolved);

            return redirect()->route('admin.news.media.edit', [$newsItem, $medium])
                ->withFragment('redaction')
                ->with('status', 'Bildabmessungen konnten nicht gelesen werden. Bitte Anonymisierung auf der Bearbeitungsseite nutzen.');
        }

        $boxes = [];
        foreach ($regions as $r) {
            $x1 = (float) ($r['x'] / 100) * $width;
            $y1 = (float) ($r['y'] / 100) * $height;
            $x2 = (float) (($r['x'] + $r['w']) / 100) * $width;
            $y2 = (float) (($r['y'] + $r['h']) / 100) * $height;
            if ($x2 > $x1 && $y2 > $y1) {
                $boxes[] = [round($x1, 2), round($y1, 2), round($x2, 2), round($y2, 2)];
            }
        }
        if ($boxes === []) {
            return redirect()->route('admin.news.media.edit', [$newsItem, $medium])
                ->withFragment('redaction')
                ->with('status', 'Keine gültigen Bereiche. Bitte auf der Bearbeitungsseite Boxen setzen und „Redaction neu rendern“.');
        }

        $medium->update([
            'redaction_boxes' => $boxes,
            'redaction_method' => $redactionMethod,
            'redaction_status' => NewsItemMedia::REDACTION_PENDING,
            'is_unkentlich' => true,
        ]);
        ProcessMediaRedaction::dispatch($medium);
        app(MediaStorage::class)->cleanupResolvedPath($resolved);

        return redirect()
            ->route('admin.news.media.edit', [$newsItem, $medium])
            ->withFragment('redaction')
            ->with('status', 'Anonymisierung in die Warteschlange gestellt. Seite in Kürze neu laden. Original bleibt erhalten; ausgeliefert wird die redigierte Version.');
    }

    public function toggleUnkenntlich(NewsItem $newsItem, int $mediaId): RedirectResponse
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        $medium = $newsItem->media()->findOrFail($mediaId);
        $medium->update(['is_unkentlich' => false]);

        return redirect()
            ->route('admin.news.edit', $newsItem)
            ->with('status', 'Markierung „Unkenntlich“ entfernt.');
    }

    public function runRedaction(NewsItem $newsItem, int $mediaId): RedirectResponse
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        $medium = $newsItem->media()->findOrFail($mediaId);
        if (! $medium->isImage()) {
            return redirect()->route('admin.news.media.edit', [$newsItem, $medium])
                ->with('status', 'Nur bei Bildern möglich.');
        }
        if ($this->redactionLockedForNewsItem($newsItem)) {
            return $this->redirectRedactionLockedByPlannedEvent($newsItem, $medium);
        }
        $medium->update(['redaction_status' => NewsItemMedia::REDACTION_PENDING]);
        ProcessMediaRedaction::dispatch($medium);

        return redirect()->route('admin.news.media.edit', [$newsItem, $medium])
            ->with('status', 'Auto-Redaction wurde in die Warteschlange gestellt. Seite in Kürze neu laden.');
    }

    /**
     * Redaction-Job sofort ausführen (ohne Queue). Kann bis zu 2 Min. dauern.
     */
    public function runRedactionNow(NewsItem $newsItem, int $mediaId): RedirectResponse
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        set_time_limit(150);
        $medium = $newsItem->media()->findOrFail($mediaId);
        if (! $medium->isImage()) {
            return redirect()->route('admin.news.media.edit', [$newsItem, $medium])
                ->with('status', 'Nur bei Bildern möglich.');
        }
        if ($this->redactionLockedForNewsItem($newsItem)) {
            return $this->redirectRedactionLockedByPlannedEvent($newsItem, $medium);
        }
        $medium->update(['redaction_status' => NewsItemMedia::REDACTION_PENDING]);
        ProcessMediaRedaction::dispatchSync($medium);
        $medium->refresh();
        $status = $medium->redaction_status === NewsItemMedia::REDACTION_DONE
            ? 'Redaction abgeschlossen.'
            : 'Redaction beendet. Status und ggf. Fehlergrund oben prüfen.';

        return redirect()->route('admin.news.media.edit', [$newsItem, $medium])
            ->with('status', $status);
    }

    public function runVideoStills(NewsItem $newsItem, int $mediaId): RedirectResponse
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        $medium = $newsItem->media()->findOrFail($mediaId);
        if (! $medium->isVideo()) {
            return redirect()->route('admin.news.edit', $newsItem)
                ->with('status', 'Nur bei Videos möglich.');
        }

        GenerateVideoStills::dispatch($medium, replaceExistingStills: true);

        return redirect()
            ->to(route('admin.news.edit', $newsItem).'?tab=bilder')
            ->with('status', 'Standbilder werden neu erzeugt (bestehende Standbilder dieses Videos werden ersetzt). Bitte den Tab „Bilder“ in Kürze neu laden.');
    }

    public function updateRedaction(Request $request, NewsItem $newsItem, int $mediaId): RedirectResponse
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        $medium = $newsItem->media()->findOrFail($mediaId);
        if (! $medium->isImage()) {
            return redirect()->route('admin.news.media.edit', [$newsItem, $medium])
                ->with('status', 'Nur bei Bildern möglich.');
        }
        if ($this->redactionLockedForNewsItem($newsItem)) {
            return $this->redirectRedactionLockedByPlannedEvent($newsItem, $medium);
        }

        // Keine Anonymisierung (z. B. Feuerwehr, Polizei): nur Status umschalten
        if ($request->has('redaction_disabled')) {
            $disabled = $request->boolean('redaction_disabled');
            $medium->update(['redaction_status' => $disabled ? NewsItemMedia::REDACTION_DISABLED : NewsItemMedia::REDACTION_PENDING]);

            return redirect()->route('admin.news.media.edit', [$newsItem, $medium])
                ->with('status', $disabled
                    ? 'Kennzeichen-Anonymisierung deaktiviert. Bild wird unverändert (Original) ausgeliefert.'
                    : 'Kennzeichen-Anonymisierung wieder aktiviert. Bei Bedarf „Redaction jetzt ausführen“ klicken.');
        }

        $boxes = $request->input('boxes');
        if (is_string($boxes)) {
            $boxes = json_decode($boxes, true) ?: [];
        }
        $request->merge(['boxes' => $boxes]);
        $request->validate([
            'boxes' => ['nullable', 'array'],
            'boxes.*' => ['array'],
            'boxes.*.0' => ['numeric', 'min:0'],
            'boxes.*.1' => ['numeric', 'min:0'],
            'boxes.*.2' => ['numeric', 'min:0'],
            'boxes.*.3' => ['numeric', 'min:0'],
            'method' => ['nullable', 'string', 'in:blur,black_box'],
        ]);
        $method = $request->input('method') ?: $medium->redaction_method ?: config('redaction.default_method', 'blur');
        $normalized = [];
        foreach ($boxes ?? [] as $b) {
            if (is_array($b) && isset($b[0], $b[1], $b[2], $b[3])) {
                $normalized[] = [(float) $b[0], (float) $b[1], (float) $b[2], (float) $b[3]];
            }
        }
        $medium->update([
            'redaction_boxes' => $normalized,
            'redaction_method' => $method,
        ]);
        $runAfter = $request->boolean('run_after');
        if ($runAfter) {
            $medium->update(['redaction_status' => NewsItemMedia::REDACTION_PENDING]);
            ProcessMediaRedaction::dispatch($medium);
        }

        return redirect()->route('admin.news.media.edit', [$newsItem, $medium])
            ->with('status', $runAfter ? 'Boxen gespeichert und Redaction gestartet.' : 'Boxen und Methode gespeichert.');
    }

    /**
     * Autor-Credit der Meldung auf alle Bild-Medien (photographer) übernehmen.
     * Aktualisiert DB und schreibt Credit nur in die Medienpaket-/Master-JPEGs (nicht redigierte Portal-Variante, nicht Preview).
     */
    public function applyAuthorCredit(NewsItem $newsItem): RedirectResponse
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        $credit = trim((string) ($newsItem->author_credit ?? ''));
        if ($credit === '') {
            return redirect()->route('admin.news.edit', $newsItem)
                ->with('error', 'Kein Autor-Credit an der Meldung hinterlegt. Bitte zuerst bei der Meldung unter „Autor / Credit“ eintragen und Meldung speichern.');
        }
        $newsItem->images()->update(['photographer' => $credit]);

        foreach ($newsItem->images()->get() as $medium) {
            $medium->photographer = $credit;
            $iptcRel = $medium->resolveIptcMasterRelativePath();
            if ($iptcRel === null) {
                continue;
            }
            try {
                $mediaStorage = app(MediaStorage::class);
                $resolved = $mediaStorage->resolveReadableLocalPath($iptcRel);
                $fullPath = $resolved['path'] ?? null;
                if (is_string($fullPath) && is_file($fullPath)) {
                    $written = ImageMetadataWriter::write($fullPath, $medium->resolvedIptcForEmbed());
                    if ($written && ($resolved['temporary'] ?? false) === true) {
                        $mediaStorage->putFromLocalFile($iptcRel, $fullPath, ['visibility' => 'public']);
                    }
                }
                $mediaStorage->cleanupResolvedPath($resolved);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('admin.news.edit', $newsItem)
            ->with('status', 'Autor-Credit wurde auf alle '.$newsItem->images->count().' Bilder übernommen (Datenbank und Datei-Metadaten).');
    }

    private static function nullableTrimmedString(mixed $v): ?string
    {
        if (! is_string($v)) {
            return null;
        }
        $t = trim($v);

        return $t !== '' ? $t : null;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    protected function creditUsersForPicker()
    {
        $user = auth()->user();
        if (! $user) {
            return collect();
        }

        if ($user->hasRole('admin')) {
            return User::query()->orderBy('name')->orderBy('id')->get();
        }

        return User::query()
            ->whereKey($user->id)
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * Team-/Künstler-Auswahl der zugewiesenen Veranstaltung – nur für Bildunterschrift (Motiv), nicht für Fotograf/Credit.
     *
     * @return array{0: list<array{value: string, label: string}>, 1: string|null}
     */
    protected function plannedEventMotivPickerData(NewsItem $newsItem): array
    {
        if (! Schema::hasColumn('news_items', 'planned_event_id')) {
            return [[], null];
        }
        $eventId = (int) ($newsItem->planned_event_id ?? 0);
        if ($eventId <= 0) {
            return [[], null];
        }
        if (! Schema::hasTable('planned_events') || ! Schema::hasTable('planned_event_teams')) {
            return [[], null];
        }

        $event = PlannedEvent::query()
            ->with(['teams' => function ($q): void {
                $q->orderBy('sort_order')->orderBy('id');
            }])
            ->find($eventId);

        if (! $event) {
            return [[], null];
        }

        $seen = [];
        $picks = [];
        $teams = $event->teams->sortBy(
            fn (PlannedEventTeam $team): int => $team->startNumber() ?? 99999
        )->values();

        foreach ($teams as $team) {
            $value = Str::limit(trim($team->motivPickerValue()), 255, '');
            if ($value === '') {
                continue;
            }
            $key = mb_strtolower($value, 'UTF-8');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $picks[] = [
                'value' => $value,
                'label' => Str::limit($team->motivPickerLabel(), 200, '…'),
            ];
        }

        $label = trim((string) ($event->name ?? ''));

        return [$picks, $label !== '' ? $label : null];
    }

    /**
     * @param  list<array{value: string, label: string}>  $picks
     * @return list<string>
     */
    protected function motivPickerValues(array $picks): array
    {
        return array_values(array_map(static fn (array $pick): string => (string) ($pick['value'] ?? ''), $picks));
    }

    /**
     * Text für KI-Nachbearbeitung der Bildunterschrift aus der der Meldung zugewiesenen Veranstaltung.
     * Optional: Zuordnung zu einem Programm-Slot anhand der Bild-Aufnahmezeit und des extrahierten Zeitplans.
     */
    protected function refinementHintFromPlannedEvent(NewsItem $newsItem, ?NewsItemMedia $medium = null): string
    {
        if (! Schema::hasColumn('news_items', 'planned_event_id')) {
            return '';
        }
        $eventId = (int) ($newsItem->planned_event_id ?? 0);
        if ($eventId <= 0 || ! Schema::hasTable('planned_events')) {
            return '';
        }

        $event = PlannedEvent::query()->with('teams')->find($eventId);
        if (! $event) {
            return '';
        }

        $block = trim(Str::limit($event->formatForAiPrompt(), 7000, "\n…"));
        if ($block === '') {
            return '';
        }

        $hint = "Veranstaltung / Motiv- und Eventkontext (dieser Meldung zugeordnet):\n".$block
            ."\n\nNutze diese Angaben nur, wenn sie zum sichtbaren Bild passen, und arbeite sie sachlich in die Bildunterschrift ein "
            .'(z. B. namentliche Nennung von Künstlern oder Teams statt vager Begriffe wie „ein Sänger“, wo es zutrifft).';

        if ($medium !== null && $medium->capture_time) {
            $slotLine = app(ScheduleSlotMatcher::class)->slotHintLineForMedia($medium, $event);
            if ($slotLine !== '') {
                $hint .= "\n\n".$slotLine;
            }
        }

        return $hint;
    }

    /**
     * Vollständiger Hinweis für Bild-KI (Veranstaltung), optional mit Künstler-/Team-Fokus.
     */
    protected function plannedEventImageAiHint(NewsItem $newsItem, string $focusTrimmed, ?NewsItemMedia $medium = null): ?string
    {
        $base = $this->refinementHintFromPlannedEvent($newsItem, $medium);
        if ($base === '') {
            return null;
        }

        $hint = $base;
        $focusTrimmed = trim($focusTrimmed);
        if ($focusTrimmed !== '') {
            $hint .= "\n\nRedaktions-Fokus für diese Bildauswahl (Künstler/Team/Motiv): ".$focusTrimmed
                ."\nArbeite diese Zuordnung sachlich in die Bildunterschrift ein, wo sie zum sichtbaren Motiv passt. "
                .'Der Fotograf (IPTC By-line) darf nicht geändert werden.';
        }

        return Str::limit($hint, 8000, "\n…");
    }

    protected function authorCreditNameFromUserId(int $userId): string
    {
        $name = User::query()->whereKey($userId)->value('name');

        return is_string($name) ? $name : '';
    }

    protected function statuses(): array
    {
        return [
            'draft' => 'Entwurf',
            'review' => 'Review',
            'published' => 'ready',
            'archived' => 'Archiviert',
        ];
    }

    /**
     * Speichert hochgeladene Medien. Dateien werden unter einer 6-stelligen Nummer (Media-ID)
     * abgelegt (z. B. 000042.jpg), damit die Speicherung einheitlich ist. Der Original-Dateiname
     * bleibt in original_name für Anzeige und Download erhalten.
     */
    protected function processMediaUploads(NewsItem $newsItem, Request $request): void
    {
        $sortOrder = (int) ($newsItem->media()->max('sort_order') ?? 0);
        $mediaStorage = app(MediaStorage::class);
        $imageIngest = app(NewsItemImageIngestService::class);

        foreach (['images' => 'image', 'videos' => 'video', 'audios' => 'audio'] as $key => $type) {
            $files = $request->file($key);
            if (! $files) {
                continue;
            }
            $files = is_array($files) ? $files : [$files];
            foreach ($files as $file) {
                if ($type === 'image') {
                    $imageIngest->ingest($newsItem, $file);

                    continue;
                }

                if (! $file->isValid()) {
                    continue;
                }
                $originalName = $file->getClientOriginalName();
                $sortOrder++;
                $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
                if (! preg_match('/^[a-z0-9]+$/', $ext)) {
                    $ext = 'bin';
                }

                $media = $newsItem->media()->create([
                    'type' => $type,
                    'path' => 'news-media/.pending',
                    'original_name' => $originalName,
                    'sort_order' => $sortOrder,
                ]);

                $path = $mediaStorage->generateMediaPath(
                    $newsItem,
                    $media,
                    $file,
                    $type
                );
                $storedPath = $mediaStorage->storeUploadedFileAs($file, dirname($path), basename($path));

                $media->update(['path' => $storedPath]);
                if ($type === 'video') {
                    ExtractVideoMetadata::dispatch($media);
                    GenerateVideoPoster::dispatch($media);
                    GenerateVideoStills::dispatch($media);
                } elseif ($type === 'audio') {
                    ExtractAudioMetadata::dispatch($media);
                }
            }
        }
    }

    private function detectUploadedImageCaptureTime(UploadedFile $file): ?Carbon
    {
        $tmpPath = $file->getRealPath();
        if (! is_string($tmpPath) || $tmpPath === '' || ! is_file($tmpPath)) {
            return null;
        }

        $meta = ImageMetadataReader::read($tmpPath);

        return ImageMetadataReader::captureTimeCarbonFromMetadata($meta);
    }

    private function abortIfCannotManageNewsItem(NewsItem $newsItem): void
    {
        if ($this->canManageNewsItemBypass()) {
            return;
        }

        if ((int) $newsItem->author_id !== (int) auth()->id()) {
            abort(403, 'Sie dürfen nur eigene Beiträge bearbeiten.');
        }
    }

    private function embedIptcFromMediumIntoMasterFile(NewsItemMedia $medium): void
    {
        if (! $medium->isImage()) {
            return;
        }

        $iptcRel = $medium->resolveIptcMasterRelativePath();
        if ($iptcRel === null) {
            return;
        }

        try {
            $mediaStorage = app(MediaStorage::class);
            $resolved = $mediaStorage->resolveReadableLocalPath($iptcRel);
            $fullPath = $resolved['path'] ?? null;
            if (is_string($fullPath) && is_file($fullPath)) {
                $written = ImageMetadataWriter::write($fullPath, $medium->resolvedIptcForEmbed());
                if ($written && ($resolved['temporary'] ?? false) === true) {
                    $mediaStorage->putFromLocalFile($iptcRel, $fullPath, ['visibility' => 'public']);
                }
            }
            $mediaStorage->cleanupResolvedPath($resolved);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Geplante Veranstaltung an der Meldung: manuelle Redaction/Anonymisierung ist deaktiviert.
     */
    private function redactionLockedForNewsItem(NewsItem $newsItem): bool
    {
        if (! Schema::hasColumn('news_items', 'planned_event_id')) {
            return false;
        }

        $id = $newsItem->planned_event_id;

        return $id !== null && $id !== '' && (int) $id > 0;
    }

    private function redirectRedactionLockedByPlannedEvent(NewsItem $newsItem, NewsItemMedia $medium): RedirectResponse
    {
        return redirect()
            ->route('admin.news.media.edit', [$newsItem, $medium])
            ->withFragment('redaction')
            ->with('error', 'Solange dieser Meldung eine geplante Veranstaltung zugewiesen ist, ist die Anonymisierung (Kennzeichen & Gesichter) nicht verfügbar. Entfernen Sie die Zuweisung auf der Meldungsbearbeitung, falls Sie Bereiche unkenntlich machen müssen.');
    }

    private function canManageNewsItemBypass(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->hasRole('admin');
    }

    private function mediaAttributeValueChanged(string $key, mixed $currentValue, mixed $newValue): bool
    {
        if (in_array($key, ['is_visible', 'versand'], true)) {
            return (bool) $currentValue !== (bool) $newValue;
        }

        if ($key === 'delivery_visible_for_organization_ids') {
            $normalize = static function (mixed $value): array {
                if (! is_array($value)) {
                    return [];
                }

                $ids = array_values(array_unique(array_filter(array_map('intval', $value))));
                sort($ids);

                return $ids;
            };

            return $normalize($currentValue) !== $normalize($newValue);
        }

        if ($key === 'metadata_recorded_at') {
            $current = $currentValue ? (string) $currentValue : null;
            $new = $newValue ? (string) $newValue : null;

            return $current !== $new;
        }

        if ($key === 'capture_time') {
            $current = $currentValue instanceof \DateTimeInterface ? $currentValue->format('Y-m-d H:i:s') : ($currentValue ? (string) $currentValue : null);
            $new = $newValue instanceof \DateTimeInterface ? $newValue->format('Y-m-d H:i:s') : ($newValue ? (string) $newValue : null);

            return $current !== $new;
        }

        $current = $currentValue === null ? null : (string) $currentValue;
        $new = $newValue === null ? null : (string) $newValue;

        return $current !== $new;
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

    private function selectedAdminBrand(Request $request): ?Brand
    {
        if (! Schema::hasTable('brands')) {
            return null;
        }

        $brandId = $this->selectedAdminBrandId($request);
        if ($brandId === null) {
            return null;
        }

        return Brand::query()
            ->where('is_active', true)
            ->whereKey($brandId)
            ->first(['id', 'name', 'key']);
    }

    /**
     * @return array<string, mixed>
     */
    private function newsCreateViewData(Request $request, ?string $forceBrandKey = null): array
    {
        $statuses = $this->statuses();
        $webTextAiEnabled = ! empty(config('media_ai.api_key'));
        $creditUsers = $this->creditUsersForPicker();
        $plannedEvents = class_exists(PlannedEvent::class) && Schema::hasTable('planned_events')
            ? PlannedEvent::queryForNewsSelect(null, auth()->user())
            : collect();
        $brands = $this->brandsForNewsForm();

        $selectedBrand = $forceBrandKey !== null
            ? $brands->firstWhere('key', $forceBrandKey)
            : $this->selectedAdminBrand($request);
        $newsBrandDefault = $selectedBrand?->id ?? $this->selectedAdminBrandId($request);
        $forceBrandId = $forceBrandKey !== null ? $selectedBrand?->id : null;

        $sourceNewsItem = null;
        $prefill = [];

        return compact('statuses', 'webTextAiEnabled', 'creditUsers', 'plannedEvents', 'brands', 'newsBrandDefault', 'selectedBrand', 'forceBrandId', 'sourceNewsItem', 'prefill');
    }

    /**
     * @return Collection<int, DeliveryDestination>
     */
    private function quickSendDestinationsForMedia(Request $request): Collection
    {
        if (! Schema::hasTable('delivery_destinations')) {
            return collect();
        }

        $allowedOrganizationIds = auth()->user()?->allowedDeliveryOrganizationIds() ?? [];
        $restrictByUserOrganizations = count($allowedOrganizationIds) > 0;

        $query = DeliveryDestination::query()
            ->where('type', 'email')
            ->where('active', true)
            ->with('organization')
            ->orderBy('label');

        if ($restrictByUserOrganizations) {
            $query->whereIn('organization_id', $allowedOrganizationIds);
        }

        $selectedBrandId = $this->selectedAdminBrandId($request);
        if ($selectedBrandId !== null && Schema::hasTable('organizations') && Schema::hasColumn('organizations', 'brand_id')) {
            $query->whereHas('organization', function ($q) use ($selectedBrandId) {
                $q->where('brand_id', $selectedBrandId);
            });
        }

        return $query->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveIsWdrJob(Request $request, array $data, ?NewsItem $existing = null, bool $isKoelnimagePhotoFlow = false): bool
    {
        if ($isKoelnimagePhotoFlow) {
            return false;
        }

        $brandId = (int) ($data['brand_id'] ?? $existing?->brand_id ?? 0);
        if ($brandId > 0 && Brand::query()->whereKey($brandId)->where('key', 'koelnimage')->exists()) {
            return false;
        }

        if ($request->boolean('no_wdr_job')) {
            return false;
        }

        $moid = trim((string) ($data['moid'] ?? $existing?->moid ?? ''));

        return $moid !== '';
    }

    private function mergeBrandIdIntoNewsData(Request $request, array &$data, ?NewsItem $existing = null): void
    {
        if (! $this->newsItemsHasColumn('brand_id')) {
            return;
        }

        $validated = $request->validated();
        if (array_key_exists('brand_id', $validated)) {
            $data['brand_id'] = $validated['brand_id'];

            return;
        }

        $this->applyBrandIdFromAdminSession($request, $data, $existing);
    }

    /**
     * @return Collection<int, Brand>
     */
    private function brandsForNewsForm(): Collection
    {
        if (! Schema::hasTable('brands') || ! $this->newsItemsHasColumn('brand_id')) {
            return collect();
        }

        return Brand::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'key']);
    }

    /**
     * Setzt news_items.brand_id aus dem Admin-Markenfilter (Session), wenn das Formular kein brand_id liefert.
     * Beim Update nur, wenn brand_id noch NULL ist (kein stiller Wechsel der Marke).
     *
     * @param  array<string, mixed>  $data
     */
    private function applyBrandIdFromAdminSession(Request $request, array &$data, ?NewsItem $existing = null): void
    {
        if (! $this->newsItemsHasColumn('brand_id')) {
            return;
        }

        $sessionBrandId = $this->selectedAdminBrandId($request);
        if ($sessionBrandId === null) {
            return;
        }

        if ($existing === null) {
            $data['brand_id'] = $sessionBrandId;

            return;
        }

        if ($existing->brand_id === null) {
            $data['brand_id'] = $sessionBrandId;
        }
    }

    private function newsItemsHasColumn(string $column): bool
    {
        static $cache = [];
        if (! array_key_exists($column, $cache)) {
            $cache[$column] = Schema::hasTable('news_items') && Schema::hasColumn('news_items', $column);
        }

        return $cache[$column];
    }

    private function nextUpdateRevisionForRoot(int $rootNewsItemId): int
    {
        if ($rootNewsItemId < 1 || ! $this->newsItemsHasColumn('parent_news_item_id') || ! $this->newsItemsHasColumn('update_revision')) {
            return 1;
        }

        $maxRevision = (int) NewsItem::query()
            ->where('parent_news_item_id', $rootNewsItemId)
            ->max('update_revision');

        return $maxRevision + 1;
    }

    /**
     * @param  list<int>  $sourceMediaIds
     */
    private function copySelectedSourceMediaToNewsItem(NewsItem $targetNewsItem, NewsItem $sourceNewsItem, array $sourceMediaIds): void
    {
        $mediaStorage = app(MediaStorage::class);
        $sourceRootNewsItemId = (int) ($sourceNewsItem->parent_news_item_id ?: $sourceNewsItem->id);
        $sourceMedia = NewsItemMedia::query()
            ->where('news_item_id', $sourceRootNewsItemId)
            ->whereIn('id', $sourceMediaIds)
            ->orderBy('sort_order')
            ->get();

        if ($sourceMedia->isEmpty()) {
            return;
        }

        $sortOrder = (int) ($targetNewsItem->media()->max('sort_order') ?? 0);
        foreach ($sourceMedia as $source) {
            $sourcePath = is_string($source->path) ? trim($source->path) : '';
            if ($sourcePath === '' || ! $mediaStorage->exists($sourcePath)) {
                continue;
            }

            $sortOrder++;
            $newMedia = $targetNewsItem->media()->create([
                'type' => $source->type,
                'path' => 'news-media/.pending',
                'original_name' => $source->original_name,
                'caption' => $source->caption,
                'image_title' => $source->image_title,
                'photographer' => $source->photographer,
                'media_keywords' => $source->media_keywords,
                'metadata_location' => $source->metadata_location,
                'metadata_recorded_at' => $source->metadata_recorded_at,
                'description' => $source->description,
                'city' => $source->city,
                'state' => $source->state,
                'country' => $source->country,
                'country_code' => $source->country_code,
                'capture_time' => $source->capture_time,
                'credit' => $source->credit,
                'copyright' => $source->copyright,
                'source' => $source->source,
                'is_visible' => (bool) $source->is_visible,
                'versand' => (bool) $source->versand,
                'delivery_visible_for_organization_ids' => $source->delivery_visible_for_organization_ids,
                'width' => $source->width,
                'height' => $source->height,
                'fps' => $source->fps,
                'bitrate_bps' => $source->bitrate_bps,
                'codec' => $source->codec,
                'field_order' => $source->field_order,
                'duration_s' => $source->duration_s,
                'sort_order' => $sortOrder,
            ]);

            $newMainPath = $mediaStorage->generateMediaPath(
                $targetNewsItem,
                $newMedia,
                $source->original_name ?: basename($sourcePath),
                $source->type === 'image' ? 'gallery' : $source->type
            );
            if (! $this->copyStoragePath($mediaStorage, $sourcePath, $newMainPath)) {
                $newMedia->delete();

                continue;
            }

            $newPreviewPath = null;
            $sourcePreviewPath = is_string($source->preview_path) ? trim($source->preview_path) : '';
            if ($sourcePreviewPath !== '' && $mediaStorage->exists($sourcePreviewPath)) {
                $newPreviewPath = $mediaStorage->generateDerivedMediaPath(
                    $targetNewsItem,
                    $newMedia,
                    basename($sourcePreviewPath),
                    'preview'
                );
                if (! $this->copyStoragePath($mediaStorage, $sourcePreviewPath, $newPreviewPath)) {
                    $newPreviewPath = null;
                }
            }

            $newMedia->update([
                'path' => $newMainPath,
                'preview_path' => $newPreviewPath,
            ]);
        }
    }

    private function copyStoragePath(MediaStorage $mediaStorage, string $sourcePath, string $targetPath): bool
    {
        $resolved = $mediaStorage->resolveReadableLocalPath($sourcePath);
        $localPath = is_array($resolved) ? ($resolved['path'] ?? null) : null;
        if (! is_string($localPath) || ! is_file($localPath)) {
            return false;
        }

        try {
            return $mediaStorage->putFromLocalFile($targetPath, $localPath, ['visibility' => 'public']);
        } finally {
            $mediaStorage->cleanupResolvedPath($resolved);
        }
    }
}
