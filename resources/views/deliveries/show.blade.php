@extends('layouts.frontend')

@section('title', 'Medienpaket | ' . $newsItem->title . ' | ' . config('app.name'))

@push('styles')
<style>
    .koelnimage-detail-dialog {
        width: min(96vw, 1400px);
        height: min(90vh, 700px);
    }
    .koelnimage-detail-media-col {
        display: flex;
        flex-direction: column;
        gap: 10px;
        padding: 24px;
        background: transparent;
        width: 100%;
        min-width: 0;
    }
    .koelnimage-detail-image-frame {
        position: relative;
        flex: 1;
        min-height: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #0b0b0b;
        border-radius: 10px;
        overflow: hidden;
    }
    .koelnimage-detail-image-frame > img.overlay-main-image {
        position: absolute;
        inset: 0;
        display: block;
        width: 100%;
        height: 100%;
        object-fit: contain;
        object-position: center;
    }
    .koelnimage-detail-info-col {
        padding: 24px;
        min-width: 0;
    }
    .koelnimage-detail-footer-controls {
        margin-top: 10px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .koelnimage-detail-footer-nav {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 42px;
        height: 42px;
        border-radius: 9999px;
        border: 1px solid rgb(244 244 245);
        background: rgba(255, 255, 255, 0.9);
        color: rgb(39 39 42);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.16);
        transition: transform 0.2s ease, background-color 0.2s ease;
    }
    .koelnimage-detail-footer-nav:hover {
        transform: scale(1.05);
        background: #fff;
    }
    .koelnimage-detail-footer-nav svg {
        width: 26px;
        height: 26px;
        stroke-width: 2.3;
    }
    .koelnimage-detail-counter {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 74px;
        border-radius: 9999px;
        background: rgba(63, 63, 70, 0.78);
        padding: 6px 12px;
        font-size: 12px;
        font-weight: 600;
        color: rgb(244 244 245);
    }
    @media (min-width: 1024px) {
        .koelnimage-detail-media-col {
            flex: 0 0 60% !important;
            width: 60% !important;
            max-width: 60% !important;
            justify-content: flex-start;
        }
        .koelnimage-detail-info-col {
            flex: 0 0 40% !important;
            width: 40% !important;
            max-width: 40% !important;
        }
        .koelnimage-detail-image-frame {
            flex: 0 0 auto;
            width: 100% !important;
            min-height: 0 !important;
            height: auto !important;
            max-height: none !important;
            aspect-ratio: 3 / 2 !important;
        }
        .koelnimage-detail-image-frame > img.overlay-main-image {
            width: 100% !important;
            height: 100% !important;
            object-fit: contain !important;
        }
    }
    @media (max-width: 1023px) {
        .koelnimage-detail-dialog {
            width: min(96vw, 1080px);
            height: min(90vh, 870px);
        }
        .koelnimage-detail-media-col {
            padding: 12px;
        }
        .koelnimage-detail-image-frame {
            margin-top: 30px;
            margin-bottom: 30px;
        }
        .koelnimage-detail-info-col {
            padding: 12px;
        }
    }
</style>
@endpush

@section('content')
@php
    $isUpdateDeliveryView = (bool) ($delivery->is_update_delivery ?? false);
    $updateReferenceNewsId = (string) ($updateReferenceNewsId ?? $newsItem->display_news_id);
    $newMediaCounts = $newMediaCounts ?? ['images' => 0, 'videos' => 0, 'audios' => 0];
