@extends('layouts.admin')

@section('content')
    <div class="space-y-6 max-w-5xl">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <a href="{{ route('admin.settings.planned-events.edit', $plannedEvent) }}" class="text-sm text-gray-600 hover:text-[#092E48] inline-block">← Veranstaltung bearbeiten</a>
                <h1 class="mt-2 text-2xl font-semibold text-gray-900">Teilnehmende / Acts / Teams</h1>
                <p class="mt-1 text-sm text-gray-600">{{ $plannedEvent->name }}</p>
                <p class="mt-1 text-xs text-gray-500">Sortiert nach Startnummer (wie auf 24h-rennen.de/teilnehmer/).</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-700">
                    {{ $plannedEvent->teams->count() }} Einträge
                </span>
                <form method="post" action="{{ route('admin.settings.planned-events.teams.sync-24h', $plannedEvent) }}" onsubmit="return confirm('Starterliste mit 24h-rennen.de abgleichen und Referenzfotos laden?');">
                    @csrf
                    <button type="submit" class="inline-flex items-center rounded-md border border-[#092E48] bg-white px-3 py-2 text-sm font-medium text-[#092E48] hover:bg-gray-50">
                        Von 24h-rennen.de aktualisieren
                    </button>
                </form>
            </div>
        </div>

        @if (session('status'))
            <p class="text-sm text-green-700 bg-green-50 border border-green-200 rounded-md px-4 py-3">{{ session('status') }}</p>
        @endif
        @if (session('error'))
            <p class="text-sm text-red-700 bg-red-50 border border-red-200 rounded-md px-4 py-3">{{ session('error') }}</p>
        @endif

        <div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden">
            @if ($plannedEvent->teams->isEmpty())
                <p class="p-6 text-sm text-gray-600">Noch keine Teams hinterlegt.</p>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach ($plannedEvent->teams as $team)
                        <details class="group p-4 sm:p-5">
                            <summary class="cursor-pointer list-none flex items-start justify-between gap-4">
                                <span class="min-w-0">
                                    @if ($team->startNumber())
                                        <span class="inline-flex rounded bg-[#092E48]/10 text-[#092E48] px-2 py-0.5 text-xs font-semibold mr-2">#{{ $team->startNumber() }}</span>
                                    @endif
                                    <span class="font-medium text-gray-900">{{ $team->name }}</span>
                                    @if ($vehicle = $team->vehicleLabel())
                                        <span class="block mt-1 text-sm text-gray-600">{{ $vehicle }}</span>
                                    @endif
                                </span>
                                <span class="text-xs text-gray-500 group-open:hidden shrink-0">aufklappen</span>
                                <span class="text-xs text-gray-500 hidden group-open:inline">zuklappen</span>
                            </summary>
                            <div class="mt-3 flex flex-col sm:flex-row gap-4">
                                @if (filled($team->reference_image_path))
                                    <div class="shrink-0">
                                        <img
                                            src="{{ route('admin.settings.planned-events.teams.reference', [$plannedEvent, $team]) }}"
                                            alt="Referenzfahrzeug"
                                            class="w-full sm:w-48 max-w-[12rem] rounded-md border border-gray-200 bg-gray-50 object-cover"
                                            loading="lazy"
                                        />
                                        <p class="mt-1 text-xs text-gray-500">Offizielles Referenzfoto (ADAC)</p>
                                    </div>
                                @endif
                                <div class="text-sm text-gray-700 whitespace-pre-wrap min-w-0 flex-1">
                                    {{ $team->notes ?: 'Keine Zusatznotiz.' }}
                                </div>
                            </div>
                        </details>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
