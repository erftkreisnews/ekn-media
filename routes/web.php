<?php

use App\Http\Controllers\Admin\AudioController;
use App\Http\Controllers\Admin\BackofficeController;
use App\Http\Controllers\Admin\BillingController;
use App\Http\Controllers\Admin\CustomerContactController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomerProductController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeliveryController as AdminDeliveryController;
use App\Http\Controllers\Admin\DeliveryDestinationController;
use App\Http\Controllers\Admin\NewsDeliveryController;
use App\Http\Controllers\Admin\NewsItemController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UsageReportController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VideoController;
use App\Http\Controllers\Admin\VideoIngestController;
use App\Http\Controllers\Auth\MicrosoftAuthController;
use App\Http\Controllers\Customer\DashboardController as CustomerDashboardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

// Hilfs-Closure: Modul fehlt (Controller-Datei nicht vorhanden) – Redirect mit Hinweis
$moduleUnavailable = function () {
    return redirect()->route('admin.dashboard')
        ->with('error', 'Dieses Modul ist derzeit nicht verfügbar. Bitte Controller-Dateien aus Backup oder Git wiederherstellen.');
};

// ========== Debug: Fehler beim Admin-Laden anzeigen (bitte nach dem Beheben wieder entfernen) ==========
Route::get('/admin-debug', function () {
    try {
        $newsCount = 0;
        if (class_exists(\App\Models\NewsItem::class)) {
            $newsCount = \App\Models\NewsItem::count();
        }

        return view('admin.dashboard', compact('newsCount'));
    } catch (\Throwable $e) {
        return response(
            '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Admin-Debug</title></head><body style="font-family:monospace;padding:1rem;background:#1e1e1e;color:#d4d4d4;">'
            .'<h1 style="color:#f48771;">Fehler beim Laden der Admin-Seite</h1>'
            .'<p><strong>'.htmlspecialchars($e->getMessage()).'</strong></p>'
            .'<p>'.htmlspecialchars($e->getFile().':'.$e->getLine()).'</p>'
            .'<pre style="white-space:pre-wrap;font-size:12px;">'.htmlspecialchars($e->getTraceAsString()).'</pre>'
            .'</body></html>',
            200,
            ['Content-Type' => 'text/html; charset=utf-8']
        );
    }
})->middleware(['auth', 'permission:access_admin'])->name('admin.debug');

// ========== Öffentlich ==========
Route::get('/', [NewsController::class, 'index'])->name('home');
Route::get('/news/{slug}', [NewsController::class, 'show'])->name('news.show');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');
Route::get('/sitemap-news.xml', [SitemapController::class, 'newsSitemap'])->name('sitemap.news');

// ========== Delivery (signed, ohne Login) ==========
Route::get('/d/{token}', [DeliveryController::class, 'show'])->name('delivery.show')->middleware('throttle:60,1');
Route::post('/d/{token}/confirm', [DeliveryController::class, 'confirm'])->name('delivery.confirm')->middleware('throttle:30,1');
// Stream vor Download-Route: Video/Audio Same-Origin (kein direkter S3-URL im <video>/<audio>)
Route::get('/d/{token}/m/{media}/stream', [DeliveryController::class, 'stream'])->name('delivery.stream')->middleware(['signed', 'throttle:120,1']);
Route::get('/d/{token}/m/{media}', [DeliveryController::class, 'download'])->name('delivery.download')->middleware('throttle:30,1');

// ========== Auth (Login, Microsoft, Passwort) ==========
Route::get('/auth/microsoft/redirect', [MicrosoftAuthController::class, 'redirect'])->name('auth.microsoft.redirect');
Route::get('/auth/microsoft/callback', [MicrosoftAuthController::class, 'callback'])->name('auth.microsoft.callback');
require __DIR__.'/auth.php';

// ========== Nach Login (auth) ==========
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        if (auth()->user()->can('access_admin')) {
            return redirect()->route('admin.dashboard');
        }

        return view('dashboard');
    })->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// ========== Admin (/admin, auth + access_admin) ==========
