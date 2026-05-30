@extends('layouts.admin')

@section('title', 'Video-Mediathek')

@section('content')
    <div
        class="admin-image-library space-y-5"
        x-data="adminVideoLibrary({
            videoIds: @js($videos->pluck('id')->values()->all()),
            detailUrlTemplate: @js(route('admin.video.show', ['media' => '__ID__'])),
            updateUrlTemplate: @js(route('admin.video.update', ['media' => '__ID__'])),
        })"
    >
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-xl sm:text-2xl font-semibold text-gray-900">Video-Mediathek</h1>
                <p class="text-sm text-gray-600 max-w-3xl">
                    Kachel anklicken öffnet das Detail-Fenster (wie bei Bildern): Abspielen, Metadaten, Versand und Sichtbarkeit.
                    Für erweiterte Bearbeitung: „Vollständig bearbeiten“.
                </p>
                @if ($videos->total() > 0)
                    <p class="mt-1 text-sm text-gray-600">
                        {{ number_format($videos->total(), 0, ',', '.') }} Videos
                        @if ($filterActive)
                            <span class="text-gray-400">· gefiltert</span>
                        @endif
                        · Seite {{ $videos->currentPage() }} / {{ $videos->lastPage() }}
                    </p>
                @endif
            </div>
            <a href="{{ route('admin.video.index', array_merge(request()->except('page'), ['export' => 1])) }}"
               class="inline-flex min-h-[2.5rem] items-center justify-center rounded-lg border border-emerald-600 bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-900 hover:bg-emerald-100">
                CSV-Export
            </a>
        </div>

        <x-admin.section title="Filter" subtitle="Suche und Meldungs-Status; Export der aktuellen Trefferliste.">
            <form method="get" action="{{ route('admin.video.index') }}" class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end min-w-0">
                <div class="w-full min-w-0 sm:max-w-md">
                    <label for="video-lib-q" class="block text-xs font-medium text-gray-500">Suche</label>
                    <input type="search" name="q" id="video-lib-q" value="{{ request('q') }}" placeholder="Titel, Dateiname, Media-ID, Meldungs-ID …"
                        class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
                </div>
                <div class="w-full min-w-0 sm:max-w-xs">
                    <label for="video-lib-status" class="block text-xs font-medium text-gray-500">Meldung</label>
                    <select name="news_status" id="video-lib-status" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
                        @foreach ($newsStatuses as $key => $label)
                            <option value="{{ $key }}" @selected((string) request('news_status') === (string) $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="inline-flex min-h-[2.5rem] items-center justify-center rounded-lg bg-[#092E48] px-4 py-2 text-sm font-medium text-white hover:bg-[#0b3858]">
                        Ergebnisse filtern
                    </button>
                    @if ($filterActive)
                        <a href="{{ route('admin.video.index') }}" class="inline-flex min-h-[2.5rem] items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-800 hover:bg-gray-50">
                            Filter zurücksetzen
                        </a>
                    @endif
                </div>
            </form>
        </x-admin.section>

        @if ($videos->isEmpty())
            <div class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-4 py-10 text-center text-sm text-gray-600 space-y-2">
                @if ($filterActive)
                    <p>Keine Videos für diese Filter.</p>
                    <p><a href="{{ route('admin.video.index') }}" class="font-medium text-[#092E48] underline">Filter zurücksetzen</a></p>
                @elseif (($totalVideosInDatabase ?? 0) > 0)
                    <p class="font-medium text-gray-800">Es gibt {{ number_format($totalVideosInDatabase, 0, ',', '.') }} Videos im System, aber keine passen zu den aktuellen Einstellungen.</p>
                    @if (filled($brandFilterLabel ?? null))
                        <p>Aktiver <strong>Brand-Filter: {{ $brandFilterLabel }}</strong> — oben im Menü auf „Alle Marken“ oder „Erftkreis News“ stellen, falls die Videos dort liegen.</p>
                    @else
                        <p>Prüfen Sie den <strong>Brand-Filter</strong> oben im Admin-Menü (z. B. „KoelnImage“ zeigt hier oft 0 Videos).</p>
                    @endif
                @else
                    <p>Noch keine Videos in der Datenbank.</p>
                @endif
            </div>
        @else
            <div class="admin-image-library-grid">
                @foreach ($videos as $m)
                    @php
                        $newsItem = $m->newsItem;
                        $poster = $m->preview_url;
                        $sec = $m->duration_s ? (int) round((float) $m->duration_s) : null;
                        $durLabel = $sec !== null ? sprintf('%d:%02d', intdiv($sec, 60), $sec % 60) : null;
                    @endphp
                    <article class="admin-image-card group cursor-pointer" @click="openCardDetail({{ $m->id }}, $event)">
                        <div class="admin-image-card__thumb aspect-video">
                            @if ($poster)
                                <img src="{{ $poster }}" alt="" loading="lazy" decoding="async" class="h-full w-full object-cover">
                            @else
                                <span class="flex h-full items-center justify-center text-xs text-gray-400">
                                    <svg class="h-10 w-10 opacity-50" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                                </span>
                            @endif
                            <span class="admin-image-card__id">#{{ $m->id }}</span>
                            <div class="admin-image-card__overlay">
                                <span class="admin-image-card__overlay-title">{{ \Illuminate\Support\Str::limit($m->original_name ?: 'Video', 28) }}</span>
                                <div class="admin-image-card__overlay-badges">
                                    @if ($m->versand)
                                        <span class="admin-image-badge admin-image-badge--ok">Versand</span>
                                    @endif
                                    @if ($m->is_visible)
                                        <span class="admin-image-badge admin-image-badge--ok">Sichtbar</span>
                                    @endif
                                    @if ($durLabel)
                                        <span class="admin-image-badge">{{ $durLabel }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-6 overflow-x-auto min-w-0 pb-1 [scrollbar-width:thin]">
                <div class="flex justify-center sm:justify-end min-w-0">
                    {{ $videos->links() }}
                </div>
            </div>
        @endif

        @include('admin.video.detail-modal')
    </div>
@endsection
