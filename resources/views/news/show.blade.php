@extends('layouts.frontend')

@php
    $hasArticleMedia = $newsItem->hasPublicPortalMedia();
    $appName = config('app.name', 'Erftkreis News');
    $siteSuffix = ' | ' . $appName;
    $materialTitlePhrase = ' — Bild- und Videomaterial verfügbar';
    $maxTitleTotal = 70;
    $reservedTitle = mb_strlen($siteSuffix) + ($hasArticleMedia ? mb_strlen($materialTitlePhrase) : 0);
    $maxHeadlineChars = max(18, $maxTitleTotal - $reservedTitle);
    $seoTitleHeadline = \Illuminate\Support\Str::limit(trim((string) $newsItem->title), $maxHeadlineChars, '…');
    $seoPageTitle = $seoTitleHeadline . ($hasArticleMedia ? $materialTitlePhrase : '') . $siteSuffix;
    if (mb_strlen($seoPageTitle) > $maxTitleTotal) {
        $seoPageTitle = mb_strimwidth($seoPageTitle, 0, $maxTitleTotal, '…', 'UTF-8');
    }

    $placeLabel = trim((string) ($newsItem->location_label ?? ''));
    if ($placeLabel === '') {
        $placeLabel = trim(implode(', ', array_filter([$newsItem->city, $newsItem->region])));
    }
    $teaserPlain = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($newsItem->teaser ?? ''))));
    $bodyPlain = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($newsItem->body ?? ''))));
    $contentHint = $teaserPlain !== '' ? $teaserPlain : $bodyPlain;
    $contentHint = \Illuminate\Support\Str::limit($contentHint, 95, '…');

    $eventForMeta = \Illuminate\Support\Str::limit(trim((string) $newsItem->title), 72, '…');
    $seoMetaDescription = $eventForMeta;
    $seoMetaDescription .= $placeLabel !== '' ? ' — ' . $placeLabel . '.' : '.';
    $seoMetaDescription .= $contentHint !== '' ? ' ' . $contentHint : '';
    $seoMetaDescription .= $hasArticleMedia ? ' Bild- und Videomaterial für Redaktionen auf Anfrage.' : '';
    $seoMetaDescription .= ' Für Medienpartner: B2B-Informationen und redaktionelle Nutzung.';
    $seoMetaDescription = trim(preg_replace('/\s+/u', ' ', $seoMetaDescription));
    if (mb_strlen($seoMetaDescription) > 160) {
        $seoMetaDescription = \Illuminate\Support\Str::limit($seoMetaDescription, 160, '…');
    }
    if (mb_strlen($seoMetaDescription) < 140) {
        $seoMetaDescription = trim($seoMetaDescription . ' Ereignisbezogene Berichte aus Köln, Bonn und dem Rhein-Erft-Kreis.');
        if (mb_strlen($seoMetaDescription) > 160) {
            $seoMetaDescription = \Illuminate\Support\Str::limit($seoMetaDescription, 160, '…');
        }
    }
@endphp

