@extends('layouts.koelnimage')

@section('title', 'Kölnimage – Professionelle Fotografie für Köln und das Rheinland')

@section('meta_description', 'Pressefotos Köln & Rheinland: Events, Karneval, Sport. Für Redaktionen & Veranstalter — Redaktionsdesk 02236 4809 488, Galerien & Kundenlogin.')

@section('content')
    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="flex w-full justify-center">
            <div class="relative w-full max-w-5xl">
                <img
                    src="{{ asset('images/koelnimage/koeln-skyline.png') }}"
                    width="1200"
                    height="400"
                    alt="Skyline Köln – Rhein, Hohenzollernbrücke, Dom und Rheinland-Silhouette"
                    title="Skyline Köln – Kölnimage Pressefotografie"
                    class="block h-auto w-full max-h-[min(52vw,320px)] object-cover object-center sm:max-h-[380px]"
                    decoding="async"
                    fetchpriority="high"
                >
                <div class="absolute inset-x-0 bottom-0 z-10 px-3 pb-3 sm:px-5 sm:pb-4">
                    <div class="w-full rounded-xl border border-gray-200/80 bg-white p-4 text-center shadow-lg sm:p-6">
                        <p class="mb-2 text-sm font-semibold text-red-700">KölnImage</p>
                        <h1 class="text-balance text-2xl font-bold leading-snug text-gray-900 sm:text-3xl md:text-4xl">
                            Pressefotografie für Events, Karneval und Sport
                        </h1>
                        <p class="mx-auto mt-3 max-w-2xl text-base leading-relaxed text-gray-700 sm:text-lg">
                            Aus Köln, dem Rheinland und vom Nürburgring — für Redaktionen, Sender und Veranstalter.
                        </p>
                        <div class="mt-5 flex flex-wrap justify-center gap-2 sm:gap-3">
                            <a href="{{ route('koelnimage.events.index') }}" title="Events entdecken" class="inline-flex min-h-[2.5rem] items-center justify-center rounded-lg bg-gray-900 px-5 py-2 text-sm font-semibold text-white shadow-md transition hover:bg-gray-800">Events entdecken</a>
                            <a href="{{ route('koelnimage.galleries.index') }}" title="Galerien" class="inline-flex min-h-[2.5rem] items-center justify-center rounded-lg border-2 border-gray-900 bg-white px-5 py-2 text-sm font-semibold text-gray-900 transition hover:bg-gray-50">Galerien</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mt-8 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-8">
        <h2 class="text-xl font-semibold text-gray-900">Pressefotografie und Eventbilder aus einer Hand</h2>
        <p class="mt-4 text-sm leading-relaxed text-gray-700 sm:text-base">
            Kölnimage beliefert <strong class="font-medium text-gray-900">Redaktionen, Online-Medien und Sender</strong> mit aktuellen Bildern von Events, Karneval und Sport im Raum Köln, Bonn und dem Rheinland.
            Unsere Fotografen sind regelmäßig in Stadien, Hallen und auf dem <a href="https://www.nuerburgring.de/" rel="noopener noreferrer" title="Nürburgring" class="font-medium text-red-700 underline decoration-red-200 hover:text-red-800">Nürburgring</a> im Einsatz — dort entstehen Serien für Rennberichte und Partnerkommunikation.
        </p>
        <p class="mt-4 text-sm leading-relaxed text-gray-700 sm:text-base">
            Für Großveranstaltungen in Köln — etwa in der <a href="https://www.lanxess-arena.de/" rel="noopener noreferrer" title="LANXESS arena Köln" class="font-medium text-red-700 underline decoration-red-200 hover:text-red-800">LANXESS arena</a> — liefern wir schnell nutzbare Motive mit klaren Bildunterschriften und Metadaten, damit Ihre Redaktion ohne Umwege arbeiten kann.
        </p>
        <p class="mt-4 text-sm leading-relaxed text-gray-700 sm:text-base">
            Materialanfragen und Freigaben klären wir direkt über unser <strong class="font-medium text-gray-900">Redaktionsdesk</strong> (Telefon 02236 4809 488). Registrierte Kunden nutzen den geschützten Downloadbereich; Interessenten starten über <a href="{{ route('koelnimage.neukunde') }}" title="Neukunde werden" class="font-medium text-red-700 hover:text-red-800">Neukunde werden</a>.
        </p>
    </section>

    <section class="mt-8 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="text-xl font-semibold text-red-800">Aktuelle Meldungen</h2>
        <div class="mt-5 grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 xl:grid-cols-4">
            @forelse($newsItems as $item)
                @include('koelnimage.partials.news-card', ['item' => $item])
            @empty
                <p class="text-sm text-gray-600">Derzeit sind keine Meldungen veröffentlicht.</p>
            @endforelse
        </div>
    </section>
@endsection