Route::middleware(['auth', 'permission:access_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // News
    Route::get('/news', [NewsItemController::class, 'index'])->name('news.index');
    Route::get('/news/create', [NewsItemController::class, 'create'])->name('news.create');
    Route::post('/news', [NewsItemController::class, 'store'])->name('news.store');
    Route::get('/news/{newsItem}', fn (App\Models\NewsItem $newsItem) => redirect()->route('admin.news.edit', $newsItem))->name('news.show');
    Route::get('/news/{newsItem}/edit', [NewsItemController::class, 'edit'])->name('news.edit');
    Route::patch('/news/{newsItem}', [NewsItemController::class, 'update'])->name('news.update');
    Route::delete('/news/{newsItem}', [NewsItemController::class, 'destroy'])->name('news.destroy');
    // Video/Audio: gleicher Ursprung wie Admin (kein direkter S3-URL → CORS/Range-Probleme im <video>/<audio>-Tag)
    Route::get('/news/{newsItem}/media/{mediaId}/playback', [NewsItemController::class, 'playbackMedia'])->name('news.media.playback');
    Route::get('/news/{newsItem}/media/{mediaId}/edit', [NewsItemController::class, 'editMedia'])->name('news.media.edit');
    Route::patch('/news/{newsItem}/media/{mediaId}', [NewsItemController::class, 'updateMedia'])->name('news.media.update');
    Route::delete('/news/{newsItem}/media/{mediaId}', [NewsItemController::class, 'destroyMedia'])->name('news.media.destroy');
    Route::post('/news/{newsItem}/media/{mediaId}/unlink', [NewsItemController::class, 'unlinkMedia'])->name('news.media.unlink');
    Route::get('/news/{newsItem}/media/{mediaId}/unkentlich', [NewsItemController::class, 'showUnkenntlichEditor'])->name('news.media.unkentlich');
    Route::post('/news/{newsItem}/media/{mediaId}/unkentlich', [NewsItemController::class, 'applyUnkenntlich'])->name('news.media.unkentlich.apply');
    Route::post('/news/{newsItem}/media/{mediaId}/unkentlich-aufheben', [NewsItemController::class, 'toggleUnkenntlich'])->name('news.media.unkentlich.aufheben');
    Route::match(['patch', 'post'], '/news/{newsItem}/media/{mediaId}/redaction', [NewsItemController::class, 'updateRedaction'])->name('news.media.redaction.update');
    Route::post('/news/{newsItem}/media/{mediaId}/redaction/run', [NewsItemController::class, 'runRedaction'])->name('news.media.redaction.run');
    Route::post('/news/{newsItem}/media/{mediaId}/redaction/run-now', [NewsItemController::class, 'runRedactionNow'])->name('news.media.redaction.run-now');
    Route::get('/news/{newsItem}/media/{mediaId}/ai-status', [NewsItemController::class, 'mediaAiStatus'])->name('news.media.ai-status');
    Route::post('/news/{newsItem}/media/{mediaId}/request-ai', [NewsItemController::class, 'requestMediaAi'])->name('news.media.request-ai');
    Route::post('/news/{newsItem}/apply-author-credit', [NewsItemController::class, 'applyAuthorCredit'])->name('news.apply-author-credit');
    Route::get('/news/{newsItem}/send', [NewsDeliveryController::class, 'prepareSend'])->name('news.send');
    Route::post('/news/{newsItem}/send', [NewsDeliveryController::class, 'send'])->name('news.send.post');
    Route::post('/news/{newsItem}/send-ftp', [NewsDeliveryController::class, 'queueFtp'])->name('news.send-ftp');
    Route::get('/news/{newsItem}/send-summary', [NewsDeliveryController::class, 'summary'])->name('news.send-summary');
    Route::post('/news/{newsItem}/send-summary', [NewsDeliveryController::class, 'applySummary'])->name('news.send-summary.apply');

    // Deliveries
    Route::get('/deliveries', [AdminDeliveryController::class, 'index'])->name('deliveries.index');
    Route::get('/deliveries/{delivery}/activity', [AdminDeliveryController::class, 'show'])->name('deliveries.show');
    Route::post('/deliveries/{delivery}/revoke', [AdminDeliveryController::class, 'revoke'])->name('deliveries.revoke');

    Route::get('/video', [VideoController::class, 'index'])->name('video.index');
    Route::get('/audio', [AudioController::class, 'index'])->name('audio.index');

    // Video-Ingest (Rohmaterial MC60 → redaktionelle Auswahl → Sendefassung)
    Route::get('/ingest', [VideoIngestController::class, 'index'])->name('ingest.index');
    Route::get('/ingest/news/{newsItem}', [VideoIngestController::class, 'newsWorkspace'])->name('ingest.news-workspace');
    Route::post('/ingest/news/{newsItem}/render', [VideoIngestController::class, 'queueRender'])->name('ingest.news-render');
    Route::get('/ingest/files/{ingestFile}/playback', [VideoIngestController::class, 'playback'])->name('ingest.playback');
    Route::post('/ingest/files/{ingestFile}/assign', [VideoIngestController::class, 'assign'])->name('ingest.assign');
    Route::get('/ingest/files/{ingestFile}', [VideoIngestController::class, 'show'])->name('ingest.show');

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::get('/settings/seo', [SettingsController::class, 'seo'])->name('settings.seo');
    Route::get('/settings/seo/google-connect', [SettingsController::class, 'googleConnect'])->name('settings.seo.google-connect');
    Route::get('/settings/seo/google-callback', [SettingsController::class, 'googleCallback'])->name('settings.seo.google-callback');
    Route::post('/settings/seo/google-disconnect', [SettingsController::class, 'googleDisconnect'])->name('settings.seo.google-disconnect');
    Route::get('/settings/backup', [SettingsController::class, 'backup'])->name('settings.backup');
    Route::post('/settings/backup/run', [SettingsController::class, 'backupRun'])->name('settings.backup.run');
    Route::get('/settings/ai', [SettingsController::class, 'ai'])->name('settings.ai');
    Route::post('/settings/ai/test', [SettingsController::class, 'aiTest'])->name('settings.ai.test');
    Route::get('/settings/jobs', [SettingsController::class, 'jobs'])->name('settings.jobs');
    Route::post('/settings/jobs/run', [SettingsController::class, 'jobsRun'])->name('settings.jobs.run');
    Route::get('/settings/media', [SettingsController::class, 'media'])->name('settings.media');

    // Backoffice
    Route::get('/backoffice', [BackofficeController::class, 'index'])->name('backoffice.index');

    Route::get('/backoffice/users', [UserController::class, 'index'])->name('backoffice.users.index');
    Route::get('/backoffice/users/{user}/edit', [UserController::class, 'edit'])->name('backoffice.users.edit');
    Route::post('/backoffice/users/{user}', [UserController::class, 'update'])->name('backoffice.users.update');
    Route::delete('/backoffice/users/{user}', [UserController::class, 'destroy'])->name('backoffice.users.destroy');

    // Billing
    Route::get('/backoffice/billing', [BillingController::class, 'index'])->name('backoffice.billing.index');
    Route::get('/backoffice/billing/create/{product}', [BillingController::class, 'create'])->name('backoffice.billing.create');
    Route::post('/backoffice/billing', [BillingController::class, 'store'])->name('backoffice.billing.store');
    Route::get('/backoffice/billing/{invoice}', [BillingController::class, 'show'])->name('backoffice.billing.show');
    Route::get('/backoffice/billing/{invoice}/pdf', [BillingController::class, 'downloadPdf'])->name('backoffice.billing.pdf');
    Route::get('/backoffice/billing/{invoice}/zugferd', [BillingController::class, 'downloadZugferd'])->name('backoffice.billing.zugferd');
    Route::get('/backoffice/billing/{invoice}/lexware-file', [BillingController::class, 'downloadLexwareFile'])->name('backoffice.billing.lexware-file');
    Route::post('/backoffice/billing/{invoice}/send', [BillingController::class, 'sendInvoice'])->name('backoffice.billing.send');
    Route::post('/backoffice/billing/{invoice}/ready-for-lexware', [BillingController::class, 'markReadyForLexware'])->name('backoffice.billing.ready-for-lexware');
    Route::post('/backoffice/billing/{invoice}/release', [BillingController::class, 'releaseDraft'])->name('backoffice.billing.release');

    // Usage
    Route::get('/backoffice/usage', [UsageReportController::class, 'index'])->name('backoffice.usage.index');
    Route::get('/backoffice/usage/create', [UsageReportController::class, 'create'])->name('backoffice.usage.create');
    Route::post('/backoffice/usage', [UsageReportController::class, 'store'])->name('backoffice.usage.store');
    Route::get('/backoffice/usage/{usageRecord}/edit', [UsageReportController::class, 'edit'])->name('backoffice.usage.edit');
    Route::put('/backoffice/usage/{usageRecord}', [UsageReportController::class, 'update'])->name('backoffice.usage.update');
    Route::delete('/backoffice/usage/{usageRecord}', [UsageReportController::class, 'destroy'])->name('backoffice.usage.destroy');

    // Redirect: /admin/products bzw. /admin/products?organization=5 (keine eigene Products-Liste)
    Route::get('/products', function () {
        $orgId = request()->query('organization');
        if ($orgId !== null && $orgId !== '' && ctype_digit((string) $orgId)) {
            return redirect()->to('/admin/customers/'.(int) $orgId.'/products');
        }

        return redirect()->route('admin.customers.index');
    })->name('products.redirect');

    // Customers
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    // Redirect: customers/products, customers/products/create oder customers//products/create (leeres organization)
    Route::get('/customers/products', fn () => redirect()->route('admin.customers.index'))->name('customers.products.redirect-empty');
    Route::get('/customers/products/create', fn () => redirect()->route('admin.customers.index'));
    Route::get('/customers//products', fn () => redirect()->route('admin.customers.index'));
    Route::get('/customers//products/create', fn () => redirect()->route('admin.customers.index'));

    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show')->whereNumber('customer');
    Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit')->whereNumber('customer');
    Route::post('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update')->whereNumber('customer');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy')->whereNumber('customer');

    Route::get('/customers/{customer}/products', [CustomerProductController::class, 'index'])->name('customers.products.index')->whereNumber('customer');
    Route::get('/customers/{customer}/products/create', [CustomerProductController::class, 'create'])->name('customers.products.create')->whereNumber('customer');
    Route::post('/customers/{customer}/products', [CustomerProductController::class, 'store'])->name('customers.products.store')->whereNumber('customer');
    Route::get('/customers/{customer}/products/{product}/edit', [CustomerProductController::class, 'edit'])->name('customers.products.edit')->whereNumber('customer');
    Route::post('/customers/{customer}/products/{product}', [CustomerProductController::class, 'update'])->name('customers.products.update')->whereNumber('customer');
    Route::post('/customers/{customer}/products/{product}/import-lexware', [CustomerProductController::class, 'importLexware'])->name('customers.products.import-lexware')->whereNumber('customer');
    Route::delete('/customers/{customer}/products/{product}', [CustomerProductController::class, 'destroy'])->name('customers.products.destroy')->whereNumber('customer');

    Route::get('/products/{product}/destinations', [DeliveryDestinationController::class, 'index'])->name('destinations.index');
    Route::get('/products/{product}/destinations/create', [DeliveryDestinationController::class, 'create'])->name('destinations.create');
    Route::post('/products/{product}/destinations', [DeliveryDestinationController::class, 'store'])->name('destinations.store');
    Route::get('/destinations/{destination}/edit', [DeliveryDestinationController::class, 'editByDestination'])->name('destinations.edit');
    Route::get('/destinations/{destination}', fn (\App\Models\DeliveryDestination $destination) => redirect()->route('admin.destinations.edit', $destination))->name('destinations.show');
    Route::put('/destinations/{destination}', [DeliveryDestinationController::class, 'update'])->name('destinations.update');
    Route::delete('/destinations/{destination}', [DeliveryDestinationController::class, 'destroy'])->name('destinations.destroy');
    Route::post('/destinations/{destination}/test', [DeliveryDestinationController::class, 'test'])->name('destinations.test');
    Route::post('/destinations/{destination}/upload', [DeliveryDestinationController::class, 'upload'])->name('destinations.upload');

    // Versandziele auf Kunden-/Organisationsebene (product_id = null)
    Route::get('/customers/{customer}/destinations', [DeliveryDestinationController::class, 'indexForCustomer'])->name('customers.destinations.index')->whereNumber('customer');
    Route::get('/customers/{customer}/destinations/create', [DeliveryDestinationController::class, 'createForCustomer'])->name('customers.destinations.create')->whereNumber('customer');
    Route::post('/customers/{customer}/destinations', [DeliveryDestinationController::class, 'storeForCustomer'])->name('customers.destinations.store')->whereNumber('customer');
    Route::get('/customers/{customer}/destinations/{destination}/edit', [DeliveryDestinationController::class, 'editForCustomer'])->name('customers.destinations.edit')->whereNumber('customer');
    Route::put('/customers/{customer}/destinations/{destination}', [DeliveryDestinationController::class, 'updateForCustomer'])->name('customers.destinations.update')->whereNumber('customer');
    Route::delete('/customers/{customer}/destinations/{destination}', [DeliveryDestinationController::class, 'destroyForCustomer'])->name('customers.destinations.destroy')->whereNumber('customer');

    // Redirect: customers//contacts und customers//contacts/create (leeres organization-Segment)
    Route::get('/customers/contacts', fn () => redirect()->route('admin.customers.index'));
    Route::get('/customers/contacts/create', fn () => redirect()->route('admin.customers.index'));
    Route::get('/customers//contacts', fn () => redirect()->route('admin.customers.index'));
    Route::get('/customers//contacts/create', fn () => redirect()->route('admin.customers.index'));

    // Contacts
    Route::get('/customers/{customer}/contacts', [CustomerContactController::class, 'index'])->name('customers.contacts.index')->whereNumber('customer');
    Route::get('/customers/{customer}/contacts/create', [CustomerContactController::class, 'create'])->name('customers.contacts.create')->whereNumber('customer');
    Route::post('/customers/{customer}/contacts', [CustomerContactController::class, 'store'])->name('customers.contacts.store')->whereNumber('customer');
    Route::get('/customers/{customer}/contacts/{contact}/edit', [CustomerContactController::class, 'edit'])->name('customers.contacts.edit')->whereNumber('customer');
    Route::post('/customers/{customer}/contacts/{contact}', [CustomerContactController::class, 'update'])->name('customers.contacts.update')->whereNumber('customer');
    Route::delete('/customers/{customer}/contacts/{contact}', [CustomerContactController::class, 'destroy'])->name('customers.contacts.destroy')->whereNumber('customer');
});

// ========== Kundenbereich (/kunden, view_customer_area) ==========
Route::middleware(['auth', 'permission:view_customer_area'])->group(function () {
    Route::get('/kunden', [CustomerDashboardController::class, 'index'])->name('customer.dashboard');
});