@section('title', $seoPageTitle)
@section('meta_description', $seoMetaDescription)
@section('canonical', route('news.show', $newsItem->slug))
@section('og_type', 'article')
@section('og_image', $newsItem->teaser_image_for_public && ($newsItem->teaser_image_for_public->public_url ?? $newsItem->teaser_image_for_public->preview_url) ? ($newsItem->teaser_image_for_public->public_url ?? $newsItem->teaser_image_for_public->preview_url) : asset('images/og-media-index.png'))

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
    .koelnimage-detail-image-frame > img.lightbox-main-image {
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
        .koelnimage-detail-image-frame > img.lightbox-main-image {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
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

@push('head_jsonld')
@php
    $articleImage = $newsItem->teaser_image_for_public ? ($newsItem->teaser_image_for_public->public_url ?? $newsItem->teaser_image_for_public->preview_url) : null;
    if ($articleImage && !\Illuminate\Support\Str::startsWith($articleImage, ['http://', 'https://'])) {
        $articleImage = config('app.url') . '/' . ltrim($articleImage, '/');
    }
    $articleImage = $articleImage ?: asset('images/erftkreis-news-logo.png');
    $datelineForJsonLd = $newsItem->location_label ?? trim(implode(', ', array_filter([
        $newsItem->city,
        $newsItem->region,
    ])));
@endphp
<script type="application/ld+json">
{
    "@@context": "https://schema.org",
    "@@type": "NewsArticle",
    "headline": @json($newsItem->title, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    "description": @json($seoMetaDescription, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    "image": @json($articleImage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    "datePublished": "{{ $newsItem->published_at?->toIso8601String() }}",
    "dateModified": "{{ ($newsItem->updated_at ?? $newsItem->published_at)?->toIso8601String() }}",
    "author": {
        "@@type": "Person",
        "name": @json($newsItem->author?->name ?? 'Alexander Franz', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    },
    "publisher": {
        "@@type": "Organization",
        "name": @json(config('app.name', 'Erftkreis News'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        "logo": {
            "@@type": "ImageObject",
            "url": @json(asset('images/erftkreis-news-logo.png'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        }
    },
    "mainEntityOfPage": {
        "@@type": "WebPage",
        "@@id": @json(route('news.show', $newsItem->slug), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    }@if($newsItem->location_label || $newsItem->city || $newsItem->region),
    "dateline": @json($datelineForJsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    "contentLocation": {
        "@@type": "Place",
        "name": @json($newsItem->location_label ?? $newsItem->city ?? $newsItem->region ?? 'Großraum Köln/Bonn', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    }
    @endif
}
</script>
@endpush

@section('content')
<div class="bg-ekn-50 -mx-4 sm:-mx-6 lg:-mx-8 py-8 sm:py-10 min-w-0 max-w-full">
    <div class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 min-w-0">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10 min-w-0">
            <!-- LEFT -->
            <div class="lg:col-span-8 min-w-0 max-w-full">
            @php
                $altForNewsMedia = function ($medium) use ($newsItem): string {
                    if (! $medium) {
                        $t = trim((string) ($newsItem->title ?? ''));
                        if ($t !== '') {
                            return $t;
                        }
                        if ($newsItem->location_label) {
                            return trim((string) $newsItem->location_label);
                        }

                        return trim(str_replace('-', ' ', (string) $newsItem->slug));
                    }
                    $label = trim((string) ($medium->display_name ?? $medium->image_title ?? ''));
                    if ($label !== '') {
                        return $label;
                    }
                    $segments = [];
                    foreach ([
                        trim((string) ($newsItem->title ?? '')),
                        $newsItem->location_label ? trim((string) $newsItem->location_label) : '',
                    ] as $v) {
                        if ($v === '') {
                            continue;
                        }
                        if (! in_array($v, $segments, true)) {
                            $segments[] = $v;
                        }
                    }
                    if ($segments !== []) {
                        return implode(' – ', $segments);
                    }

                    return trim(str_replace('-', ' ', (string) $newsItem->slug));
                };
            @endphp
            {{-- Block 1: Überschrift --}}
            <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 px-4 py-4 sm:px-6 sm:py-5 mb-4 w-full max-w-4xl xl:max-w-5xl mx-auto min-w-0">
                @php
                    // Split only when ":" is used as a textual separator (e.g. "Titel: Untertitel"),
                    // not for times like "19:45".
                    $titleParts = preg_split('/:\h+/', $newsItem->title, 2);
                @endphp
                <h1 class="text-xl sm:text-2xl font-semibold text-ekn-900 leading-snug break-words">
                    @if(count($titleParts) === 2)
                        <span>{{ e($titleParts[0]) }}:</span>
                        <span class="sm:block"> {{ e(trim($titleParts[1])) }}</span>
                    @else
                        {{ $newsItem->title }}
                    @endif
                </h1>
                @if($hasArticleMedia)
                    <p class="mt-3 text-sm text-slate-700 break-words">
                        📸 Bild- und Videomaterial zu diesem Einsatz verfügbar
                    </p>
                @endif
            </div>

            {{-- Block 2: Teaserbild + Inhalt --}}
            <article class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 overflow-hidden w-full max-w-4xl xl:max-w-5xl mx-auto min-w-0">
    <div class="p-4 sm:p-6">
        @php
            $galleryImages = $newsItem->sortGalleryImagesByCaptureTime(
                $newsItem->publicPortalImages()->where('versand', true)->filter(
                    fn ($img) => $img->public_url || $img->preview_url
                ),
                newestFirst: false
            );
            $galleryDisplayUrl = static fn ($img) => $img->public_url ?? $img->preview_url;
            $showFollowupSection = $hasArticleMedia || $galleryImages->count() > 0;
            $photographerCredit = trim((string) ($newsItem->author_credit ?? ''));
            if ($photographerCredit === '' && $newsItem->author) {
                $photographerCredit = trim((string) $newsItem->author->name);
            }
            $h2EinsatzId = 'h2-einsatz-' . $newsItem->id;
            $h2MaterialId = 'h2-material-' . $newsItem->id;
            $h2EinsatzText = $newsItem->location_article_headline;
        @endphp

        <section aria-labelledby="{{ $h2EinsatzId }}" class="min-w-0">
        <h2 id="{{ $h2EinsatzId }}" class="text-lg sm:text-xl font-semibold text-ekn-900 mb-4 break-words">{{ $h2EinsatzText }}</h2>
        @if($newsItem->teaser_image_for_public)
            <figure class="mb-6 relative">
                <div class="relative rounded-lg overflow-hidden border border-gray-200">
                    @php
                        $teaserImageUrl = $newsItem->teaser_image_for_public->public_url ?? $newsItem->teaser_image_for_public->preview_url;
                        $imgAltFinal = $altForNewsMedia($newsItem->teaser_image_for_public);
                    @endphp
                    @if($teaserImageUrl)
                    <img
                        src="{{ $teaserImageUrl }}"
                        alt="{{ $imgAltFinal }}"
                        title="{{ $imgAltFinal }}"
                        class="w-full max-w-full h-auto max-h-96 object-cover"
                    >
                    <span class="absolute inset-0 flex items-center justify-center pointer-events-none" aria-hidden="true">
                        <img src="{{ asset('images/erftkreis-news-logo.png') }}?v=2" alt="" aria-hidden="true" title="Erftkreis News" class="max-w-[10%] max-h-[10%] w-auto h-auto object-contain opacity-20">
                    </span>
                    @else
                    <div class="w-full aspect-video max-h-96 bg-slate-200 flex items-center justify-center text-slate-500 text-sm">Bild wird vorbereitet</div>
                    @endif
                </div>
                @if($newsItem->teaser_image_for_public->caption)
                    <figcaption class="mt-2 text-sm text-gray-500 break-words">{{ $newsItem->teaser_image_for_public->caption }}</figcaption>
                @endif
            </figure>
        @endif

        <div class="mt-4 min-w-0">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4 text-sm text-slate-600">
                <div class="min-w-0 break-words">
                    Datum: {{ $newsItem->published_at?->format('d.m.Y, H:i') }} Uhr
                </div>
                <div class="sm:text-right min-w-0 break-words">
                    NEWSID: {{ $newsItem->id }}
                    @if($newsItem->location_label)
                        - {{ $newsItem->location_label }}
                    @endif
                </div>
            </div>

            @if($newsItem->subheadline)
                <div class="mt-4 font-semibold text-ekn-900 break-words">
                    {{ $newsItem->subheadline }}
                </div>
            @endif
        </div>

        {{-- space-y: Preflight setzt p-Margin auf 0; @tailwindcss/typography ist im Frontend oft nicht aktiv --}}
        <div class="prose prose-gray max-w-none text-gray-700 mt-4 space-y-4 min-w-0 [overflow-wrap:anywhere] [&_img]:max-w-full [&_img]:h-auto [&_picture]:block [&_picture]:max-w-full [&_video]:max-w-full [&_video]:h-auto [&_iframe]:max-w-full [&_svg]:max-w-full [&_table]:block [&_table]:w-full [&_table]:max-w-full [&_table]:overflow-x-auto [&_pre]:max-w-full [&_pre]:overflow-x-auto [&_pre]:text-sm [&_code]:break-words [&_blockquote]:break-words">
            {!! $newsItem->bodyHtmlForWeb() !!}
        </div>
        </section>

        @if($showFollowupSection)
        <section aria-labelledby="{{ $h2MaterialId }}" class="mt-10 min-w-0">
        <h2 id="{{ $h2MaterialId }}" class="text-lg sm:text-xl font-semibold text-ekn-900 mb-4 break-words">Bild- und Videomaterial zum Einsatz verfügbar</h2>
        <div class="space-y-2 text-sm text-slate-700 break-words mb-6">
            <p>Redaktionen erhalten auf Anfrage kurzfristig Bild- und Videomaterial zu diesem Einsatz.</p>
            <p class="font-medium text-ekn-900">02236 4809 488</p>
            <p>
                <a href="{{ route('neukunden') }}" class="inline-flex min-h-[44px] items-center text-ekn-900 text-sm font-medium hover:underline focus:outline-none focus:ring-2 focus:ring-ekn-900 focus:ring-offset-2 rounded py-1 -my-1">
                    Material anfragen zu diesem Einsatz
                </a>
            </p>
        </div>

        @if($galleryImages->count())
        <div
            x-data="{
                open: false,
                index: 0,
                articleAltFallback: @js(trim((string) ($newsItem->title ?? '')) ?: trim(str_replace('-', ' ', (string) $newsItem->slug))),
                images: @js($galleryImages->values()->map(fn($img) => [
                    'url' => $galleryDisplayUrl($img),
                    'caption' => $img->caption,
                    'alt' => $altForNewsMedia($img),
                ]))
            }"
            class="mt-8 min-w-0 max-w-full"
        >

            <!-- Thumbnail Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 sm:gap-4 min-w-0">
                @foreach($galleryImages as $i => $img)
                    <div
                        class="relative rounded-lg overflow-hidden border border-slate-200 cursor-pointer hover:shadow-md transition"
                        @click="open = true; index = {{ $loop->index }}"
                    >
                        <img
                            src="{{ $galleryDisplayUrl($img) }}"
                            alt="{{ $altForNewsMedia($img) }}"
                            class="w-full max-w-full aspect-[16/10] object-cover"
                            loading="lazy"
                        >

                        <!-- Wasserzeichen -->
                        <span class="absolute inset-0 flex items-center justify-center pointer-events-none" aria-hidden="true">
                            <img src="{{ asset('images/erftkreis-news-logo.png') }}?v=2" alt="" aria-hidden="true" class="max-w-[12%] max-h-[12%] opacity-20">
                        </span>
                    </div>
                @endforeach
            </div>

            <!-- Lightbox -->
            <div
                x-show="open"
                x-cloak
                x-transition.opacity
                @keydown.escape.window="open=false"
                @keydown.arrow-left.window="index = (index - 1 + images.length) % images.length"
                @keydown.arrow-right.window="index = (index + 1) % images.length"
                @click.self="open=false"
                class="fixed inset-0 z-[100] flex items-center justify-center bg-black backdrop-blur-[1px] p-3 sm:p-5"
            >
                <div class="koelnimage-detail-dialog relative flex items-stretch overflow-hidden rounded-xl bg-white shadow-2xl" @click.stop>
                    <div class="koelnimage-detail-media-col relative min-h-0 w-full lg:w-[60%] lg:flex-none">
                        <div class="koelnimage-detail-image-frame">
                            <img
                                :src="images[index].url"
                                :alt="(images[index].alt && String(images[index].alt).trim()) ? images[index].alt : articleAltFallback"
                                class="lightbox-main-image"
                            >
                            <span class="absolute inset-0 flex items-center justify-center pointer-events-none" aria-hidden="true">
                                <img src="{{ asset('images/erftkreis-news-logo.png') }}?v=2" class="max-w-[10%] max-h-[10%] opacity-15" alt="" aria-hidden="true">
                            </span>
                        </div>
                        <div class="koelnimage-detail-footer-controls">
                            <button
                                type="button"
                                @click="index = (index - 1 + images.length) % images.length"
                                class="koelnimage-detail-footer-nav focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/80"
                                aria-label="Vorheriges Bild"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                    <polyline points="15 18 9 12 15 6"></polyline>
                                </svg>
                            </button>
                            <p class="koelnimage-detail-counter" x-text="(index + 1) + ' / ' + images.length"></p>
                            <button
                                type="button"
                                @click="index = (index + 1) % images.length"
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
                                <h3 class="text-[2.25rem] font-extrabold leading-[1.15] tracking-tight text-zinc-900" x-text="images[index].caption || ((images[index].alt && String(images[index].alt).trim()) ? images[index].alt : articleAltFallback)"></h3>
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
                                <button type="button" class="w-full rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-base font-semibold text-zinc-800 hover:bg-zinc-100" @click="open=false">Schließen</button>
                            </div>
                        </div>
                    </aside>
                    <button
                        type="button"
                        @click="open=false"
                        class="inline-flex h-10 w-10 items-center justify-center text-lg font-semibold text-white transition focus-visible:outline-none"
                        style="position:absolute; right:20px; top:20px; z-index:120; border:1px solid rgba(255,255,255,.4); border-radius:9999px; background:rgba(0,0,0,.55); box-shadow:0 6px 18px rgba(0,0,0,.25);"
                        aria-label="Schließen"
                    >✕</button>
                </div>
            </div>
        </div>
        @endif

        @if($galleryImages->count())
            <div class="mt-8 text-sm text-gray-500">
                @if($newsItem->author_credit || $newsItem->author)
                    <p class="break-words">
                        Quelle:
                        @if($newsItem->author_credit)
                            {{ $newsItem->author_credit }}
                        @elseif($newsItem->author)
                            {{ $newsItem->author->name }}
                        @endif
                    </p>
                @endif

                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4 text-sm text-gray-500 min-w-0">
                    <span class="break-words">
                        Die Bilder sind honorarpflichtig zzgl. 7 % MwSt. Alle Rechte vorbehalten.
                    </span>

                    <a href="{{ route('home') }}"
                       class="inline-flex min-h-[44px] shrink-0 items-center justify-center sm:justify-end text-[#092E48] font-medium hover:underline text-center sm:text-right">
                        ← Zurück zur Übersicht
                    </a>
                </div>
            </div>
        @endif
        </section>
        @endif
    </div>
</article>
        </div>

            <!-- RIGHT SIDEBAR -->
            <aside class="lg:col-span-4 space-y-5 min-w-0 max-w-full">
                <!-- Redaktionsdesk -->
                <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-5 sm:p-6 text-center min-w-0 break-words">
                    <h3 class="text-sm font-semibold text-ekn-900 uppercase tracking-wide mb-2">
                        Redaktionsdesk (24h)
                    </h3>
                    <p class="text-slate-700 text-sm">
                        Ansprechpartner für Redaktionen und Sender<br>
                        <span class="font-semibold text-ekn-900">24h‑Hotline: 02236 4809 488</span>
                    </p>
                </div>

                <!-- Kundenlogin -->
                <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-5 sm:p-6 space-y-3 min-w-0">
                    <h4 class="text-sm font-semibold text-ekn-900 uppercase tracking-wide mb-1">
                        Kundenlogin
                    </h4>
                    <p class="text-xs text-slate-600">Noch kein Zugang? Als Redaktion oder Sender registrieren lassen.</p>
                    <a href="{{ route('login') }}"
                       class="inline-flex w-full min-h-[44px] items-center justify-center text-center px-4 py-2.5 bg-ekn-900 text-white text-sm font-medium rounded-xl hover:bg-ekn-800 transition">
                        Anmelden
                    </a>
                    <a href="{{ route('neukunden') }}"
                       class="inline-flex w-full min-h-[44px] items-center justify-center text-center px-4 py-2.5 border border-ekn-900 text-ekn-900 text-sm font-medium rounded-xl hover:bg-ekn-50 transition">
                        Neukunde werden
                    </a>
                </div>

            </aside>
        </div>
    </div>
</div>
@endsection
