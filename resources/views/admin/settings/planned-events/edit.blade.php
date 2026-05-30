@extends('layouts.admin')

@section('content')
    <div class="space-y-6 max-w-4xl">
        <a href="{{ route('admin.settings.planned-events.index') }}" class="text-sm text-gray-600 hover:text-[#092E48] inline-block">← Veranstaltungen</a>

        @if (session('status'))
            <div class="rounded-md bg-green-50 p-4 border border-green-200">
                <p class="text-sm text-green-800">{{ session('status') }}</p>
            </div>
        @endif
        @if (session('schedule_pdf_extraction'))
            <div class="rounded-md bg-amber-50 p-4 border border-amber-200">
                <p class="text-sm text-amber-900 font-medium">Programm-PDF: Text konnte nicht automatisch übernommen werden</p>
                <p class="text-sm text-amber-900 mt-1">{{ session('schedule_pdf_extraction') }}</p>
            </div>
        @endif

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <h1 class="text-2xl font-semibold text-gray-900">Veranstaltung bearbeiten</h1>
                <p class="mt-1 text-sm text-gray-600">{{ $plannedEvent->name }}</p>
                <p class="mt-2 text-xs text-gray-500 break-all">
                    Aufklappbare Liste aller Teams:
                    <a href="{{ route('admin.settings.planned-events.teams', $plannedEvent) }}" class="text-[#092E48] font-medium hover:underline">{{ route('admin.settings.planned-events.teams', $plannedEvent) }}</a>
                    @if ($plannedEvent->teams->isEmpty())
                        <span class="text-amber-700">(noch 0 Einträge – nach Bulk-Import oder Zeilen hier unten speichern, erscheinen sie dort.)</span>
                    @endif
                </p>
            </div>
            <a
                href="{{ route('admin.settings.planned-events.teams', $plannedEvent) }}"
                class="inline-flex items-center shrink-0 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-[#092E48] hover:bg-gray-50"
            >
                Teilnehmerübersicht
                @if ($plannedEvent->teams->isNotEmpty())
                    ({{ $plannedEvent->teams->count() }})
                @else
                    (leer)
                @endif
            </a>
        </div>

        <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
            @include('admin.settings.planned-events._form', [
                'plannedEvent' => $plannedEvent,
                'venueAddressPresets' => $venueAddressPresets,
                'draftDefaults' => [],
            ])
        </div>
    </div>
@endsection
