@extends('layouts.admin')

@section('content')
    <div class="space-y-6 text-left">
        @if (session('status'))
            <div class="rounded-md bg-green-50 p-4 border border-green-200">
                <p class="text-sm text-green-800">{{ session('status') }}</p>
            </div>
        @endif

        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Nachrichten</h1>
                <p class="mt-1 text-sm text-gray-600">Verwalte alle News-Einträge im System.</p>
            </div>
            <form method="get" action="{{ route('admin.news.index') }}" class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                <div class="relative flex-1 sm:w-64 mt-1 sm:mt-0">
                    <input
                        type="search"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="Suche nach Titel, ID, Autor …"
                        class="block w-full rounded-2xl border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                        autocomplete="off"
                    >
                </div>
                @php
                    $adminBrandKey = $selectedBrand?->key ?? null;
                    $showErftkreisCreate = $adminBrandKey === null || $adminBrandKey === 'erftkreis_news';
                    $showKoelnimageFoto = $adminBrandKey === null || $adminBrandKey === 'koelnimage';
                    $koelnimageBtnPrimary = $showKoelnimageFoto && ! $showErftkreisCreate;
                @endphp
                <div class="flex flex-wrap gap-2">
                    @if ($showErftkreisCreate)
                        <a
                            href="{{ route('admin.news.create') }}"
                            class="inline-flex justify-center items-center px-4 py-2.5 text-sm font-medium rounded-2xl bg-[#092E48] text-white hover:bg-[#0b3858] focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#092E48]"
                        >
                            + Neue Nachricht
                            @if ($adminBrandKey === null)
                                <span class="hidden sm:inline opacity-80">(Erftkreis News)</span>
                            @endif
                        </a>
                    @endif
                    @if ($showKoelnimageFoto)
                        <a
                            href="{{ route('admin.koelnimage.foto.create') }}"
                            @class([
                                'inline-flex justify-center items-center px-4 py-2.5 text-sm font-medium rounded-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#092E48]',
                                'bg-[#092E48] text-white hover:bg-[#0b3858]' => $koelnimageBtnPrimary,
                                'border border-[#092E48] text-[#092E48] hover:bg-[#092E48]/5' => ! $koelnimageBtnPrimary,
                            ])
                        >
                            + Foto (Kölnimage)
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <x-admin.card class="mt-2">
            @php
                $currentSort = (string) ($sort ?? request('sort', 'created_at'));
                $currentDirection = (string) ($direction ?? request('direction', 'desc'));
                $sortableHeaderClass = 'inline-flex items-center gap-1 hover:text-[#092E48]';
                $sortIndicator = function (string $column) use ($currentSort, $currentDirection): string {
                    if ($currentSort !== $column) {
                        return '↕';
                    }
                    return $currentDirection === 'asc' ? '↑' : '↓';
                };
                $sortUrl = function (string $column) use ($currentSort, $currentDirection): string {
                    $nextDirection = ($currentSort === $column && $currentDirection === 'asc') ? 'desc' : 'asc';
                    return route('admin.news.index', array_merge(request()->query(), [
                        'sort' => $column,
                        'direction' => $nextDirection,
                    ]));
                };
            @endphp
            <x-admin.table-cards :items="$newsItems">
                <x-slot:table>
                    {{-- Tabelle ab lg (table-cards); overflow-x-auto bei Zoom/sehr breiten Tabellen --}}
                    <div class="overflow-x-auto -mx-1 sm:mx-0">
                        <table class="w-full min-w-[920px] divide-y divide-gray-200 table-auto">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        <a href="{{ $sortUrl('status') }}" class="{{ $sortableHeaderClass }}">Status <span>{{ $sortIndicator('status') }}</span></a>
                                    </th>
                                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        <a href="{{ $sortUrl('teaser_image') }}" class="{{ $sortableHeaderClass }}">Teaserbild <span>{{ $sortIndicator('teaser_image') }}</span></a>
                                    </th>
                                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        <a href="{{ $sortUrl('id') }}" class="{{ $sortableHeaderClass }}">News-ID <span>{{ $sortIndicator('id') }}</span></a>
                                    </th>
                                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[12rem] max-w-md">
                                        <a href="{{ $sortUrl('title') }}" class="{{ $sortableHeaderClass }}">Titel <span>{{ $sortIndicator('title') }}</span></a>
                                    </th>
                                    <th scope="col" class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        <a href="{{ $sortUrl('images_count') }}" class="{{ $sortableHeaderClass }}">Bilder <span>{{ $sortIndicator('images_count') }}</span></a>
                                    </th>
                                    <th scope="col" class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        <a href="{{ $sortUrl('videos_count') }}" class="{{ $sortableHeaderClass }}">Videos <span>{{ $sortIndicator('videos_count') }}</span></a>
                                    </th>
                                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        <a href="{{ $sortUrl('created_at') }}" class="{{ $sortableHeaderClass }}">Erstellt <span>{{ $sortIndicator('created_at') }}</span></a>
                                    </th>
                                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        <a href="{{ $sortUrl('author') }}" class="{{ $sortableHeaderClass }}">Autor <span>{{ $sortIndicator('author') }}</span></a>
                                    </th>
                                    <th scope="col" class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap w-[9rem]">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($newsItems as $newsItem)
                            @php
                                $statusLabels = [
                                    'draft' => 'Entwurf',
                                    'review' => 'Review',
                                    'published' => 'ready',
                                    'archived' => 'Archiviert',
                                ];
                                $status = $newsItem->status ?? 'draft';
                                $badgeClasses = match ($status) {
                                    'draft' => 'bg-gray-100 text-gray-800',
                                    'review' => 'bg-yellow-100 text-yellow-800',
                                    'published' => 'bg-green-100 text-green-800',
                                    'archived' => 'bg-red-100 text-red-800',
                                    default => 'bg-gray-100 text-gray-800',
                                };
                            @endphp
                            <tr class="align-top">
                                <td class="px-3 py-3 text-sm whitespace-nowrap align-middle">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badgeClasses }}">
                                        {{ $statusLabels[$status] ?? ucfirst($status) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-sm whitespace-nowrap align-top">
                                    @php $teaserImage = $newsItem->teaser_image; @endphp
                                    <div class="h-16 flex items-center justify-center">
                                        @if ($teaserImage)
                                            <img src="{{ $teaserImage->thumb_url ?: ($teaserImage->preview_url ?: $teaserImage->url) }}" alt="{{ $newsItem->title ?: ($newsItem->subheadline ?: 'Bildmaterial von Erftkreis News Media') }}" class="h-12 w-16 rounded border border-gray-200 object-cover" loading="eager" decoding="sync">
                                        @else
                                            <span class="inline-flex h-12 w-16 items-center justify-center rounded border border-gray-200 bg-gray-50 text-gray-400 text-xs">–</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-sm text-gray-600 whitespace-nowrap font-mono align-middle">{{ $newsItem->display_news_id }}</td>
                                <td class="px-3 py-3 text-sm text-gray-900 align-top max-w-md">
                                    <div class="font-medium break-words leading-snug" title="{{ $newsItem->title }}">{{ $newsItem->title }}</div>
                                </td>
                                <td class="px-3 py-3 text-sm text-gray-600 whitespace-nowrap text-center align-middle">{{ $newsItem->images->count() }}</td>
                                <td class="px-3 py-3 text-sm text-gray-600 whitespace-nowrap text-center align-middle">{{ $newsItem->videos->count() }}</td>
                                <td class="px-3 py-3 text-sm text-gray-500 whitespace-nowrap align-middle">{{ $newsItem->created_at?->format('d.m.Y H:i') ?? '–' }}</td>
                                <td class="px-3 py-3 text-sm text-gray-600 whitespace-nowrap align-middle">{{ optional($newsItem->author)->name ?? '–' }}</td>
                                <td class="px-3 py-3 text-sm text-right align-middle">
                                    <div class="flex flex-col gap-2 items-end sm:flex-row sm:flex-wrap sm:justify-end sm:gap-2">
                                        <a href="{{ route('admin.news.edit', $newsItem) }}" class="inline-flex items-center justify-center min-h-[40px] px-3 py-2 text-xs font-medium rounded-xl border border-[#092E48] text-[#092E48] hover:bg-[#092E48]/5">Bearbeiten</a>
                                        <form action="{{ route('admin.news.destroy', $newsItem) }}" method="POST" class="inline-flex" onsubmit="return window.adminConfirmDelete(this)" data-delete-prompt="Diese Nachricht wirklich löschen? Geben Sie zur Bestätigung „ja“ ein.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex items-center justify-center min-h-[40px] px-3 py-2 text-xs font-medium rounded-xl border border-red-600 text-red-700 hover:bg-red-50">Löschen</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-slot:table>

                <x-slot:cards>
                    @forelse($newsItems as $newsItem)
                        @php
                            $statusLabels = [
                                'draft' => 'Entwurf',
                                'review' => 'Review',
                                'published' => 'ready',
                                'archived' => 'Archiviert',
                            ];
                            $status = $newsItem->status ?? 'draft';
                            $badgeClasses = match ($status) {
                                'draft' => 'bg-gray-100 text-gray-800',
                                'review' => 'bg-yellow-100 text-yellow-800',
                                'published' => 'bg-green-100 text-green-800',
                                'archived' => 'bg-red-100 text-red-800',
                                default => 'bg-gray-100 text-gray-800',
                            };
                            $teaserImage = $newsItem->teaser_image;
                        @endphp
                        <article class="rounded-2xl border border-slate-200/80 bg-white shadow-sm px-4 py-4 flex flex-col sm:flex-row gap-4">
                            @if($teaserImage)
                                <div class="shrink-0 sm:pt-0.5">
                                    <img src="{{ $teaserImage->thumb_url ?: ($teaserImage->preview_url ?: $teaserImage->url) }}" alt="{{ $newsItem->title ?: ($newsItem->subheadline ?: 'Bildmaterial von Erftkreis News Media') }}" class="h-16 w-24 sm:h-16 sm:w-20 rounded-xl border border-slate-200 object-cover" loading="eager" decoding="sync">
                                </div>
                            @endif
                            <div class="flex-1 min-w-0 flex flex-col">
                                <div class="flex flex-wrap items-center justify-between gap-x-2 gap-y-1">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $badgeClasses }}">
                                        {{ $statusLabels[$status] ?? ucfirst($status) }}
                                    </span>
                                    <span class="text-xs text-slate-500 font-mono tabular-nums">#{{ $newsItem->display_news_id }}</span>
                                </div>
                                <h2 class="mt-2 text-base font-semibold text-slate-900 leading-snug">
                                    {{ $newsItem->title }}
                                </h2>
                                <p class="mt-2 text-sm text-slate-600">
                                    <span class="font-medium text-slate-700">{{ $newsItem->created_at?->format('d.m.Y H:i') ?? '–' }}</span>
                                </p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ optional($newsItem->author)->name ?? '–' }}
                                    <span class="text-slate-300 mx-1">·</span>
                                    Bilder {{ $newsItem->images->count() }}, Videos {{ $newsItem->videos->count() }}
                                </p>
                                <div class="mt-4 grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:gap-2">
                                    <a href="{{ route('admin.news.edit', $newsItem) }}" class="inline-flex justify-center items-center min-h-[44px] px-3 py-2.5 text-sm font-medium rounded-xl border border-[#092E48] text-[#092E48] hover:bg-[#092E48]/5 col-span-2 sm:col-span-1 sm:min-w-[8rem]">
                                        Bearbeiten
                                    </a>
                                    <form action="{{ route('admin.news.destroy', $newsItem) }}" method="POST" class="col-span-2 sm:col-span-1 sm:inline-flex" onsubmit="return window.adminConfirmDelete(this)" data-delete-prompt="Diese Nachricht wirklich löschen? Geben Sie zur Bestätigung „ja“ ein.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex justify-center items-center min-h-[44px] w-full px-3 py-2.5 text-sm font-medium rounded-xl border border-red-600 text-red-700 hover:bg-red-50">
                                            Löschen
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="px-2 py-6 text-center text-sm text-gray-500">
                            Es sind noch keine Nachrichten vorhanden.
                        </div>
                    @endforelse
                </x-slot:cards>
            </x-admin.table-cards>

            @if ($newsItems->hasPages())
                <div class="mt-4 overflow-x-auto pb-1 px-3 sm:px-6">
                    {{ $newsItems->links() }}
                </div>
            @endif
        </x-admin.card>
    </div>
@endsection
