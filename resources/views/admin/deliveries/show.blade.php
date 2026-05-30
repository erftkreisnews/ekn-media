@extends('layouts.admin')

@section('content')
    <x-admin.page
        title="Versand-Aktivität"
        subtitle="Zugriffe chronologisch; Herunterladen nach Video, Bild und Audio, mehrfache Downloads pro Medium zusammengefasst."
    >
        <x-slot:actions>
            <a href="{{ route('admin.deliveries.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium rounded-2xl border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#092E48]">
                ← Zurück zur Übersicht
            </a>
        </x-slot:actions>

        @php
            $d = $delivery;
            $newsItem = $d->newsItem;
            $title = $newsItem->title ?? '—';
        @endphp

        <x-admin.card>
            <div class="p-4 sm:p-6 space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 border-b border-gray-200 pb-4">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase">Empfänger</p>
                        <p class="mt-0.5 text-sm font-medium text-gray-900">{{ $d->recipient_email }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase">Nachricht</p>
                        <p class="mt-0.5 text-sm">
                            <a href="{{ route('admin.news.edit', $newsItem) }}" class="text-[#092E48] hover:underline font-medium">{{ \Illuminate\Support\Str::limit($title, 50) }}</a>
                            <span class="text-gray-400">#{{ $newsItem->id ?? '—' }}</span>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase">Erstellt / Läuft ab</p>
                        <p class="mt-0.5 text-sm text-gray-700">{{ $d->created_at->format('d.m.Y H:i') }} · Läuft ab {{ $d->expires_at->format('d.m.Y H:i') }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase">Status</p>
                        <p class="mt-0.5">
                            @if($d->revoked_at)
                                <span class="inline-flex items-center rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700">Widerrufen</span>
                            @elseif($d->expires_at->isPast())
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">Abgelaufen</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Aktiv</span>
                            @endif
                        </p>
                    </div>
                </div>

                <div>
                    <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-3">Seitenaufruf &amp; Bestätigung</h2>
                    @if($activity['timeline']->isEmpty())
                        <p class="text-sm text-gray-500 py-2">Noch kein Aufruf oder keine Bestätigung protokolliert.</p>
                    @else
                        <ul class="space-y-0 divide-y divide-gray-100">
                            @foreach($activity['timeline'] as $event)
                                <li class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 py-3 first:pt-0">
                                    <div class="flex items-start gap-3">
                                        <span class="flex-shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-medium
                                            @if($event->event_type === 'opened') bg-blue-50 text-blue-700
                                            @elseif($event->event_type === 'confirmed') bg-emerald-50 text-emerald-700
                                            @else bg-red-50 text-red-700
                                            @endif">
                                            @if($event->event_type === 'opened') Ö
                                            @elseif($event->event_type === 'confirmed') ✓
                                            @else W
                                            @endif
                                        </span>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">
                                                @if($event->event_type === 'opened')
                                                    Seite geöffnet
                                                @elseif($event->event_type === 'confirmed')
                                                    Redaktion & Produkt bestätigt
                                                    @if($event->self_reported_organization_name || $event->self_reported_product_name)
                                                        <span class="text-gray-600 font-normal">({{ $event->self_reported_organization_name ?? '—' }} · {{ $event->self_reported_product_name ?? '—' }})</span>
                                                    @elseif($event->organization || $event->product)
                                                        <span class="text-gray-600 font-normal">({{ $event->organization?->name ?? '—' }} · {{ $event->product?->name ?? '—' }})</span>
                                                    @endif
                                                @else
                                                    Versand widerrufen
                                                @endif
                                            </p>
                                            <p class="text-xs text-gray-500 mt-0.5">{{ $event->created_at->format('d.m.Y H:i:s') }} Uhr</p>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="border-t border-gray-200 pt-6">
                    <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-1">Heruntergeladene Medien</h2>
                    <p class="text-xs text-gray-500 mb-4">Nach Medientyp sortiert. Mehrfaches Herunterladen desselben Inhalts erscheint als eine Zeile mit Download-Anzahl.</p>

                    @php
                        $downloadSections = [
                            ['title' => 'Video', 'key' => 'video'],
                            ['title' => 'Bilder', 'key' => 'image'],
                            ['title' => 'Audio', 'key' => 'audio'],
                            ['title' => 'Sonstiges', 'key' => 'other'],
                        ];
                        $hasAnyDownload = collect($activity['downloads'])->sum(fn (array $rows) => count($rows)) > 0;
                    @endphp

                    @if(!$hasAnyDownload)
                        <p class="text-sm text-gray-500 py-2">Noch kein Download protokolliert.</p>
                    @else
                        <div class="space-y-8">
                            @foreach($downloadSections as $section)
                                @php $rows = $activity['downloads'][$section['key']] ?? []; @endphp
                                @if(count($rows) === 0)
                                    @continue
                                @endif
                                <div>
                                    <h3 class="text-xs font-semibold text-[#092E48] uppercase tracking-wide mb-3">{{ $section['title'] }}</h3>
                                    <ul class="space-y-0 divide-y divide-gray-100">
                                        @foreach($rows as $row)
                                            @php
                                                $event = $row['event'];
                                                $count = $row['count'];
                                                $snapType = $event->download_media_type ?? $event->media?->type;
                                                $typeForDownload = match ($snapType) {
                                                    'image' => 'Bild',
                                                    'video' => 'Video',
                                                    'audio' => 'Audio',
                                                    default => 'Medium',
                                                };
                                                $labelForDownload = $event->download_media_label
                                                    ?? ($event->media ? $event->media->delivery_activity_label : null);
                                                $fileForDownload = $event->download_media_file_name
                                                    ?? ($event->media ? $event->media->delivery_activity_stored_file_name : null);
                                                if ($fileForDownload !== null && $labelForDownload !== null
                                                    && mb_strtolower(trim($fileForDownload)) === mb_strtolower(trim($labelForDownload))) {
                                                    $fileForDownload = null;
                                                }
                                            @endphp
                                            <li class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-1 py-3 first:pt-0">
                                                <div class="flex items-start gap-3">
                                                    <span class="flex-shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-medium bg-[#092E48]/10 text-[#092E48]">↓</span>
                                                    <div>
                                                        <p class="text-sm font-medium text-gray-900">
                                                            @if($labelForDownload)
                                                                Heruntergeladen:
                                                                <span class="text-gray-600 font-normal">{{ $typeForDownload }}</span>
                                                                ·
                                                                <span class="font-medium">{{ $labelForDownload }}</span>
                                                                @if($count > 1)
                                                                    <span class="text-gray-700 font-semibold"> = {{ $count }}×</span>
                                                                @endif
                                                                @if($fileForDownload)
                                                                    <span class="block mt-1 text-xs font-normal text-gray-500">Datei: {{ $fileForDownload }}</span>
                                                                @endif
                                                            @elseif($event->media_id)
                                                                Heruntergeladen: {{ $typeForDownload }} · Medium #{{ $event->media_id }} (Details fehlen – älterer Eintrag)
                                                                @if($count > 1)
                                                                    <span class="text-gray-700 font-semibold"> = {{ $count }}×</span>
                                                                @endif
                                                            @else
                                                                Heruntergeladen: Eintrag ohne Mediendetails
                                                                @if($count > 1)
                                                                    <span class="text-gray-700 font-semibold"> = {{ $count }}×</span>
                                                                @endif
                                                            @endif
                                                        </p>
                                                        <p class="text-xs text-gray-500 mt-0.5">
                                                            @if($count === 1)
                                                                {{ $row['first_at']->format('d.m.Y H:i:s') }} Uhr
                                                            @else
                                                                @if($row['first_at']->equalTo($row['last_at']))
                                                                    {{ $row['first_at']->format('d.m.Y H:i:s') }} Uhr
                                                                @else
                                                                    Erste: {{ $row['first_at']->format('d.m.Y H:i:s') }} Uhr · Letzte: {{ $row['last_at']->format('d.m.Y H:i:s') }} Uhr
                                                                @endif
                                                            @endif
                                                        </p>
                                                    </div>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                @if(!$d->revoked_at && !$d->expires_at->isPast())
                    <div class="pt-2 border-t border-gray-200">
                        <form action="{{ route('admin.deliveries.revoke', $d) }}" method="POST" class="inline" onsubmit="return confirm('Diesen Versand sofort widerrufen?');">
                            @csrf
                            <button type="submit" class="text-sm text-amber-600 hover:text-amber-800 font-medium">Versand widerrufen</button>
                        </form>
                    </div>
                @endif
            </div>
        </x-admin.card>
    </x-admin.page>
@endsection
