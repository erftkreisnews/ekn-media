<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateImageMetadata;
use App\Jobs\GenerateNewsMediaPreview;
use App\Jobs\ProcessMediaRedaction;
use App\Mail\QuickMediaBatchDeliveryMail;
use App\Mail\QuickMediaDeliveryMail;
use App\Models\Delivery;
use App\Models\DeliveryDestination;
use App\Models\DeliveryRunItem;
use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use App\Services\MediaQualityCheck;
use App\Services\MediaStorage;
use App\Support\AdminMediaLibraryScope;
use App\Support\ImageWebSearchLinks;
use App\Support\MediaCaptionLocationDateTail;
use App\Support\MediaKeywordNormalizer;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImageController extends Controller
{
    private const QUICK_SEND_MAX_MAIL_BYTES = 10475274; // 9.99 MiB

    private const QUICK_SEND_BULK_MIN_PER_IMAGE_BYTES = 2097152; // 2.00 MiB

    /**
     * Zentrale Bild-Mediathek: Kacheln mit Vorschau, Link zur Bearbeitung (wie Video-Mediathek).
     */
    public function index(Request $request): View|StreamedResponse
    {
        $query = $this->imageLibraryQuery($request);

        if ($request->boolean('export')) {
            return $this->exportImageLibraryCsv(clone $query);
        }

        $perPage = (int) $request->input('per_page', 60);
        if (! in_array($perPage, [30, 60, 120], true)) {
            $perPage = 60;
        }

        $this->applyImageLibrarySort($query, (string) $request->input('sort', 'newest'));

        $images = $query->paginate($perPage)->withQueryString();

        $newsStatuses = [
            '' => 'Alle Meldungs-Status',
            'draft' => 'Entwurf',
            'review' => 'Review',
            'published' => 'Veröffentlicht',
            'archived' => 'Archiviert',
        ];

        $versandFilters = [
            '' => 'Versand: alle',
            'yes' => 'Versand: ja',
            'no' => 'Versand: nein',
        ];

        $visibleFilters = [
            '' => 'Sichtbarkeit: alle',
            'yes' => 'Nur sichtbar',
            'no' => 'Nur ausgeblendet',
        ];

        $sortOptions = [
            'newest' => 'Neueste zuerst',
            'oldest' => 'Älteste zuerst',
            'filename_az' => 'Dateiname A–Z',
        ];

        $perPageOptions = [30, 60, 120];

        $filterActive = $request->filled('news_status')
            || $request->filled('news_item_id')
            || $request->filled('year')
            || $request->filled('versand')
            || $request->filled('visible')
            || $request->filled('sort')
            || (int) $request->input('per_page', 60) !== 60
            || trim((string) $request->get('q', '')) !== '';

        $eventOptionsQuery = NewsItem::query()
            ->whereHas('media', static fn (Builder $b) => $b->where('type', 'image'));

        $selectedBrandId = $this->selectedAdminBrandId($request);
        if ($selectedBrandId !== null && Schema::hasColumn('news_items', 'brand_id')) {
            $eventOptionsQuery->where('brand_id', $selectedBrandId);
        }

        $eventOptions = $eventOptionsQuery
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(250)
            ->get(['id', 'title', 'published_at']);

        $yearOptions = $this->imageLibraryYearOptions($request);

        return view('admin.images.index', compact(
            'images',
            'newsStatuses',
            'versandFilters',
            'visibleFilters',
            'sortOptions',
            'perPageOptions',
            'perPage',
            'filterActive',
            'eventOptions',
            'yearOptions',
        ));
    }

    public function show(Request $request, int $media): JsonResponse
    {
        $medium = NewsItemMedia::query()
            ->with('newsItem')
            ->where('type', 'image')
            ->findOrFail($media);

        $this->abortIfCannotAccessImage($request, $medium);

        return response()->json($this->imageDetailPayload($medium));
    }

    /**
     * Same-Origin-Fallback für den Canvas-Editor (wenn Presigned S3/CORS nicht greift).
     * Streamt von S3 durch PHP, ohne die Datei dauerhaft auf dem App-Server zu speichern.
     * Liefert ausschließlich die Originaldatei (path / original_path), niemals preview_path.
     */
    public function editorSource(Request $request, int $media): StreamedResponse|BinaryFileResponse|Response
    {
        $medium = NewsItemMedia::query()
            ->with('newsItem')
            ->where('type', 'image')
            ->findOrFail($media);

        $this->abortIfCannotAccessImage($request, $medium);

        $path = $this->resolveEditorSourcePath($medium);
        if ($path === null) {
            abort(404, 'Bilddatei nicht verfügbar.');
        }

        $mediaStorage = app(MediaStorage::class);
        if (! $mediaStorage->exists($path)) {
            abort(404, 'Bilddatei nicht gefunden.');
        }

        $safeName = basename($path) !== '' ? basename($path) : 'image.jpg';
        $etag = $this->editorSourceEtag($mediaStorage, $path);
        $cacheHeaders = [
            'Cache-Control' => 'private, max-age=3600, stale-while-revalidate=600',
            'Content-Disposition' => 'inline; filename="'.$safeName.'"',
        ];
        if ($etag !== null) {
            $cacheHeaders['ETag'] = $etag;
            if ($request->headers->get('If-None-Match') === $etag) {
                return response(null, 304, $cacheHeaders);
            }
        }

        $resolved = $mediaStorage->resolveReadableLocalPath($path);
        // Nur echte lokale Pfade – niemals S3→Temp-Kopie (würde Server-Speicher belegen).
        if ($resolved !== null && is_file($resolved['path']) && ($resolved['temporary'] ?? false) !== true) {
            $mime = $this->editorSourceMimeType($resolved['path'], $safeName);
            $response = response()->file($resolved['path'], array_merge($cacheHeaders, [
                'Content-Type' => $mime,
            ]));

            return $response;
        }

        $disk = $mediaStorage->activeDisk();
        if (! $disk->exists($path) && $mediaStorage->fallbackDiskName() !== $mediaStorage->activeDiskName()) {
            $disk = $mediaStorage->fallbackDisk();
        }

        return $disk->response($path, $safeName, $cacheHeaders);
    }

    public function update(Request $request, int $media): JsonResponse
    {
        $medium = NewsItemMedia::query()
            ->with('newsItem')
            ->where('type', 'image')
            ->findOrFail($media);

        $this->abortIfCannotAccessImage($request, $medium);

        $rules = [
            'image_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'caption' => ['sometimes', 'nullable', 'string', 'max:1800'],
            'description' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'media_keywords' => ['sometimes', 'nullable', 'string', 'max:512'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'state' => ['sometimes', 'nullable', 'string', 'max:255'],
            'country' => ['sometimes', 'nullable', 'string', 'max:255'],
            'versand' => ['sometimes', 'boolean'],
            'is_visible' => ['sometimes', 'boolean'],
            'is_teaser' => ['sometimes', 'boolean'],
        ];

        $validated = $request->validate($rules);
        $update = [];

        foreach (['image_title', 'caption', 'description', 'city', 'state', 'country'] as $field) {
            if (array_key_exists($field, $validated)) {
                $value = $validated[$field];
                $update[$field] = is_string($value) && trim($value) === '' ? null : $value;
            }
        }

        if (array_key_exists('media_keywords', $validated)) {
            $update['media_keywords'] = MediaKeywordNormalizer::normalizeCommaSeparatedString($validated['media_keywords']);
        }

        foreach (['versand', 'is_visible'] as $boolField) {
            if ($request->has($boolField)) {
                $update[$boolField] = $request->boolean($boolField);
            }
        }

        if ($request->has('is_teaser')) {
            $wantsTeaser = $request->boolean('is_teaser');
            $update['is_teaser'] = $wantsTeaser;
            if ($wantsTeaser && $medium->newsItem) {
                $medium->newsItem->images()->where('id', '!=', $medium->id)->update(['is_teaser' => false]);
            }
        }

        if (array_key_exists('caption', $update) && $medium->newsItem) {
            $ghost = new NewsItemMedia;
            $ghost->forceFill([
                'city' => array_key_exists('city', $update) ? $update['city'] : $medium->city,
                'capture_time' => $medium->capture_time,
            ]);
            $ghost->setRelation('newsItem', $medium->newsItem);
            $captionInput = is_string($update['caption'] ?? null) ? $update['caption'] : '';
            $merged = MediaCaptionLocationDateTail::appendToCaption($medium->newsItem, $ghost, $captionInput);
            $update['caption'] = $merged === '' ? null : $merged;
        }

        if ($update !== []) {
            $medium->update($update);
            $medium->refresh();
            $medium->load('newsItem');
        }

        return response()->json([
            'ok' => true,
            'detail' => $this->imageDetailPayload($medium),
        ]);
    }

    public function editorSave(Request $request, int $media): JsonResponse
    {
        $source = NewsItemMedia::query()
            ->with('newsItem')
            ->where('type', 'image')
            ->findOrFail($media);

        $this->abortIfCannotAccessImage($request, $source);

        $newsItem = $source->newsItem;
        if ($newsItem === null) {
            return response()->json(['message' => 'Keine Meldung zugeordnet.'], 422);
        }

        $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg', 'max:25600'],
        ]);

        $file = $request->file('image');
        if (! $file instanceof \Illuminate\Http\UploadedFile || ! $file->isValid()) {
            return response()->json(['message' => 'Ungültige Bilddatei.'], 422);
        }

        $mediaStorage = app(MediaStorage::class);
        $sortOrder = (int) ($newsItem->media()->max('sort_order') ?? 0) + 1;
        $baseName = pathinfo((string) ($source->original_name ?: 'bild.jpg'), PATHINFO_FILENAME);
        $originalName = $baseName.'-bearbeitet.jpg';

        $newMedia = $newsItem->media()->create([
            'type' => 'image',
            'path' => 'news-media/.pending',
            'original_name' => $originalName,
            'sort_order' => $sortOrder,
            'image_title' => $source->image_title,
            'caption' => $source->caption,
            'description' => $source->description,
            'media_keywords' => $source->media_keywords,
            'photographer' => $source->photographer,
            'credit' => $source->credit,
            'copyright' => $source->copyright,
            'source' => $source->source,
            'city' => $source->city,
            'state' => $source->state,
            'country' => $source->country,
            'country_code' => $source->country_code,
            'capture_time' => $source->capture_time,
            'metadata_recorded_at' => $source->metadata_recorded_at,
            'is_visible' => $source->is_visible,
            'versand' => $source->versand,
            'is_teaser' => false,
            'brand_id' => $source->brand_id,
        ]);

        $path = $mediaStorage->generateMediaPath($newsItem, $newMedia, $file, 'gallery');
        $storedPath = $mediaStorage->storeUploadedFileAs($file, dirname($path), basename($path));
        $newMedia->update(['path' => $storedPath]);

        $newMedia->update([
            'redaction_status' => $newMedia->shouldAutoRedact()
                ? NewsItemMedia::REDACTION_PENDING
                : NewsItemMedia::REDACTION_DISABLED,
        ]);

        if ($newMedia->isImage() && filled($newMedia->path)) {
            app(MediaQualityCheck::class)->runAndSave($newMedia);
        }

        $previewPath = $mediaStorage->generateDerivedMediaPath($newsItem, $newMedia, $originalName.'.webp', 'preview');
        $newMedia->update(['preview_path' => $previewPath]);
        $watermarkPath = public_path('images/erftkreis-news-logo.png');
        if (is_file($watermarkPath)) {
            GenerateNewsMediaPreview::dispatchSync($mediaStorage->activeDiskName(), $storedPath, $previewPath, $watermarkPath);
        }

        try {
            $newMedia->markAiQueued();
            GenerateImageMetadata::dispatch($newMedia);
        } catch (\Throwable $e) {
            report($e);
        }

        if ($newMedia->shouldAutoRedact()) {
            ProcessMediaRedaction::dispatch($newMedia);
        }

        $newMedia->refresh();
        $newMedia->load('newsItem');

        return response()->json([
            'ok' => true,
            'media_id' => $newMedia->id,
            'detail' => $this->imageDetailPayload($newMedia),
        ]);
    }

    public function destroy(Request $request, int $media): RedirectResponse
    {
        $medium = NewsItemMedia::query()
            ->with('newsItem')
            ->where('type', 'image')
            ->findOrFail($media);

        $this->abortIfCannotAccessImage($request, $medium);

        $medium->delete();

        return back()->with('status', 'Bild wurde gelöscht.');
    }

    public function quickSendForm(Request $request, int $media): View
    {
        $medium = NewsItemMedia::query()
            ->with('newsItem')
            ->where('type', 'image')
            ->findOrFail($media);

        $this->abortIfCannotAccessImage($request, $medium);

        $quickSendDestinations = $this->quickSendDestinationsForMedia($request);

        return view('admin.images.quick-send', [
            'medium' => $medium,
            'quickSendDestinations' => $quickSendDestinations,
        ]);
    }

    public function quickSend(Request $request, int $media): RedirectResponse
    {
        $medium = NewsItemMedia::query()
            ->with('newsItem')
            ->where('type', 'image')
            ->findOrFail($media);

        $this->abortIfCannotAccessImage($request, $medium);

        $data = $request->validate([
            'delivery_destination_id' => ['nullable', 'integer'],
            'recipient_email' => ['nullable', 'string', 'max:1000'],
            'subject' => ['nullable', 'string', 'max:180'],
            'editor_note' => ['nullable', 'string', 'max:3000'],
        ]);

        $destinationId = (int) ($data['delivery_destination_id'] ?? 0);
        $recipientEmailRaw = trim((string) ($data['recipient_email'] ?? ''));
        $recipientEmails = collect(explode(',', $recipientEmailRaw))
            ->map(fn ($email) => trim((string) $email))
            ->filter(fn ($email) => $email !== '')
            ->unique()
            ->values();
        $subjectOverride = trim((string) ($data['subject'] ?? ''));
        $editorNote = trim((string) ($data['editor_note'] ?? ''));

        if ($destinationId < 1 && $recipientEmails->isEmpty()) {
            return back()->with('error', 'Bitte ein Versandziel auswählen oder mindestens eine E-Mail-Adresse eintragen.');
        }

        if (! $medium->versand) {
            $medium->update(['versand' => true]);
            $medium->refresh();
        }
        $originalAttachmentPath = $medium->resolveDeliveryDownloadRelativePath();
        if (! is_string($originalAttachmentPath) || $originalAttachmentPath === '') {
            return back()->with('error', 'Das Originalbild ist aktuell nicht verfügbar und kann daher nicht als Anhang versendet werden.');
        }
        $singleBytes = $this->resolveAttachmentSizeBytes($medium, $originalAttachmentPath);
        if (! is_int($singleBytes) || $singleBytes <= 0) {
            return back()->with('error', 'Die Dateigröße des Originalbilds konnte nicht ermittelt werden.');
        }
        if ($singleBytes > self::QUICK_SEND_MAX_MAIL_BYTES) {
            return back()->with('error', 'Dieses Bild ist zu groß für den Sofortversand ('.$this->bytesToMbString($singleBytes).' MB > 9,99 MB).');
        }

        $sendCount = 0;
        $selectedBrandId = $this->selectedAdminBrandId($request);

        if ($destinationId > 0) {
            $destination = DeliveryDestination::query()
                ->where('id', $destinationId)
                ->where('type', 'email')
                ->where('active', true)
                ->with('organization')
                ->first();

            if (! $destination) {
                return back()->with('error', 'Das gewählte Versandziel ist ungültig oder inaktiv.');
            }

            if ($selectedBrandId !== null && Schema::hasTable('organizations') && Schema::hasColumn('organizations', 'brand_id')) {
                $destinationOrgBrandId = (int) ($destination->organization?->brand_id ?? 0);
                if ($destinationOrgBrandId !== $selectedBrandId) {
                    return back()->with('error', 'Das Versandziel gehört nicht zum aktuell ausgewählten Brand.');
                }
            }

            if (! $request->user()?->canSendToOrganization((int) $destination->organization_id)) {
                return back()->with('error', 'Du darfst an dieses Versandziel nicht versenden.');
            }

            if (! $medium->isVisibleForFtpDestination((int) $destination->organization_id)) {
                return back()->with('error', 'Dieses Medium ist für die Ziel-Organisation nicht freigegeben.');
            }

            $toAddresses = $destination->getEmailToAddresses();
            if (empty($toAddresses)) {
                return back()->with('error', 'Das Versandziel hat keine E-Mail-Empfänger (To) konfiguriert.');
            }

            $delivery = $this->buildQuickSendDelivery($medium, $toAddresses[0], $destination->organization_id);

            $mail = new QuickMediaDeliveryMail(
                $medium->newsItem,
                $medium,
                $delivery,
                $subjectOverride !== '' ? $subjectOverride : null,
                $editorNote !== '' ? $editorNote : null,
                $destination
            );

            Mail::to($toAddresses)
                ->cc($destination->getEmailCcAddresses())
                ->bcc($destination->getEmailBccAddresses())
                ->send($mail);

            $sendCount += count($toAddresses);
        }

        if ($recipientEmails->isNotEmpty()) {
            $validator = Validator::make(
                ['recipient_emails' => $recipientEmails->all()],
                ['recipient_emails.*' => ['required', 'email']]
            );
            if ($validator->fails()) {
                return back()->withErrors(['recipient_email' => 'Bitte nur gültige E-Mail-Adressen eingeben (mehrere mit Komma trennen).']);
            }

            if ($request->user()?->hasDeliveryOrganizationRestriction()) {
                return back()->with('error', 'Direktversand per Einzel-E-Mail ist für deinen Benutzer deaktiviert.');
            }

            foreach ($recipientEmails as $recipientEmail) {
                $delivery = $this->buildQuickSendDelivery($medium, $recipientEmail, null);

                Mail::to($recipientEmail)->send(
                    new QuickMediaDeliveryMail(
                        $medium->newsItem,
                        $medium,
                        $delivery,
                        $subjectOverride !== '' ? $subjectOverride : null,
                        $editorNote !== '' ? $editorNote : null,
                        null
                    )
                );
                $sendCount++;
            }
        }

        return back()->with('status', 'Sofortversand ausgelöst ('.$sendCount.' Empfänger).');
    }

    public function quickSendBulkForm(Request $request): View|RedirectResponse
    {
        $mediaIds = collect((array) $request->input('media_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($mediaIds->isEmpty()) {
            return redirect()
                ->route('admin.images.index')
                ->with('error', 'Bitte mindestens ein Bild für den Sammelversand auswählen.');
        }

        $media = NewsItemMedia::query()
            ->with('newsItem')
            ->where('type', 'image')
            ->whereIn('id', $mediaIds->all())
            ->orderByDesc('id')
            ->get();

        if ($media->count() !== $mediaIds->count()) {
            return redirect()
                ->route('admin.images.index')
                ->with('error', 'Mindestens ein ausgewähltes Bild ist nicht verfügbar.');
        }

        foreach ($media as $item) {
            $this->abortIfCannotAccessImage($request, $item);
        }

        $quickSendDestinations = $this->quickSendDestinationsForMedia($request);

        return view('admin.images.quick-send-bulk', [
            'media' => $media,
            'quickSendDestinations' => $quickSendDestinations,
        ]);
    }

    public function quickSendBulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'media_ids' => ['required', 'array', 'min:1'],
            'media_ids.*' => ['integer', 'min:1'],
            'delivery_destination_id' => ['nullable', 'integer'],
            'recipient_email' => ['nullable', 'string', 'max:1000'],
            'subject' => ['nullable', 'string', 'max:180'],
            'editor_note' => ['nullable', 'string', 'max:3000'],
        ]);

        $mediaIds = collect((array) ($data['media_ids'] ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();
        $media = NewsItemMedia::query()
            ->with('newsItem')
            ->where('type', 'image')
            ->whereIn('id', $mediaIds->all())
            ->orderByDesc('id')
            ->get();

        if ($media->count() !== $mediaIds->count()) {
            return back()->with('error', 'Mindestens ein ausgewähltes Bild ist nicht verfügbar.');
        }

        foreach ($media as $item) {
            $this->abortIfCannotAccessImage($request, $item);
            if (! $item->versand) {
                $item->update(['versand' => true]);
                $item->refresh();
            }
            $path = $item->resolveDeliveryDownloadRelativePath();
            if (! is_string($path) || $path === '') {
                return back()->with('error', 'Mindestens ein Originalbild ist aktuell nicht verfügbar und kann nicht als Anhang versendet werden (Media-ID '.$item->id.').');
            }
            $sizeBytes = $this->resolveAttachmentSizeBytes($item, $path);
            if (! is_int($sizeBytes) || $sizeBytes <= 0) {
                return back()->with('error', 'Die Dateigröße konnte für mindestens ein ausgewähltes Bild nicht ermittelt werden (Media-ID '.$item->id.').');
            }
            $attachmentSizes[(int) $item->id] = $sizeBytes;
        }
        $mailBytesTotal = array_sum($attachmentSizes ?? []);
        $mailMaxBytes = self::QUICK_SEND_MAX_MAIL_BYTES;
        $perImageBudgetBytes = max(
            self::QUICK_SEND_BULK_MIN_PER_IMAGE_BYTES,
            (int) floor($mailMaxBytes / max(1, $media->count()))
        );
        $oversizedByBudgetIds = collect($attachmentSizes ?? [])
            ->filter(fn ($size) => (int) $size > $perImageBudgetBytes)
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->values();
        if ($oversizedByBudgetIds->isNotEmpty()) {
            return back()->with(
                'error',
                'Mindestens ein Bild überschreitet das erlaubte Größenbudget pro Bild bei dieser Auswahl ('
                .$this->bytesToMbString($perImageBudgetBytes).' MB je Bild): Media-ID '
                .$oversizedByBudgetIds->implode(', ')
            );
        }
        if ($mailBytesTotal > $mailMaxBytes) {
            return back()->with(
                'error',
                'Die Gesamtgröße der ausgewählten Bilder ist zu groß für eine Mail ('
                .$this->bytesToMbString($mailBytesTotal).' MB > 9,99 MB). Bitte Auswahl reduzieren.'
            );
        }

        $destinationId = (int) ($data['delivery_destination_id'] ?? 0);
        $recipientEmailRaw = trim((string) ($data['recipient_email'] ?? ''));
        $recipientEmails = collect(explode(',', $recipientEmailRaw))
            ->map(fn ($email) => trim((string) $email))
            ->filter(fn ($email) => $email !== '')
            ->unique()
            ->values();
        $subjectOverride = trim((string) ($data['subject'] ?? ''));
        $editorNote = trim((string) ($data['editor_note'] ?? ''));

        if ($destinationId < 1 && $recipientEmails->isEmpty()) {
            return back()->with('error', 'Bitte ein Versandziel auswählen oder mindestens eine E-Mail-Adresse eintragen.');
        }

        $selectedBrandId = $this->selectedAdminBrandId($request);
        $sendCount = 0;

        if ($destinationId > 0) {
            $destination = DeliveryDestination::query()
                ->where('id', $destinationId)
                ->where('type', 'email')
                ->where('active', true)
                ->with('organization')
                ->first();

            if (! $destination) {
                return back()->with('error', 'Das gewählte Versandziel ist ungültig oder inaktiv.');
            }

            if ($selectedBrandId !== null && Schema::hasTable('organizations') && Schema::hasColumn('organizations', 'brand_id')) {
                $destinationOrgBrandId = (int) ($destination->organization?->brand_id ?? 0);
                if ($destinationOrgBrandId !== $selectedBrandId) {
                    return back()->with('error', 'Das Versandziel gehört nicht zum aktuell ausgewählten Brand.');
                }
            }

            if (! $request->user()?->canSendToOrganization((int) $destination->organization_id)) {
                return back()->with('error', 'Du darfst an dieses Versandziel nicht versenden.');
            }

            $notVisibleIds = $media
                ->filter(fn (NewsItemMedia $item) => ! $item->isVisibleForFtpDestination((int) $destination->organization_id))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values();
            if ($notVisibleIds->isNotEmpty()) {
                return back()->with('error', 'Einige ausgewählte Bilder sind für die Ziel-Organisation nicht freigegeben: '.implode(', ', $notVisibleIds->all()));
            }

            $toAddresses = $destination->getEmailToAddresses();
            if (empty($toAddresses)) {
                return back()->with('error', 'Das Versandziel hat keine E-Mail-Empfänger (To) konfiguriert.');
            }

            Mail::to($toAddresses)
                ->cc($destination->getEmailCcAddresses())
                ->bcc($destination->getEmailBccAddresses())
                ->send(new QuickMediaBatchDeliveryMail(
                    $media,
                    $subjectOverride !== '' ? $subjectOverride : null,
                    $editorNote !== '' ? $editorNote : null,
                    $destination
                ));

            $sendCount += count($toAddresses);
        }

        if ($recipientEmails->isNotEmpty()) {
            $validator = Validator::make(
                ['recipient_emails' => $recipientEmails->all()],
                ['recipient_emails.*' => ['required', 'email']]
            );
            if ($validator->fails()) {
                return back()->withErrors(['recipient_email' => 'Bitte nur gültige E-Mail-Adressen eingeben (mehrere mit Komma trennen).']);
            }

            if ($request->user()?->hasDeliveryOrganizationRestriction()) {
                return back()->with('error', 'Direktversand per Einzel-E-Mail ist für deinen Benutzer deaktiviert.');
            }

            foreach ($recipientEmails as $recipientEmail) {
                Mail::to($recipientEmail)->send(
                    new QuickMediaBatchDeliveryMail(
                        $media,
                        $subjectOverride !== '' ? $subjectOverride : null,
                        $editorNote !== '' ? $editorNote : null,
                        null
                    )
                );
                $sendCount++;
            }
        }

        return redirect()
            ->route('admin.images.index')
            ->with('status', 'Sammel-Sofortversand ausgelöst ('.$sendCount.' Empfänger, '.$media->count().' Bild(er)).');
    }

    /**
     * @return Builder<NewsItemMedia>
     */
    /**
     * @return array<string, mixed>
     */
    private function imageDetailPayload(NewsItemMedia $medium): array
    {
        $newsItem = $medium->newsItem;
        $keywords = collect(explode(',', (string) ($medium->media_keywords ?? '')))
            ->map(fn (string $k) => trim($k))
            ->filter()
            ->values()
            ->all();

        $webSearch = ImageWebSearchLinks::forMedia($medium);
        $fileSizeKb = $medium->file_size_kb;
        $fileSizeLabel = $fileSizeKb >= 1024
            ? number_format($fileSizeKb / 1024, 2, ',', '.').' MB'
            : number_format($fileSizeKb, 0, ',', '.').' KB';

        $format = '—';
        $path = (string) ($medium->path ?? $medium->original_path ?? '');
        if ($path !== '') {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $format = $ext !== '' ? strtoupper($ext) : '—';
        }

        $flags = $this->imageProcessingFlags($medium);
        $originalUrl = $medium->library_image_url;
        $exifDetails = $medium->readExifDetailsFromMasterFile();

        $width = $medium->width;
        $height = $medium->height;
        foreach ($exifDetails as $row) {
            if (($row['label'] ?? '') === 'Abmessungen' && (! $width || ! $height)) {
                if (preg_match('/^(\d+)\s×\s(\d+)\s+px$/u', (string) ($row['value'] ?? ''), $m)) {
                    $width = (int) $m[1];
                    $height = (int) $m[2];
                }
                break;
            }
        }

        return [
            'id' => $medium->id,
            'original_name' => $medium->original_name,
            'image_title' => $medium->image_title,
            'headline' => $medium->image_title ?: $medium->caption,
            'caption' => $medium->caption,
            'description' => $medium->description,
            'photographer' => $medium->photographer ?: $medium->credit,
            'media_keywords' => $medium->media_keywords,
            'keywords' => $keywords,
            'city' => $medium->city,
            'state' => $medium->state,
            'country' => $medium->country,
            'capture_time' => $medium->capture_time?->toIso8601String(),
            'capture_time_label' => $medium->capture_time?->format('d.m.Y, H:i').' Uhr',
            'created_at_label' => $medium->created_at?->format('d.m.Y, H:i').' Uhr',
            'updated_at_label' => $medium->updated_at?->format('d.m.Y, H:i').' Uhr',
            'preview_url' => $originalUrl,
            'preview_urls' => [
                'original' => $originalUrl,
            ],
            'thumb_url' => $originalUrl,
            'exif_details' => $exifDetails,
            'width' => $width,
            'height' => $height,
            'file_size_label' => $fileSizeLabel,
            'format' => $format,
            'flags' => $flags,
            'status' => [
                'versand' => (bool) $medium->versand,
                'is_visible' => (bool) $medium->is_visible,
                'is_teaser' => (bool) $medium->is_teaser,
                'is_unkentlich' => (bool) $medium->is_unkentlich,
                'ai_status' => $medium->ai_status,
                'quality_status' => $medium->quality_status,
            ],
            'news_item' => $newsItem ? [
                'id' => $newsItem->id,
                'title' => $newsItem->title,
                'status' => $newsItem->status,
                'location' => Schema::hasColumn('news_items', 'location_chip_label')
                    ? $newsItem->location_chip_label
                    : null,
                'edit_url' => route('admin.news.edit', $newsItem),
            ] : null,
            'urls' => [
                'edit' => $newsItem ? route('admin.news.media.edit', [$newsItem, $medium->id]) : null,
                'image_editor' => $newsItem ? route('admin.news.media.image-editor', [$newsItem, $medium->id]) : null,
                'update' => route('admin.images.update', $medium->id),
                'editor_source' => route('admin.images.editor-source', $medium->id),
                'quick_send' => route('admin.images.quick-send', $medium->id),
                'google_lens' => $webSearch['google_lens'] ?? null,
                'google_news' => $webSearch['google_news'] ?? null,
                'publication_finding' => route('admin.backoffice.publication-findings.create', [
                    'news_item_media_id' => $medium->id,
                    'news_item_id' => $newsItem?->id,
                ]),
            ],
        ];
    }

    private function resolveEditorSourcePath(NewsItemMedia $medium): ?string
    {
        return $medium->resolveEditorSourceRelativePath();
    }

    private function editorSourceMimeType(string $fullPath, string $safeName): string
    {
        $guessed = mime_content_type($fullPath);
        if (is_string($guessed) && str_starts_with($guessed, 'image/')) {
            return $guessed;
        }

        $fromName = match (strtolower(pathinfo($safeName, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'tif', 'tiff' => 'image/tiff',
            default => null,
        };

        return $fromName ?? 'image/jpeg';
    }

    private function editorSourceEtag(MediaStorage $mediaStorage, string $path): ?string
    {
        $size = $mediaStorage->size($path);
        if ($size < 1) {
            return null;
        }

        $digest = in_array('xxh128', hash_algos(), true)
            ? hash('xxh128', $path.':'.$size)
            : hash('sha256', $path.'|'.$size);

        return '"'.$digest.'"';
    }

    /**
     * @return array<string, bool>
     */
    private function imageProcessingFlags(NewsItemMedia $medium): array
    {
        $hasFtp = Schema::hasTable('delivery_run_items')
            && DeliveryRunItem::query()->where('news_item_media_id', $medium->id)->exists();

        $aiDone = in_array((string) ($medium->ai_status ?? ''), ['done', 'completed', 'success'], true);
        $hasIptc = filled($medium->image_title) || filled($medium->caption) || filled($medium->media_keywords);

        return [
            'processed' => filled($medium->path),
            'ai_processed' => $aiDone,
            'iptc_written' => $hasIptc,
            'ftp_sent' => $hasFtp,
            'public' => (bool) $medium->is_visible && $medium->public_path !== null,
        ];
    }

    private function imageLibraryQuery(Request $request): Builder
    {
        $q = NewsItemMedia::query()
            ->where('type', 'image')
            ->where(function (Builder $b): void {
                $b->where('path', 'not like', '%/derived/%')
                    ->where('path', 'not like', '%/thumb/%')
                    ->where('path', 'not like', '%.webp');
            })
            ->with(['newsItem']);

        AdminMediaLibraryScope::applyAuthorScope($q, $request);
        AdminMediaLibraryScope::applyBrandScope($q, $request, AdminMediaLibraryScope::selectedBrandId($request));

        if ($request->filled('news_status')) {
            $q->whereHas('newsItem', function (Builder $b) use ($request): void {
                $b->where('status', $request->string('news_status'));
            });
        }

        $search = trim((string) $request->get('q', ''));
        if ($search !== '') {
            $q->where(function (Builder $b) use ($search): void {
                $b->where('original_name', 'like', '%'.$search.'%')
                    ->orWhere('image_title', 'like', '%'.$search.'%')
                    ->orWhere('caption', 'like', '%'.$search.'%')
                    ->orWhere('media_keywords', 'like', '%'.$search.'%')
                    ->orWhereHas('newsItem', function (Builder $n) use ($search): void {
                        $n->where('title', 'like', '%'.$search.'%');
                    });
                if (ctype_digit($search)) {
                    $b->orWhere('id', (int) $search)
                        ->orWhere('news_item_id', (int) $search);
                }
            });
        }

        if ($request->filled('news_item_id') && ctype_digit((string) $request->input('news_item_id'))) {
            $q->where('news_item_id', (int) $request->input('news_item_id'));
        }

        if ($request->filled('year') && ctype_digit((string) $request->input('year'))) {
            $year = (int) $request->input('year');
            $q->whereRaw(
                'YEAR('.self::effectiveCaptureTimeSql().') = ?',
                [$year]
            );
        }

        $versand = (string) $request->input('versand', '');
        if ($versand === 'yes') {
            $q->where('versand', true);
        } elseif ($versand === 'no') {
            $q->where('versand', false);
        }

        $visible = (string) $request->input('visible', '');
        if ($visible === 'yes') {
            $q->where('is_visible', true);
        } elseif ($visible === 'no') {
            $q->where('is_visible', false);
        }

        $this->applyImageLibraryDedupe($q);

        return $q;
    }

    /**
     * Pro Meldung nur ein Eintrag je Original-Dateiname (bzw. Speicherpfad); behält den neuesten Datensatz.
     */
    private function applyImageLibraryDedupe(Builder $query): void
    {
        $table = $query->getModel()->getTable();

        $dedupeSubquery = NewsItemMedia::query()
            ->where('type', 'image')
            ->where(function (Builder $b): void {
                $b->where('path', 'not like', '%/derived/%')
                    ->where('path', 'not like', '%/thumb/%')
                    ->where('path', 'not like', '%.webp');
            })
            ->selectRaw('MAX(id) as id')
            ->groupByRaw(
                "news_item_id, COALESCE(NULLIF(LOWER(TRIM(original_name)), ''), path)"
            );

        $query->whereIn($table.'.id', $dedupeSubquery);
    }

    private function applyImageLibrarySort(Builder $query, string $sort): void
    {
        if ($sort === 'oldest') {
            $query->orderBy(DB::raw(self::effectiveCaptureTimeSql()))
                ->orderBy('id');

            return;
        }

        if ($sort === 'filename_az') {
            $query->orderBy('original_name')->orderBy('id');

            return;
        }

        $query->orderByDesc(DB::raw(self::effectiveCaptureTimeSql()))
            ->orderByDesc('id');
    }

    /**
     * Aufnahmezeit mit Fallback auf Upload-Datum (Stills/Uploads ohne EXIF).
     */
    private static function effectiveCaptureTimeSql(): string
    {
        return 'COALESCE(capture_time, created_at)';
    }

    /**
     * @return list<int>
     */
    private function imageLibraryYearOptions(Request $request): array
    {
        $yearsQuery = NewsItemMedia::query()
            ->where('type', 'image')
            ->where(function (Builder $b): void {
                $b->where('path', 'not like', '%/derived/%')
                    ->where('path', 'not like', '%/thumb/%')
                    ->where('path', 'not like', '%.webp');
            });

        $user = $request->user();
        if (! ($user && $user->hasRole('admin'))) {
            $yearsQuery->whereHas('newsItem', function (Builder $b) use ($user): void {
                $b->where('author_id', $user?->id);
            });
        }

        $selectedBrandId = $this->selectedAdminBrandId($request);
        if ($selectedBrandId !== null && Schema::hasColumn('news_item_media', 'brand_id')) {
            $yearsQuery->where(function (Builder $b) use ($selectedBrandId): void {
                $b->where('brand_id', $selectedBrandId)
                    ->orWhere(function (Builder $fallback) use ($selectedBrandId): void {
                        $fallback->whereNull('brand_id')
                            ->whereHas('newsItem', function (Builder $news) use ($selectedBrandId): void {
                                $news->where('brand_id', $selectedBrandId);
                            });
                    });
            });
        }

        $this->applyImageLibraryDedupe($yearsQuery);

        return (clone $yearsQuery)
            ->selectRaw('DISTINCT YEAR('.self::effectiveCaptureTimeSql().') as y')
            ->pluck('y')
            ->filter(fn ($y) => is_numeric($y) && (int) $y > 1970)
            ->map(fn ($y) => (int) $y)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
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

    private function abortIfCannotAccessImage(Request $request, NewsItemMedia $medium): void
    {
        $user = $request->user();
        if (AdminMediaLibraryScope::canBrowseAllMedia($user)) {
            return;
        }

        $authorId = (int) ($medium->newsItem?->author_id ?? 0);
        if ($authorId > 0 && $authorId === (int) $user?->id) {
            return;
        }

        abort(403, 'Sie dürfen dieses Bild nicht versenden.');
    }

    /**
     * @return Collection<int, DeliveryDestination>
     */
    private function quickSendDestinationsForMedia(Request $request): Collection
    {
        if (! Schema::hasTable('delivery_destinations')) {
            return collect();
        }

        $allowedOrganizationIds = $request->user()?->allowedDeliveryOrganizationIds() ?? [];
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

    private function buildQuickSendDelivery(NewsItemMedia $medium, string $recipientEmail, ?int $allowedOrganizationId): ?Delivery
    {
        if ($medium->news_item_id) {
            $delivery = Delivery::create([
                'news_item_id' => (int) $medium->news_item_id,
                'recipient_email' => $recipientEmail,
                'expires_at' => now()->addHours(48),
                'created_by' => auth()->id(),
                'allowed_organization_id' => $allowedOrganizationId,
            ]);

            return $delivery;
        }

        return null;
    }

    private function resolveAttachmentSizeBytes(NewsItemMedia $medium, string $relativePath): ?int
    {
        try {
            $size = app(\App\Services\MediaStorage::class)->size($relativePath);

            return is_int($size) && $size > 0 ? $size : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function bytesToMbString(int $bytes): string
    {
        return number_format($bytes / 1048576, 2, ',', '.');
    }

    /**
     * @param  Builder<NewsItemMedia>  $query
     */
    private function exportImageLibraryCsv(Builder $query): StreamedResponse
    {
        $filename = 'bild-mediathek-'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'media_id',
                'news_id',
                'news_title',
                'news_status',
                'original_name',
                'image_title',
                'width',
                'height',
                'updated_at',
            ]);

            $query->orderBy('id')->chunk(500, function ($rows) use ($out): void {
                foreach ($rows as $m) {
                    /** @var NewsItemMedia $m */
                    fputcsv($out, [
                        $m->id,
                        $m->news_item_id,
                        $m->newsItem?->title,
                        $m->newsItem?->status,
                        $m->original_name,
                        $m->image_title,
                        $m->width,
                        $m->height,
                        $m->updated_at?->toIso8601String(),
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
