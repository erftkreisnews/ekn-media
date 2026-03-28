@extends('layouts.admin')

@section('content')
    <x-admin.page
        title="Versand-Aktivität"
        subtitle="Wer hat wann was angeschaut oder heruntergeladen."
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
                    <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-3">Aktivitätsverlauf</h2>
                    @if($d->events->isEmpty())
                        <p class="text-sm text-gray-500 py-4">Noch keine Aktivität (Seite wurde nicht geöffnet).</p>
                    @else
                        <ul class="space-y-0 divide-y divide-gray-100">
                            @foreach($d->events as $event)
                                <li class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 py-3 first:pt-0">
                                    <div class="flex items-start gap-3">
                                        <span class="flex-shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-medium
                                            @if($event->event_type === 'opened') bg-blue-50 text-blue-700
                                            @elseif($event->event_type === 'confirmed') bg-emerald-50 text-emerald-700
                                            @elseif($event->event_type === 'download') bg-[#092E48]/10 text-[#092E48]
                                            @else bg-red-50 text-red-700
                                            @endif">
                                            @if($event->event_type === 'opened') Ö
                                            @elseif($event->event_type === 'confirmed') ✓
                                            @elseif($event->event_type === 'download') ↓
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
                                                @elseif($event->event_type === 'download')
                                                    @php
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
                                                    @if($labelForDownload)
                                                        Heruntergeladen:
                                                        <span class="text-gray-600 font-normal">{{ $typeForDownload }}</span>
                                                        ·
                                                        <span class="font-medium">{{ $labelForDownload }}</span>
                                                        @if($fileForDownload)
                                                            <span class="block mt-1 text-xs font-normal text-gray-500">Datei: {{ $fileForDownload }}</span>
                                                        @endif
                                                    @elseif($event->media_id)
                                                        Heruntergeladen: {{ $typeForDownload }} · Medium #{{ $event->media_id }} (Details fehlen – älterer Eintrag)
                                                    @else
                                                        Heruntergeladen: Eintrag ohne Mediendetails
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
