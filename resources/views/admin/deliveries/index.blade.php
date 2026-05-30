@extends('layouts.admin')

@section('content')
    <x-admin.page
        x-data="{
            search: '',
            matches(rowText) {
                if (!this.search) return true;
                return rowText.toLowerCase().includes(this.search.toLowerCase());
            }
        }"
        title="Medienversand"
        subtitle="{{ isset($newsItem) && $newsItem ? 'Nur Versände für Meldung #' . $newsItem->id : 'Übersicht aller Versände und Widerrufe.' }}"
    >
        @if(isset($newsItem) && $newsItem)
            <div class="mb-4 rounded-lg border border-[#092E48]/20 bg-[#092E48]/5 px-4 py-3 text-sm text-gray-700">
                <a href="{{ route('admin.news.edit', $newsItem) }}" class="font-medium text-[#092E48] hover:underline">← Zurück zur Meldung „{{ \Illuminate\Support\Str::limit($newsItem->title, 50) }}“ (#{{ $newsItem->id }})</a>
            </div>
        @endif

        <x-slot:actions>
            <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto items-stretch sm:items-center">
                <div class="flex-1 sm:w-64">
                    <input
                        type="search"
                        x-model="search"
                        placeholder="Suche nach Empfänger, Titel, ID …"
                        class="block w-full rounded-2xl border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                    >
                </div>
                @if(!isset($newsItem) || ! $newsItem)
                    @php
                        $isArchive = ($scope ?? 'active') === 'archive';
                        $baseParams = [];
                        if (request()->has('news_item_id')) {
                            $baseParams['news_item_id'] = request()->integer('news_item_id');
                        }
                    @endphp
                    <div class="flex justify-end">
                        <div class="inline-flex items-center rounded-full bg-gray-100 p-0.5 text-xs font-medium">
                            <a
                                href="{{ route('admin.deliveries.index', $baseParams) }}"
                                class="px-3 py-1.5 rounded-full transition-colors {{ $isArchive ? 'text-gray-600 hover:text-[#092E48]' : 'bg-white text-[#092E48] shadow-sm' }}"
                            >
                                Aktiv (letzte 48 Std.)
                            </a>
                            <a
                                href="{{ route('admin.deliveries.index', array_merge($baseParams, ['scope' => 'archive'])) }}"
                                class="px-3 py-1.5 rounded-full transition-colors {{ $isArchive ? 'bg-white text-[#092E48] shadow-sm' : 'text-gray-600 hover:text-[#092E48]' }}"
                            >
                                Archiv
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </x-slot:actions>

        @if (session('status'))
            <div class="rounded-md bg-green-50 p-4 border border-green-200">
                <p class="text-sm text-green-800">{{ session('status') }}</p>
            </div>
        @endif

        <x-admin.card>
            <x-admin.table-cards :items="$deliveries">
                <x-slot:table>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nachricht / NEWSID</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Empfänger</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Läuft ab</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Erstellt</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aktion</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse($deliveries as $d)
                                    @php
                                        $title = $d->newsItem->title ?? '—';
                                        $statusLabel = $d->revoked_at
                                            ? 'Widerrufen'
                                            : ($d->expires_at->isPast() ? 'Abgelaufen' : 'Aktiv');
                                    @endphp
                                    <tr
                                        class="hover:bg-gray-50/50 align-middle"
                                        x-show="matches(@js(Str::lower($title.' '.$d->recipient_email.' '.($d->newsItem->id ?? ''))))"
                                    >
                                        <td class="px-4 py-3 text-sm">
                                            <a href="{{ route('admin.news.edit', $d->newsItem) }}" class="text-[#092E48] hover:underline">
                                                {{ \Illuminate\Support\Str::limit($title, 40) }}
                                            </a>
                                            <span class="text-gray-400">#{{ $d->newsItem->id ?? '—' }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ $d->recipient_email }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ $d->expires_at->format('d.m.Y H:i') }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            @if($d->revoked_at)
                                                <span class="inline-flex items-center rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700">Widerrufen</span>
                                            @elseif($d->expires_at->isPast())
                                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">Abgelaufen</span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Aktiv</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ $d->created_at->format('d.m.Y H:i') }}</td>
                                        <td class="px-4 py-3 text-right text-sm">
                                            <a href="{{ route('admin.deliveries.show', $d) }}" class="text-[#092E48] hover:underline font-medium">Aktivität</a>
                                            @if(!$d->revoked_at && !$d->expires_at->isPast())
                                                <span class="text-gray-300 mx-1">|</span>
                                                <form action="{{ route('admin.deliveries.revoke', $d) }}" method="POST" class="inline" onsubmit="return confirm('Diesen Versand sofort widerrufen?');">
                                                    @csrf
                                                    <button type="submit" class="text-amber-600 hover:text-amber-800 font-medium">Widerrufen</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">Noch keine Versände.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-slot:table>

                <x-slot:cards>
                    @forelse($deliveries as $d)
                        @php
                            $title = $d->newsItem->title ?? '—';
                            $statusLabel = $d->revoked_at
                                ? 'Widerrufen'
                                : ($d->expires_at->isPast() ? 'Abgelaufen' : 'Aktiv');
                            $statusClass = $d->revoked_at
                                ? 'bg-red-50 text-red-700'
                                : ($d->expires_at->isPast() ? 'bg-slate-100 text-slate-600' : 'bg-emerald-50 text-emerald-700');
                        @endphp
                        <article
                            class="rounded-2xl border border-slate-200/80 bg-white shadow-sm px-4 py-3"
                            x-show="matches(@js(Str::lower($title.' '.$d->recipient_email.' '.($d->newsItem->id ?? ''))))"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h2 class="text-sm font-semibold text-slate-900 line-clamp-2">
                                        {{ $title }}
                                    </h2>
                                    <p class="mt-1 text-xs text-slate-500">
                                        #{{ $d->newsItem->id ?? '—' }} · {{ $d->recipient_email }}
                                    </p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        Läuft ab: {{ $d->expires_at->format('d.m.Y H:i') }} · Erstellt: {{ $d->created_at->format('d.m.Y H:i') }}
                                    </p>
                                </div>
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusClass }}">
                                    {{ $statusLabel }}
                                </span>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2 justify-end">
                                @if(!$d->revoked_at && !$d->expires_at->isPast())
                                    <form
                                        action="{{ route('admin.deliveries.revoke', $d) }}"
                                        method="POST"
                                        class="inline-flex w-1/2 sm:w-auto justify-end"
                                        onsubmit="return confirm('Diesen Versand sofort widerrufen?');"
                                    >
                                        @csrf
                                        <button
                                            type="submit"
                                            class="inline-flex justify-center items-center px-3 py-2 w-full sm:w-auto text-xs font-medium rounded-xl border border-amber-500 text-amber-700 hover:bg-amber-50"
                                        >
                                            Widerrufen
                                        </button>
                                    </form>
                                @endif
                                <a
                                    href="{{ route('admin.deliveries.show', $d) }}"
                                    class="inline-flex justify-center items-center px-3 py-2 w-full sm:w-auto text-xs font-semibold rounded-xl bg-[#092E48] text-white hover:bg-[#0b3858]"
                                >
                                    Aktivität
                                </a>
                            </div>
                        </article>
                    @empty
                        <div class="px-2 py-6 text-center text-sm text-gray-500">
                            Noch keine Versände.
                        </div>
                    @endforelse
                </x-slot:cards>
            </x-admin.table-cards>

            @if($deliveries->hasPages())
                <div class="mt-4">
                    {{ $deliveries->links() }}
                </div>
            @endif
        </x-admin.card>

        @if(isset($deliveryRuns) && $deliveryRuns->isNotEmpty())
            <x-admin.card class="mt-6">
                <h2 class="px-4 pt-4 text-sm font-semibold text-gray-900">FTP- / SFTP-Uploads</h2>
                <p class="px-4 pb-2 text-xs text-gray-500">Letzte Upload-Läufe (max. 20), z. B. WDR FTP.</p>

                <div class="overflow-x-auto px-4 pb-4">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Run</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ziel</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Typ</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ordner</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Start / Ende</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                        @foreach($deliveryRuns as $run)
                            @php
                                $dest = $run->deliveryDestination;
                                $type = $dest->type ?? 'ftp';
                                $status = $run->status ?? 'queued';
                                $badgeClass = match ($status) {
                                    'success' => 'bg-emerald-50 text-emerald-700',
                                    'failed' => 'bg-red-50 text-red-700',
                                    'running' => 'bg-amber-50 text-amber-700',
                                    default => 'bg-slate-100 text-slate-700',
                                };
                                $statusLabel = ucfirst($status);

                                $items = $run->items;
                                $firstItem = $items->firstWhere('status', '!=', 'skipped') ?? $items->first();
                                $newsItem = $firstItem?->media?->newsItem;
                                $folder = null;
                                if ($newsItem && $dest && in_array($type, ['ftp','ftps','sftp'], true) && $dest->usesFtpSubfolderPerNewsItem()) {
                                    $folder = $newsItem->ftpSubfolderNameForDestination($dest);
                                }
                                $total = $items->where('status', '!=', 'skipped')->count();
                                $success = $items->where('status', 'success')->count();
                                $failed = $items->where('status', 'failed')->count();
                            @endphp
                            <tr>
                                <td class="px-3 py-2 text-sm text-gray-600">#{{ $run->id }}</td>
                                <td class="px-3 py-2 text-sm">
                                    <div class="font-medium text-gray-900">{{ $dest->label ?? 'Unbekanntes Ziel' }}</div>
                                    @if($dest?->organization)
                                        <div class="text-xs text-gray-500">{{ $dest->organization->name }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-sm text-gray-600 uppercase">{{ $type }}</td>
                                <td class="px-3 py-2 text-sm text-gray-600">
                                    @if($folder)
                                        {{ $folder }}
                                    @else
                                        <span class="text-gray-400">–</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-sm">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badgeClass }}">
                                        {{ $statusLabel }}
                                        @if($total > 0)
                                            <span class="ml-1 text-[11px] font-normal">({{ $success }} / {{ $total }} ok)</span>
                                        @endif
                                        @if($status === 'failed' && $failed > 0)
                                            <span class="ml-1 text-[11px] font-normal">– {{ $failed }} Fehler</span>
                                        @endif
                                    </span>
                                    @if($run->message)
                                        <div class="mt-1 text-xs text-gray-500">{{ $run->message }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-xs text-gray-600">
                                    @if($run->started_at)
                                        <div>Start: {{ $run->started_at->format('d.m.Y H:i:s') }}</div>
                                    @endif
                                    @if($run->finished_at)
                                        <div>Ende: {{ $run->finished_at->format('d.m.Y H:i:s') }}</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        @endif
    </x-admin.page>
@endsection

