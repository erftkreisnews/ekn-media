<?php

use App\Http\Controllers\KoelnImage\DownloadController;
use App\Http\Controllers\KoelnImage\EventController;
use App\Http\Controllers\KoelnImage\GalleryController;
use App\Http\Controllers\KoelnImage\HomeController;
use App\Http\Controllers\KoelnImage\NewsGalleryController;
use App\Http\Controllers\KoelnImage\SitemapController;
use App\Http\Controllers\NeukundenController;
use App\Http\Controllers\NewsController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('koelnimage.home');

Route::get('/sitemap-images.xml', [SitemapController::class, 'imagesXml'])->name('koelnimage.sitemap.images');

Route::get('/gallery/photos/{slug}', [NewsGalleryController::class, 'index'])->name('koelnimage.gallery.photos');
Route::get('/gallery/photos/{slug}/time-window', [NewsGalleryController::class, 'timeWindow'])->name('koelnimage.gallery.time-window');
Route::middleware('auth')->get('/gallery/photos/{slug}/download/{media}', [NewsGalleryController::class, 'download'])
    ->whereNumber('media')
    ->name('koelnimage.gallery.download');
Route::post('/gallery/photos/{slug}/favorites/toggle', [NewsGalleryController::class, 'toggleFavorite'])->name('koelnimage.gallery.favorites.toggle');
Route::post('/gallery/photos/{slug}/cart/toggle', [NewsGalleryController::class, 'toggleCart'])->name('koelnimage.gallery.cart.toggle');

Route::get('/news/{slug}/gallery/photos', static function (string $slug) {
    $to = route('koelnimage.gallery.photos', ['slug' => $slug]);
    $qs = request()->getQueryString();
    if (is_string($qs) && $qs !== '') {
        $to .= (str_contains($to, '?') ? '&' : '?').$qs;
    }

    return redirect()->to($to, 301);
});
Route::middleware('auth')->get('/news/{slug}/gallery/download/{media}', static function (string $slug, int $media) {
    $to = route('koelnimage.gallery.download', ['slug' => $slug, 'media' => $media]);
    $qs = request()->getQueryString();
    if (is_string($qs) && $qs !== '') {
        $to .= (str_contains($to, '?') ? '&' : '?').$qs;
    }

    return redirect()->to($to, 301);
})->whereNumber('media');
Route::post('/news/{slug}/gallery/favorites/toggle', [NewsGalleryController::class, 'toggleFavorite']);
Route::post('/news/{slug}/gallery/cart/toggle', [NewsGalleryController::class, 'toggleCart']);

Route::get('/news/{slug}', [NewsController::class, 'show'])->name('koelnimage.news.show');

Route::get('/events', [EventController::class, 'index'])->name('koelnimage.events.index');
Route::get('/motorsport', [EventController::class, 'motorsport'])->name('koelnimage.motorsport.index');
Route::get('/galerien', [GalleryController::class, 'index'])->name('koelnimage.galleries.index');
Route::get('/kunden/downloads', [DownloadController::class, 'index'])->name('koelnimage.customer.downloads');

Route::get('/neukunde', [NeukundenController::class, 'create'])->name('koelnimage.neukunde');
Route::post('/neukunde', [NeukundenController::class, 'store'])->name('koelnimage.neukunde.store')->middleware('throttle:5,1');
Route::redirect('/neukunden', '/neukunde', 301);
