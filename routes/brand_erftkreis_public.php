<?php

use App\Http\Controllers\NeukundenController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\WitnessPortalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Öffentlicher Auftritt Erftkreis News (EKN)
|--------------------------------------------------------------------------
|
| Wird von routes/web.php entweder ohne Domain (Legacy) oder innerhalb von
| Route::domain(BRAND_ERFTKREIS_HOST) geladen, damit andere Marken-Hostnamen
| nicht versehentlich die Startseite / News / Sitemaps der EKN ausliefern.
|
*/

Route::get('/', [NewsController::class, 'index'])->name('home');
Route::get('/news', [NewsController::class, 'index'])->name('news.index');
Route::redirect('/news/test', '/news', 301)->name('news.legacy.test');
Route::redirect('/nachricht/{legacyId}', '/news', 301)->whereNumber('legacyId');
Route::view('/agb', 'legal.agb')->name('agb');
Route::get('/neukunden', [NeukundenController::class, 'create'])->name('neukunden');
Route::post('/neukunden', [NeukundenController::class, 'store'])->name('neukunden.store')->middleware('throttle:5,1');
Route::get('/news/{slug}', [NewsController::class, 'show'])->name('news.show');

$witnessPortalRoutes = function (string $showRouteName, string $storeRouteName): void {
    Route::get('e/{token}', [WitnessPortalController::class, 'show'])
        ->name($showRouteName)
        ->where('token', '[A-Za-z0-9]{32,128}')
        ->middleware('throttle:witness-show');
    Route::post('e/{token}', [WitnessPortalController::class, 'store'])
        ->name($storeRouteName)
        ->where('token', '[A-Za-z0-9]{32,128}')
        ->middleware(['witness.honeypot', 'throttle:witness-upload']);
};

// Unter Hauptdomain (funktioniert auch ohne Subdomain-DNS)
Route::prefix('witness')->group(function () use ($witnessPortalRoutes): void {
    $witnessPortalRoutes('witness.upload.show', 'witness.upload.store');
});

// Dieselben Endpunkte zusätzlich auf WITNESS_PORTAL_HOST (z. B. zeugen.…)
$witnessHost = trim((string) config('witness.portal_host'));
if ($witnessHost !== '') {
    Route::domain($witnessHost)->group(function () use ($witnessPortalRoutes): void {
        $witnessPortalRoutes('witness.portal.show', 'witness.portal.store');
    });
}

Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');
Route::get('/sitemap-news.xml', [SitemapController::class, 'newsSitemap'])->name('sitemap.news');

Route::get('/brand-health', fn () => response('erftkreis_news', 200))
    ->name('brand.erftkreis.health');
