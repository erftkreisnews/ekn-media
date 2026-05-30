@extends('layouts.admin')

@section('content')
    <div class="space-y-6 max-w-5xl">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                <a href="{{ route('admin.event-planning.index') }}" class="text-gray-600 hover:text-[#092E48]">← Eventplanung</a>
                <span class="text-gray-400 mx-2" aria-hidden="true">·</span>
                <a href="{{ route('admin.settings.index') }}" class="text-gray-500 hover:text-[#092E48] text-xs">Einstellungen</a>
                <a href="{{ route('admin.settings.house-squad.edit') }}" class="text-[#092E48] hover:underline font-medium">Haus-Kader</a>
                <a href="{{ route('admin.settings.planned-events.global') }}" class="text-[#092E48] hover:underline font-medium">Zusatz-Vorgaben (organisationsweit)</a>
                <a href="{{ route('admin.settings.event-suggestions.index') }}" class="text-[#092E48] hover:underline font-medium">Event-Vorschläge</a>
            </div>
            <a
                href="{{ route('admin.settings.planned-events.create') }}"
                class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]"
            >
                Veranstaltung anlegen
            </a>
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
            <h1 class="text-2xl font-semibold text-gray-900">KI-Vorgaben für Sport &amp; Events</h1>
            <p class="mt-1 text-sm font-medium text-gray-700">Geplante Veranstaltungen</p>
            <p class="mt-2 text-sm text-gray-600">
                Pro Event Name, Zeitraum, KI-Vorgaben und Teams pflegen. In der Meldung wählt ihr die passende Veranstaltung – die <span class="font-medium text-gray-800">Bild-KI</span> bekommt dann diesen Kontext automatisch. Den <a href="{{ route('admin.settings.house-squad.edit') }}" class="text-[#092E48] font-medium hover:underline">Haus-Kader</a> (z. B. 1. FC Köln Herren) pflegt ihr separat; er wird bei der Bild-KI zusätzlich mitgegeben, wenn aktiviert.
            </p>
        </div>

        <div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden">
            @if ($events->isEmpty())
                <p class="p-6 text-sm text-gray-600">Noch keine Veranstaltung angelegt.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-700">Veranstaltung</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-700">Zeitraum</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-700">Teams</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-700">PDF</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-700">Status</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-700">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($events as $event)
                                <tr class="hover:bg-gray-50/80">
                                    <td class="px-4 py-3">
                                        <span class="font-medium text-gray-900">{{ $event->name }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                        @if ($event->date_label)
                                            {{ $event->date_label }}
                                        @elseif ($event->starts_at)
                                            {{ $event->starts_at->format('d.m.Y') }}
                                            @if ($event->ends_at)
                                                – {{ $event->ends_at->format('d.m.Y') }}
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">{{ $event->teams_count }}</td>
                                    <td class="px-4 py-3 text-gray-600">
                                        @if ($event->hasSchedulePdf())
                                            <a href="{{ route('admin.settings.planned-events.schedule', $event) }}" class="text-[#092E48] hover:underline">PDF</a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($event->is_active)
                                            <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">aktiv</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-700">inaktiv</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                                        @if ($event->teams_count > 0)
                                            <a href="{{ route('admin.settings.planned-events.teams', $event) }}" class="text-[#092E48] hover:underline">Teilnehmer</a>
                                            <span class="text-gray-300">·</span>
                                        @endif
                                        <a href="{{ route('admin.settings.planned-events.edit', $event) }}" class="text-[#092E48] hover:underline">Bearbeiten</a>
                                        <form method="post" action="{{ route('admin.settings.planned-events.destroy', $event) }}" class="inline" onsubmit="return confirm('Veranstaltung wirklich löschen?');">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="confirmation" value="ja">
                                            <button type="submit" class="text-red-600 hover:underline">Löschen</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
