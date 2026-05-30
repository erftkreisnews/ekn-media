@php
    $statusLabels = [
        'draft' => 'Entwurf',
        'review' => 'Review',
        'published' => 'ready',
        'archived' => 'Archiviert',
    ];
@endphp

<div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden">
    {{-- Tabelle (ab md) --}}
    <div class="hidden md:block overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Status</th>
                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Teaserbild</th>
                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">News-ID</th>
                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Titel</th>
                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Bilder</th>
                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Videos</th>
                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Datum / Uhrzeit</th>
                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Autor</th>
                    <th scope="col" class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Aktionen</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse ($newsItems as $newsItem)
                    @php
                        $status = $newsItem->status;
                        $badgeClasses = match ($status) {
                            'draft' => 'bg-gray-100 text-gray-800',
                            'review' => 'bg-yellow-100 text-yellow-800',
                            'published' => 'bg-green-100 text-green-800',
                            'archived' => 'bg-red-100 text-red-800',
                            default => 'bg-gray-100 text-gray-800',
                        };
                        $imagesCount = 0;
                        $videosCount = 0;
                    @endphp
                    <tr>
                        <td class="px-3 py-3 text-sm whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badgeClasses }}">
                                {{ $statusLabels[$status] ?? ucfirst($status) }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-sm whitespace-nowrap align-middle">
                            @php $teaserImage = $newsItem->teaser_image; @endphp
                            @if($teaserImage)
                                <img src="{{ $teaserImage->thumb_url ?: ($teaserImage->preview_url ?: $teaserImage->url) }}" alt="{{ $newsItem->title ?: ($newsItem->subheadline ?: 'Bildmaterial von Erftkreis News Media') }}" class="h-12 w-16 object-cover rounded border border-gray-200">
                            @else
                                <span class="inline-flex h-12 w-16 items-center justify-center rounded border border-gray-200 bg-gray-50 text-gray-400 text-xs" title="Teaserbild (noch nicht hinterlegt)">–</span>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-sm text-gray-600 whitespace-nowrap font-mono">{{ $newsItem->display_news_id }}</td>
                        <td class="px-3 py-3 text-sm text-gray-900">
                            <div class="font-medium max-w-[220px] truncate" title="{{ $newsItem->title }}">{{ $newsItem->title }}</div>
                        </td>
                        <td class="px-3 py-3 text-sm text-gray-600 whitespace-nowrap text-center">{{ $imagesCount }}</td>
                        <td class="px-3 py-3 text-sm text-gray-600 whitespace-nowrap text-center">{{ $videosCount }}</td>
                        <td class="px-3 py-3 text-sm text-gray-500 whitespace-nowrap">{{ optional($newsItem->updated_at)->format('d.m.Y H:i') }}</td>
                        <td class="px-3 py-3 text-sm text-gray-600 whitespace-nowrap">{{ optional($newsItem->author)->name ?? '–' }}</td>
                        <td class="px-3 py-3 text-sm text-right whitespace-nowrap">
                            <a
                                href="{{ route('admin.news.edit', $newsItem) }}"
                                class="inline-flex items-center justify-center min-h-[36px] min-w-[44px] px-3 py-2 text-xs font-medium rounded border border-[#092E48] text-[#092E48] hover:bg-[#092E48]/5 touch-manipulation"
                            >
                                Bearbeiten
                            </a>
                            <form
                                action="{{ route('admin.news.destroy', $newsItem) }}"
                                method="POST"
                                class="inline-block ml-1"
                                onsubmit="return window.adminConfirmDelete(this)"
                                data-delete-prompt="Diese Nachricht wirklich löschen? Geben Sie zur Bestätigung „ja“ ein."
                            >
                                @csrf
                                @method('DELETE')
                                <button
                                    type="submit"
                                    class="inline-flex items-center justify-center min-h-[36px] min-w-[44px] px-3 py-2 text-xs font-medium rounded border border-red-600 text-red-700 hover:bg-red-50 touch-manipulation"
                                >
                                    Löschen
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-6 text-center text-sm text-gray-500">
                            Es sind noch keine Nachrichten vorhanden.
                            @if(isset($databaseCount) && $databaseCount > 0)
                                <br><span class="text-amber-600 mt-2 inline-block">Hinweis: In der Datenbank sind {{ $databaseCount }} Einträge – die Anzeige könnte an einem Fehler liegen.</span>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Karten-Liste für kleine Screens (unter 640px) --}}
    <div class="md:hidden divide-y divide-gray-200">
        @forelse ($newsItems as $newsItem)
            @php
                $status = $newsItem->status;
                $badgeClasses = match ($status) {
                    'draft' => 'bg-gray-100 text-gray-800',
                    'review' => 'bg-yellow-100 text-yellow-800',
                    'published' => 'bg-green-100 text-green-800',
                    'archived' => 'bg-red-100 text-red-800',
                    default => 'bg-gray-100 text-gray-800',
                };
            @endphp
            <div class="p-4">
                <div class="flex justify-between items-start gap-2">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badgeClasses }}">
                                {{ $statusLabels[$status] ?? ucfirst($status) }}
                            </span>
                            <span class="text-xs text-gray-500 font-mono">ID {{ $newsItem->display_news_id }}</span>
                        </div>
                        <p class="font-medium text-gray-900 truncate mt-1">{{ $newsItem->title }}</p>
                        <p class="text-sm text-gray-500 mt-0.5">{{ optional($newsItem->author)->name ?? '–' }} · {{ optional($newsItem->updated_at)->format('d.m.Y H:i') }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">Bilder: 0 · Videos: 0</p>
                    </div>
                    <div class="flex flex-col gap-2 shrink-0">
                        <a
                            href="{{ route('admin.news.edit', $newsItem) }}"
                            class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 text-sm font-medium rounded border border-[#092E48] text-[#092E48] hover:bg-[#092E48]/5 touch-manipulation"
                        >
                            Bearbeiten
                        </a>
                        <form action="{{ route('admin.news.destroy', $newsItem) }}" method="POST" onsubmit="return window.adminConfirmDelete(this)" data-delete-prompt="Diese Nachricht wirklich löschen? Geben Sie zur Bestätigung „ja“ ein.">
                            @csrf
                            @method('DELETE')
                            <button
                                type="submit"
                                class="w-full inline-flex items-center justify-center min-h-[44px] px-4 py-2 text-sm font-medium rounded border border-red-600 text-red-700 hover:bg-red-50 touch-manipulation"
                            >
                                Löschen
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="px-4 py-6 text-center text-sm text-gray-500 md:hidden">
                Es sind noch keine Nachrichten vorhanden.
                @if(isset($databaseCount) && $databaseCount > 0)
                    <br><span class="text-amber-600 mt-2 inline-block">In der Datenbank: {{ $databaseCount }} Einträge.</span>
                @endif
            </div>
        @endforelse
    </div>

    @if ($newsItems->hasPages())
        <div class="px-3 sm:px-6 py-3 border-t border-gray-200 overflow-x-auto">
            {{ $newsItems->links() }}
        </div>
    @endif
</div>
