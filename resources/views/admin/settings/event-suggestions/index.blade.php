@extends('layouts.admin')

@section('content')
    <div class="space-y-6 max-w-5xl">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                <a href="{{ route('admin.event-planning.index') }}" class="text-gray-600 hover:text-[#092E48]">← Eventplanung</a>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 p-4 border border-green-200">
                <p class="text-sm text-green-800">{{ session('status') }}</p>
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-md bg-red-50 p-4 border border-red-200">
                <p class="text-sm text-red-800">{{ session('error') }}</p>
            </div>
        @endif

        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Event-Vorschläge</h1>
            <p class="mt-2 text-sm text-gray-600">
                Täglich (Cron + Scheduler) eingespielte öffentliche Termine rund um Sport, Konzerte und Shows.
                Über <span class="font-medium text-gray-800">„In Planung übernehmen“</span> öffnet sich das Anlegen-Formular mit vorbefüllten Feldern;
                Pflicht-Adressfelder bitte immer gegenprüfen, dann speichern.
                Quellen: <span class="font-medium text-gray-800">LANXESS arena</span> (offizieller JSON-Kalender), optional Ticketmaster, optional RSS-Feeds — siehe <code class="text-xs bg-gray-100 px-1 rounded">config/event_discovery.php</code> / Umgebungsvariablen.
            </p>
        </div>

        <div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden">
            @if ($suggestions->isEmpty())
                <p class="p-6 text-sm text-gray-600">Keine ausstehenden Vorschläge. Nach dem nächsten Abruf erscheinen neue Einträge hier.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-700">Zeitraum</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-700">Titel</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-700">Ort</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-700">Quelle</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-700">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($suggestions as $item)
                                <tr class="hover:bg-gray-50/80 align-top">
                                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                        @if ($item->starts_at)
                                            {{ $item->starts_at->format('d.m.Y') }}
                                            @if ($item->ends_at && $item->ends_at->ne($item->starts_at))
                                                <div class="text-xs text-gray-500">bis {{ $item->ends_at->format('d.m.Y') }}</div>
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900">{{ $item->title }}</div>
                                        @if ($item->category)
                                            <div class="text-xs text-gray-500 mt-0.5">{{ $item->category }}</div>
                                        @endif
                                        @if ($item->info_url)
                                            <a href="{{ $item->info_url }}" target="_blank" rel="noopener noreferrer" class="text-xs text-[#092E48] hover:underline mt-1 inline-block">Infoseite</a>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">
                                        {{ $item->venue_name ?: '—' }}
                                        @if ($item->venue_city)
                                            <div class="text-xs text-gray-500">{{ $item->venue_city }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">
                                        {{ strtoupper($item->source) }}
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <a
                                            href="{{ route('admin.settings.event-suggestions.import', $item) }}"
                                            class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858] mr-1"
                                        >
                                            In Planung übernehmen
                                        </a>
                                        <form method="post" action="{{ route('admin.settings.event-suggestions.dismiss', $item) }}" class="inline-block" onsubmit="return confirm('Vorschlag wirklich verwerfen?');">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                                                Verwerfen
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t border-gray-100">
                    {{ $suggestions->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
