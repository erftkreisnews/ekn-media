@extends('layouts.frontend')

@section('title', 'Erftkreis News Media – Bild- und Videomaterial für Redaktionen')
@section('meta_description', 'Bild- und Videomaterial für Redaktionen aus Köln, Bonn und dem Rhein-Erft-Kreis. Aktuelle Einsatzbilder, Videos und Blaulichtlagen für Medien.')
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
<script type="application/ld+json">
{
    "@@context": "https://schema.org",
    "@@type": "NewsMediaOrganization",
    "name": "Erftkreis News Media",
    "description": "Bild- und Videomaterial für Redaktionen",
    "areaServed": [
        "Köln",
        "Bonn",
        "Rhein-Erft-Kreis"
    ]
}
</script>
@endpush

@section('content')
@php
    $contactUrl = route('neukunden');
    $searchQuery = (string) ($searchQuery ?? request('q', ''));
    $hasSearch = (bool) ($hasSearch ?? trim($searchQuery) !== '');
    $searchQuery = trim($searchQuery);
@endphp
<div class="bg-ekn-50 -mx-4 sm:-mx-6 lg:-mx-8 py-3 sm:py-4 min-w-0 max-w-full">
    <div class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 min-w-0">
        {{-- Hero: klare B2B-Positionierung ohne doppelte Aussagen --}}
        <div id="medienangebote" class="bg-white rounded-2xl ring-1 ring-slate-200 p-5 sm:p-6 mb-4 w-full min-w-0 text-left">
            <div class="pb-2">
                <span class="inline-flex items-center text-[11px] font-semibold tracking-[0.3em] uppercase text-ekn-900/60">
                    Medienportal
                </span>
            </div>
            <div class="pt-1">
                <h1 class="text-xl sm:text-2xl font-semibold text-ekn-900 break-words">
                    Bild- und Videomaterial für Redaktionen – Einsätze aus Köln, Bonn und NRW
                </h1>
                <div class="mt-4 lg:hidden">
                    <a href="{{ $contactUrl }}" class="inline-flex min-h-[44px] items-center justify-center px-4 py-2.5 bg-ekn-900 text-white text-sm font-medium rounded-xl hover:bg-ekn-800 transition">
                        Material anfragen
                    </a>
                </div>
                <div class="mt-10 flex flex-col sm:flex-row sm:items-start sm:justify-center gap-5 sm:gap-8 lg:grid lg:grid-cols-12 lg:items-start lg:gap-10 min-w-0">
                    <div class="min-w-0 w-full sm:max-w-2xl lg:col-span-8 lg:max-w-none space-y-3 text-sm sm:text-base text-slate-700">
                        <p class="leading-relaxed break-words">
                            Erftkreis News Media liefert Bild- und Videomaterial für Redaktionen aus dem Rhein-Erft-Kreis, Köln, Bonn und Leverkusen. Auch Einsätze im Rhein-Sieg-Kreis sowie in angrenzenden Regionen Nordrhein-Westfalens werden regelmäßig abgedeckt.
                        </p>
                        <p class="leading-relaxed break-words">
                            Ich bin Alexander Franz, Foto- und Videojournalist. Ich dokumentiere Einsätze vor Ort und liefere aktuelles Material für die Berichterstattung – direkt aus der Lage heraus oder kurz danach.
                        </p>
                        <p class="leading-relaxed break-words">
                            Diese Seite zeigt aktuelle Einsätze aus der Region und gibt einen Überblick, zu welchen Lagen Bild- und Videomaterial verfügbar ist.
                        </p>
                        <p class="pt-2 text-center text-sm sm:text-base font-medium text-ekn-900 break-words">
                            📞 Material verfügbar – jetzt direkt anrufen: 02236 4809 488
                        </p>
                    </div>
                    <figure class="w-full max-w-[14rem] shrink-0 self-center rounded-xl overflow-hidden ring-1 ring-slate-200 bg-slate-100 mx-auto sm:mx-auto sm:w-56 sm:max-w-[14rem] lg:col-span-4 lg:mx-auto">
                        <img
                            src="{{ asset('images/alexander-franz-portrait.png') }}"
                            alt="Bild- und Videojournalist Alexander Franz im Einsatz"
                            title="Bild- und Videojournalist Alexander Franz im Einsatz"
                            class="w-full max-w-full h-auto object-cover object-top max-h-52 sm:max-h-56"
                            loading="lazy"
                            decoding="async"
                        >
                        <figcaption class="px-3 py-2 text-xs text-slate-600 bg-white break-words">
                            Alexander Franz beim 24‑Stunden‑Rennen am Nürburgring.
                        </figcaption>
                    </figure>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 min-w-0">
            {{-- Links: Feed-Stream --}}
            <div class="lg:col-span-8 space-y-5 min-w-0 max-w-full">
                <section id="aktuelle-einsaetze" class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-5 sm:p-6 min-w-0">
                    <h2 class="text-lg sm:text-xl font-semibold text-ekn-900 break-words">Aktuelle Einsätze – Bild- und Videomaterial verfügbar</h2>
                    <p class="mt-2 text-sm text-slate-700 break-words">
                        Übersicht aktueller Lagen aus Köln, Bonn und NRW. Material kann direkt angefragt werden.
                    </p>
                </section>
                @if($hasSearch && $news->total() > 0)
                    <section class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-4 sm:p-5 min-w-0">
                        <p class="text-sm sm:text-base font-medium text-ekn-900 break-words">
                            Suchergebnisse für „{{ $searchQuery }}“
                        </p>
                    </section>
                @endif
                @forelse($news as $item)
                @php $newsShowUrl = route('news.show', $item->slug); @endphp
                <article class="block bg-white rounded-2xl ring-1 ring-slate-200 p-5 sm:p-6 hover:ring-ekn-900 transition w-full max-w-4xl xl:max-w-5xl min-w-0">
                    <div class="flex flex-col sm:flex-row sm:items-start gap-5 sm:gap-6 min-w-0">
                        @if($item->teaser_image_for_public)
                        <a href="{{ $newsShowUrl }}" aria-label="Beitrag öffnen: {{ $item->title }}" class="sm:shrink-0 w-full sm:w-44 sm:max-w-[11rem] max-w-full cursor-pointer rounded-xl focus:outline-none focus:ring-2 focus:ring-ekn-900 focus:ring-offset-2">
                            <span class="sr-only">Beitrag öffnen: {{ $item->title }}</span>
                            @php
                                $teaserImageUrl = $item->teaser_image_for_public->thumb_url ?: $item->teaser_image_for_public->preview_url;
                                $teaserAlt = trim($item->teaser_image_for_public->display_name ?? $item->teaser_image_for_public->image_title ?? '');
                                $teaserContextAlt = trim(implode(' – ', array_filter([
                                    $item->title,
                                    $item->location_label,
                                    $item->teaser_image_for_public->caption ?? null,
                                ])));
                                $teaserAltFinal = $teaserAlt !== ''
                                    ? $teaserAlt
                                    : ($teaserContextAlt !== '' ? $teaserContextAlt : (trim((string) $item->title) !== '' ? $item->title : 'Bildmaterial von Erftkreis News Media'));
                            @endphp
                            <div class="relative w-full max-w-full overflow-hidden rounded-xl ring-1 ring-slate-200 bg-slate-100" style="padding-bottom: 66.6667%; height: 0;">
                            @if($teaserImageUrl)
                            <img
                                src="{{ $teaserImageUrl }}"
                                alt="{{ $teaserAltFinal }}"
                                title="{{ $teaserAltFinal }}"
                                class="absolute inset-0 w-full h-full object-cover object-center"
                                loading="{{ $loop->first ? 'eager' : 'lazy' }}"
                                @if($loop->first) fetchpriority="high" @endif
                                decoding="async"
                            >
                            <span class="absolute inset-0 flex items-center justify-center pointer-events-none" aria-hidden="true">
                                <img src="{{ asset('images/erftkreis-news-logo.png') }}?v=2" alt="" aria-hidden="true" title="Erftkreis News" class="max-w-[14%] max-h-[14%] w-auto h-auto object-contain opacity-[0.35]">
                            </span>
                            @else
                            <div class="absolute inset-0 flex items-center justify-center text-slate-400 text-xs">Bild wird vorbereitet</div>
                            @endif
                            </div>
                        </a>
                        @endif
                        <div class="min-w-0 flex-1 max-w-full">
                            <p class="text-xs uppercase text-slate-500 mb-2 break-words [overflow-wrap:anywhere]">
                                {{ $item->published_at?->format('d.m.Y, H:i') }} · NEWSID: {{ $item->id }}@if($item->location_label) · {{ $item->location_label }}@endif
                            </p>
                            <div class="flex flex-wrap gap-2 mb-3 min-w-0">
                                @if($item->publicPortalImages()->count() > 0)
                                    <span class="inline-flex items-center rounded-full bg-ekn-900/10 text-ekn-900 px-2.5 py-0.5 text-xs font-medium">Foto</span>
                                @endif
                                @if($item->publicPortalVideos()->count() > 0)
                                    <span class="inline-flex items-center rounded-full bg-ekn-900/10 text-ekn-900 px-2.5 py-0.5 text-xs font-medium">Video</span>
                                @endif
                                @if($item->publicPortalAudios()->count() > 0)
                                    <span class="inline-flex items-center rounded-full bg-ekn-900/10 text-ekn-900 px-2.5 py-0.5 text-xs font-medium">Audio</span>
                                @endif
                            </div>
                            <h3 class="text-lg sm:text-xl font-semibold text-ekn-900 mb-2 leading-snug break-words">
                                <a href="{{ $newsShowUrl }}" class="text-ekn-900 hover:underline focus:outline-none focus:ring-2 focus:ring-ekn-900 focus:ring-offset-2 rounded">{{ $item->title }}</a>
                            </h3>
                            <p class="text-slate-600 text-sm sm:text-base line-clamp-2 leading-relaxed break-words">{!! Str::limit(strip_tags($item->teaser ?: $item->body), 160) !!}</p>
                            <p class="mt-4 flex flex-wrap items-center gap-4">
                                <a href="{{ $newsShowUrl }}" class="inline-flex min-h-[44px] items-center text-ekn-900 text-sm font-medium hover:underline focus:outline-none focus:ring-2 focus:ring-ekn-900 focus:ring-offset-2 rounded py-1 -my-1">Details →</a>
                                <a href="{{ $contactUrl }}" aria-label="Material zum Einsatz {{ $item->title }} anfragen" class="inline-flex min-h-[44px] items-center text-ekn-900 text-sm font-medium hover:underline focus:outline-none focus:ring-2 focus:ring-ekn-900 focus:ring-offset-2 rounded py-1 -my-1">
                                    Material anfragen
                                </a>
                            </p>
                        </div>
                    </div>
                </article>
                @empty
                <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-8 text-center min-w-0">
                    @if($hasSearch)
                        <p class="text-ekn-900 font-medium break-words">Keine passenden Einsätze gefunden.</p>
                        <p class="mt-2 text-slate-600 break-words">
                            Versuchen Sie es mit einem Ort, Thema oder der News-ID – oder kontaktieren Sie uns direkt für Materialanfragen.
                        </p>
                    @else
                        <p class="text-slate-600 break-words">Aktuell sind keine Nachrichten veröffentlicht.</p>
                    @endif
                </div>
                @endforelse

                @if(isset($news) && method_exists($news, 'total'))
                <div class="mt-2 text-center text-xs text-slate-500 break-words">
                    Ergebnisse: {{ $news->total() }}
                </div>
                @endif
            </div>

            {{-- Rechts: Filterpanel + Sidebar-Cards --}}
            <aside class="lg:col-span-4 space-y-5 min-w-0 max-w-full">
                <div class="bg-ekn-900 text-white rounded-2xl shadow-sm ring-1 ring-ekn-900 p-5 sm:p-6 min-w-0 space-y-4 text-center">
                    <p class="text-sm font-semibold uppercase tracking-wide">Material anfragen</p>
                    <p class="text-base font-semibold break-words">
                        <a href="tel:+4922364809488" class="inline-flex items-center justify-center gap-2 hover:underline focus:outline-none focus:ring-2 focus:ring-white/80 rounded" aria-label="Jetzt anrufen unter 02236 4809 488">
                            <span aria-hidden="true">📞</span>
                            <span>02236 4809 488</span>
                        </a>
                    </p>
                    <p class="text-sm text-white/90 break-words">Direkter Kontakt für Redaktionen</p>
                    <p class="text-sm text-white/90 break-words">Material zu aktuellen Einsätzen kann kurzfristig angefragt werden.</p>
                    <a href="tel:+4922364809488" class="inline-flex min-h-[44px] items-center justify-center px-4 py-2.5 bg-white text-ekn-900 text-sm font-medium rounded-xl hover:bg-slate-100 transition mx-auto">
                        Jetzt anrufen
                    </a>
                </div>

                <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-5 sm:p-6 min-w-0">
                    <p class="text-sm font-semibold text-ekn-900 uppercase tracking-wide mb-2">Verfügbarkeit</p>
                    <p class="text-sm text-slate-700 break-words">
                        Aktuelle Pressefotos und Videomaterial zu Einsätzen in Köln, Bonn und dem Rheinland.
                    </p>
                </div>

                <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-5 sm:p-6 min-w-0">
                    <p class="text-sm font-semibold text-ekn-900 uppercase tracking-wide mb-2">Material suchen</p>
                    <p class="text-xs text-slate-600 mb-3 break-words">Suchen Sie nach Einsatzort, Thema oder News-ID.</p>
                    <form method="get" action="{{ route('home') }}" class="space-y-3 min-w-0">
                        <div class="min-w-0">
                            <label for="news-filter-q" class="sr-only">Material suchen</label>
                            <input type="search" name="q" id="news-filter-q" value="{{ $searchQuery }}" placeholder="Suchbegriff, Ort oder News-ID eingeben" class="w-full min-w-0 max-w-full rounded-xl border-slate-300 shadow-sm text-sm focus:ring-ekn-900 focus:border-ekn-900">
                        </div>
                        <button type="submit" class="w-full min-h-[44px] inline-flex items-center justify-center px-4 py-2.5 bg-ekn-900 text-white text-sm font-medium rounded-xl hover:bg-ekn-800 transition">Suchen</button>
                    </form>
                </div>

                <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-5 sm:p-6 min-w-0">
                    <p class="text-sm font-semibold text-ekn-900 uppercase tracking-wide mb-2">Für Redaktionen</p>
                    <ul class="space-y-1 text-sm">
                        <li>
                            <a href="{{ route('neukunden') }}" class="inline-flex min-h-[44px] items-center text-ekn-900 font-medium hover:underline py-1">
                                Material anfragen
                            </a>
                        </li>
                        <li>
                            <a href="#medienangebote" class="inline-flex min-h-[44px] items-center text-ekn-900 font-medium hover:underline py-1">
                                Medienangebote
                            </a>
                        </li>
                        <li>
                            <a href="#aktuelle-einsaetze" class="inline-flex min-h-[44px] items-center text-ekn-900 font-medium hover:underline py-1">
                                Aktuelle Einsätze
                            </a>
                        </li>
                    </ul>
                </div>
            </aside>
        </div>

        @if(isset($news) && $news->hasPages())
        <div class="mt-6 overflow-x-auto min-w-0 max-w-full pb-2 [scrollbar-width:thin]">
            <div class="flex justify-center sm:justify-end min-w-min px-1">
                {{ $news->links() }}
            </div>
        </div>
        @endif

        <section class="mt-6 bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-5 sm:p-6 min-w-0">
            <h2 class="text-lg sm:text-xl font-semibold text-ekn-900 break-words">Für Redaktionen, Agenturen und Medienhäuser</h2>
            <p class="mt-3 text-sm sm:text-base text-slate-700 leading-relaxed break-words">
                Unser Material wird von Fernsehsendern, Online-Redaktionen und Nachrichtenagenturen für die aktuelle Berichterstattung genutzt.
            </p>
            <ul class="mt-3 flex flex-wrap gap-2 text-sm text-slate-700">
                <li class="px-3 py-1 rounded-full bg-slate-100 border border-slate-200">TV-Sender</li>
                <li class="px-3 py-1 rounded-full bg-slate-100 border border-slate-200">Online-Redaktionen</li>
                <li class="px-3 py-1 rounded-full bg-slate-100 border border-slate-200">Nachrichtenagenturen</li>
            </ul>
        </section>

        <section class="mt-6 bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-5 sm:p-6 min-w-0 text-center">
            <h2 class="text-lg sm:text-xl font-semibold text-ekn-900 break-words">Material anfragen</h2>
            <p class="mt-3 text-sm sm:text-base text-slate-700 leading-relaxed break-words">
                Sie benötigen aktuelles Bild- oder Videomaterial? Wir liefern schnell und zuverlässig.
            </p>
            <div class="mt-4">
                <a href="{{ $contactUrl }}" class="inline-flex min-h-[44px] items-center justify-center px-4 py-2.5 bg-ekn-900 text-white text-sm font-medium rounded-xl hover:bg-ekn-800 transition">
                    Jetzt Kontakt aufnehmen
                </a>
            </div>
        </section>
    </div>
</div>
@endsection
