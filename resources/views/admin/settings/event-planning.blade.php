@extends('layouts.admin')

@section('content')
    <div class="space-y-8 max-w-4xl">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
            <a href="{{ route('admin.event-planning.index') }}" class="text-gray-600 hover:text-[#092E48]">← Eventplanung</a>
            <a href="{{ route('admin.settings.index') }}" class="text-gray-600 hover:text-[#092E48]">Alle Einstellungen</a>
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
            <p class="mt-2 text-sm text-gray-600">
                Pro <span class="font-medium text-gray-800">Veranstaltung</span> (z.&nbsp;B. 24h Qualifiers und 24h-Hauptevent getrennt) legt ihr Teams und Textvorgaben an und ordnet das Event in der Meldung zu – die Bild-KI nutzt das automatisch.
                Unter <a href="{{ route('admin.settings.house-squad.edit') }}" class="text-[#092E48] font-medium hover:underline">Haus-Kader</a> pflegt ihr ein Stammsquad (z. B. 1. FC Köln Herren) für die Bild-KI.
                Optional: organisationsweiter Zusatztext, wenn kein Event gewählt ist.
            </p>
        </div>

        <div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden">
            <div class="px-6 py-3 border-b border-gray-200 bg-[#092E48]/5">
                <h2 class="text-sm font-semibold tracking-wide text-gray-900 uppercase">Veranstaltungen &amp; Teams</h2>
            </div>
            <div class="p-6 space-y-4">
                <p class="text-sm text-gray-600">
                    Hier pflegt ihr die Events, zu denen ihr unterwegs schnell Bilder verschicken wollt – inklusive Teamliste und streckenspezifischen Hinweisen für die KI.
                </p>
                <div class="flex flex-wrap gap-3">
                    <a
                        href="{{ route('admin.settings.planned-events.index') }}"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]"
                    >
                        Veranstaltungen verwalten
                    </a>
                    <a
                        href="{{ route('admin.settings.planned-events.create') }}"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-gray-300 text-gray-800 hover:bg-gray-50"
                    >
                        Neue Veranstaltung
                    </a>
                    <a
                        href="{{ route('admin.settings.house-squad.edit') }}"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-gray-300 text-gray-800 hover:bg-gray-50"
                    >
                        Haus-Kader
                    </a>
                </div>
            </div>
        </div>

        <div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden">
            <div class="px-6 py-3 border-b border-gray-200 bg-gray-50">
                <h2 class="text-sm font-semibold tracking-wide text-gray-900 uppercase">Optional: organisationsweite Zusatz-Vorgaben</h2>
            </div>
            <form method="post" action="{{ route('admin.settings.planned-events.global.save') }}" class="p-6 space-y-6">
                @csrf
                <p class="text-xs text-gray-500">
                    Gilt nur, wenn an der <span class="font-medium text-gray-700">Meldung kein geplantes Event</span> gewählt ist. Sinnvoll als Fallback; pro Event sind die Einträge oben präziser.
                </p>
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="hidden" name="event_planning_enabled" value="0">
                    <input
                        type="checkbox"
                        name="event_planning_enabled"
                        value="1"
                        class="mt-1 rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]"
                        @checked($eventPlanningEnabled)
                    >
                    <span>
                        <span class="block text-sm font-medium text-gray-900">Zusatz-Vorgaben für die Bild-KI aktiv</span>
                        <span class="block text-xs text-gray-500 mt-0.5">Wenn aus, wird der Text unten nicht an die KI geschickt (bleibt gespeichert).</span>
                    </span>
                </label>

                <div>
                    <label for="event_planning_context" class="block text-sm font-medium text-gray-700 mb-1">Zusatz-Text</label>
                    <textarea
                        id="event_planning_context"
                        name="event_planning_context"
                        rows="8"
                        maxlength="60000"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] text-sm font-mono"
                        placeholder="Nur wenn kein Event in der Meldung gewählt ist …"
                    >{{ old('event_planning_context', $eventPlanningContext) }}</textarea>
                    @error('event_planning_context')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-2xl text-white bg-[#092E48] hover:bg-[#0b3858] min-h-[2.75rem]">
                    Zusatz-Vorgaben speichern
                </button>
            </form>
        </div>
    </div>
@endsection
