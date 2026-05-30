@php
    $selectedPlannedEventId = old('planned_event_id', $newsItem?->planned_event_id);
    $selectedStr = $selectedPlannedEventId !== null && $selectedPlannedEventId !== '' ? (string) $selectedPlannedEventId : '';
    $hasSchedulePdfExtractedColumn = \Illuminate\Support\Facades\Schema::hasColumn('planned_events', 'schedule_pdf_extracted_text');

    $eventsPayload = [];
    foreach ($plannedEvents as $ev) {
        $eventsPayload[(string) $ev->id] = [
            'name' => $ev->name,
            'date_label' => $ev->date_label,
            'starts_at' => $ev->starts_at?->format('d.m.Y'),
            'ends_at' => $ev->ends_at?->format('d.m.Y'),
            'ai_context' => $ev->ai_context ? \Illuminate\Support\Str::limit(trim(strip_tags((string) $ev->ai_context)), 1200) : null,
            'teams' => $ev->teams->map(fn ($t) => [
                'name' => $t->name,
                'notes' => $t->notes ? \Illuminate\Support\Str::limit(trim(strip_tags((string) $t->notes)), 200) : null,
            ])->values()->all(),
            'has_schedule_pdf' => $ev->hasSchedulePdf(),
            'schedule_pdf_name' => $ev->hasSchedulePdf()
                ? ($ev->schedule_pdf_original_name ?: 'programm.pdf')
                : null,
            'schedule_text_preview' => ($hasSchedulePdfExtractedColumn && filled($ev->schedule_pdf_extracted_text ?? null))
                ? \Illuminate\Support\Str::limit(trim(strip_tags((string) $ev->schedule_pdf_extracted_text)), 900)
                : null,
        ];
    }
@endphp

<div
    id="section-planned-event"
    class="rounded-xl border border-[#092E48]/20 bg-[#092E48]/[0.04] p-4 sm:p-5 space-y-3"
    x-data="{
        events: @js($eventsPayload),
        selectedId: @js($selectedStr),
        get summary() {
            const id = String(this.selectedId || '');
            return id && this.events[id] ? this.events[id] : null;
        }
    }"
