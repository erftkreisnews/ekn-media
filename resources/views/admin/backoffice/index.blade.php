@extends('layouts.admin')

@section('content')
    <div class="space-y-4">
        <div>
            <a href="{{ route('admin.settings.index') }}" class="text-sm text-gray-600 hover:text-[#092E48] mb-2 inline-block">← Einstellungen</a>
            <h1 class="text-2xl font-semibold text-gray-900">Backoffice</h1>
            <p class="mt-1 text-sm text-gray-600">
                Interner Backoffice‑Bereich für Benutzerverwaltung, Abrechnung und Nachverfolgung. Die ersten Module werden hier Schritt für Schritt ergänzt.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Benutzer &amp; Rollen</h2>
                <p class="mt-2 text-gray-900 font-semibold">
                    Admin‑Benutzer und Rechte verwalten
                </p>
                <p class="mt-1 text-sm text-gray-600">
                    Übersicht aller Benutzer mit ihren Rollen. Rollen können hier zugewiesen oder entfernt werden.
                </p>
                <div class="mt-4">
                    <a
                        href="{{ route('admin.backoffice.users.index') }}"
                        class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]"
                    >
                        Benutzer &amp; Rollen öffnen
                    </a>
                </div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Abrechnung (geplant)</h2>
                <p class="mt-2 text-gray-900 font-semibold">
                    Kunden‑Abrechnung und Verträge
                </p>
                <p class="mt-1 text-sm text-gray-600">
                    Hier können später Tarife, Verträge und Abrechnungen gepflegt werden.
                </p>
                <div class="mt-4">
                    <a
                        href="{{ route('admin.backoffice.billing.index') }}"
                        class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]"
                    >
                        Kunden‑Abrechnung öffnen
                    </a>
                </div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Nutzung &amp; Versand‑Auswertungen</h2>
                <p class="mt-2 text-gray-900 font-semibold">
                    Nutzung pro Nachricht erfassen &amp; auswerten
                </p>
                <p class="mt-1 text-sm text-gray-600">
                    Erfasse für veröffentlichte Nachrichten, welche Kunden/Produkte wie viele Bilder und Videominuten genutzt haben. Die Übersicht zeigt Forecasts für Monat, Quartal, Halbjahr und Jahr.
                </p>
                <div class="mt-4">
                    <a
                        href="{{ route('admin.backoffice.usage.index') }}"
                        class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]"
                    >
                        Nutzung &amp; Auswertungen öffnen
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection

