@extends('layouts.admin')

@section('content')
    <div
        x-data="{
            search: '',
            matches(rowText) {
                if (!this.search) return true;
                return rowText.toLowerCase().includes(this.search.toLowerCase());
            }
        }"
        class="space-y-6 text-left"
    >
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
            <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                <div class="relative flex-1 sm:w-64 mt-1 sm:mt-0">
                    <input
                        type="search"
                        x-model="search"
                        placeholder="Suche nach Titel, ID, Autor …"
                        class="block w-full rounded-2xl border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                    >
                </div>
                <a
                    href="{{ route('admin.news.create') }}"
                    class="inline-flex justify-center items-center px-4 py-2.5 text-sm font-medium rounded-2xl bg-[#092E48] text-white hover:bg-[#0b3858] focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#092E48]"
                >
                    + Neue Nachricht
                </a>
            </div>
        </div>

        <x-admin.card class="mt-2">
            <x-admin.table-cards :items="$newsItems">
                <x-slot:table>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 table-fixed">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Status</th>
                                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Teaserbild</th>
                                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">News-ID</th>
                                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-[28%]">Titel</th>
                                    <th scope="col" class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Bilder</th>
                                    <th scope="col" class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Videos</th>
                                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Datum / Uhrzeit</th>
                                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Autor</th>
                                    <th scope="col" class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Aktionen</th>
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
                            <tr
                                x-show="matches(`{{ Str::lower($newsItem->title.' '.$newsItem->id.' '.optional($newsItem->author)->name) }}`)"
                                class="align-top"
                            >
                                <td class="px-3 py-3 text-sm whitespace-nowrap align-middle">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badgeClasses }}">
                                        {{ $statusLabels[$status] ?? ucfirst($status) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-sm whitespace-nowrap align-top">
                                    @php $teaserImage = $newsItem->teaser_image; @endphp
                                    <div class="h-16 flex items-center justify-center">
                                        @if ($teaserImage)
                                            <img src="{{ $teaserImage->thumb_url ?: ($teaserImage->preview_url ?: $teaserImage->url) }}" alt="" class="h-12 w-16 rounded border border-gray-200 object-cover" loading="eager" decoding="sync">
                                        @else
                                            <span class="inline-flex h-12 w-16 items-center justify-center rounded border border-gray-200 bg-gray-50 text-gray-400 text-xs">–</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-sm text-gray-600 whitespace-nowrap font-mono align-middle">{{ $newsItem->id }}</td>
                                <td class="px-3 py-3 text-sm text-gray-900 align-top w-[28%]">
                                    <div class="font-medium break-words leading-snug" title="{{ $newsItem->title }}">{{ $newsItem->title }}</div>
                                </td>
                                <td class="px-3 py-3 text-sm text-gray-600 whitespace-nowrap text-center align-middle">{{ $newsItem->images->count() }}</td>
                                <td class="px-3 py-3 text-sm text-gray-600 whitespace-nowrap text-center align-middle">{{ $newsItem->videos->count() }}</td>
                                <td class="px-3 py-3 text-sm text-gray-500 whitespace-nowrap align-middle">{{ $newsItem->updated_at?->format('d.m.Y H:i') ?? '–' }}</td>
                                <td class="px-3 py-3 text-sm text-gray-600 whitespace-nowrap align-middle">{{ optional($newsItem->author)->name ?? '–' }}</td>
                                <td class="px-3 py-3 text-sm text-right whitespace-nowrap align-middle">
                                    <a href="{{ route('admin.news.edit', $newsItem) }}" class="inline-flex items-center px-3 py-2 text-xs font-medium rounded-xl border border-[#092E48] text-[#092E48] hover:bg-[#092E48]/5">Bearbeiten</a>
                                    <form action="{{ route('admin.news.destroy', $newsItem) }}" method="POST" class="inline-block ml-1" onsubmit="return confirm('Diese Nachricht wirklich löschen?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center px-3 py-2 text-xs font-medium rounded-xl border border-red-600 text-red-700 hover:bg-red-50">Löschen</button>
                                    </form>
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
                        <article
                            class="rounded-2xl border border-slate-200/80 bg-white shadow-sm px-4 py-3 flex gap-3"
                            x-show="matches(`{{ Str::lower($newsItem->title.' '.$newsItem->id.' '.optional($newsItem->author)->name) }}`)"
                        >
                            @if($teaserImage)
                                <div class="flex-shrink-0">
                                    <img src="{{ $teaserImage->thumb_url ?: ($teaserImage->preview_url ?: $teaserImage->url) }}" alt="" class="h-16 w-20 rounded-xl border border-slate-200 object-cover" loading="eager" decoding="sync">
                                </div>
                            @endif
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badgeClasses }}">
                                        {{ $statusLabels[$status] ?? ucfirst($status) }}
                                    </span>
                                    <span class="text-xs text-slate-500 font-mono">#{{ $newsItem->id }}</span>
                                </div>
                                <h2 class="mt-1 text-sm font-semibold text-slate-900 line-clamp-2">
                                    {{ $newsItem->title }}
                                </h2>
                                <p class="mt-1 text-xs text-slate-500 flex flex-wrap gap-x-2 gap-y-0.5">
                                    <span>{{ $newsItem->updated_at?->format('d.m.Y H:i') ?? '–' }}</span>
                                    <span>·</span>
                                    <span>{{ optional($newsItem->author)->name ?? '–' }}</span>
                                    <span>·</span>
                                    <span>Bilder: {{ $newsItem->images->count() }}, Videos: {{ $newsItem->videos->count() }}</span>
                                </p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <a href="{{ route('admin.news.edit', $newsItem) }}" class="inline-flex flex-1 sm:flex-none justify-center items-center px-3 py-2 text-xs font-medium rounded-xl border border-[#092E48] text-[#092E48] hover:bg-[#092E48]/5">
                                        Bearbeiten
                                    </a>
                                    <form action="{{ route('admin.news.destroy', $newsItem) }}" method="POST" class="inline-flex flex-1 sm:flex-none justify-center" onsubmit="return confirm('Diese Nachricht wirklich löschen?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex justify-center items-center px-3 py-2 w-full text-xs font-medium rounded-xl border border-red-600 text-red-700 hover:bg-red-50">
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
                <div class="mt-4">
                    {{ $newsItems->links() }}
                </div>
            @endif
        </x-admin.card>
    </div>
@endsection
