@extends('layouts.frontend')

@section('title', 'Erftkreis News Media – Bilder und Videos für Redaktionen')
@section('meta_description', 'Erftkreis News Media: aktuelles Bild- und Videomaterial für Redaktionen aus Köln, Bonn und dem Rhein-Erft-Kreis – produziert von Alexander Franz.')
@section('og_image', asset('images/og-media-index.png'))
@section('canonical', route('home'))

@push('head_jsonld')
<script type="application/ld+json">
{
    "@@context": "https://schema.org",
    "@@type": "WebSite",
    "name": "{{ e(config('app.name', 'Erftkreis News')) }}",
    "url": "{{ e(route('home')) }}",
    "description": "Erftkreis News Media – aktuelles Bild- und Videomaterial für Redaktionen im Großraum Köln/Bonn.",
    "inLanguage": "de-DE",
    "areaServed": [
        { "@@type": "City", "name": "Köln" },
        { "@@type": "City", "name": "Bonn" },
        { "@@type": "AdministrativeArea", "name": "Rhein-Erft-Kreis" },
        { "@@type": "AdministrativeArea", "name": "Großraum Köln/Bonn" }
    ]
}
</script>
@endpush

@section('content')
<div class="bg-ekn-50 -mx-4 sm:-mx-6 lg:-mx-8 py-8 sm:py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Intro für Medienpartner --}}
        <div class="bg-white rounded-2xl ring-1 ring-slate-200 px-5 py-4 sm:px-6 sm:py-5 mb-4 flex flex-col gap-1.5">
            <span class="inline-flex items-center text-[11px] font-semibold tracking-[0.3em] uppercase text-ekn-900/60">
                Medienportal
            </span>
            <h1 class="text-xl sm:text-2xl font-semibold text-ekn-900">
                Das Medienportal von Erftkreis News
            </h1>
        </div>

        <div class="bg-white rounded-2xl ring-1 ring-slate-200 px-5 py-5 sm:px-6 sm:py-6 mb-8">
            <div class="flex flex-col sm:flex-row gap-6 sm:items-start">
                <div class="sm:flex-1 flex flex-col gap-3">
                    <p class="text-sm sm:text-base text-slate-700 max-w-3xl">
                        Wenn in Köln, Bonn oder im Rhein‑Erft‑Kreis etwas Relevantes passiert – ob größerer Polizei‑ oder Feuerwehreinsatz,
                        Sportereignis, Veranstaltung, Kultur oder Karneval – zählt für Redaktionen vor allem eines:
                        schnell verfügbares und verlässliches Bild‑ und Videomaterial.
                    </p>
            <p class="text-sm sm:text-base text-slate-700 max-w-3xl">
                Ich bin Bild- und Videojournalist Alexander Franz und begleite Einsätze, Veranstaltungen und Ereignisse im Großraum Köln/Bonn
                regelmäßig direkt vor Ort – von Blaulichtlagen über Sport bis hin zu Kultur und Karneval. Mein Material entsteht nah am Geschehen,
                journalistisch sauber recherchiert und redaktionell verwertbar aufbereitet.
            </p>
                    <p class="text-sm sm:text-base text-slate-700 max-w-3xl">
                        Über diese Seite biete ich Redaktionen laufend aktuelles Bild- und Videomaterial zu Einsätzen von Polizei und Feuerwehr, Unfällen, Großlagen und Veranstaltungen im Raum Köln/Bonn und Rhein‑Erft‑Kreis an. So lassen sich Beiträge schnell bebildern, ohne auf ein eigenes Team vor Ort angewiesen zu sein.
                    </p>
                </div>
                <div class="sm:w-64 flex sm:justify-end">
                    <figure class="w-full rounded-2xl overflow-hidden ring-1 ring-slate-200 bg-slate-100 mx-auto sm:mx-0">
                        <img
                            src="{{ asset('images/alexander-franz-portrait.png') }}"
                            alt="Bild- und Videojournalist Alexander Franz im Einsatz"
                            class="w-full h-full object-cover object-top max-h-64"
                            loading="eager"
                            decoding="sync"
                        >
                        <figcaption class="px-3 py-2 text-xs text-slate-600 bg-white/80">
                            Alexander Franz beim 24‑Stunden‑Rennen am Nürburgring.
                        </figcaption>
                    </figure>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10">
            {{-- Links: Feed-Stream --}}
            <div class="lg:col-span-8 space-y-8 min-w-0">
                @forelse($news as $item)
                @php $newsShowUrl = route('news.show', $item->slug); @endphp
                <article class="block bg-white rounded-2xl ring-1 ring-slate-200 p-6 sm:p-7 hover:ring-ekn-900 transition">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-5 sm:gap-6">
                        @if($item->teaser_image)
                        <a href="{{ $newsShowUrl }}" class="sm:shrink-0 w-full sm:w-44 cursor-pointer rounded-xl focus:outline-none focus:ring-2 focus:ring-ekn-900 focus:ring-offset-2">
                            @php
                                $teaserImageUrl = $item->teaser_image->thumb_url ?: $item->teaser_image->preview_url;
                                $teaserAlt = trim($item->teaser_image->display_name ?? $item->teaser_image->image_title ?? $item->title ?? '');
                            @endphp
                            <div class="relative w-full overflow-hidden rounded-xl ring-1 ring-slate-200 bg-slate-100" style="padding-bottom: 66.6667%; height: 0;">
                            @if($teaserImageUrl)
                            <img
                                src="{{ $teaserImageUrl }}"
                                alt="{{ $teaserAlt ?: 'Teaser: ' . $item->title }}"
                                title="{{ $teaserAlt ?: $item->title }}"
                                class="absolute inset-0 w-full h-full object-cover object-center"
                                loading="eager"
                                decoding="sync"
                            >
                            <span class="absolute inset-0 flex items-center justify-center pointer-events-none" aria-hidden="true">
                                <img src="{{ asset('images/erftkreis-news-logo.png') }}?v=2" alt="" title="Erftkreis News" class="max-w-[14%] max-h-[14%] w-auto h-auto object-contain opacity-[0.35]">
                            </span>
                            @else
                            <div class="absolute inset-0 flex items-center justify-center text-slate-400 text-xs">Bild wird vorbereitet</div>
                            @endif
                            </div>
                        </a>
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="text-xs uppercase text-slate-500 mb-2">
                                {{ $item->published_at?->format('d.m.Y, H:i') }} · NEWSID: {{ $item->id }}@if($item->location_label) · {{ $item->location_label }}@endif
                            </p>
                            <div class="flex flex-wrap gap-2 mb-3">
                                @if($item->images->count() > 0)
                                    <span class="inline-flex items-center rounded-full bg-ekn-900/10 text-ekn-900 px-2.5 py-0.5 text-xs font-medium">Foto</span>
                                @endif
                                @if($item->videos->count() > 0)
                                    <span class="inline-flex items-center rounded-full bg-ekn-900/10 text-ekn-900 px-2.5 py-0.5 text-xs font-medium">Video</span>
                                @endif
                                @if($item->audios->count() > 0)
                                    <span class="inline-flex items-center rounded-full bg-ekn-900/10 text-ekn-900 px-2.5 py-0.5 text-xs font-medium">Audio</span>
                                @endif
                            </div>
                            <h2 class="text-lg sm:text-xl font-semibold text-ekn-900 mb-2 leading-snug">
                                <a href="{{ $newsShowUrl }}" class="text-ekn-900 hover:underline focus:outline-none focus:ring-2 focus:ring-ekn-900 focus:ring-offset-2 rounded">{{ $item->title }}</a>
                            </h2>
                            <p class="text-slate-600 text-sm sm:text-base line-clamp-2 leading-relaxed">{!! Str::limit(strip_tags($item->teaser ?: $item->body), 160) !!}</p>
                            <p class="mt-4">
                                <a href="{{ $newsShowUrl }}" class="text-ekn-900 text-sm font-medium hover:underline focus:outline-none focus:ring-2 focus:ring-ekn-900 focus:ring-offset-2 rounded">Details →</a>
                            </p>
                        </div>
                    </div>
                </article>
                @empty
                <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-8 text-center">
                    <p class="text-slate-600">Aktuell sind keine Nachrichten veröffentlicht.</p>
                </div>
                @endforelse

                @if(isset($news) && method_exists($news, 'total'))
                <div class="mt-2 text-center text-xs text-slate-500">
                    Ergebnisse: {{ $news->total() }}
                </div>
                @endif
            </div>

            {{-- Rechts: Filterpanel + Sidebar-Cards --}}
            <aside class="lg:col-span-4 space-y-5">
                <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-5 sm:p-6">
                    <h3 class="text-sm font-semibold text-ekn-900 uppercase tracking-wide mb-3">Filter</h3>
                    <form method="get" action="{{ route('home') }}" class="space-y-3">
                        <div>
                            <label for="news-filter-q" class="sr-only">Suchbegriff</label>
                            <input type="search" name="q" id="news-filter-q" value="{{ request('q') }}" placeholder="Suchbegriff eingeben" class="w-full rounded-xl border-slate-300 shadow-sm text-sm focus:ring-ekn-900 focus:border-ekn-900">
                        </div>
                        <button type="submit" class="w-full px-4 py-2.5 bg-ekn-900 text-white text-sm font-medium rounded-xl hover:bg-ekn-800 transition">Suchen</button>
                    </form>
                </div>
                <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-5 sm:p-6 text-center">
                    <h4 class="text-sm font-semibold text-ekn-900 uppercase tracking-wide mb-2">Redaktionsdesk (24h)</h4>
                    <p class="text-slate-700 text-sm">
                        Ansprechpartner für Redaktionen und Sender<br>
                        <span class="font-semibold text-ekn-900">24h‑Hotline: 02236&nbsp;480&nbsp;9488</span>
                    </p>
                </div>
                <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-5 sm:p-6">
                    <h4 class="text-sm font-semibold text-ekn-900 uppercase tracking-wide mb-2">Weitere Seiten</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="{{ url('/') }}" class="text-ekn-900 font-medium hover:underline">Startseite</a></li>
                        <li><a href="{{ route('home') }}" class="text-ekn-900 font-medium hover:underline">Medienangebote</a></li>
                        <li><a href="https://www.erftkreis-news.de" target="_blank" rel="noopener noreferrer" class="text-ekn-900 font-medium hover:underline">Aktuelle Nachrichten (erftkreis-news.de)</a></li>
                    </ul>
                </div>
                @guest
                <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-5 sm:p-6">
                    <h4 class="text-sm font-semibold text-ekn-900 uppercase tracking-wide mb-3">Kundenlogin</h4>
                    <a href="{{ route('login') }}" class="block w-full text-center px-4 py-2.5 bg-ekn-900 text-white text-sm font-medium rounded-xl hover:bg-ekn-800 transition">Anmelden</a>
                </div>
                @endguest
            </aside>
        </div>

        @if(isset($news) && $news->hasPages())
        <div class="mt-6">
            {{ $news->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
