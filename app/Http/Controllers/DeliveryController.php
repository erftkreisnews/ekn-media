<?php

namespace App\Http\Controllers;

use App\Mail\DeliveryDownloadNotificationMail;
use App\Models\Contact;
use App\Models\Delivery;
use App\Models\DeliveryEvent;
use App\Models\NewsItemMedia;
use App\Models\Organization;
use App\Models\Product;
use App\Models\RecipientConfirmation;
use App\Services\ImageMetadataReader;
use App\Services\ImageMetadataWriter;
use App\Services\MediaStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeliveryController extends Controller
{
    private function findDelivery(string $token): ?Delivery
    {
        return Delivery::where('token', $token)->first();
    }

    /**
     * Keyed Hash (HMAC) für IP und User-Agent – DSGVO-konform pseudonymisiert.
     * APP_KEY als HMAC-Key verhindert Re-Identifizierung ohne Schlüssel; Aufbewahrungsfristen siehe Dokumentation.
     */
    private function hashIpUa(): array
    {
        $key = config('app.key');
        $ip = request()->ip() ?? '';
        $ua = request()->userAgent() ?? '';

        return [
            'ip_hash' => hash_hmac('sha256', $ip, $key),
            'ua_hash' => hash_hmac('sha256', $ua, $key),
        ];
    }

    private function ensureValidDelivery(Delivery $delivery): void
    {
        if ($delivery->isRevoked()) {
            abort(410, 'Dieser Versand wurde widerrufen.');
        }
        if ($delivery->isExpired()) {
            abort(410, 'Dieser Link ist abgelaufen.');
        }
    }

    private function touchAccess(Delivery $delivery, bool $logOpened = false): void
    {
        $now = now();
        $delivery->last_access_at = $now;
        if ($delivery->first_opened_at === null) {
            $delivery->first_opened_at = $now;
        }
        $hashes = $this->hashIpUa();
        if ($delivery->ip_hash === null) {
            $delivery->ip_hash = $hashes['ip_hash'];
            $delivery->ua_hash = $hashes['ua_hash'];
        }
        $delivery->save();

        if ($logOpened) {
            $hadOpened = DeliveryEvent::where('delivery_id', $delivery->id)->where('event_type', 'opened')->exists();
            if (! $hadOpened) {
                DeliveryEvent::create([
                    'delivery_id' => $delivery->id,
                    'event_type' => 'opened',
                    'ip_hash' => $hashes['ip_hash'],
                    'ua_hash' => $hashes['ua_hash'],
                    'created_at' => $now,
                ]);
            }
        }
    }

    public function show(string $token): View|RedirectResponse
    {
        $delivery = $this->findDelivery($token);
        if (! $delivery) {
            abort(404, 'Versand nicht gefunden.');
        }
        $this->ensureValidDelivery($delivery);
        $this->touchAccess($delivery, true);

        // Komfort: Wenn Empfänger die Redaktion/Produkt-Angaben bereits innerhalb der letzten 6 Monate bestätigt hat,
        // bestätigen wir diesen Versand automatisch und zeigen den Hinweis nicht erneut.
        if ($delivery->confirmed_at === null) {
            $now = now();
            $email = (string) $delivery->recipient_email;

            $q = RecipientConfirmation::query()
                ->where('email', $email)
                ->where('confirmed_until', '>', $now);

            if ($delivery->allowed_organization_id) {
                $q->where('organization_id', (int) $delivery->allowed_organization_id)
                    ->whereNotNull('product_id');
            }

            $conf = $q->orderByDesc('last_confirmed_at')->first();
            if ($conf) {
                $hashes = $this->hashIpUa();
                $delivery->confirmed_at = $now;
                if ($conf->organization_id) {
                    $delivery->organization_id = $conf->organization_id;
                    $delivery->product_id = $conf->product_id;
                    $delivery->self_reported_organization_name = null;
                    $delivery->self_reported_product_name = null;
                } else {
                    $delivery->self_reported_organization_name = $conf->self_reported_organization_name;
                    $delivery->self_reported_product_name = $conf->self_reported_product_name;
                }
                $delivery->save();

                DeliveryEvent::create([
                    'delivery_id' => $delivery->id,
                    'event_type' => 'confirmed',
                    'organization_id' => $delivery->organization_id,
                    'product_id' => $delivery->product_id,
                    'self_reported_organization_name' => $delivery->self_reported_organization_name,
                    'self_reported_product_name' => $delivery->self_reported_product_name,
                    'ip_hash' => $hashes['ip_hash'],
                    'ua_hash' => $hashes['ua_hash'],
                    'created_at' => $now,
                ]);
            }
        }

        $newsItem = $delivery->newsItem;
        $newsItem->load(['media']);

        if ($delivery->allowed_organization_id) {
            $organizations = Organization::where('id', $delivery->allowed_organization_id)->orderBy('name')->get();
            $productsByOrg = Product::where('organization_id', $delivery->allowed_organization_id)->get()->groupBy('organization_id');
        } else {
            $organizations = collect();
            $productsByOrg = collect();
        }

        $allowedMedia = $newsItem->media->where('versand', true)->values();
        $downloadUrls = [];
        $streamUrls = [];
        $mediaStorage = app(MediaStorage::class);
        $streamExpiry = now()->addMinutes(10);
        foreach ($allowedMedia as $media) {
            $downloadUrls[$media->id] = URL::temporarySignedRoute(
                'delivery.download',
                $streamExpiry,
                ['token' => $delivery->token, 'media' => $media->id]
            );
            if ($media->isVideo() || $media->isAudio()) {
                $relPath = $media->resolveDeliveryDownloadRelativePath();
                $presigned = $relPath
                    ? $mediaStorage->temporaryPlaybackUrlForPath($relPath, $streamExpiry)
                    : null;
                // S3: direkter Browser-Download vom Storage (schnell). Sonst Laravel-Stream (gleiche Origin).
                $streamUrls[$media->id] = $presigned ?? URL::temporarySignedRoute(
                    'delivery.stream',
                    $streamExpiry,
                    ['token' => $delivery->token, 'media' => $media->id]
                );
            }
        }

        $confirmUrl = URL::temporarySignedRoute(
            'delivery.confirm',
            now()->addHours(48),
            ['token' => $delivery->token]
        );

        return view('deliveries.show', [
            'delivery' => $delivery,
            'newsItem' => $newsItem,
            'organizations' => $organizations,
            'productsByOrg' => $productsByOrg,
            'allowedMedia' => $allowedMedia,
            'downloadUrls' => $downloadUrls,
            'streamUrls' => $streamUrls,
            'confirmUrl' => $confirmUrl,
        ]);
    }

    public function confirm(Request $request, string $token): RedirectResponse
    {
        $delivery = $this->findDelivery($token);
        if (! $delivery) {
            abort(404, 'Versand nicht gefunden.');
        }
        $this->ensureValidDelivery($delivery);
        $this->touchAccess($delivery, false);

        $hashes = $this->hashIpUa();

        if ($delivery->allowed_organization_id) {
            $organizationId = (int) $request->input('organization_id');
            $productId = (int) $request->input('product_id');
            if ((int) $delivery->allowed_organization_id !== $organizationId) {
                return redirect()->route('delivery.show', ['token' => $token])
                    ->with('error', 'Bitte wählen Sie eine der angezeigten Redaktionen.')
                    ->withInput();
            }
            $product = Product::where('id', $productId)->where('organization_id', $organizationId)->first();
            if (! $product) {
                return redirect()->route('delivery.show', ['token' => $token])
                    ->with('error', 'Bitte wählen Sie eine gültige Redaktion und ein gültiges Produkt.')
                    ->withInput();
            }
            $delivery->organization_id = $organizationId;
            $delivery->product_id = $productId;
            $delivery->confirmed_at = now();
            $delivery->save();

            DeliveryEvent::create([
                'delivery_id' => $delivery->id,
                'event_type' => 'confirmed',
                'organization_id' => $organizationId,
                'product_id' => $productId,
                'ip_hash' => $hashes['ip_hash'],
                'ua_hash' => $hashes['ua_hash'],
                'created_at' => now(),
            ]);

            if ($request->boolean('save_as_recipient')) {
                Contact::firstOrCreate(
                    [
                        'organization_id' => $organizationId,
                        'email' => $delivery->recipient_email,
                    ],
                    [
                        'product_id' => $productId,
                        'name' => $delivery->recipient_email,
                    ]
                );
            }

            RecipientConfirmation::updateOrCreate(
                [
                    'email' => $delivery->recipient_email,
                    'organization_id' => $organizationId,
                    'product_id' => $productId,
                ],
                [
                    'last_confirmed_at' => now(),
                    'confirmed_until' => now()->addMonthsNoOverflow(6),
                    'self_reported_organization_name' => null,
                    'self_reported_product_name' => null,
                ]
            );
        } else {
            $orgName = trim((string) $request->input('self_reported_organization_name', ''));
            $productName = trim((string) $request->input('self_reported_product_name', ''));
            if ($orgName === '' || $productName === '') {
                return redirect()->route('delivery.show', ['token' => $token])
                    ->with('error', 'Bitte tragen Sie Medienhaus (Redaktion) und Format (Produkt) ein.')
                    ->withInput();
            }
            $delivery->self_reported_organization_name = $orgName;
            $delivery->self_reported_product_name = $productName;
            $delivery->confirmed_at = now();
            $delivery->save();

            DeliveryEvent::create([
                'delivery_id' => $delivery->id,
                'event_type' => 'confirmed',
                'self_reported_organization_name' => $orgName,
                'self_reported_product_name' => $productName,
                'ip_hash' => $hashes['ip_hash'],
                'ua_hash' => $hashes['ua_hash'],
                'created_at' => now(),
            ]);

            if ($request->boolean('save_as_recipient')) {
                $org = Organization::firstOrCreate(
                    ['name' => $orgName],
                    ['name' => $orgName]
                );
                $product = Product::firstOrCreate(
                    [
                        'organization_id' => $org->id,
                        'name' => $productName,
                    ],
                    ['name' => $productName]
                );
                $delivery->organization_id = $org->id;
                $delivery->product_id = $product->id;
                $delivery->save();
                Contact::firstOrCreate(
                    [
                        'organization_id' => $org->id,
                        'email' => $delivery->recipient_email,
                    ],
                    [
                        'product_id' => $product->id,
                        'name' => $delivery->recipient_email,
                    ]
                );
            }

            RecipientConfirmation::updateOrCreate(
                [
                    'email' => $delivery->recipient_email,
                    'organization_id' => $delivery->organization_id,
                    'product_id' => $delivery->product_id,
                    'self_reported_organization_name' => $orgName,
                    'self_reported_product_name' => $productName,
                ],
                [
                    'last_confirmed_at' => now(),
                    'confirmed_until' => now()->addMonthsNoOverflow(6),
                ]
            );
        }

        return redirect()->route('delivery.show', ['token' => $token])
            ->with('status', 'Vielen Dank. Sie können nun die Medien herunterladen.');
    }

    /**
     * Inline-Stream für Video/Audio auf der Medienpaket-Seite (Same-Origin, Range-fähig).
     * Kein delivery_events „download“ – sonst würde jeder Range-Chunk zählen.
     * Signiert wie delivery.download (Middleware „signed“).
     */
    public function stream(string $token, int $media): StreamedResponse|BinaryFileResponse
    {
        $delivery = $this->findDelivery($token);
        if (! $delivery) {
            abort(404, 'Versand nicht gefunden.');
        }
        $this->ensureValidDelivery($delivery);
        $this->touchAccess($delivery, false);

        $mediaModel = $delivery->newsItem->media()->where('id', $media)->where('versand', true)->first();
        if (! $mediaModel) {
            abort(404, 'Medium nicht gefunden oder nicht freigegeben.');
        }
        if (! $mediaModel->isVideo() && ! $mediaModel->isAudio()) {
            abort(404);
        }

        $path = $mediaModel->resolveDeliveryDownloadRelativePath();
        if ($path === null) {
            abort(404, 'Medium derzeit nicht freigegeben.');
        }

        $mediaStorage = app(MediaStorage::class);
        $disk = $mediaStorage->activeDisk();
        if (! $disk->exists($path) && $mediaStorage->fallbackDiskName() !== $mediaStorage->activeDiskName()) {
            $disk = $mediaStorage->fallbackDisk();
        }
        if (! $disk->exists($path)) {
            abort(404, 'Datei nicht gefunden.');
        }

        $filename = $mediaModel->original_name ?: basename($path);

        return $disk->response($path, $filename, [
            'Accept-Ranges' => 'bytes',
        ]);
    }

    public function download(string $token, int $media): BinaryFileResponse|RedirectResponse
    {
        $delivery = $this->findDelivery($token);
        if (! $delivery) {
            abort(404, 'Versand nicht gefunden.');
        }
        $this->ensureValidDelivery($delivery);
        $this->touchAccess($delivery, false);

        $mediaModel = $delivery->newsItem->media()->where('id', $media)->where('versand', true)->first();
        if (! $mediaModel) {
            abort(404, 'Medium nicht gefunden oder nicht freigegeben.');
        }

        $path = $mediaModel->resolveDeliveryDownloadRelativePath();
        if ($path === null) {
            abort(404, 'Medium derzeit nicht freigegeben.');
        }
        $resolved = app(MediaStorage::class)->resolveReadableLocalPath($path);
        $fullPath = $resolved['path'] ?? null;
        if (! is_string($fullPath) || ! is_file($fullPath)) {
            abort(404, 'Datei nicht gefunden.');
        }

        // Erst nach erfolgreicher Auflösung: Aktivität + optionale Benachrichtigungs-Mail (echter Download)
        $hashes = $this->hashIpUa();
        $this->recordDeliveryDownloadEvent($delivery, $mediaModel, $hashes);

        // Sicherstellen, dass Bild-Metadaten in der ausgelieferten Datei eingebettet sind
        if (method_exists($mediaModel, 'isImage') && $mediaModel->isImage()) {
            $written = ImageMetadataWriter::write($fullPath, [
                'image_title' => $mediaModel->image_title,
                'photographer' => $mediaModel->photographer,
                'caption' => $mediaModel->caption,
                'credit' => config('newsdesk.iptc_credit', 'Erftkreis News'),
                'copyright' => config('newsdesk.iptc_copyright', '© Erftkreis News. Alle Rechte vorbehalten.'),
            ]);

            // Debug-Log: Welche IPTC-Daten liegen direkt vor dem Download in der Datei?
            $debugMeta = ImageMetadataReader::read($fullPath);
            Log::info('delivery.download.iptc', [
                'delivery_id' => $delivery->id,
                'media_id' => $mediaModel->id,
                'path' => $path,
                'writer_ok' => $written,
                'iptc' => $debugMeta,
            ]);
        }

        $downloadName = $mediaModel->original_name ?: basename($path);

        $response = response()->download($fullPath, $downloadName);
        if (($resolved['temporary'] ?? false) === true) {
            $response->deleteFileAfterSend(true);
        }

        return $response;
    }

    /**
     * Download in delivery_events protokollieren – optional Deduplizierung gegen Doppelklicks
     * (gleicher Versand + gleiche Datei innerhalb weniger Sekunden).
     *
     * @param  array{ip_hash: string, ua_hash: string}  $hashes
     */
    private function recordDeliveryDownloadEvent(Delivery $delivery, NewsItemMedia $mediaModel, array $hashes): void
    {
        $dedupeSec = (int) config('newsdesk.delivery_download_dedupe_seconds', 5);
        if ($dedupeSec > 0) {
            $duplicate = DeliveryEvent::query()
                ->where('delivery_id', $delivery->id)
                ->where('event_type', 'download')
                ->where('media_id', $mediaModel->id)
                ->where('created_at', '>=', now()->subSeconds($dedupeSec))
                ->exists();
            if ($duplicate) {
                return;
            }
        }

        $payload = [
            'delivery_id' => $delivery->id,
            'event_type' => 'download',
            'media_id' => $mediaModel->id,
            'organization_id' => $delivery->organization_id,
            'product_id' => $delivery->product_id,
            'ip_hash' => $hashes['ip_hash'],
            'ua_hash' => $hashes['ua_hash'],
            'created_at' => now(),
        ];

        // Snapshot-Spalten nur, wenn Migrationen auf der DB gelaufen sind (sonst 42S22).
        if (Schema::hasColumn('delivery_events', 'download_media_type')) {
            $payload['download_media_type'] = $mediaModel->type;
            $payload['download_media_label'] = $mediaModel->delivery_activity_label;
            $payload['download_media_file_name'] = $mediaModel->delivery_activity_stored_file_name;
        }

        DeliveryEvent::create($payload);

        $notifyEmails = config('newsdesk.delivery_download_notify_emails', []);
        if ($notifyEmails !== []) {
            try {
                Mail::to($notifyEmails)->queue(new DeliveryDownloadNotificationMail($delivery, $mediaModel));
            } catch (\Throwable $e) {
                Log::warning('delivery.download.notify_enqueue_failed', [
                    'delivery_id' => $delivery->id,
                    'media_id' => $mediaModel->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
