@php
    $articleAltFallback = trim((string) ($newsItem->title ?? '')) ?: trim(str_replace('-', ' ', (string) $newsItem->slug));
    $lightboxPublishedAtLabel = $newsItem->published_at
        ? $newsItem->published_at->format('d.m.Y, H:i').' Uhr'
        : null;
    $creditLine = filled($newsItem->author_credit) ? trim((string) $newsItem->author_credit) : '';
    $authorName = $newsItem->author?->name;
    $photographerCredit = '';
    if ($creditLine !== '' && ($authorName === null || strcasecmp($creditLine, $authorName) !== 0)) {
        $photographerCredit = $creditLine;
    } elseif ($authorName) {
        $photographerCredit = $authorName;
    }
@endphp

<div
    id="koelnimage-gallery"
    x-ref="galleryTop"
    class="koelnimage-hub-gallery koelnimage-hub-gallery-v2 not-prose mt-10 rounded-xl border border-red-100 bg-white p-4 shadow-sm ring-1 ring-red-50/80 sm:p-6"
    x-data="koelnimageGalleryHub({
        apiBase: @js(route('koelnimage.gallery.photos', ['slug' => $newsItem->slug])),
        timeWindowUrl: @js(route('koelnimage.gallery.time-window', ['slug' => $newsItem->slug])),
        favoritesUrl: @js(route('koelnimage.gallery.favorites.toggle', ['slug' => $newsItem->slug])),
        cartUrl: @js(route('koelnimage.gallery.cart.toggle', ['slug' => $newsItem->slug])),
        csrfToken: @js(csrf_token()),
        articleAltFallback: @js($articleAltFallback),
        publishedAtLabel: @js($lightboxPublishedAtLabel),
        locationLabel: @js(filled($newsItem->location_chip_label) ? $newsItem->location_chip_label : null),
        photographerCredit: @js($photographerCredit !== '' ? $photographerCredit : null),
    })"
