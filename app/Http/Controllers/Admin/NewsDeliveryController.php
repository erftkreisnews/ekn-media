<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\UploadMediaToDestinationJob;
use App\Mail\NewsDeliveryMail;
use App\Mail\QuickMediaDeliveryMail;
use App\Models\Contact;
use App\Models\Delivery;
use App\Models\DeliveryDestination;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunItem;
use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use App\Services\NewsDeliveryUpdateSummaryService;
use App\Services\WdrRecipientGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class NewsDeliveryController extends Controller
{
    private const QUICK_SEND_MAX_MAIL_BYTES = 10475274; // 9.99 MiB

    /**
     * Versand vorbereiten: Versandziele (E-Mail) der Organisationen oder Einzelempfänger.
     * Bei WDR-Job mit MoID: nur WDR-Versandziele bzw. WDR-Empfänger zulässig.
     */
    public function prepareSend(Request $request, NewsItem $newsItem): View|RedirectResponse
    {
        $this->abortIfCannotManageNewsItem($newsItem);
        $selectedBrandId = $this->selectedAdminBrandId($request);

        if ($newsItem->blocksAdminSendWithoutMoid()) {
            return redirect()
                ->route('admin.news.edit', $newsItem)
                ->with('error', 'Bei einem WDR-Job muss eine MoID eingetragen werden (wichtig für WDR-Abrechnung). Bitte unter „Nachricht“ das Feld „MoID“ ausfüllen oder „Kein WDR-Job“ ankreuzen.');
        }

        $defaultEmail = config('newsdesk.delivery_recipient', '');
        $allowedOrganizationIds = auth()->user()?->allowedDeliveryOrganizationIds() ?? [];
        $restrictByUserOrganizations = count($allowedOrganizationIds) > 0;

        $destinationsQuery = DeliveryDestination::where('type', 'email')
            ->where('active', true)
            ->with('organization')
            ->orderBy('label');

        if ($restrictByUserOrganizations) {
            $destinationsQuery->whereIn('organization_id', $allowedOrganizationIds);
        }
        if ($selectedBrandId !== null && Schema::hasTable('organizations') && Schema::hasColumn('organizations', 'brand_id')) {
            $destinationsQuery->whereHas('organization', function ($q) use ($selectedBrandId) {
                $q->where('brand_id', $selectedBrandId);
            });
        }

        if ($newsItem->hasMoidRestriction()) {
            $names = config('newsdesk.wdr_organization_names', ['WDR', 'Westdeutscher Rundfunk']);
            $destinationsQuery->whereHas('organization', function ($q) use ($names) {
                $q->where(function ($q2) use ($names) {
                    foreach ($names as $name) {
                        $q2->orWhere('name', 'like', '%'.$name.'%');
                    }
                });
            });
        }

        $destinations = $destinationsQuery->get();
        $destinationsByOrg = $destinations->groupBy(fn ($d) => $d->organization_id ?? 0);

        // FTP-/SFTP-Versandziele für separaten Reiter
        $ftpDestinationsQuery = DeliveryDestination::whereIn('type', ['ftp', 'ftps', 'sftp'])
            ->where('active', true)
            ->with('organization')
            ->orderBy('label');

        if ($restrictByUserOrganizations) {
            $ftpDestinationsQuery->whereIn('organization_id', $allowedOrganizationIds);
        }
        if ($selectedBrandId !== null && Schema::hasTable('organizations') && Schema::hasColumn('organizations', 'brand_id')) {
            $ftpDestinationsQuery->whereHas('organization', function ($q) use ($selectedBrandId) {
                $q->where('brand_id', $selectedBrandId);
            });
        }

        if ($newsItem->hasMoidRestriction()) {
            $names = config('newsdesk.wdr_organization_names', ['WDR', 'Westdeutscher Rundfunk']);
            $ftpDestinationsQuery->whereHas('organization', function ($q) use ($names) {
                $q->where(function ($q2) use ($names) {
                    foreach ($names as $name) {
                        $q2->orWhere('name', 'like', '%'.$name.'%');
                    }
                });
            });
        }

        $ftpDestinations = $ftpDestinationsQuery->get();
        $ftpDestinationsByOrg = $ftpDestinations->groupBy(fn ($d) => $d->organization_id ?? 0);

        $contactsQuery = Contact::whereNotNull('email')
            ->where('email', '!=', '')
            ->with('organization')
            ->orderBy('name');

        if ($restrictByUserOrganizations) {
            $contactsQuery->whereIn('organization_id', $allowedOrganizationIds);
        }
        if ($selectedBrandId !== null && Schema::hasTable('organizations') && Schema::hasColumn('organizations', 'brand_id')) {
            $contactsQuery->whereHas('organization', function ($q) use ($selectedBrandId) {
                $q->where('brand_id', $selectedBrandId);
            });
        }

        if ($newsItem->hasMoidRestriction()) {
            $names = config('newsdesk.wdr_organization_names', ['WDR', 'Westdeutscher Rundfunk']);
            $contactsQuery->whereHas('organization', function ($q) use ($names) {
                $q->where(function ($q2) use ($names) {
                    foreach ($names as $name) {
                        $q2->orWhere('name', 'like', '%'.$name.'%');
                    }
                });
            });
        }

        $contacts = $contactsQuery->get();
        $ftpMediaTypes = $this->ftpMediaTypesForNewsItem($newsItem);
        $ftpSelectableMedia = $newsItem->media()
            ->whereIn('type', $ftpMediaTypes)
            ->where('versand', true)
            ->orderBy('sort_order')
            ->get();
        $requestedPreselectMediaId = (int) $request->query('preselect_media_id', 0);
        $preselectedFtpMediaId = $requestedPreselectMediaId > 0
            && $ftpSelectableMedia->contains(fn (NewsItemMedia $media) => (int) $media->id === $requestedPreselectMediaId)
            ? $requestedPreselectMediaId
            : null;

        $updatePreview = app(NewsDeliveryUpdateSummaryService::class)->preview(
            $newsItem,
            $request->boolean('is_update_delivery'),
            $request->query('context')
        );

        return view('admin.news.prepare-send', [
            'newsItem' => $newsItem,
            'hasPriorDeliveries' => $newsItem->hasPriorDeliveries(),
            'updatePreview' => $updatePreview,
            'destinations' => $destinations,
            'destinationsByOrg' => $destinationsByOrg,
            'ftpDestinations' => $ftpDestinations,
            'ftpDestinationsByOrg' => $ftpDestinationsByOrg,
            'contacts' => $contacts,
            'ftpSelectableMedia' => $ftpSelectableMedia,
            'preselectedFtpMediaId' => $preselectedFtpMediaId,
            'ftpMediaTypeLabels' => $this->ftpMediaTypeLabels($ftpMediaTypes),
            'defaultEmail' => $defaultEmail,
            'onlyWdrAllowed' => $newsItem->hasMoidRestriction(),
            'restrictByUserOrganizations' => $restrictByUserOrganizations,
        ]);
    }

    /**
     * FTP-/SFTP-Upload für alle versand-markierten Medien dieser Nachricht einreihen.
     */
    public function queueFtp(Request $request, NewsItem $newsItem): RedirectResponse
    {
        $this->abortIfCannotManageNewsItem($newsItem);
        $selectedBrandId = $this->selectedAdminBrandId($request);

        $destinationId = (int) $request->input('ftp_destination_id');

        $destination = DeliveryDestination::where('id', $destinationId)
            ->whereIn('type', ['ftp', 'ftps', 'sftp'])
            ->where('active', true)
            ->with('organization')
            ->first();

        if (! $destination) {
            return redirect()
                ->route('admin.news.send', $newsItem)
                ->with('error', 'Ungültiges FTP-/SFTP-Versandziel.');
        }

        if ($selectedBrandId !== null && Schema::hasTable('organizations') && Schema::hasColumn('organizations', 'brand_id')) {
            $destinationOrgBrandId = (int) ($destination->organization?->brand_id ?? 0);
            if ($destinationOrgBrandId !== $selectedBrandId) {
                return redirect()
                    ->route('admin.news.send', $newsItem)
                    ->with('error', 'Das gewählte FTP-/SFTP-Versandziel gehört nicht zum aktuell ausgewählten Brand.');
            }
        }

        if (! auth()->user()?->canSendToOrganization((int) $destination->organization_id)) {
            return redirect()
                ->route('admin.news.send', $newsItem)
                ->with('error', 'Du darfst an dieses Versandziel nicht versenden.');
        }

        if ($newsItem->hasMoidRestriction()) {
            $names = config('newsdesk.wdr_organization_names', ['WDR', 'Westdeutscher Rundfunk']);
            $org = $destination->organization;
            if (! $org || ! collect($names)->contains(fn ($n) => stripos($org->name, $n) !== false)) {
                return redirect()
                    ->route('admin.news.send', $newsItem)
                    ->with('error', 'Diese Meldung (MoID) darf nur an WDR-Versandziele versendet werden.');
            }
        }

        $requestedMediaIds = collect((array) $request->input('ftp_media_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();
        $forceReupload = $request->boolean('force_reupload');

        // Medien mit Versand = ja, eingeschränkt auf Ziel-Organisation (falls gesetzt)
        $destOrgId = $destination->organization_id;
        $ftpMediaTypes = $this->ftpMediaTypesForNewsItem($newsItem);
        $eligibleMedia = NewsItemMedia::where('news_item_id', $newsItem->id)
            ->whereIn('type', $ftpMediaTypes)
            ->where('versand', true)
            ->get()
            ->filter(fn (NewsItemMedia $m) => $m->isVisibleForFtpDestination($destOrgId))
            ->values();

        if ($requestedMediaIds->isNotEmpty()) {
            $selectedMedia = $eligibleMedia
                ->whereIn('id', $requestedMediaIds)
                ->values();

            if ($selectedMedia->count() !== $requestedMediaIds->count()) {
                return redirect()
                    ->route('admin.news.send', $newsItem)
                    ->withInput()
                    ->with('error', 'Mindestens ein gewähltes Medium ist für dieses FTP-Ziel nicht verfügbar.');
            }
        } else {
            $selectedMedia = $eligibleMedia;
        }

        $alreadyUploadedMediaIds = collect();
        if (! $forceReupload) {
            $alreadyUploadedMediaIds = DeliveryRunItem::query()
                ->where('status', 'success')
                ->whereNotNull('news_item_media_id')
                ->whereHas('deliveryRun', function ($q) use ($destination) {
                    $q->where('delivery_destination_id', $destination->id);
                })
                ->whereIn('news_item_media_id', $selectedMedia->pluck('id')->all())
                ->pluck('news_item_media_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $selectedMedia = $selectedMedia
                ->reject(fn (NewsItemMedia $media) => $alreadyUploadedMediaIds->contains((int) $media->id))
                ->values();
        }

        $mediaIds = $selectedMedia
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (empty($mediaIds)) {
            if ($forceReupload) {
                $mediaTypeLabel = ($this->ftpMediaTypeLabels($ftpMediaTypes)['plural'] ?? 'Medien');

                return redirect()
                    ->route('admin.news.send', $newsItem)
                    ->with(
                        'error',
                        'Re-Upload wurde angefordert, aber für dieses FTP-Ziel sind keine '.$mediaTypeLabel
                        .' uploadfähig (Prüfung: Typ, Versand=Ja, Ziel-Freigabe je Organisation).'
                    );
            }

            if ($eligibleMedia->isEmpty()) {
                $mediaTypeLabel = ($this->ftpMediaTypeLabels($ftpMediaTypes)['plural'] ?? 'Medien');

                return redirect()
                    ->route('admin.news.send', $newsItem)
                    ->with(
                        'error',
                        'Für dieses FTP-Ziel sind aktuell keine '.$mediaTypeLabel
                        .' uploadfähig (Prüfung: Typ, Versand=Ja, Ziel-Freigabe je Organisation).'
                    );
            }

            return redirect()
                ->route('admin.news.send', $newsItem)
                ->with('status', 'Keine neuen Medien für dieses FTP-Ziel: Bereits erfolgreich hochgeladene Dateien werden nicht erneut übertragen.');
        }

        $run = DeliveryRun::create([
            'delivery_destination_id' => $destination->id,
            'status' => 'queued',
        ]);

        foreach ($mediaIds as $mediaId) {
            $media = NewsItemMedia::find($mediaId);
            $run->items()->create([
                'news_item_media_id' => $mediaId,
                'filename' => $media ? basename($media->path) : 'unknown',
                'status' => 'pending',
            ]);
        }

        // Upload nur einreihen (asynchron), damit der Request/UI nicht blockiert.
        UploadMediaToDestinationJob::dispatch($destination->id, $mediaIds, (int) $run->id);

        $skippedAlreadyUploadedCount = $alreadyUploadedMediaIds->count();
        $modeNote = $forceReupload ? ' (Re-Upload erzwungen)' : '';

        return redirect()
            ->route('admin.news.send', $newsItem)
            ->with('status', 'FTP-/SFTP-Upload wurde gestartet'.$modeNote.' (Run #'.$run->id.', '.count($mediaIds).' Datei(en), '.$skippedAlreadyUploadedCount.' bereits erfolgreich zuvor hochgeladen und daher übersprungen). Du kannst jetzt normal weiterarbeiten.');
    }

    /**
     * Sofortversand eines einzelnen Mediums (z. B. Bild) aus der Medienbearbeitung.
     */
    public function quickMediaSend(Request $request, NewsItem $newsItem, int $mediaId): RedirectResponse
    {
        $this->abortIfCannotManageNewsItem($newsItem);
        $selectedBrandId = $this->selectedAdminBrandId($request);

        $medium = $newsItem->media()->findOrFail($mediaId);

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
        $singleBytes = $this->resolveAttachmentSizeBytes($originalAttachmentPath);
        if (! is_int($singleBytes) || $singleBytes <= 0) {
            return back()->with('error', 'Die Dateigröße des Originalbilds konnte nicht ermittelt werden.');
        }
        if ($singleBytes > self::QUICK_SEND_MAX_MAIL_BYTES) {
            return back()->with('error', 'Dieses Bild ist zu groß für den Sofortversand ('.$this->bytesToMbString($singleBytes).' MB > 9,99 MB).');
        }

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

            if (! auth()->user()?->canSendToOrganization((int) $destination->organization_id)) {
                return back()->with('error', 'Du darfst an dieses Versandziel nicht versenden.');
            }

            if (! $medium->isVisibleForFtpDestination((int) $destination->organization_id)) {
                return back()->with('error', 'Dieses Medium ist für die Ziel-Organisation nicht freigegeben.');
            }

            $toAddresses = $destination->getEmailToAddresses();
            if (empty($toAddresses)) {
                return back()->with('error', 'Das Versandziel hat keine E-Mail-Empfänger (To) konfiguriert.');
            }

            $delivery = Delivery::create([
                'news_item_id' => $newsItem->id,
                'recipient_email' => $toAddresses[0],
                'expires_at' => now()->addHours(48),
                'created_by' => auth()->id(),
                'allowed_organization_id' => $destination->organization_id,
            ]);

            $mail = new QuickMediaDeliveryMail(
                $newsItem,
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

            if (auth()->user()?->hasDeliveryOrganizationRestriction()) {
                return back()->with('error', 'Direktversand per Einzel-E-Mail ist für deinen Benutzer deaktiviert.');
            }

            foreach ($recipientEmails as $recipientEmail) {
                $delivery = Delivery::create([
                    'news_item_id' => $newsItem->id,
                    'recipient_email' => $recipientEmail,
                    'expires_at' => now()->addHours(48),
                    'created_by' => auth()->id(),
                ]);

                Mail::to($recipientEmail)->send(
                    new QuickMediaDeliveryMail(
                        $newsItem,
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

    public function send(Request $request, NewsItem $newsItem): RedirectResponse
    {
        $this->abortIfCannotManageNewsItem($newsItem);
        $selectedBrandId = $this->selectedAdminBrandId($request);

        if ($newsItem->blocksAdminSendWithoutMoid()) {
            return redirect()
                ->route('admin.news.edit', $newsItem)
                ->with('error', 'Bei einem WDR-Job muss eine MoID eingetragen werden. Bitte unter „Nachricht“ das Feld „MoID“ ausfüllen oder „Kein WDR-Job“ ankreuzen.');
        }

        $destinationIds = collect((array) $request->input('delivery_destination_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();
        $recipientEmailRaw = trim((string) $request->input('recipient_email'));
        $recipientEmails = collect(explode(',', $recipientEmailRaw))
            ->map(fn ($email) => trim((string) $email))
            ->filter(fn ($email) => $email !== '')
            ->unique()
            ->values();

        if ($destinationIds->isEmpty() && $recipientEmails->isEmpty()) {
            return redirect()
                ->route('admin.news.send', $newsItem)
                ->withInput()
                ->with('error', 'Bitte mindestens ein Versandziel auswählen oder eine E-Mail-Adresse eintragen.');
        }

        $lastEmailCount = 0;
        // PATCH: add statements and updates support for news items
        $isUpdateDelivery = $newsItem->shouldTreatDispatchAsUpdate(
            $request->boolean('is_update_delivery'),
            $request->query('context')
        );
        $dispatchMedia = $newsItem->media()->get();
        $hasAnyDispatchMedia = $dispatchMedia->contains(fn (NewsItemMedia $m): bool => (bool) $m->versand);
        $allowsMediaMissingFirstReport = (bool) ($newsItem->planned_video_upload ?? false);
        if (! $isUpdateDelivery && ! $hasAnyDispatchMedia && ! $allowsMediaMissingFirstReport) {
            return redirect()
                ->route('admin.news.send', $newsItem)
                ->withInput()
                ->with('error', 'Erstmeldungen ohne versandfähige Medien sind gesperrt. Bitte zuerst mindestens ein Medium mit „Versand = Ja“ freigeben.');
        }
        $summary = app(NewsDeliveryUpdateSummaryService::class);
        $updateBaselineDeliveryAt = $isUpdateDelivery ? $summary->baselineAt($newsItem) : null;
        $updateNote = trim((string) $request->input('update_note', ''));
        if ($updateNote === '' && $isUpdateDelivery) {
            $updateNote = $summary->buildSummaryNote($newsItem, $updateBaselineDeliveryAt, true);
        }
        if (mb_strlen($updateNote) > 500) {
            $updateNote = mb_substr($updateNote, 0, 500);
        }
        $supportsUpdateDeliveryContextColumns = Schema::hasColumn('deliveries', 'is_update_delivery')
            && Schema::hasColumn('deliveries', 'update_baseline_delivery_at');
        $deliveryPhase = $newsItem->resolveDeliveryPhase($isUpdateDelivery);

        if ($destinationIds->isNotEmpty()) {
            $destinations = DeliveryDestination::whereIn('id', $destinationIds)
                ->where('type', 'email')
                ->where('active', true)
                ->with('organization')
                ->get()
                ->keyBy('id');

            if ($destinations->count() !== $destinationIds->count()) {
                return redirect()
                    ->route('admin.news.send', $newsItem)
                    ->withInput()
                    ->with('error', 'Mindestens eines der gewählten Versandziele ist ungültig oder inaktiv.');
            }

            if ($selectedBrandId !== null && Schema::hasTable('organizations') && Schema::hasColumn('organizations', 'brand_id')) {
                foreach ($destinations as $destination) {
                    $destinationOrgBrandId = (int) ($destination->organization?->brand_id ?? 0);
                    if ($destinationOrgBrandId !== $selectedBrandId) {
                        return redirect()
                            ->route('admin.news.send', $newsItem)
                            ->withInput()
                            ->with('error', 'Mindestens ein gewähltes Versandziel gehört nicht zum aktuell ausgewählten Brand.');
                    }
                }
            }

            foreach ($destinations as $destination) {
                if (! auth()->user()?->canSendToOrganization((int) $destination->organization_id)) {
                    return redirect()
                        ->route('admin.news.send', $newsItem)
                        ->withInput()
                        ->with('error', 'Mindestens ein gewähltes Versandziel ist für deinen Benutzer nicht freigegeben.');
                }
            }

            foreach ($destinationIds as $destinationId) {
                /** @var \App\Models\DeliveryDestination $destination */
                $destination = $destinations->get($destinationId);
                $toAddresses = $destination->getEmailToAddresses();
                if (empty($toAddresses)) {
                    return redirect()
                        ->route('admin.news.send', $newsItem)
                        ->withInput()
                        ->with('error', 'Das Versandziel „'.$destination->label.'“ hat keine E-Mail-Empfänger (To) konfiguriert.');
                }
                if (! $isUpdateDelivery) {
                    $hasVisibleMediaForDestination = $dispatchMedia->contains(
                        fn (NewsItemMedia $m): bool => $m->isVisibleForFtpDestination((int) $destination->organization_id)
                    );
                    if (! $hasVisibleMediaForDestination && ! $allowsMediaMissingFirstReport) {
                        return redirect()
                            ->route('admin.news.send', $newsItem)
                            ->withInput()
                            ->with('error', 'Erstmeldung an „'.$destination->label.'“ blockiert: Für dieses Ziel ist kein versandfähiges Medium freigegeben.');
                    }
                }

                if ($newsItem->hasMoidRestriction()) {
                    $names = config('newsdesk.wdr_organization_names', ['WDR', 'Westdeutscher Rundfunk']);
                    $org = $destination->organization;
                    if (! $org || ! collect($names)->contains(fn ($n) => stripos($org->name, $n) !== false)) {
                        return redirect()
                            ->route('admin.news.send', $newsItem)
                            ->withInput()
                            ->with('error', 'Diese Meldung (MoID) darf nur an WDR-Versandziele versendet werden. Ungültig: „'.($org->name ?? $destination->label).'“.');
                    }
                }

                $delivery = Delivery::create([
                    'news_item_id' => $newsItem->id,
                    'recipient_email' => $toAddresses[0],
                    'expires_at' => now()->addHours(48),
                    'created_by' => auth()->id(),
                    'allowed_organization_id' => $destination->organization_id,
                    ...($supportsUpdateDeliveryContextColumns ? [
                        'is_update_delivery' => $isUpdateDelivery,
                        'update_baseline_delivery_at' => $updateBaselineDeliveryAt,
                    ] : []),
                ]);

                $deliveryUrl = URL::temporarySignedRoute('delivery.show', now()->addHours(48), ['token' => $delivery->token]);

                $mail = new NewsDeliveryMail(
                    $newsItem,
                    $delivery,
                    $deliveryUrl,
                    $destination,
                    $isUpdateDelivery,
                    $deliveryPhase,
                    $updateNote !== '' ? $updateNote : null
                );
                Mail::to($toAddresses)
                    ->cc($destination->getEmailCcAddresses())
                    ->bcc($destination->getEmailBccAddresses())
                    ->send($mail);

                $lastEmailCount += count($toAddresses);
            }
        }

        if ($recipientEmails->isNotEmpty()) {
            $validator = Validator::make(
                ['recipient_emails' => $recipientEmails->all()],
                ['recipient_emails.*' => ['required', 'email']]
            );
            if ($validator->fails()) {
                return redirect()
                    ->route('admin.news.send', $newsItem)
                    ->withInput()
                    ->withErrors(['recipient_email' => 'Bitte nur gültige E-Mail-Adressen eingeben (mehrere mit Komma trennen).']);
            }

            if (auth()->user()?->hasDeliveryOrganizationRestriction()) {
                return redirect()
                    ->route('admin.news.send', $newsItem)
                    ->withInput()
                    ->with('error', 'Direktversand per Einzel-E-Mail ist für deinen Benutzer deaktiviert. Bitte nutze ein freigegebenes Versandziel.');
            }

            if ($newsItem->hasMoidRestriction()) {
                $guard = app(WdrRecipientGuard::class);
                foreach ($recipientEmails as $recipientEmail) {
                    if (! $guard->isWdrRecipient($recipientEmail)) {
                        return redirect()
                            ->route('admin.news.send', $newsItem)
                            ->withInput()
                            ->with('error', 'Diese Nachricht hat eine MoID (WDR-Job) und darf nur an den Westdeutschen Rundfunk (WDR) versendet werden. Mindestens ein Empfänger ist kein zulässiger WDR-Empfänger.');
                    }
                }
            }

            foreach ($recipientEmails as $recipientEmail) {
                if (! $isUpdateDelivery) {
                    $hasVisibleMediaForDirectEmail = $dispatchMedia->contains(
                        fn (NewsItemMedia $m): bool => $m->isVisibleForFtpDestination(null)
                    );
                    if (! $hasVisibleMediaForDirectEmail && ! $allowsMediaMissingFirstReport) {
                        return redirect()
                            ->route('admin.news.send', $newsItem)
                            ->withInput()
                            ->with('error', 'Erstmeldung per Direkt-E-Mail blockiert: Es ist kein versandfähiges Medium für den Versand freigegeben.');
                    }
                }
                $delivery = Delivery::create([
                    'news_item_id' => $newsItem->id,
                    'recipient_email' => $recipientEmail,
                    'expires_at' => now()->addHours(48),
                    'created_by' => auth()->id(),
                    ...($supportsUpdateDeliveryContextColumns ? [
                        'is_update_delivery' => $isUpdateDelivery,
                        'update_baseline_delivery_at' => $updateBaselineDeliveryAt,
                    ] : []),
                ]);

                $deliveryUrl = URL::temporarySignedRoute('delivery.show', now()->addHours(48), ['token' => $delivery->token]);
                Mail::to($recipientEmail)->send(new NewsDeliveryMail(
                    $newsItem,
                    $delivery,
                    $deliveryUrl,
                    null,
                    $isUpdateDelivery,
                    $deliveryPhase,
                    $updateNote !== '' ? $updateNote : null
                ));

                $lastEmailCount++;
            }
        }

        if ($lastEmailCount > 0) {
            $newsItem->promoteFromFirstReportToUpdateAfterDispatch();
        }

        return redirect()
            ->route('admin.news.send-summary', [
                'newsItem' => $newsItem,
                'last_email_count' => $lastEmailCount,
            ]);
    }

    /**
     * Zusammenfassungsseite nach Versand: zeigt, wohin die Nachricht gesendet wurde,
     * und ermöglicht eine schnelle Entscheidung zur Portal-Sichtbarkeit.
     */
    public function summary(Request $request, NewsItem $newsItem): View
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        $lastEmailCount = (int) $request->query('last_email_count', 0);
        $lastFtpFiles = (int) $request->query('last_ftp_files', 0);

        $totalEmailDeliveries = $newsItem->deliveries()->count();

        $ftpRunsQuery = DeliveryRun::query()
            ->whereHas('items.media', function ($q) use ($newsItem) {
                $q->where('news_item_id', $newsItem->id);
            });

        $totalFtpRuns = (clone $ftpRunsQuery)->distinct('delivery_runs.id')->count('delivery_runs.id');

        $ftpItemsQuery = DeliveryRunItem::query()
            ->whereHas('media', function ($q) use ($newsItem) {
                $q->where('news_item_id', $newsItem->id);
            });

        // Summe aller Zeilen: ein Eintrag pro Medium und Lauf (mehrere Läufe/Ziele = höhere Zahl).
        $totalFtpRunItems = (clone $ftpItemsQuery)->count();

        // Eindeutige Medien-Assets dieser Meldung, die in mindestens einem Lauf vorkamen.
        $totalFtpDistinctMedia = (clone $ftpItemsQuery)
            ->whereNotNull('news_item_media_id')
            ->distinct()
            ->count('news_item_media_id');

        $totalFtpSuccessfulItems = (clone $ftpItemsQuery)->where('status', 'success')->count();

        $isCurrentlyPublic = $newsItem->status === 'published'
            && $newsItem->published_at !== null
            && $newsItem->published_at->isPast()
            && ($newsItem->embargo_at === null || $newsItem->embargo_at->isPast());

        return view('admin.news.send-summary', [
            'newsItem' => $newsItem,
            'lastEmailCount' => $lastEmailCount,
            'lastFtpFiles' => $lastFtpFiles,
            'totalEmailDeliveries' => $totalEmailDeliveries,
            'totalFtpRuns' => $totalFtpRuns,
            'totalFtpRunItems' => $totalFtpRunItems,
            'totalFtpDistinctMedia' => $totalFtpDistinctMedia,
            'totalFtpSuccessfulItems' => $totalFtpSuccessfulItems,
            'isCurrentlyPublic' => $isCurrentlyPublic,
        ]);
    }

    /**
     * Portal-Sichtbarkeit nach Versand setzen und zurück zur Übersicht springen.
     */
    public function applySummary(Request $request, NewsItem $newsItem): RedirectResponse
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        $data = $request->validate([
            'portal_visibility' => ['required', 'in:published,hidden'],
        ]);

        if ($data['portal_visibility'] === 'published') {
            $newsItem->status = 'published';
            if (! $newsItem->published_at) {
                $newsItem->published_at = now();
            }
        } else {
            $newsItem->status = 'draft';
            $newsItem->published_at = null;
        }

        $newsItem->save();

        return redirect()
            ->route('admin.news.index')
            ->with('status', 'Versand abgeschlossen. Portal-Sichtbarkeit wurde aktualisiert.');
    }

    private function abortIfCannotManageNewsItem(NewsItem $newsItem): void
    {
        $user = auth()->user();
        if ($user && $user->hasRole('admin')) {
            return;
        }

        if ((int) $newsItem->author_id !== (int) auth()->id()) {
            abort(403, 'Sie dürfen nur eigene Beiträge bearbeiten.');
        }
    }

    /**
     * FTP-Auswahl nach Brand:
     * - koelnimage: Bilder
     * - sonst: Videos und Bilder (gemischte Redaktions-Workflows)
     *
     * @return list<string>
     */
    private function ftpMediaTypesForNewsItem(NewsItem $newsItem): array
    {
        $newsItem->loadMissing('brand');
        $brandKey = (string) ($newsItem->brand?->key ?? '');
        if ($brandKey === 'koelnimage') {
            return ['image'];
        }

        return ['video', 'image'];
    }

    /**
     * @param  list<string>  $types
     * @return array{singular: string, plural: string}
     */
    private function ftpMediaTypeLabels(array $types): array
    {
        if ($types === ['image']) {
            return [
                'singular' => 'Bild',
                'plural' => 'Bilder',
            ];
        }

        if ($types === ['video']) {
            return [
                'singular' => 'Video',
                'plural' => 'Videos',
            ];
        }

        return [
            'singular' => 'Medium',
            'plural' => 'Medien',
        ];
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

    private function resolveAttachmentSizeBytes(string $relativePath): ?int
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
}