>
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <h3 class="text-sm font-semibold text-[#092E48]">
                Geplante Veranstaltung
            </h3>
            <p class="mt-0.5 text-xs text-gray-600 max-w-prose">
                Zuerst das Event wählen – dann habt ihr Zeitraum, KI-Vorgaben und Teams im Blick, bevor ihr Titel und Text schreibt. Wird gespeichert und an die Bild-KI übergeben.
            </p>
        </div>
        <div class="shrink-0 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs font-medium">
            <a href="{{ route('admin.event-planning.index') }}" class="text-[#092E48] hover:underline">Eventplanung</a>
            <span class="text-gray-300" aria-hidden="true">|</span>
            <a href="{{ route('admin.settings.house-squad.edit') }}" class="text-[#092E48] hover:underline">Haus-Kader</a>
        </div>
    </div>

    @if ($plannedEvents->isEmpty())
        <p class="text-sm text-gray-600">
            Noch keine Veranstaltung angelegt.
            <a href="{{ route('admin.settings.planned-events.create') }}" class="text-[#092E48] font-medium hover:underline">Erste Veranstaltung anlegen</a>
        </p>
    @else
        <div class="space-y-2">
            <label for="planned_event_id" class="sr-only">Geplante Veranstaltung</label>
            <select
                name="planned_event_id"
                id="planned_event_id"
                x-model="selectedId"
                class="block w-full max-w-2xl rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] text-sm"
            >
                <option value="">— Keine Zuordnung (z. B. kein Sport-Event) —</option>
                @foreach ($plannedEvents as $ev)
                    <option value="{{ $ev->id }}">
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

        <template x-if="summary">
            <details class="rounded-lg border border-gray-200 bg-white group" open>
                <summary class="cursor-pointer list-none px-3 py-3 sm:px-4 sm:py-3 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-medium text-sm text-gray-900 truncate" x-text="summary.name"></p>
                        <p class="text-xs text-gray-600 mt-0.5">Event-Informationen ein-/ausklappen</p>
                    </div>
                    <span class="text-gray-400 group-open:rotate-180 transition-transform shrink-0" aria-hidden="true">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </span>
                </summary>

                <div class="px-3 pb-3 sm:px-4 sm:pb-4 text-sm space-y-2 border-t border-gray-100">
                    <details class="rounded-md border border-gray-200 bg-gray-50/70 group/sub" open>
                        <summary class="cursor-pointer list-none px-3 py-2 flex items-center justify-between gap-3">
                            <span class="text-xs font-semibold tracking-wide text-gray-700 uppercase">Zeitraum</span>
                            <span class="text-gray-400 group-open/sub:rotate-180 transition-transform shrink-0" aria-hidden="true">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </span>
                        </summary>
                        <div class="px-3 pb-3 text-xs text-gray-600 space-y-1">
                            <template x-if="summary.date_label">
                                <p><span class="font-medium text-gray-700">Zeitraum:</span> <span x-text="summary.date_label"></span></p>
                            </template>
                            <template x-if="summary.starts_at || summary.ends_at">
                                <p>
                                    <span class="font-medium text-gray-700">Kalender:</span>
                                    <span x-text="[summary.starts_at, summary.ends_at].filter(Boolean).join(' – ') || '—'"></span>
                                </p>
                            </template>
                        </div>
                    </details>

                    <template x-if="summary.has_schedule_pdf || summary.schedule_text_preview">
                        <details class="rounded-md border border-gray-200 bg-gray-50/70 group/sub">
                            <summary class="cursor-pointer list-none px-3 py-2 flex items-center justify-between gap-3">
                                <span class="text-xs font-semibold tracking-wide text-gray-700 uppercase">Programm</span>
                                <span class="text-gray-400 group-open/sub:rotate-180 transition-transform shrink-0" aria-hidden="true">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </span>
                            </summary>
                            <div class="px-3 pb-3 text-xs text-gray-600 space-y-2">
                                <template x-if="summary.has_schedule_pdf">
                                    <p>
                                        <span class="font-medium text-gray-700">Programm-PDF:</span>
                                        <span x-text="summary.schedule_pdf_name"></span>
                                        <span class="text-gray-500"> (Download unter Einstellungen → Veranstaltung bearbeiten)</span>
                                    </p>
                                </template>
                                <template x-if="summary.schedule_text_preview">
                                    <div>
                                        <p class="font-medium text-gray-700 mb-1">Auszug Programmtext (für Redaktion)</p>
                                        <p class="whitespace-pre-wrap max-h-36 overflow-y-auto border border-gray-100 rounded p-2 bg-white" x-text="summary.schedule_text_preview"></p>
                                    </div>
                                </template>
                            </div>
                        </details>
                    </template>

                    <template x-if="summary.ai_context">
                        <details class="rounded-md border border-gray-200 bg-gray-50/70 group/sub">
                            <summary class="cursor-pointer list-none px-3 py-2 flex items-center justify-between gap-3">
                                <span class="text-xs font-semibold tracking-wide text-gray-700 uppercase">KI-Vorgaben</span>
                                <span class="text-gray-400 group-open/sub:rotate-180 transition-transform shrink-0" aria-hidden="true">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </span>
                            </summary>
                            <div class="px-3 pb-3">
                                <p class="text-xs text-gray-600 whitespace-pre-wrap max-h-40 overflow-y-auto" x-text="summary.ai_context"></p>
                            </div>
                        </details>
                    </template>

                    <template x-if="summary.teams && summary.teams.length">
                        <details class="rounded-md border border-gray-200 bg-gray-50/70 group/sub">
                            <summary class="cursor-pointer list-none px-3 py-2 flex items-center justify-between gap-3">
                                <span class="text-xs font-semibold tracking-wide text-gray-700 uppercase">
                                    Teams (<span x-text="summary.teams.length"></span>)
                                </span>
                                <span class="text-gray-400 group-open/sub:rotate-180 transition-transform shrink-0" aria-hidden="true">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </span>
                            </summary>
                            <div class="px-3 pb-3">
                                <ul class="text-xs text-gray-600 space-y-1 list-disc list-inside max-h-56 overflow-y-auto pr-1">
                                    <template x-for="(t, i) in summary.teams" :key="i">
                                        <li>
                                            <span x-text="t.name"></span>
                                            <template x-if="t.notes">
                                                <span class="text-gray-500"> — <span x-text="t.notes"></span></span>
                                            </template>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </details>
                    </template>
                </div>
            </details>
        </template>
    @endif
</div>
