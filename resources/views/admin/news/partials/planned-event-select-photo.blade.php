@php
    $selectedPlannedEventId = old('planned_event_id', $newsItem?->planned_event_id);
@endphp

<div id="section-planned-event" class="rounded-xl border border-[#092E48]/20 bg-[#092E48]/[0.04] p-4 sm:p-5 space-y-3">
    <div>
        <h3 class="text-sm font-semibold text-[#092E48]">
            Geplante Veranstaltung
        </h3>
        <p class="mt-0.5 text-xs text-gray-600 max-w-prose">
            Optional: Event auswählen, um die Foto-Strecke sauber zuzuordnen.
        </p>
    </div>

    @if ($plannedEvents->isEmpty())
        <p class="text-sm text-gray-600">
            Noch keine Veranstaltung angelegt.
            <a href="{{ route('admin.settings.planned-events.create') }}" class="text-[#092E48] font-medium hover:underline">Veranstaltung anlegen</a>
        </p>
    @else
        <div class="space-y-2">
            <label for="planned_event_id" class="block text-sm font-medium text-gray-700">
                Veranstaltung
            </label>
            <select
                name="planned_event_id"
                id="planned_event_id"
                class="block w-full max-w-2xl rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] text-sm"
            >
                <option value="">— Keine Zuordnung —</option>
                @foreach ($plannedEvents as $ev)
                    <option
                        value="{{ $ev->id }}"
                        @selected((string) $selectedPlannedEventId === (string) $ev->id)
                    >
                        {{ $ev->name }}
                        @if (filled($ev->date_label))
                            ({{ $ev->date_label }})
                        @endif
                        @if (! $ev->is_active)
                            [inaktiv]
                        @endif
                    </option>
                @endforeach
            </select>
            @error('planned_event_id')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    @endif
</div>
