@extends('layouts.frontend')

@section('title', $newsItem->title . ' | ' . config('app.name'))
@section('meta_description', Str::limit(strip_tags($newsItem->teaser ?: $newsItem->body), 160))
@section('canonical', route('news.show', $newsItem->slug))
@section('og_type', 'article')
@section('og_image', $newsItem->teaser_image && ($newsItem->teaser_image->public_url ?? $newsItem->teaser_image->preview_url) ? ($newsItem->teaser_image->public_url ?? $newsItem->teaser_image->preview_url) : asset('images/og-media-index.png'))

@push('head_jsonld')
@php
    $articleImage = $newsItem->teaser_image ? ($newsItem->teaser_image->public_url ?? $newsItem->teaser_image->preview_url) : null;
    if ($articleImage && !\Illuminate\Support\Str::startsWith($articleImage, ['http://', 'https://'])) {
        $articleImage = config('app.url') . '/' . ltrim($articleImage, '/');
    }
    $articleImage = $articleImage ?: asset('images/erftkreis-news-logo.png');
@endphp
<script type="application/ld+json">
{
    "@@context": "https://schema.org",
    "@@type": "NewsArticle",
    "headline": {{ json_encode($newsItem->title) }},
    "description": {{ json_encode(Str::limit(strip_tags($newsItem->teaser ?: $newsItem->body), 160)) }},
    "image": {{ json_encode($articleImage) }},
    "datePublished": "{{ $newsItem->published_at?->toIso8601String() }}",
    "dateModified": "{{ ($newsItem->updated_at ?? $newsItem->published_at)?->toIso8601String() }}",
    "author": {
        "@@type": "Person",
        "name": {{ json_encode($newsItem->author?->name ?? 'Alexander Franz') }}
    },
    "publisher": {
        "@@type": "Organization",
        "name": {{ json_encode(config('app.name', 'Erftkreis News')) }},
        "logo": {
            "@@type": "ImageObject",
            "url": {{ json_encode(asset('images/erftkreis-news-logo.png')) }}
        }
    },
    "mainEntityOfPage": {
        "@@type": "WebPage",
        "@@id": {{ json_encode(route('news.show', $newsItem->slug)) }}
    }@if($newsItem->location_label || $newsItem->city || $newsItem->region),
    "dateline": {{ json_encode($newsItem->location_label ?? trim(implode(', ', array_filter([$newsItem->city, $newsItem->region])))) }},
    "contentLocation": {
        "@@type": "Place",
        "name": {{ json_encode($newsItem->location_label ?? $newsItem->city ?? $newsItem->region ?? 'Großraum Köln/Bonn') }}
    }
    @endif
}
</script>
@endpush

