<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\UploadMediaToDestinationJob;
use App\Mail\NewsDeliveryMail;
use App\Models\Contact;
use App\Models\Delivery;
use App\Models\DeliveryDestination;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunItem;
use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use App\Services\WdrRecipientGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class NewsDeliveryController extends Controller
{
    /**
     * Versand vorbereiten: Versandziele (E-Mail) der Organisationen oder Einzelempfänger.
     * Bei WDR-Job mit MoID: nur WDR-Versandziele bzw. WDR-Empfänger zulässig.
     */
    public function prepareSend(NewsItem $newsItem): View|RedirectResponse
    {
        if ($newsItem->isWdrJob() && empty(trim((string) $newsItem->moid))) {
            return redirect()
                ->route('admin.news.edit', $newsItem)
                ->with('error', 'Bei einem WDR-Job muss eine MoID eingetragen werden (wichtig für WDR-Abrechnung). Bitte unter „Nachricht“ das Feld „MoID“ ausfüllen oder „Kein WDR-Job“ ankreuzen.');
        }

        $defaultEmail = config('newsdesk.delivery_recipient', '');

        $destinationsQuery = DeliveryDestination::where('type', 'email')
            ->where('active', true)
            ->with('organization')
            ->orderBy('label');

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

        return view('admin.news.prepare-send', [
            'newsItem' => $newsItem,
            'destinations' => $destinations,
            'destinationsByOrg' => $destinationsByOrg,
            'ftpDestinations' => $ftpDestinations,
            'ftpDestinationsByOrg' => $ftpDestinationsByOrg,
            'contacts' => $contacts,
            'defaultEmail' => $defaultEmail,
            'onlyWdrAllowed' => $newsItem->hasMoidRestriction(),
        ]);
    }

    /**
     * FTP-/SFTP-Upload für alle versand-markierten Medien dieser Nachricht einreihen.
     */
    public function queueFtp(Request $request, NewsItem $newsItem): RedirectResponse
    {
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

        if ($newsItem->hasMoidRestriction()) {
            $names = config('newsdesk.wdr_organization_names', ['WDR', 'Westdeutscher Rundfunk']);
            $org = $destination->organization;
            if (! $org || ! collect($names)->contains(fn ($n) => stripos($org->name, $n) !== false)) {
                return redirect()
                    ->route('admin.news.send', $newsItem)
                    ->with('error', 'Diese Meldung (MoID) darf nur an WDR-Versandziele versendet werden.');
            }
        }

        // Alle Medien dieser Nachricht, die für Versand markiert sind
        $mediaIds = NewsItemMedia::where('news_item_id', $newsItem->id)
            ->where('versand', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (empty($mediaIds)) {
            return redirect()
                ->route('admin.news.send', $newsItem)
                ->with('error', 'Für diese Nachricht sind keine Medien mit „Versand = Ja“ markiert.');
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

        // Upload für diese Nachricht sofort ausführen, damit der Nutzer nicht auf einen Cron-Worker warten muss.
        UploadMediaToDestinationJob::dispatchSync($destination->id, $mediaIds, (int) $run->id);

        return redirect()
            ->route('admin.news.send-summary', [
                'newsItem' => $newsItem,
                'last_ftp_files' => count($mediaIds),
                'last_ftp_run' => $run->id,
            ]);
    }

    public function send(Request $request, NewsItem $newsItem): RedirectResponse
    {
        if ($newsItem->isWdrJob() && empty(trim((string) $newsItem->moid))) {
            return redirect()
                ->route('admin.news.edit', $newsItem)
                ->with('error', 'Bei einem WDR-Job muss eine MoID eingetragen werden. Bitte unter „Nachricht“ das Feld „MoID“ ausfüllen oder „Kein WDR-Job“ ankreuzen.');
        }

        $destinationIds = collect((array) $request->input('delivery_destination_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();
        $recipientEmail = trim((string) $request->input('recipient_email'));

        if ($destinationIds->isEmpty() && $recipientEmail === '') {
            return redirect()
                ->route('admin.news.send', $newsItem)
                ->withInput()
                ->with('error', 'Bitte mindestens ein Versandziel auswählen oder eine E-Mail-Adresse eintragen.');
        }

        $lastEmailCount = 0;

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
                ]);

                $deliveryUrl = URL::temporarySignedRoute('delivery.show', now()->addHours(48), ['token' => $delivery->token]);

                $mail = new NewsDeliveryMail($newsItem, $delivery, $deliveryUrl, $destination);
                Mail::to($toAddresses)
                    ->cc($destination->getEmailCcAddresses())
                    ->bcc($destination->getEmailBccAddresses())
                    ->send($mail);

                $lastEmailCount += count($toAddresses);
            }
        }

        if ($recipientEmail !== '') {
            $request->validate(['recipient_email' => ['required', 'email']]);

            if ($newsItem->hasMoidRestriction()) {
                $guard = app(WdrRecipientGuard::class);
                if (! $guard->isWdrRecipient($recipientEmail)) {
                    return redirect()
                        ->route('admin.news.send', $newsItem)
                        ->withInput()
                        ->with('error', 'Diese Nachricht hat eine MoID (WDR-Job) und darf nur an den Westdeutschen Rundfunk (WDR) versendet werden. Der gewählte Empfänger ist kein zulässiger WDR-Empfänger.');
                }
            }

            $delivery = Delivery::create([
                'news_item_id' => $newsItem->id,
                'recipient_email' => $recipientEmail,
                'expires_at' => now()->addHours(48),
                'created_by' => auth()->id(),
            ]);

            $deliveryUrl = URL::temporarySignedRoute('delivery.show', now()->addHours(48), ['token' => $delivery->token]);
            Mail::to($recipientEmail)->send(new NewsDeliveryMail($newsItem, $delivery, $deliveryUrl));

            $lastEmailCount++;
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
        $lastEmailCount = (int) $request->query('last_email_count', 0);
        $lastFtpFiles = (int) $request->query('last_ftp_files', 0);

        $totalEmailDeliveries = $newsItem->deliveries()->count();

        $ftpRunsQuery = DeliveryRun::query()
            ->whereHas('items.media', function ($q) use ($newsItem) {
                $q->where('news_item_id', $newsItem->id);
            });

        $totalFtpRuns = (clone $ftpRunsQuery)->distinct('delivery_runs.id')->count('delivery_runs.id');

        $totalFtpFiles = DeliveryRunItem::query()
            ->whereHas('media', function ($q) use ($newsItem) {
                $q->where('news_item_id', $newsItem->id);
            })
            ->count();

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
            'totalFtpFiles' => $totalFtpFiles,
            'isCurrentlyPublic' => $isCurrentlyPublic,
        ]);
    }

    /**
     * Portal-Sichtbarkeit nach Versand setzen und zurück zur Übersicht springen.
     */
    public function applySummary(Request $request, NewsItem $newsItem): RedirectResponse
    {
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
}