@endphp
<div class="bg-ekn-50 -mx-4 sm:-mx-6 lg:-mx-8 py-8 sm:py-10">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Block 1: Überschrift im gleichen Stil wie News-Detail --}}
        <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 px-4 py-4 sm:px-6 sm:py-5 mb-4">
            @php
                // Split only when ":" is a textual separator, not in times (e.g. "19:45").
                $titleParts = preg_split('/:\h+/', $newsItem->title, 2);
            @endphp
            <h1 class="text-xl sm:text-2xl font-semibold text-ekn-900 leading-snug">
                @if(count($titleParts) === 2)
                    <span>{{ e($titleParts[0]) }}:</span>
                    <span class="sm:block"> {{ e(trim($titleParts[1])) }}</span>
                @else
                    {{ $newsItem->title }}
                @endif
            </h1>
            @include('deliveries.partials.update-banner')
        </div>

        {{-- Block 2: Beschreibung & Downloads --}}
        <article class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 overflow-hidden">
            <div class="p-4 sm:p-6">
                <div class="flex flex-wrap items-baseline justify-between gap-4 text-sm text-slate-600 mb-4">
                    <div>
                        Datum: {{ $newsItem->published_at?->format('d.m.Y, H:i') }} Uhr
                        · NEWSID: {{ $newsItem->display_news_id }}
                        @if($newsItem->location_label)
                            · {{ $newsItem->location_label }}
                        @endif
                    </div>
                    <div class="text-ekn-900 font-medium">
                        Link gültig bis {{ $delivery->expires_at->format('d.m.Y H:i') }} Uhr
                    </div>
                </div>

                @include('deliveries.partials.timeline', ['deliveryTimeline' => $deliveryTimeline ?? collect()])

                @php
                    $hasTimeline = ($deliveryTimeline ?? collect())->isNotEmpty();
                @endphp
                @if(! $hasTimeline)
                    @if($newsItem->subheadline)
                        <div class="font-semibold text-ekn-900 mb-4">{{ $newsItem->subheadline }}</div>
                    @endif
                    <div class="prose prose-gray max-w-none text-gray-700 mb-6 space-y-4">
                        {!! $newsItem->body !!}
                    </div>
                @endif

                @if(session('error'))
                    <div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800 mb-4">
                        {{ session('error') }}
                    </div>
                @endif
                @if(session('status'))
                    <div class="rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-800 mb-4">
                        {{ session('status') }}
                    </div>
                @endif

                @if($newsItem->liveu_on_site)
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <div class="rounded-xl border border-[#092E48]/15 bg-gradient-to-br from-slate-50 via-white to-[#092E48]/[0.04] shadow-sm ring-1 ring-black/5 px-4 py-4 sm:px-5 sm:py-4">
                            <div class="flex gap-4 items-center">
                                <span class="inline-flex h-11 shrink-0 items-center justify-center rounded-xl bg-white px-2.5 shadow-sm ring-1 ring-gray-200/80" aria-hidden="true">
                                    <img
                                        src="{{ asset('images/liveu-logo.png') }}"
                                        alt=""
                                        aria-hidden="true"
                                        width="72"
                                        height="28"
                                        class="h-7 w-auto max-w-[5rem] object-contain object-center"
                                        loading="lazy"
                                        decoding="async"
                                    />
                                </span>
                                <div class="min-w-0 flex-1 text-left">
                                    <p class="text-[11px] font-semibold uppercase tracking-wider text-[#092E48]">Hinweis · LiveU</p>
                                    <h2 class="mt-0.5 text-xl font-bold tracking-tight text-gray-900 sm:text-2xl">LiveU vor Ort verfügbar</h2>
                                    <p class="mt-2 text-sm leading-snug text-gray-600">
                                        Das Material kann in Echtzeit via LiveU bereitgestellt werden. (Reporter vor Ort per N-1 jederzeit möglich.)
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @php
                    $firstReportUrl = $firstReportUrl ?? null;
                    $latestUpdateUrl = $latestUpdateUrl ?? null;
                    $newMediaIds = array_map('intval', (array) ($newMediaIds ?? []));
                    $allowedImages = $allowedMedia->filter(fn ($m) => $m->isImage())->values();
                    $allowedVideos = $allowedMedia->filter(fn ($m) => $m->isVideo())->values();
                    $allowedAudios = $allowedMedia->filter(fn ($m) => $m->isAudio())->values();
                    $photographerCredit = trim((string) ($newsItem->author_credit ?? ''));
                    if ($photographerCredit === '' && $newsItem->author) {
                        $photographerCredit = trim((string) $newsItem->author->name);
                    }
                    $overlayImageItems = $allowedImages->map(function ($m) use ($downloadUrls, $streamUrls) {
                        $hasPublic = (bool) $m->public_url;
                        $offerDownload = $hasPublic
                            || (
                                $m->redaction_status === \App\Models\NewsItemMedia::REDACTION_FAILED
                                && ($m->preview_path || $m->path)
                            );

                        return [
                            'id' => $m->id,
                            'url' => $streamUrls[$m->id] ?? $m->public_url ?? $m->preview_url,
                            'title' => $m->display_name ?: $m->original_name ?: ('Medium #' . $m->id),
                            'caption' => $m->caption,
                            'download_url' => $offerDownload ? ($downloadUrls[$m->id] ?? '#') : '#',
                        ];
                    })->values();
                @endphp

                {{-- Medienliste: Kacheln anklickbar → Detailansicht (Lightbox) mit Download --}}
                <section
                    class="{{ $newsItem->liveu_on_site ? 'mt-3' : 'border-t border-gray-200 pt-6 mt-6' }}"
                    x-data="{
                        overlayOpen: false,
                        overlayIndex: 0,
                        overlayItems: @js($overlayImageItems),
                        openOverlay(i) {
                            if (!this.overlayItems[i]) return;
                            this.overlayIndex = i;
                            this.overlayOpen = true;
                        },
                        closeOverlay() {
                            this.overlayOpen = false;
                        },
                        prevOverlay() {
                            if (!this.overlayItems.length) return;
                            this.overlayIndex = (this.overlayIndex - 1 + this.overlayItems.length) % this.overlayItems.length;
                        },
                        nextOverlay() {
                            if (!this.overlayItems.length) return;
                            this.overlayIndex = (this.overlayIndex + 1) % this.overlayItems.length;
                        },
                        currentOverlay() {
                            return this.overlayItems[this.overlayIndex] || null;
                        }
                    }"
                    x-effect="
                        document.body.classList.toggle('overflow-hidden', overlayOpen);
                        document.documentElement.classList.toggle('overflow-hidden', overlayOpen);
                    "
                    @keydown.escape.window="closeOverlay()"
                    @keydown.arrow-left.window="overlayOpen && prevOverlay()"
                    @keydown.arrow-right.window="overlayOpen && nextOverlay()"
                >
                        {{-- Lightbox: Bild in Großansicht (ohne Zeitfenster-Button) --}}
                        <div x-show="overlayOpen" x-cloak
                            x-transition:enter="ease-out duration-200"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            x-transition:leave="ease-in duration-150"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                            class="fixed inset-0 z-[999] flex items-center justify-center bg-black/60 backdrop-blur-sm p-3 sm:p-5"
                            @click.self="closeOverlay()">
                            <div class="koelnimage-detail-dialog relative flex items-stretch overflow-hidden rounded-xl bg-white shadow-2xl" @click.stop>
                                <div class="koelnimage-detail-media-col relative min-h-0 w-full lg:w-[60%] lg:flex-none">
                                    <div class="koelnimage-detail-image-frame">
                                        <p class="absolute inline-flex items-center rounded-md border border-red-800 bg-red-700 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-white shadow-md" style="left: 24px; top: 24px; z-index: 45;" x-show="currentOverlay() && currentOverlay().id">
                                            ID:&nbsp;<span x-text="currentOverlay() ? currentOverlay().id : ''"></span>
                                        </p>
                                        <img :src="currentOverlay()?.url || ''" :alt="currentOverlay()?.title || 'Bild'" class="overlay-main-image">
                                    </div>
                                    <div class="koelnimage-detail-footer-controls">
                                        <button
                                            type="button"
                                            @click="prevOverlay()"
                                            class="koelnimage-detail-footer-nav focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/80"
                                            aria-label="Vorheriges Bild"
                                        >
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                                <polyline points="15 18 9 12 15 6"></polyline>
                                            </svg>
                                        </button>
                                        <p class="koelnimage-detail-counter" x-text="(overlayIndex + 1) + ' / ' + overlayItems.length"></p>
                                        <button
                                            type="button"
                                            @click="nextOverlay()"
                                            class="koelnimage-detail-footer-nav focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/80"
                                            aria-label="Nächstes Bild"
                                        >
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                                <polyline points="9 18 15 12 9 6"></polyline>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <aside class="koelnimage-detail-info-col relative hidden h-full flex-col border-l border-zinc-200 bg-white lg:flex lg:w-[40%] lg:flex-none">
                                    <div class="min-h-0 overflow-y-auto pr-12">
                                        <div>
                                            <div class="mb-2 flex items-start justify-between gap-3">
                                                <p class="text-base leading-none text-amber-400">★★★★☆</p>
                                            </div>
                                            <h3 class="text-[2.25rem] font-extrabold leading-[1.15] tracking-tight text-zinc-900" x-text="(currentOverlay() && currentOverlay().caption) ? currentOverlay().caption : ((currentOverlay() && currentOverlay().title) ? currentOverlay().title : 'Bildansicht')"></h3>
                                        </div>
                                        <div class="mt-6">
                                            <p class="mb-2 text-[11px] font-bold uppercase tracking-[0.08em] text-zinc-500">Details</p>
                                            <div class="space-y-2.5 text-[17px] text-zinc-700">
                                                <p class="flex items-center gap-2.5">
                                                    <span class="inline-flex h-5 w-5 items-center justify-center text-zinc-400">◷</span>
                                                    <span>{{ $newsItem->published_at?->format('d.m.Y, H:i') }} Uhr</span>
                                                </p>
                                                @if($newsItem->location_label)
                                                    <p class="flex items-center gap-2.5">
                                                        <span class="inline-flex h-5 w-5 items-center justify-center text-zinc-400">⌖</span>
                                                        <span class="line-clamp-1">{{ $newsItem->location_label }}</span>
                                                    </p>
                                                @endif
                                                @if($photographerCredit !== '')
                                                    <p class="flex items-center gap-2.5">
                                                        <span class="inline-flex h-5 w-5 items-center justify-center text-zinc-400" aria-hidden="true">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-4 w-4">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 8.5A2.5 2.5 0 0 1 5.5 6H8l1.2-1.6A2 2 0 0 1 10.8 3.6h2.4a2 2 0 0 1 1.6.8L16 6h2.5A2.5 2.5 0 0 1 21 8.5v8A2.5 2.5 0 0 1 18.5 19h-13A2.5 2.5 0 0 1 3 16.5v-8Z"/>
                                                                <circle cx="12" cy="12.5" r="3.5" stroke-width="1.8"/>
                                                            </svg>
                                                        </span>
                                                        <span class="line-clamp-1">{{ $photographerCredit }}</span>
                                                    </p>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="mt-6 border-t border-zinc-200 pt-4">
                                            <div class="grid grid-cols-2 gap-2">
                                                <a
                                                    x-show="currentOverlay()?.download_url && currentOverlay().download_url !== '#'"
                                                    :href="currentOverlay()?.download_url"
                                                    class="inline-flex items-center justify-center rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-base font-semibold text-zinc-800 hover:bg-zinc-100"
                                                >
                                                    Download
                                                </a>
                                                <button type="button" @click="closeOverlay()" class="inline-flex items-center justify-center rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-base font-semibold text-zinc-800 hover:bg-zinc-100">Schließen</button>
                                            </div>
                                        </div>
                                    </div>
                                </aside>
                                <button
                                    type="button"
                                    class="inline-flex h-10 w-10 items-center justify-center text-lg font-semibold text-white transition focus-visible:outline-none"
                                    style="position:absolute; right:20px; top:20px; z-index:120; border:1px solid rgba(255,255,255,.4); border-radius:9999px; background:rgba(0,0,0,.55); box-shadow:0 6px 18px rgba(0,0,0,.25);"
                                    @click="closeOverlay()"
                                    aria-label="Schließen"
                                >✕</button>
                            </div>
                        </div>

                        <div x-show="!overlayOpen" x-cloak>
                        @if($firstReportUrl || $latestUpdateUrl)
                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 mb-4">
                                <p class="text-sm font-semibold text-slate-900">Schnellwechsel zwischen Erstmeldung und Updates</p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @if($firstReportUrl)
                                        <a
                                            href="{{ $firstReportUrl }}"
                                            class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-800 hover:bg-slate-100"
                                        >
                                            Zur Erstmeldung (NewsID {{ $updateReferenceNewsId }})
                                        </a>
                                    @endif
                                    @if($latestUpdateUrl)
                                        <a
                                            href="{{ $latestUpdateUrl }}"
                                            class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-800 hover:bg-slate-100"
                                        >
                                            Zum neuesten Update
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if($allowedMedia->isEmpty())
                            <p class="{{ $newsItem->liveu_on_site ? 'mt-2 text-gray-500' : 'text-gray-500' }}">Keine Medien für den Versand freigegeben.</p>
                        @else
                            {{-- Medien-Bereich: bei LiveU weniger Abstand (sonst doppelt: Section + innerer Block) --}}
                            <div class="{{ $newsItem->liveu_on_site ? 'mt-3 pt-4 border-t border-gray-200' : 'mt-10 pt-8 border-t border-gray-200' }}">
                                <h2 class="text-lg font-semibold text-ekn-900 mb-1">Medien zum Download</h2>
                                <p class="text-sm text-gray-500 mb-6">
                                    Bilder anklicken für Detailansicht. Audio und Video können direkt im Browser abgespielt werden.
                                    Download-Links sind 10 Minuten gültig. Seite neu laden erzeugt neue Links.
                                    Für mehrere Downloads bitte „Auswahl herunterladen“ nutzen (kein ZIP).
                                </p>

                                {{-- Multi-Select Download (ohne ZIP) --}}
                                <div class="flex flex-wrap items-center gap-3 mb-6">
                                    <button
                                        type="button"
                                        id="downloadSelectedBtn"
                                        class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-[#092E48] text-[#092E48] bg-white hover:bg-[#092E48]/5 transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                                    >
                                        Auswahl herunterladen (nacheinander)
                                    </button>
                                    <span id="downloadSelectedStatus" class="text-sm text-gray-500"></span>
                                </div>

                                <iframe id="download-queue-iframe" style="display:none" aria-hidden="true"></iframe>

                            {{-- Bilder --}}
                            @if($allowedImages->isNotEmpty())
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                    @foreach($allowedImages as $media)
                                        @php
                                            $hasPublic = (bool) $media->public_url;
                                            $previewUrl = $streamUrls[$media->id] ?? $media->public_url ?? $media->preview_url;
                                            $displayName = $media->display_name ?: $media->original_name ?: 'Medium #' . $media->id;
                                            $hasPreviewImage = $previewUrl !== null && $previewUrl !== '';
                                            $isNewForUpdate = $isUpdateDeliveryView && in_array((int) $media->id, $newMediaIds, true);
                                            $offerDownload = $hasPublic
                                                || (
                                                    $media->redaction_status === \App\Models\NewsItemMedia::REDACTION_FAILED
                                                    && ($media->preview_path || $media->path)
                                                );
                                            $downloadUrl = $downloadUrls[$media->id] ?? '#';
                                        @endphp
                                        <div class="border border-gray-200 rounded-lg overflow-hidden bg-white shadow-sm">
                                            <div class="aspect-video bg-gray-100 relative flex border-l-4 border-l-[#092E48] {{ $hasPreviewImage ? 'cursor-zoom-in' : '' }}"
                                                @if($hasPreviewImage) @click="openOverlay({{ $loop->index }})" @endif>
                                                @if($hasPreviewImage)
                                                <img src="{{ $previewUrl }}" alt="{{ $displayName }}" class="w-full h-full object-cover pointer-events-none">
                                                @else
                                                <div class="w-full h-full flex items-center justify-center text-slate-500 text-sm px-2">Bild wird vorbereitet</div>
                                                @endif
                                            </div>
                                            <div class="p-3 space-y-2">
                                                <p class="text-xs font-mono text-gray-500">{{ sprintf('%06d', $media->id) }}</p>
                                                @if($isUpdateDeliveryView)
                                                    <p class="text-[11px] font-semibold {{ $isNewForUpdate ? 'text-emerald-700' : 'text-slate-500' }}">
                                                        {{ $isNewForUpdate ? 'Neu in diesem Update' : 'Bereits zuvor gesendet' }}
                                                    </p>
                                                @endif
                                                <p class="text-sm font-medium text-gray-900 truncate min-w-0" title="{{ $media->display_name ?: $media->original_name }}">{{ $media->display_name ?: $media->original_name ?: 'Medium #' . $media->id }}</p>
                                                @if($media->width || $media->height)
                                                    <p class="text-xs text-gray-500">{{ $media->width }}×{{ $media->height }}</p>
                                                @endif
                                                @if($media->photographer)
                                                    <p class="text-xs text-gray-500">Fotograf: {{ $media->photographer }}</p>
                                                @endif
                                                @if($media->caption)
                                                    <p class="text-xs text-gray-500 leading-snug">
                                                        {{ \Illuminate\Support\Str::limit($media->caption, 200) }}
                                                    </p>
                                                @endif
                                                <div class="pt-2 border-t border-gray-100 flex flex-wrap gap-2 items-start">
                                                    @if($offerDownload)
                                                    <button type="button"
                                                        @click="openOverlay({{ $loop->index }})"
                                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 hover:border-[#092E48] hover:text-[#092E48] transition-colors">
                                                        Ansehen
                                                    </button>
                                                    <a href="{{ $downloadUrl }}"
                                                       class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 hover:border-[#092E48] hover:text-[#092E48] transition-colors">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                        Download
                                                    </a>
                                                    <label class="inline-flex items-center gap-2 text-xs text-gray-600 cursor-pointer">
                                                        <input
                                                            type="checkbox"
                                                            class="dl-select rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]"
                                                            data-download-url="{{ $downloadUrl }}"
                                                            value="{{ $media->id }}"
                                                        >
                                                        Auswählen
                                                    </label>
                                                    @else
                                                        @if($media->redaction_status === \App\Models\NewsItemMedia::REDACTION_PENDING)
                                                            <p class="text-xs text-slate-500">Für dieses Bild ist der Download in Kürze möglich. Bitte die Seite später erneut laden.</p>
                                                        @else
                                                            <p class="text-xs text-slate-500">Download in Kürze verfügbar. Bitte die Seite später erneut laden.</p>
                                                        @endif
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Audios vor Video (O-Töne oft gleichrangig / sichtbarer) --}}
                            @if($allowedAudios->isNotEmpty())
                                <div class="mt-6 pt-6 border-t-2 border-gray-300">
                                    <h3 class="text-base font-semibold text-ekn-900 mb-1">Audio zum Download</h3>
                                    <p class="text-sm text-gray-500 mb-4">Audio hier abspielen oder über „Download“ speichern.</p>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                        @foreach($allowedAudios as $media)
                                            @php
                                                // Nur Stream-URL: delivery.download setzt Attachment-Disposition (ungeeignet für <audio>).
                                                $audioPlayUrl = $streamUrls[$media->id] ?? '#';
                                                $isNewForUpdate = $isUpdateDeliveryView && in_array((int) $media->id, $newMediaIds, true);
                                            @endphp
                                            <div class="border border-gray-200 rounded-lg overflow-hidden bg-white shadow-sm">
                                                <div class="px-3 pt-3 pb-1 bg-gray-50 border-b border-gray-100 border-l-4 border-l-[#092E48]">
                                                    @if($audioPlayUrl !== '#')
                                                        <audio src="{{ $audioPlayUrl }}" controls preload="metadata" class="w-full h-10">
                                                            <a href="{{ $downloadUrls[$media->id] ?? '#' }}">Audio herunterladen</a>
                                                        </audio>
                                                    @else
                                                        <div class="aspect-video bg-gray-100 flex items-center justify-center">
                                                            <span class="text-gray-400" aria-hidden="true">
                                                                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/></svg>
                                                            </span>
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="p-3 space-y-2">
                                                    <p class="text-xs font-mono text-gray-500">{{ sprintf('%06d', $media->id) }}</p>
                                                    @if($isUpdateDeliveryView)
                                                        <p class="text-[11px] font-semibold {{ $isNewForUpdate ? 'text-emerald-700' : 'text-slate-500' }}">
                                                            {{ $isNewForUpdate ? 'Neu in diesem Update' : 'Bereits zuvor gesendet' }}
                                                        </p>
                                                    @endif
                                                    <p class="text-sm font-medium text-gray-900 truncate min-w-0" title="{{ $media->display_name ?: $media->original_name }}">{{ $media->display_name ?: $media->original_name ?: 'Medium #' . $media->id }}</p>
                                                    @if($media->audio_metazeile)
                                                        <p class="text-xs text-gray-500">{{ $media->audio_metazeile }}</p>
                                                    @endif
                                                    @if($media->duration_s || $media->bitrate_bps)
                                                        <p class="text-xs text-gray-500">
                                                            @if($media->duration_s) {{ number_format($media->duration_s, 1) }} s @endif
                                                            @if($media->bitrate_bps) {{ round($media->bitrate_bps / 1000) }} kbps @endif
                                                        </p>
                                                    @endif
                                                    <div class="pt-2 border-t border-gray-100">
                                                        <a href="{{ $downloadUrls[$media->id] ?? '#' }}"
                                                           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 hover:border-[#092E48] hover:text-[#092E48] transition-colors">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                            Download
                                                        </a>
                                                        <div class="mt-2">
                                                            <label class="inline-flex items-center gap-2 text-xs text-gray-600 cursor-pointer">
                                                                <input
                                                                    type="checkbox"
                                                                    class="dl-select rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]"
                                                                    data-download-url="{{ $downloadUrls[$media->id] ?? '#' }}"
                                                                    value="{{ $media->id }}"
                                                                >
                                                                Auswählen
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Video zum Download --}}
                            @if($allowedVideos->isNotEmpty())
                                <div class="mt-6 pt-6 border-t-2 border-gray-300">
                                    <h3 class="text-base font-semibold text-ekn-900 mb-1">Video zum Download</h3>
                                    <p class="text-sm text-gray-500 mb-4">Videos hier abspielen oder über „Download“ speichern.</p>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                        @foreach($allowedVideos as $media)
                                            @php
                                                $videoDownloadUrl = $downloadUrls[$media->id] ?? '#';
                                                $videoXmpDownloadUrl = $videoXmpDownloadUrls[$media->id] ?? '#';
                                                $isNewForUpdate = $isUpdateDeliveryView && in_array((int) $media->id, $newMediaIds, true);
                                            @endphp
                                            <div class="border border-gray-200 rounded-lg overflow-hidden bg-white shadow-sm">
                                                <div class="aspect-video bg-gray-900 border-l-4 border-l-[#092E48] flex items-center justify-center overflow-hidden">
                                                    {{-- Same-Origin-Stream: direkte S3-URL im <video> scheitert oft (CORS/Range) --}}
                                                    <video
                                                        src="{{ $streamUrls[$media->id] ?? '#' }}"
                                                        controls
                                                        preload="metadata"
                                                        playsinline
                                                        class="w-full h-full object-contain"
                                                        aria-label="Video: {{ $media->display_name ?: $media->original_name }}"
                                                    >
                                                        Ihr Browser unterstützt die Wiedergabe nicht.
                                                        <a href="{{ $downloadUrls[$media->id] ?? '#' }}">Video herunterladen</a>
                                                    </video>
                                                </div>
                                                <div class="p-3 space-y-2">
                                                    <p class="text-xs font-mono text-gray-500">{{ sprintf('%06d', $media->id) }}</p>
                                                    @if($isUpdateDeliveryView)
                                                        <p class="text-[11px] font-semibold {{ $isNewForUpdate ? 'text-emerald-700' : 'text-slate-500' }}">
                                                            {{ $isNewForUpdate ? 'Neu in diesem Update' : 'Bereits zuvor gesendet' }}
                                                        </p>
                                                    @endif
                                                    <p class="text-sm font-medium text-gray-900 truncate min-w-0" title="{{ $media->display_name ?: $media->original_name }}">{{ $media->display_name ?: $media->original_name ?: 'Medium #' . $media->id }}</p>
                                                    @if($media->video_metazeile)
                                                        <p class="text-xs text-gray-500">{{ $media->video_metazeile }}</p>
                                                    @endif
                                                    <div class="pt-2 border-t border-gray-100">
                                                        <a href="{{ $videoDownloadUrl }}"
                                                           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 hover:border-[#092E48] hover:text-[#092E48] transition-colors">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                            Download
                                                        </a>
                                                        <a href="{{ $videoXmpDownloadUrl }}"
                                                           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 hover:border-[#092E48] hover:text-[#092E48] transition-colors">
                                                            XMP
                                                        </a>
                                                        <div class="mt-2">
                                                            <label class="inline-flex items-center gap-2 text-xs text-gray-600 cursor-pointer">
                                                                <input
                                                                    type="checkbox"
                                                                    class="dl-select rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]"
                                                                    data-download-url="{{ $videoDownloadUrl }}"
                                                                    value="{{ $media->id }}"
                                                                >
                                                                Auswählen
                                                            </label>
                                                            <label class="inline-flex items-center gap-2 text-xs text-gray-600 cursor-pointer mt-1">
                                                                <input
                                                                    type="checkbox"
                                                                    class="dl-select rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]"
                                                                    data-download-url="{{ $videoXmpDownloadUrl }}"
                                                                    value="xmp-{{ $media->id }}"
                                                                >
                                                                XMP auswählen
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            </div>
                        @endif
                        </div>
                    </section>

                @if($delivery->confirmed_at === null)
                    {{-- Optional: Redaktion & Produkt angeben (keine Pflicht mehr vor dem Download). --}}
                    <section class="border border-ekn-200 rounded-xl bg-ekn-50/50 p-6 mt-8">
                        <h2 class="text-lg font-semibold text-ekn-900 mb-3">Redaktion &amp; Produkt (optional)</h2>
                        @if($delivery->allowed_organization_id)
                            <p class="text-sm text-gray-600 mb-4">
                                Diese Angaben helfen uns bei der Dokumentation der Nutzung.
                                Sie können die Medien auch ohne Angabe direkt herunterladen.
                            </p>
                            <form method="POST" action="{{ $confirmUrl }}" x-data="{
                                orgId: @js(old('organization_id')),
                                productId: @js(old('product_id')),
                                productsByOrg: @js($productsByOrg->map(fn($items) => $items->map(fn($p) => ['id' => $p->id, 'name' => $p->name])->values())->toArray())
                            }">
                                @csrf
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label for="organization_id" class="block text-sm font-medium text-gray-700 mb-1">Medienhaus (Redaktion)</label>
                                        <select name="organization_id" id="organization_id" required
                                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                                                x-model="orgId"
                                                x-on:change="productId = ''">
                                            <option value="">— Bitte wählen —</option>
                                            @foreach($organizations as $org)
                                                <option value="{{ $org->id }}" @selected(old('organization_id') == $org->id)>{{ $org->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('organization_id')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label for="product_id" class="block text-sm font-medium text-gray-700 mb-1">Format (Produkt)</label>
                                        <select name="product_id" id="product_id" required
                                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                                                x-model="productId">
                                            <option value="">— Zuerst Medienhaus wählen —</option>
                                            <template x-for="p in (productsByOrg[orgId] || [])" :key="p.id">
                                                <option :value="p.id" x-text="p.name"></option>
                                            </template>
                                        </select>
                                        @error('product_id')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                                <label class="inline-flex items-center gap-2 cursor-pointer mb-4 block">
                                    <input type="checkbox" name="save_as_recipient" value="1" {{ old('save_as_recipient') ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]">
                                    <span class="text-sm text-gray-700">Mich als Empfänger im System anlegen (für künftige Versände)</span>
                                </label>
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-[#092E48] text-white text-sm font-medium rounded-md hover:bg-[#0b3858]">
                                    Angaben speichern
                                </button>
                            </form>
                        @else
                            <p class="text-sm text-gray-600 mb-4">
                                Wenn Sie möchten, können Sie hier Ihr Medienhaus (Redaktion) und das Format (Produkt) angeben.
                                Diese Information ist freiwillig und dient ausschließlich unserer internen Dokumentation.
                            </p>
                            <form method="POST" action="{{ $confirmUrl }}">
                                @csrf
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label for="self_reported_organization_name" class="block text-sm font-medium text-gray-700 mb-1">Medienhaus (Redaktion)</label>
                                        <input type="text" name="self_reported_organization_name" id="self_reported_organization_name"
                                               value="{{ old('self_reported_organization_name') }}"
                                               maxlength="255" placeholder="z. B. Westdeutscher Rundfunk"
                                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                                        @error('self_reported_organization_name')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label for="self_reported_product_name" class="block text-sm font-medium text-gray-700 mb-1">Format (Produkt)</label>
                                        <input type="text" name="self_reported_product_name" id="self_reported_product_name"
                                               value="{{ old('self_reported_product_name') }}"
                                               maxlength="255" placeholder="z. B. Studio Köln"
                                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                                        @error('self_reported_product_name')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                                <label class="inline-flex items-center gap-2 cursor-pointer mb-4 block">
                                    <input type="checkbox" name="save_as_recipient" value="1" {{ old('save_as_recipient') ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]">
                                    <span class="text-sm text-gray-700">Mich als Empfänger im System anlegen (für künftige Versände)</span>
                                </label>
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-[#092E48] text-white text-sm font-medium rounded-md hover:bg-[#0b3858]">
                                    Angaben speichern
                                </button>
                            </form>
                        @endif
                        <p class="mt-3 text-xs text-gray-500">
                            Mit dem Herunterladen der Dateien erklären Sie sich damit einverstanden, dass wir die von Ihnen
                            freiwillig angegebenen Daten (z. B. Redaktion, Format) zum Zweck der Versand-Dokumentation verarbeiten.
                        </p>
                    </section>
                @endif
            </div>
        </article>
    </div>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const btn = document.getElementById('downloadSelectedBtn');
            const statusEl = document.getElementById('downloadSelectedStatus');
            const iframe = document.getElementById('download-queue-iframe');

            if (!btn || !statusEl || !iframe) return;

            const delayMs = 1400; // Browser nicht mit gleichzeitigen Downloads überlasten.

            btn.addEventListener('click', () => {
                const selected = Array.from(document.querySelectorAll('input.dl-select:checked'))
                    .map((el) => el.dataset.downloadUrl)
                    .filter((u) => typeof u === 'string' && u.length > 0 && u !== '#');

                if (selected.length === 0) {
                    statusEl.textContent = 'Bitte zuerst Dateien auswählen.';
                    return;
                }

                btn.disabled = true;
                statusEl.textContent = `Starte Download von ${selected.length} Datei(en)...`;

                let i = 0;
                const next = () => {
                    if (i >= selected.length) {
                        btn.disabled = false;
                        statusEl.textContent = 'Downloads gestartet.';
                        return;
                    }

                    iframe.src = selected[i++];
                    setTimeout(next, delayMs);
                };

                next();
            });
        });
    </script>
@endpush

@endsection