@section('content')
<div class="bg-ekn-50 -mx-4 sm:-mx-6 lg:-mx-8 py-8 sm:py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10">
            <!-- LEFT -->
            <div class="lg:col-span-8 min-w-0">
            {{-- Block 1: Überschrift --}}
            <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 px-4 py-4 sm:px-6 sm:py-5 mb-4 max-w-4xl">
                @php
                    $titleParts = explode(':', $newsItem->title, 2);
                @endphp
                <h1 class="text-xl sm:text-2xl font-semibold text-ekn-900 leading-snug">
                    @if(count($titleParts) === 2)
                        <span>{{ e($titleParts[0]) }}:</span>
                        <span class="sm:block"> {{ e(trim($titleParts[1])) }}</span>
                    @else
                        {{ $newsItem->title }}
                    @endif
                </h1>
            </div>

            {{-- Block 2: Teaserbild + Inhalt --}}
            <article class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 overflow-hidden max-w-4xl">
    <div class="p-4 sm:p-6">
        @if($newsItem->teaser_image)
            <figure class="mb-6 relative">
                <div class="relative rounded-lg overflow-hidden border border-gray-200">
                    @php $teaserImageUrl = $newsItem->teaser_image->public_url ?? $newsItem->teaser_image->preview_url; $imgAlt = trim($newsItem->teaser_image->display_name ?? $newsItem->teaser_image->image_title ?? $newsItem->title ?? ''); @endphp
                    @if($teaserImageUrl)
                    <img
                        src="{{ $teaserImageUrl }}"
                        alt="{{ $imgAlt ?: 'Teaser: ' . $newsItem->title }}"
                        title="{{ $imgAlt ?: $newsItem->title }}"
                        class="w-full max-h-96 object-cover"
                    >
                    <span class="absolute inset-0 flex items-center justify-center pointer-events-none" aria-hidden="true">
                        <img src="{{ asset('images/erftkreis-news-logo.png') }}?v=2" alt="" title="Erftkreis News" class="max-w-[10%] max-h-[10%] w-auto h-auto object-contain opacity-20">
                    </span>
                    @else
                    <div class="w-full aspect-video max-h-96 bg-slate-200 flex items-center justify-center text-slate-500 text-sm">Bild wird vorbereitet</div>
                    @endif
                </div>
                @if($newsItem->teaser_image->caption)
                    <figcaption class="mt-2 text-sm text-gray-500">{{ $newsItem->teaser_image->caption }}</figcaption>
                @endif
            </figure>
        @endif

        <div class="mt-4">
            <div class="flex items-baseline justify-between gap-4 text-sm text-slate-600">
                <div>
                    Datum: {{ $newsItem->published_at?->format('d.m.Y, H:i') }} Uhr
                </div>
                <div class="text-right">
                    NEWSID: {{ $newsItem->id }}
                    @if($newsItem->location_label)
                        - {{ $newsItem->location_label }}
                    @endif
                </div>
            </div>

            @if($newsItem->subheadline)
                <div class="mt-4 font-semibold text-ekn-900">
                    {{ $newsItem->subheadline }}
                </div>
            @endif
        </div>

        {{-- space-y: Preflight setzt p-Margin auf 0; @tailwindcss/typography ist im Frontend oft nicht aktiv --}}
        <div class="prose prose-gray max-w-none text-gray-700 mt-4 space-y-4">
            {!! $newsItem->bodyHtmlForWeb() !!}
        </div>

        @php
            // Öffentliche Galerie: redigierte URL bevorzugen, sonst Vorschau (z. B. solange Kennzeichnung läuft).
            // Nur public_url war zu streng – dann erschien die Galerie leer, obwohl Vorschau existierte.
            $galleryImages = $newsItem->images->where('versand', true)->filter(
                fn ($img) => $img->public_url || $img->preview_url
            );
            $galleryDisplayUrl = static fn ($img) => $img->public_url ?? $img->preview_url;
        @endphp

        @if($galleryImages->count())
        <div
            x-data="{
                open: false,
                index: 0,
                images: @js($galleryImages->values()->map(fn($img) => [
                    'url' => $galleryDisplayUrl($img),
                    'caption' => $img->caption
                ]))
            }"
            class="mt-10"
        >

            <!-- Thumbnail Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                @foreach($galleryImages as $i => $img)
                    <div
                        class="relative rounded-lg overflow-hidden border border-slate-200 cursor-pointer hover:shadow-md transition"
                        @click="open = true; index = {{ $loop->index }}"
                    >
                        <img
                            src="{{ $galleryDisplayUrl($img) }}"
                            class="w-full aspect-[16/10] object-cover"
                            loading="lazy"
                        >

                        <!-- Wasserzeichen -->
                        <span class="absolute inset-0 flex items-center justify-center pointer-events-none">
                            <img
                                src="{{ asset('images/erftkreis-news-logo.png') }}?v=2"
                                class="max-w-[12%] max-h-[12%] opacity-20"
                            >
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
                class="fixed inset-0 z-50 bg-black/90"
            >
                <!-- Center stage wrapper -->
                <div class="absolute inset-0 flex items-center justify-center p-4 sm:p-8">
                    <div class="relative w-full max-w-6xl" @click.stop>

                        <!-- Image stage -->
                        <div class="relative rounded-2xl overflow-hidden bg-black/30 ring-1 ring-white/10">
                            <img
                                :src="images[index].url"
                                class="w-full max-h-[82vh] object-contain"
                                alt=""
                            >

                            <!-- watermark (dezent, blockiert keine clicks) -->
                            <span class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                <img
                                    src="{{ asset('images/erftkreis-news-logo.png') }}?v=2"
                                    class="max-w-[10%] max-h-[10%] opacity-15"
                                    alt=""
                                >
                            </span>
                        </div>

                        <!-- Caption -->
                        <template x-if="images[index].caption">
                            <div class="mt-3 text-center text-sm text-white/80" x-text="images[index].caption"></div>
                        </template>

                        <!-- Close button -->
                        <button
                            type="button"
                            @click="open=false"
                            class="absolute -top-3 -right-3 sm:top-0 sm:right-0 translate-y-0 translate-x-0
                                   w-11 h-11 rounded-full bg-black/50 hover:bg-black/70 text-white
                                   flex items-center justify-center text-3xl leading-none ring-1 ring-white/10"
                            aria-label="Schließen"
                        >&times;</button>

                        <!-- Prev/Next controls (eigene Ebene, garantiert klickbar) -->
                        <div class="pointer-events-none">
                            <!-- Prev -->
                            <button
                                type="button"
                                @click="index = (index - 1 + images.length) % images.length"
                                class="pointer-events-auto absolute left-0 top-1/2 -translate-y-1/2
                                       w-12 h-12 sm:w-14 sm:h-14 rounded-full bg-black/50 hover:bg-black/70 text-white
                                       flex items-center justify-center ring-1 ring-white/10"
                                aria-label="Vorheriges Bild"
                            >
                                <span class="text-3xl leading-none">&#10094;</span>
                            </button>

                            <!-- Next -->
                            <button
                                type="button"
                                @click="index = (index + 1) % images.length"
                                class="pointer-events-auto absolute right-0 top-1/2 -translate-y-1/2
                                       w-12 h-12 sm:w-14 sm:h-14 rounded-full bg-black/50 hover:bg-black/70 text-white
                                       flex items-center justify-center ring-1 ring-white/10"
                                aria-label="Nächstes Bild"
                            >
                                <span class="text-3xl leading-none">&#10095;</span>
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($galleryImages->count())
            <div class="mt-8 text-sm text-gray-500">
                @if($newsItem->author_credit || $newsItem->author)
                    <p>
                        Quelle:
                        @if($newsItem->author_credit)
                            {{ $newsItem->author_credit }}
                        @elseif($newsItem->author)
                            {{ $newsItem->author->name }}
                        @endif
                    </p>
                @endif

                <div class="mt-4 flex items-center justify-between gap-4 text-sm text-gray-500">
                    <span>
                        Die Bilder sind honorarpflichtig zzgl. 7 % MwSt. Alle Rechte vorbehalten.
                    </span>

                    <a href="{{ route('home') }}"
                       class="text-[#092E48] font-medium hover:underline whitespace-nowrap">
                        ← Zurück zur Übersicht
                    </a>
                </div>
            </div>
        @endif
    </div>
</article>
        </div>

            <!-- RIGHT SIDEBAR -->
            <aside class="lg:col-span-4 space-y-5">
                <!-- Redaktionsdesk -->
                <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-5 sm:p-6 text-center">
                    <h3 class="text-sm font-semibold text-ekn-900 uppercase tracking-wide mb-2">
                        Redaktionsdesk (24h)
                    </h3>
                    <p class="text-slate-700 text-sm">
                        Ansprechpartner für Redaktionen und Sender<br>
                        <span class="font-semibold text-ekn-900">24h‑Hotline: 02236 480 9488</span>
                    </p>
                </div>

                <!-- Kundenlogin -->
                <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-5 sm:p-6">
                    <h4 class="text-sm font-semibold text-ekn-900 uppercase tracking-wide mb-3">
                        Kundenlogin
                    </h4>

                    <a href="{{ route('login') }}"
                       class="block w-full text-center px-4 py-2.5 bg-ekn-900 text-white text-sm font-medium rounded-xl hover:bg-ekn-800 transition">
                        Anmelden
                    </a>
                </div>

            </aside>
        </div>
    </div>
</div>
@endsection
