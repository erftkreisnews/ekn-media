@extends('layouts.admin')

@section('title', 'Eventplanung')

@section('content')
    <div class="space-y-8">
        <div>
            <p class="text-sm text-gray-600">
                <a href="{{ route('admin.dashboard') }}" class="hover:text-[#092E48]">Dashboard</a>
                <span class="mx-1 text-gray-400" aria-hidden="true">/</span>
                <span class="font-medium text-gray-900">Eventplanung</span>
            </p>
            <h1 class="mt-2 text-2xl font-semibold text-gray-900">Eventplanung</h1>
            <p class="mt-2 text-sm text-gray-600 max-w-2xl">
                Eigener Bereich für geplante Veranstaltungen, KI-Kontext für Meldungen, automatische Vorschläge und Haus-Kader.
                Technische Systemeinstellungen bleiben unter <a href="{{ route('admin.settings.index') }}" class="text-[#092E48] font-medium hover:underline">Einstellungen</a>.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <a
                href="{{ route('admin.settings.planned-events.index') }}"
                class="group block rounded-2xl border border-gray-200 bg-white p-6 shadow-sm hover:border-[#092E48]/20 hover:shadow-md transition"
            >
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Events</p>
                <h2 class="mt-2 text-lg font-semibold text-gray-900 group-hover:text-[#092E48]">Geplante Veranstaltungen</h2>
                <p class="mt-2 text-3xl font-bold text-[#092E48]">{{ $plannedEventsCount }}</p>
                <p class="mt-1 text-sm text-gray-600">Namen, Zeitraum, Teams, PDF-Ablauf, KI-Vorgaben</p>
                <p class="mt-4 text-sm font-medium text-[#092E48] group-hover:underline">Öffnen →</p>
            </a>

            <a
                href="{{ route('admin.settings.event-suggestions.index') }}"
                class="group block rounded-2xl border border-gray-200 bg-white p-6 shadow-sm hover:border-[#092E48]/20 hover:shadow-md transition"
            >
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Vorschläge</p>
                <h2 class="mt-2 text-lg font-semibold text-gray-900 group-hover:text-[#092E48]">Event-Vorschläge</h2>
                <p class="mt-2 text-3xl font-bold text-[#092E48]">{{ $pendingSuggestionsCount }}</p>
                <p class="mt-1 text-sm text-gray-600">Automatisch eingespielte öffentliche Termine (offen)</p>
                <p class="mt-4 text-sm font-medium text-[#092E48] group-hover:underline">Prüfen →</p>
            </a>

            <a
                href="{{ route('admin.settings.planned-events.global') }}"
                class="group block rounded-2xl border border-gray-200 bg-white p-6 shadow-sm hover:border-[#092E48]/20 hover:shadow-md transition"
            >
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Organisation</p>
                <h2 class="mt-2 text-lg font-semibold text-gray-900 group-hover:text-[#092E48]">Zusatz-Vorgaben</h2>
                <p class="mt-2 text-sm text-gray-600">Organisationsweite Texte für die Bild-KI / Events</p>
                <p class="mt-4 text-sm font-medium text-[#092E48] group-hover:underline">Bearbeiten →</p>
            </a>

            <a
                href="{{ route('admin.settings.house-squad.edit') }}"
                class="group block rounded-2xl border border-gray-200 bg-white p-6 shadow-sm hover:border-[#092E48]/20 hover:shadow-md transition sm:col-span-2 xl:col-span-1"
            >
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Referenz</p>
                <h2 class="mt-2 text-lg font-semibold text-gray-900 group-hover:text-[#092E48]">Haus-Kader</h2>
                <p class="mt-2 text-sm text-gray-600">z. B. Stammmannschaft — wird bei der Bild-KI mitgegeben, wenn aktiviert</p>
                <p class="mt-4 text-sm font-medium text-[#092E48] group-hover:underline">Pflegen →</p>
            </a>
        </div>
    </div>
@endsection
