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
use App\Services\DeliveryTimelineBuilder;
use App\Services\MediaStorage;
use App\Services\VideoMetadataXmpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
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

    public function show(string $token): Response|RedirectResponse
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
        // PATCH: add statements and updates support for news items
        $newsItem->load(['media', 'parentNewsItem']);
        if (Schema::hasTable('news_item_statements') && Schema::hasTable('news_item_updates')) {
            $newsItem->load([
                'statements' => fn ($q) => $q->latest('received_at')->latest('id'),
                'updates' => fn ($q) => $q->latest('happened_at')->latest('id'),
            ]);
        } else {
            $newsItem->setRelation('statements', collect());
            $newsItem->setRelation('updates', collect());
        }

        if ($delivery->allowed_organization_id) {
            $organizations = Organization::where('id', $delivery->allowed_organization_id)->orderBy('name')->get();
            $productsByOrg = Product::where('organization_id', $delivery->allowed_organization_id)->get()->groupBy('organization_id');
        } else {
            $organizations = collect();
            $productsByOrg = collect();
        }

        $allowedMedia = $newsItem->media
            ->where('versand', true)
            ->filter(fn (NewsItemMedia $m) => $m->isVisibleInDeliveryPackage($delivery))
            ->values();
        $newMediaIds = [];
        $newMediaCounts = [
            'images' => 0,
            'videos' => 0,
            'audios' => 0,
        ];
        if ($delivery->is_update_delivery) {
            $newMedia = $allowedMedia;
            if ($delivery->update_baseline_delivery_at) {
                $baseline = $delivery->update_baseline_delivery_at;
                $newMedia = $allowedMedia
                    ->filter(fn (NewsItemMedia $m) => $m->created_at && $m->created_at->gt($baseline))
                    ->values();
            }
            $newMediaIds = $newMedia->pluck('id')->map(fn ($id) => (int) $id)->all();
            $newMediaCounts = [
                'images' => $newMedia->where('type', 'image')->count(),
                'videos' => $newMedia->where('type', 'video')->count(),
                'audios' => $newMedia->where('type', 'audio')->count(),
            ];
            $allowedMedia = $allowedMedia
                ->sortByDesc(fn (NewsItemMedia $m) => in_array((int) $m->id, $newMediaIds, true))
                ->values();
        }
        $updateReferenceNewsId = $newsItem->parentNewsItem?->display_news_id ?? $newsItem->display_news_id;
        $rootNewsItemId = (int) ($newsItem->parent_news_item_id ?: $newsItem->id);
        $firstReportDelivery = Delivery::query()
            ->where('recipient_email', $delivery->recipient_email)
            ->where('news_item_id', $rootNewsItemId)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->first();
        $latestUpdateDelivery = Delivery::query()
            ->where('recipient_email', $delivery->recipient_email)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->whereHas('newsItem', function ($q) use ($rootNewsItemId) {
                $q->where('parent_news_item_id', $rootNewsItemId);
            })
            ->orderByDesc('created_at')
            ->first();
        $firstReportUrl = $firstReportDelivery && (string) $firstReportDelivery->id !== (string) $delivery->id
            ? route('delivery.show', ['token' => $firstReportDelivery->token])
            : null;
        $latestUpdateUrl = $latestUpdateDelivery && (string) $latestUpdateDelivery->id !== (string) $delivery->id
            ? route('delivery.show', ['token' => $latestUpdateDelivery->token])
            : null;
        $downloadUrls = [];
        $videoXmpDownloadUrls = [];
        $streamUrls = [];
        $mediaStorage = app(MediaStorage::class);
        $streamExpiry = now()->addMinutes(10);
        foreach ($allowedMedia as $media) {
            $downloadUrls[$media->id] = URL::temporarySignedRoute(
                'delivery.download',
                $streamExpiry,
                ['token' => $delivery->token, 'media' => $media->id]
            );
            $signedStreamUrl = URL::temporarySignedRoute(
                'delivery.stream',
                $streamExpiry,
                ['token' => $delivery->token, 'media' => $media->id]
            );
            if ($media->isImage()) {
                $streamUrls[$media->id] = $signedStreamUrl;
            }
            if ($media->isVideo() || $media->isAudio()) {
                $relPath = $media->resolveDeliveryDownloadRelativePath();
                $presigned = $relPath
                    ? $mediaStorage->temporaryPlaybackUrlForPath($relPath, $streamExpiry)
                    : null;
                // S3: direkter Browser-Download vom Storage (schnell). Sonst Laravel-Stream (gleiche Origin).
                $streamUrls[$media->id] = $presigned ?? $signedStreamUrl;
            }
            if ($media->isVideo()) {
                $videoXmpDownloadUrls[$media->id] = URL::temporarySignedRoute(
                    'delivery.download.video-xmp',
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

        $view = view('deliveries.show', [
            'delivery' => $delivery,
            'newsItem' => $newsItem,
            'organizations' => $organizations,
            'productsByOrg' => $productsByOrg,
            'allowedMedia' => $allowedMedia,
            'downloadUrls' => $downloadUrls,
            'videoXmpDownloadUrls' => $videoXmpDownloadUrls,
            'streamUrls' => $streamUrls,
            'confirmUrl' => $confirmUrl,
            'updateReferenceNewsId' => $updateReferenceNewsId,
            'firstReportUrl' => $firstReportUrl,
            'latestUpdateUrl' => $latestUpdateUrl,
            'newMediaIds' => $newMediaIds,
            'newMediaCounts' => $newMediaCounts,
            'deliveryTimeline' => app(DeliveryTimelineBuilder::class)->build($newsItem),
        ]);

        // Signed delivery links should never serve stale cached HTML.
        return response($view)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
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
        if (! $mediaModel->isVisibleInDeliveryPackage($delivery)) {
            abort(403, 'Dieses Medium ist für Ihre Redaktion nicht freigegeben.');
        }
        if (! $mediaModel->isVideo() && ! $mediaModel->isAudio() && ! $mediaModel->isImage()) {
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

        // Für Inline-Stream ausschließlich einen ASCII-sicheren Dateinamen verwenden.
        // original_name kann Umlaute/Sonderzeichen enthalten und so Header-Exceptions auslösen.
        $safeInlineName = basename($path);

        return $disk->response($path, $safeInlineName, [
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
        if (! $mediaModel->isVisibleInDeliveryPackage($delivery)) {
            abort(403, 'Dieses Medium ist für Ihre Redaktion nicht freigegeben.');
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

        // Sicherstellen, dass Bild-Metadaten in der ausgelieferten Datei eingebettet sind (inkl. UTF-8, Headline, Schlagwörter)
        if (method_exists($mediaModel, 'isImage') && $mediaModel->isImage()) {
            $mediaModel->loadMissing('newsItem');
            $written = ImageMetadataWriter::write($fullPath, $mediaModel->resolvedIptcForEmbed());

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

    public function downloadVideoXmp(string $token, int $media): BinaryFileResponse
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
        if (! $mediaModel->isVisibleInDeliveryPackage($delivery)) {
            abort(403, 'Dieses Medium ist für Ihre Redaktion nicht freigegeben.');
        }
        if (! $mediaModel->isVideo()) {
            abort(404, 'XMP ist nur für Video verfügbar.');
        }

        $xmpContent = app(VideoMetadataXmpService::class)->generateForVideoMedia($mediaModel);
        $videoName = $mediaModel->original_name ?: basename((string) $mediaModel->path);
        $baseName = pathinfo($videoName, PATHINFO_FILENAME);
        $downloadName = ($baseName !== '' ? $baseName : 'video').'.xmp';

        $tmpFile = tempnam(sys_get_temp_dir(), 'video-xmp-');
        if ($tmpFile === false) {
            abort(500, 'Temporäre XMP-Datei konnte nicht erstellt werden.');
        }

        file_put_contents($tmpFile, $xmpContent);

        return response()->download($tmpFile, $downloadName, [
            'Content-Type' => 'application/rdf+xml; charset=UTF-8',
        ])->deleteFileAfterSend(true);
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

        // Freitext-Angaben vom Versand mitspiegeln (z. B. wenn noch keine org/product-IDs gesetzt sind).
        if (Schema::hasColumn('delivery_events', 'self_reported_organization_name')) {
            $payload['self_reported_organization_name'] = $delivery->self_reported_organization_name;
            $payload['self_reported_product_name'] = $delivery->self_reported_product_name;
        }

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