>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-red-800">Galerie</h2>
            <p class="mt-1 text-xs text-gray-500" x-text="resultSummary"></p>
        </div>
        <div class="flex flex-wrap items-center gap-2 text-xs">
            <span class="rounded-full bg-red-50 px-2.5 py-1 font-medium text-red-900" x-show="meta.favorites_count > 0" x-cloak>
                <span x-text="meta.favorites_count"></span> gemerkt
            </span>
            <span class="rounded-full bg-zinc-100 px-2.5 py-1 font-medium text-zinc-800" x-show="meta.cart_count > 0" x-cloak>
                <span x-text="meta.cart_count"></span> im Warenkorb
            </span>
        </div>
    </div>

    <form class="mt-4 space-y-3 rounded-lg border border-zinc-200 bg-zinc-50/80 p-3 sm:p-4" @submit.prevent="applyFilters()">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label class="block sm:col-span-2 lg:col-span-2">
                <span class="mb-1 block text-xs font-medium text-zinc-600">Suche</span>
                <input
                    type="search"
                    x-model="filters.q"
                    placeholder="Titel, Stichwort, Dateiname…"
                    class="w-full rounded-lg border-zinc-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"
                >
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-medium text-zinc-600">Sortierung</span>
                <select x-model="filters.sort" class="w-full rounded-lg border-zinc-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                    <option value="newest">Neueste zuerst</option>
                    <option value="oldest">Älteste zuerst</option>
                    <option value="filename_az">Dateiname A–Z</option>
                </select>
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-medium text-zinc-600">Pro Seite</span>
                <select x-model.number="filters.per_page" class="w-full rounded-lg border-zinc-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                    <option value="200">200</option>
                </select>
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-medium text-zinc-600">Von (Datum)</span>
                <input type="date" x-model="filters.from" class="w-full rounded-lg border-zinc-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-medium text-zinc-600">Bis (Datum)</span>
                <input type="date" x-model="filters.to" class="w-full rounded-lg border-zinc-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
            </label>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="submit" class="inline-flex min-h-[40px] items-center rounded-lg bg-red-700 px-4 py-2 text-sm font-medium text-white hover:bg-red-800">
                Filter anwenden
            </button>
            <button type="button" @click="resetFilters()" class="inline-flex min-h-[40px] items-center rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-medium text-zinc-800 hover:bg-zinc-50">
                Zurücksetzen
            </button>
            <div class="ml-auto flex rounded-lg border border-zinc-200 bg-white p-0.5" role="group" aria-label="Layout">
                <button type="button" @click="setLayout('grid')" :class="layout === 'grid' ? 'bg-red-700 text-white' : 'text-zinc-700 hover:bg-zinc-50'" class="rounded-md px-3 py-1.5 text-xs font-medium">Grid</button>
                <button type="button" @click="setLayout('masonry')" :class="layout === 'masonry' ? 'bg-red-700 text-white' : 'text-zinc-700 hover:bg-zinc-50'" class="rounded-md px-3 py-1.5 text-xs font-medium">Masonry</button>
                <button type="button" @click="setLayout('fullscreen')" :class="layout === 'fullscreen' ? 'bg-red-700 text-white' : 'text-zinc-700 hover:bg-zinc-50'" class="rounded-md px-3 py-1.5 text-xs font-medium">Groß</button>
            </div>
        </div>
    </form>

    <p x-show="error" x-text="error" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800" x-cloak></p>

    <div x-show="loading" class="mt-6 flex justify-center py-12" x-cloak>
        <span class="text-sm text-zinc-500">Galerie wird geladen…</span>
    </div>

    <div
        x-show="!loading && photos.length > 0"
        class="galerie-grid layout-gallery mt-6"
        :class="{
            'is-masonry': layout === 'masonry',
            'is-fullscreen': layout === 'fullscreen',
            'is-grid': layout === 'grid'
        }"
        x-cloak
    >
        <template x-for="(photo, idx) in photos" :key="photo.id">
            <article class="gallery-card" @click="openLightbox(idx)">
                <div class="gallery-card-image-container">
                    <div class="gallery-card-aspect" :class="layout === 'fullscreen' ? 'gallery-card-aspect-wide' : ''">
                        <img
                            :src="cardImageSrc(photo)"
                            :alt="photo.displayTitle"
                            class="gallery-card-image"
                            loading="lazy"
                            decoding="async"
                        >
                    </div>
                    <span class="gallery-card-id-badge" x-text="'#' + photo.id"></span>
                    <div class="gallery-card-hover">
                        <span>Details anzeigen</span>
                    </div>
                </div>
                <div class="gallery-card-content">
                    <p class="gallery-card-title" x-text="photo.displayTitle"></p>
                    <p class="gallery-card-meta" x-show="photo.captureLabel" x-text="photo.captureLabel"></p>
                    <div class="gallery-card-actions">
                        <button
                            type="button"
                            class="gallery-action-btn"
                            :class="photo.is_favorite ? 'is-active-favorite' : ''"
                            :aria-pressed="photo.is_favorite ? 'true' : 'false'"
                            aria-label="Merken"
                            @click="toggleFavorite(photo, $event)"
                        >
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                        </button>
                        <button
                            type="button"
                            class="gallery-action-btn"
                            :class="photo.is_in_cart ? 'is-active-cart' : ''"
                            :aria-pressed="photo.is_in_cart ? 'true' : 'false'"
                            aria-label="Warenkorb"
                            @click="toggleCart(photo, $event)"
                        >
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zm10 0c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2zM7.16 14h9.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49A1 1 0 0 0 21.05 5H6.21L5.27 3H2v2h2l3.6 7.59-1.35 2.44C5.52 15.37 6.48 17 8 17h12v-2H8l1.16-1z"/></svg>
                        </button>
                        <a
                            x-show="photo.download_url"
                            :href="photo.download_url"
                            class="gallery-action-btn ml-auto"
                            @click.stop
                            download
                            aria-label="Download"
                        >
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                        </a>
                    </div>
                </div>
            </article>
        </template>
    </div>

    <p x-show="!loading && !error && photos.length === 0" class="mt-6 text-center text-sm text-zinc-600" x-cloak>
        Keine öffentlichen Bilder für diese Filter.
    </p>

    <nav
        x-show="!loading && meta.last_page > 1"
        class="mt-6 flex flex-wrap items-center justify-center gap-2"
        aria-label="Galerie-Seiten"
        x-cloak
    >
        <button
            type="button"
            class="rounded-lg border border-zinc-300 px-3 py-1.5 text-sm disabled:opacity-40"
            :disabled="meta.current_page <= 1"
            @click="goToPage(meta.current_page - 1)"
        >Zurück</button>
        <span class="text-sm text-zinc-600" x-text="'Seite ' + meta.current_page + ' / ' + meta.last_page"></span>
        <button
            type="button"
            class="rounded-lg border border-zinc-300 px-3 py-1.5 text-sm disabled:opacity-40"
            :disabled="meta.current_page >= meta.last_page"
            @click="goToPage(meta.current_page + 1)"
        >Weiter</button>
    </nav>

    <p class="mt-4 text-xs text-gray-500">Tipp: Bild antippen für Vollansicht · Pfeiltasten · Esc schließt.</p>

    <div
        x-show="lightboxOpen"
        x-cloak
        x-transition.opacity
        @keydown.escape.window="closeLightbox()"
        @keydown.arrow-left.window="lightboxOpen && lightboxPrev()"
        @keydown.arrow-right.window="lightboxOpen && lightboxNext()"
        @click.self="closeLightbox()"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 p-3 backdrop-blur-sm sm:p-5"
        role="dialog"
        aria-modal="true"
        aria-label="Galerie Vollansicht"
    >
        <div class="koelnimage-detail-dialog relative flex max-h-[90vh] flex-col overflow-hidden rounded-xl bg-white shadow-2xl lg:flex-row" @click.stop>
            <div class="koelnimage-detail-media-col relative min-h-0 w-full lg:w-[60%] lg:flex-none">
                <div class="koelnimage-detail-image-frame">
                    <img
                        :src="lightboxImageSrc(lightboxPhoto)"
                        :alt="lightboxPhoto?.displayTitle || articleAltFallback"
                        class="lightbox-main-image"
                    >
                </div>
                <div class="koelnimage-detail-footer-controls">
                    <button type="button" class="koelnimage-detail-footer-nav" @click="lightboxPrev()" aria-label="Vorheriges Bild">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>
                    </button>
                    <p class="koelnimage-detail-counter" x-text="(lightboxIndex + 1) + ' / ' + photos.length"></p>
                    <button type="button" class="koelnimage-detail-footer-nav" @click="lightboxNext()" aria-label="Nächstes Bild">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </button>
                </div>
                <div class="flex flex-wrap gap-2 px-1">
                    <button type="button" @click="loadTimeWindow('30s')" class="rounded-full border border-zinc-200 bg-white px-2.5 py-1 text-xs font-medium text-zinc-700 hover:bg-zinc-50">±30s</button>
                    <button type="button" @click="loadTimeWindow('60s')" class="rounded-full border border-zinc-200 bg-white px-2.5 py-1 text-xs font-medium text-zinc-700 hover:bg-zinc-50">±60s</button>
                    <button type="button" @click="loadTimeWindow('2m')" class="rounded-full border border-zinc-200 bg-white px-2.5 py-1 text-xs font-medium text-zinc-700 hover:bg-zinc-50">±2 Min</button>
                    <button type="button" @click="loadTimeWindow('5m')" class="rounded-full border border-zinc-200 bg-white px-2.5 py-1 text-xs font-medium text-zinc-700 hover:bg-zinc-50">±5 Min</button>
                </div>
            </div>
            <aside class="koelnimage-detail-info-col relative flex max-h-[40vh] flex-col overflow-y-auto border-t border-red-100 bg-white lg:max-h-[90vh] lg:border-l lg:border-t-0 lg:w-[40%] lg:flex-none">
                <div class="min-h-0 flex-1 pr-10 lg:pr-12">
                    <h3 class="text-xl font-extrabold leading-tight tracking-tight text-red-950 sm:text-2xl" x-text="lightboxPhoto?.displayTitle || articleAltFallback"></h3>
                    <div class="mt-6">
                        <p class="mb-2 text-[11px] font-bold uppercase tracking-[0.08em] text-red-800/60">Details</p>
                        <div class="space-y-2.5 text-sm text-red-950/90">
                            <p class="flex items-start gap-1.5" x-show="lightboxPhoto?.captureLabel">
                                <span class="shrink-0 text-red-400" aria-hidden="true">◷</span>
                                <span class="min-w-0">Aufnahmezeit: <span x-text="lightboxPhoto?.captureLabel"></span></span>
                            </p>
                            <p class="flex items-start gap-1.5" x-show="publishedAtLabel">
                                <span class="shrink-0 text-red-400" aria-hidden="true">◇</span>
                                <span class="min-w-0">Meldung online: <span x-text="publishedAtLabel"></span></span>
                            </p>
                            <p class="flex items-start gap-1.5" x-show="locationLabel">
                                <span class="shrink-0 text-red-400" aria-hidden="true">⌖</span>
                                <span class="min-w-0">Ort: <span x-text="locationLabel"></span></span>
                            </p>
                            <p class="flex items-start gap-1.5" x-show="photographerCredit">
                                <span class="shrink-0 text-red-400" aria-hidden="true">◎</span>
                                <span class="min-w-0" x-text="photographerCredit"></span>
                            </p>
                            <p class="flex items-start gap-1.5" x-show="lightboxPhoto?.download_url">
                                <span class="shrink-0 text-red-400" aria-hidden="true">↓</span>
                                <a :href="lightboxPhoto?.download_url" class="font-medium text-red-800 underline" download>Original herunterladen</a>
                            </p>
                        </div>
                    </div>

                    <div x-show="timeWindowOpen" class="mt-6 border-t border-red-100 pt-4" x-cloak>
                        <p class="mb-2 text-[11px] font-bold uppercase tracking-[0.08em] text-red-800/60">Gleiches Zeitfenster</p>
                        <p x-show="timeWindowLoading" class="text-sm text-zinc-500">Lade…</p>
                        <p x-show="!timeWindowLoading && timeWindowPhotos.length === 0" class="text-sm text-zinc-500">Keine weiteren Bilder in diesem Zeitfenster.</p>
                        <div class="grid grid-cols-4 gap-2" x-show="!timeWindowLoading && timeWindowPhotos.length > 0">
                            <template x-for="tw in timeWindowPhotos" :key="'tw-' + tw.id">
                                <button type="button" class="overflow-hidden rounded border border-zinc-200" @click="openTimeWindowPhoto(tw)">
                                    <img :src="cardImageSrc(tw)" :alt="tw.displayTitle" class="aspect-square w-full object-cover">
                                </button>
                            </template>
                        </div>
                    </div>

                    <div class="mt-6 border-t border-red-100 pt-4">
                        <button type="button" class="w-full rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-semibold text-red-900 hover:bg-red-100" @click="closeLightbox()">Schließen</button>
                    </div>
                </div>
            </aside>
            <button
                type="button"
                class="absolute right-3 top-3 z-[120] inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/40 bg-black/55 text-lg font-semibold text-white shadow-lg transition hover:bg-black/70 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/80 lg:right-5 lg:top-5"
                @click="closeLightbox()"
                aria-label="Schließen"
            >✕</button>
        </div>
    </div>
</div>
