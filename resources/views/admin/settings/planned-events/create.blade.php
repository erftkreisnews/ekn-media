@extends('layouts.admin')

@section('content')
    <div class="space-y-6 max-w-4xl">
        <a href="{{ route('admin.settings.planned-events.index') }}" class="text-sm text-gray-600 hover:text-[#092E48] inline-block">← Veranstaltungen</a>

        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Veranstaltung anlegen</h1>
            <p class="mt-2 text-sm text-gray-600">
                z. B. 24h Qualifiers und 24h-Hauptevent als zwei eigene Einträge. Erst <span class="font-medium text-gray-800">„Veranstaltung anlegen“</span> schreibt einen Datensatz — davor könnt ihr die Eingaben als <span class="font-medium text-gray-800">Entwurf</span> sichern (Session, ohne Datenbankeintrag).
            </p>
        </div>

        <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
            @include('admin.settings.planned-events._form', [
                'plannedEvent' => null,
                'venueAddressPresets' => $venueAddressPresets,
                'draftDefaults' => $draftDefaults ?? [],
            ])
        </div>
    </div>
@endsection
