@extends('layouts.admin')

@section('title', 'Bilder')

@section('content')
    <div
        class="admin-image-library space-y-5"
        x-data="adminImageLibrary({
            imageIds: @js($images->pluck('id')->values()->all()),
            detailUrlTemplate: @js(route('admin.images.show', ['media' => '__ID__'])),
            updateUrlTemplate: @js(route('admin.images.update', ['media' => '__ID__'])),
        })"
    >
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-gray-900">Bilder</h1>
                <p class="text-sm text-gray-600">
                    @if ($images->total() > 0)
                        {{ number_format($images->total(), 0, ',', '.') }} Bilder
                        @if ($filterActive)
                            <span class="text-gray-400">· gefiltert</span>
                        @endif
                    @else
                        0 Bilder
                    @endif
                </p>
            </div>
            <a href="{{ route('admin.images.index', array_merge(request()->except('page'), ['export' => 1])) }}"
               class="inline-flex min-h-[2.5rem] items-center justify-center rounded-lg border border-emerald-600 bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-900 hover:bg-emerald-100">
                CSV-Export
            </a>
        </div>

        <form method="get" action="{{ route('admin.images.index') }}" class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm sm:p-4 space-y-3">
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
                <label class="block sm:col-span-2 lg:col-span-2 xl:col-span-2">
                    <span class="sr-only">Suche</span>
                    <input type="search" name="q" value="{{ request('q') }}"
                        placeholder="Headline, Keywords, Beschreibung, ID…"
                        class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                </label>
                <label class="block">
                    <span class="sr-only">Einsatz</span>
                    <select name="news_item_id" class="block w-full rounded-lg border-gray-300 text-sm shadow-sm">
                        <option value="">Alle Einsätze</option>
                        @foreach ($eventOptions as $event)
                            <option value="{{ $event->id }}" @selected((string) request('news_item_id') === (string) $event->id)>
                                {{ \Illuminate\Support\Str::limit($event->title, 48) }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="sr-only">Jahr</span>
                    <select name="year" class="block w-full rounded-lg border-gray-300 text-sm shadow-sm">
                        <option value="">Alle Jahre</option>
                        @foreach ($yearOptions as $year)
                            <option value="{{ $year }}" @selected((string) request('year') === (string) $year)>{{ $year }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="sr-only">Meldungs-Status</span>
                    <select name="news_status" class="block w-full rounded-lg border-gray-300 text-sm shadow-sm">
                        @foreach ($newsStatuses as $key => $label)
                            <option value="{{ $key }}" @selected((string) request('news_status') === (string) $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="sr-only">Versand</span>
                    <select name="versand" class="block w-full rounded-lg border-gray-300 text-sm shadow-sm">
                        @foreach ($versandFilters as $key => $label)
                            <option value="{{ $key }}" @selected((string) request('versand') === (string) $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="sr-only">Sichtbarkeit</span>
                    <select name="visible" class="block w-full rounded-lg border-gray-300 text-sm shadow-sm">
                        @foreach ($visibleFilters as $key => $label)
                            <option value="{{ $key }}" @selected((string) request('visible') === (string) $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <label class="block">
                    <span class="sr-only">Sortierung</span>
                    <select name="sort" class="rounded-lg border-gray-300 text-sm shadow-sm">
                        @foreach ($sortOptions as $key => $label)
                            <option value="{{ $key }}" @selected((string) request('sort', 'newest') === (string) $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="sr-only">Pro Seite</span>
                    <select name="per_page" class="rounded-lg border-gray-300 text-sm shadow-sm">
                        @foreach ($perPageOptions as $option)
                            <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }} / Seite</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit" class="inline-flex min-h-[2.25rem] items-center rounded-lg bg-[#092E48] px-4 py-2 text-sm font-medium text-white hover:bg-[#0b3858]">
                    Anwenden
                </button>
                @if ($filterActive)
                    <a href="{{ route('admin.images.index') }}" class="inline-flex min-h-[2.25rem] items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-800 hover:bg-gray-50">
                        Zurücksetzen
                    </a>
                @endif
            </div>
        </form>

        <details class="rounded-lg border border-sky-200 bg-sky-50/80 text-sm text-sky-900">
            <summary class="cursor-pointer px-4 py-2 font-medium">Fundstellen-Recherche (Google Lens / News)</summary>
            <div class="border-t border-sky-200 px-4 py-3 text-sky-800 space-y-1">
                <p>Pro Bild unten: <strong>Google Lens</strong> und <strong>Fundstelle erfassen</strong> — manuell, ohne automatischen Scan.</p>
            </div>
        </details>

        @if ($images->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-16 text-center text-sm text-gray-600">
                @if ($filterActive)
                    Keine Bilder für diese Filter.
                    <a href="{{ route('admin.images.index') }}" class="font-medium text-[#092E48] underline">Filter zurücksetzen</a>
                @else
                    Noch keine Bilder in der Datenbank.
                @endif
            </div>
        @else
            <form method="post" action="{{ route('admin.images.quick-send.bulk') }}" class="space-y-3">
                @csrf
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2">
                    <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" data-image-select-all class="rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]"
                            @change="toggleSelectAll($event)">
                        Alle auf dieser Seite auswählen
                    </label>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs text-gray-500">Seite {{ $images->currentPage() }} / {{ $images->lastPage() }}</span>
                        <button type="submit" class="inline-flex items-center rounded-lg bg-[#092E48] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#0b3858]">
                            Ausgewählte senden
                        </button>
                    </div>
                </div>

                <div class="admin-image-library-grid">
                    @foreach ($images as $m)
                        @php
                            $thumb = $m->library_image_url;
                        @endphp
                        <article class="admin-image-card group cursor-pointer" @click="openCardDetail({{ $m->id }}, $event)">
                            <label class="admin-image-card__check" @click.stop>
                                <input type="checkbox" name="media_ids[]" value="{{ $m->id }}" data-image-select
                                    class="rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]"
                                    @change="onCardCheckboxChange()">
                            </label>
                            <div class="admin-image-card__thumb">
                                @if ($thumb)
                                    <img src="{{ $thumb }}" alt="" loading="lazy" decoding="async" class="h-full w-full object-cover">
                                @else
                                    <span class="flex h-full items-center justify-center text-xs text-gray-400">Keine Vorschau</span>
                                @endif
                                <span class="admin-image-card__id">#{{ $m->id }}</span>
                                <div class="admin-image-card__overlay">
                                    <span class="admin-image-card__overlay-title">{{ \Illuminate\Support\Str::limit($m->original_name ?: 'Bild', 28) }}</span>
                                    <div class="admin-image-card__overlay-badges">
                                        @if ($m->versand)
                                            <span class="admin-image-badge admin-image-badge--ok">Versand</span>
                                        @endif
                                        @if ($m->is_visible)
                                            <span class="admin-image-badge admin-image-badge--ok">Sichtbar</span>
                                        @endif
                                        @if ($m->is_teaser)
                                            <span class="admin-image-badge admin-image-badge--star">Titel</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="overflow-x-auto pt-2">
                    {{ $images->links() }}
                </div>
            </form>
        @endif

        @include('admin.images.detail-modal')
    </div>
@endsection
