<?php

use App\Http\Controllers\Admin\AdminEntryController;
use App\Http\Controllers\Auth\MicrosoftAuthController;
use App\Http\Controllers\Customer\DashboardController as CustomerDashboardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\ProfileController;
use App\Support\AdminPermissions;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Multi-Brand: Kölnimage (eigene Domain) + Erftkreis News (öffentlich)
|--------------------------------------------------------------------------
*/

$domainRoutingEnabled = (bool) config('brands.domain_routing_enabled', false);
$erftkreisHost = trim((string) config('brands.hosts.erftkreis_news'));
$koelnimageHost = trim((string) config('brands.hosts.koelnimage'));

if ($koelnimageHost !== '') {
    Route::domain($koelnimageHost)->middleware('brand.resolve')->group(function (): void {
        require base_path('routes/brand_koelnimage.php');
    });
}

if ($domainRoutingEnabled && $erftkreisHost !== '') {
    Route::domain($erftkreisHost)->middleware('brand.resolve')->group(function (): void {
        require base_path('routes/brand_erftkreis_public.php');
    });
} elseif (! $domainRoutingEnabled) {
    Route::middleware('brand.resolve')->group(function (): void {
        require base_path('routes/brand_erftkreis_public.php');
    });
}

// ========== Beweismittel Behördenzugang (Polizei / STA, ohne Admin-Login) ==========
use App\Http\Controllers\PublicationFindingAuthorityController;

Route::get('/recht/beweismittel/{token}', [PublicationFindingAuthorityController::class, 'show'])
    ->name('publication-finding.authority.show')
    ->middleware('throttle:60,1');
Route::post('/recht/beweismittel/{token}/passwort', [PublicationFindingAuthorityController::class, 'verifyPassword'])
    ->name('publication-finding.authority.password')
    ->middleware('throttle:20,1');
Route::get('/recht/beweismittel/{token}/download', [PublicationFindingAuthorityController::class, 'download'])
    ->name('publication-finding.authority.download')
    ->middleware(['signed', 'throttle:30,1']);

// ========== Delivery (signed, ohne Login) ==========
Route::get('/d/{token}', [DeliveryController::class, 'show'])->name('delivery.show')->middleware('throttle:60,1');
Route::post('/d/{token}/confirm', [DeliveryController::class, 'confirm'])->name('delivery.confirm')->middleware('throttle:30,1');
Route::get('/d/{token}/m/{media}/stream', [DeliveryController::class, 'stream'])->name('delivery.stream')->middleware(['signed', 'throttle:120,1']);
Route::get('/d/{token}/m/{media}', [DeliveryController::class, 'download'])->name('delivery.download')->middleware(['signed', 'throttle:120,1']);
Route::get('/d/{token}/m/{media}/video-xmp', [DeliveryController::class, 'downloadVideoXmp'])->name('delivery.download.video-xmp')->middleware(['signed', 'throttle:120,1']);

// ========== Auth ==========
Route::get('/auth/microsoft/redirect', [MicrosoftAuthController::class, 'redirect'])->name('auth.microsoft.redirect');
Route::get('/auth/microsoft/callback', [MicrosoftAuthController::class, 'callback'])->name('auth.microsoft.callback');
require __DIR__.'/auth.php';

// ========== Nach Login ==========
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        if (auth()->user()->can(AdminPermissions::ACCESS)) {
            return redirect()->route('admin.dashboard');
        }

        return view('dashboard');
    })->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// ========== Admin ==========
Route::get('/admin', [AdminEntryController::class, 'show'])->name('admin.dashboard');

Route::middleware(['auth', 'permission:'.AdminPermissions::ACCESS, 'delete.confirm'])->prefix('admin')->name('admin.')->group(function (): void {
    require base_path('routes/admin_web_group.php');
});

// ========== Kundenbereich ==========
Route::middleware(['auth', 'permission:view_customer_area'])->group(function () {
    Route::get('/kunden', [CustomerDashboardController::class, 'index'])->name('customer.dashboard');
});
