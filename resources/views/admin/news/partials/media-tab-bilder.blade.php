{{-- Tab „Bilder“: Drop-Zone (Drag & Drop + Klick) + Karten mit Thumbnail, Toggles, Aktionen (Nachricht → Bilder → Videos → Audios) --}}
@php
    $newsItemImages = $newsItem->images;
    $existingImageNames = $newsItemImages->pluck('original_name')->filter()->values()->toArray();
    $redactionBlockedByPlannedEvent = \Illuminate\Support\Facades\Schema::hasColumn('news_items', 'planned_event_id')
        && filled($newsItem->planned_event_id);
    $bulkPlannedEventImageAiEnabled = $bulkPlannedEventImageAiEnabled ?? false;
    $bulkEventMotivAssignEnabled = $bulkEventMotivAssignEnabled ?? false;
    $bulkEventMotivPicks = $bulkEventMotivPicks ?? [];
    $bulkEventMotivLabel = $bulkEventMotivLabel ?? null;
@endphp
<div class="bg-white shadow-sm rounded-2xl border border-gray-200 overflow-hidden min-w-0 max-w-full" x-data="imageUploadDropzone({{ json_encode($existingImageNames) }}, {{ (int) $newsItemImages->count() }})">
    <div class="px-4 sm:px-6 py-4 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <h2 class="text-base font-semibold text-gray-900">Bilder</h2>
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
            @if (!$newsItemImages->isEmpty() && trim((string) ($newsItem->author_credit ?? '')) !== '')
                <button type="submit" form="applyAuthorCreditForm"
                    class="inline-flex justify-center items-center px-4 py-2 text-sm font-medium rounded-2xl border border-[#092E48] text-[#092E48] bg-white hover:bg-[#092E48]/5 min-h-[2.75rem]">
                    Credit des Autors auf alle Bilder übernehmen
                </button>
            @endif
            <div class="flex flex-col items-stretch sm:items-center gap-1">
                <button type="submit" form="newsEditForm" data-upload="images"
                    class="inline-flex justify-center items-center px-4 py-2 text-sm font-medium rounded-2xl text-white bg-[#092E48] hover:bg-[#0b3858] min-h-[2.75rem]">
                    Hochladen
                </button>
                <p class="text-xs text-gray-500"
                   x-text="existingCount + ' Bilder bereits hochgeladen' + (pendingCount > 0 ? ' · ' + pendingCount + ' ausgewählt' : '')">
                    {{ (int) $newsItemImages->count() }} Bilder bereits hochgeladen
                </p>
            </div>
        </div>
    </div>

    {{-- Drop-Zone: Dateien ablegen oder klicken zum Auswählen (Duplikate nur in aktueller Auswahl) --}}
    <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
        <input x-ref="fileInput" type="file" name="images[]" form="newsEditForm" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" multiple
            class="sr-only"
            @change="pendingFiles = Array.from($event.target.files || []); _skipped = 0">
        <input x-ref="cameraInput" type="file" name="images[]" form="newsEditForm" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" capture="environment"
            class="sr-only"
            @change="$nextTick(() => { const h = document.getElementById('cameraUploadHint'); if (h) { h.textContent = ($event.target.files && $event.target.files.length > 0) ? '1 Kamera-Foto ausgewählt.' : 'Kein Kamera-Foto ausgewählt.'; } })">
        <div class="mb-3 sm:hidden">
            <button
                type="button"
                @click="$refs.cameraInput.click()"
                class="inline-flex w-full items-center justify-center rounded-xl border border-[#092E48] bg-[#092E48] px-4 py-2.5 text-sm font-medium text-white hover:bg-[#0b3858] min-h-[2.75rem]"
            >
                Foto aufnehmen / auswählen
            </button>
            <p class="mt-1 text-xs text-gray-500">Nimmt ein Handyfoto auf und lädt es mit „Hochladen“ direkt dieser News zu (JPG/PNG/WEBP).</p>
            <p id="cameraUploadHint" class="mt-1 text-xs text-[#092E48]">Kein Kamera-Foto ausgewählt.</p>
        </div>
        <div
            @click="$refs.fileInput.click()"
            @dragover.prevent="dragOver = true"
            @dragleave.prevent="dragOver = false"
            @drop.prevent="addFilesFromDrop($event.dataTransfer.files); dragOver = false"
            :class="dragOver ? 'border-[#092E48] bg-[#092E48]/5' : 'border-gray-300'"
            class="border-2 border-dashed rounded-2xl p-4 sm:p-8 text-center cursor-pointer transition-colors hover:border-[#092E48] hover:bg-gray-50/80">
            <p class="text-sm font-medium text-gray-700">Dateien hier ablegen oder klicken zum Auswählen</p>
            <p class="mt-1 text-xs text-gray-500">Erlaubt: JPG, PNG, WEBP. Doppelte Dateinamen werden nur innerhalb der aktuellen Auswahl ignoriert. Große Mengen werden beim Hochladen automatisch in Teilen von je {{ (int) config('media.news_upload_batch_size', 18) }} Dateien übertragen.</p>
            <p x-show="pendingCount > 0" x-cloak class="mt-2 text-sm text-[#092E48]" x-text="pendingCount + ' Datei(en) zum Hochladen ausgewählt'"></p>
            <p x-show="skippedCount > 0" x-cloak class="mt-1 text-xs text-amber-600" x-text="skippedCount + ' Doppelte übersprungen'"></p>
        </div>
        <button
            type="submit"
            form="newsEditForm"
            data-upload="images"
            class="sm:hidden mt-3 inline-flex w-full items-center justify-center rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-800 hover:bg-emerald-100 min-h-[2.75rem]"
        >
            Bilder hochladen
        </button>
    </div>
    @error('images')
        <p class="px-6 py-2 text-sm text-red-600">{{ $message }}</p>
    @enderror
    @error('images.*')
        <p class="px-6 py-2 text-sm text-red-600">{{ $message }}</p>
    @enderror

    <details class="px-4 sm:px-6 py-2">
        <summary class="text-sm font-medium text-gray-700 cursor-pointer list-none">Anforderungen an Pressebilder (aufklappen)</summary>
        <div class="mt-3 p-4 rounded-lg bg-gray-50 border border-gray-200 text-sm text-gray-700 space-y-3 max-h-64 overflow-y-auto">
            <p><strong>Technisch:</strong> Ziel: 4.500 Pixel an der langen Kante (keine Hochskalierung). Mindestgröße für Upload: 1.800 Pixel an der langen Kante (mobil-tauglich). Erlaubt: JPG/PNG/WEBP. Keine Kompressionsartefakte, keine Über-/Unterschärfung, keine Filter/Presets/Effekte, keine Wasserzeichen oder Logos.</p>
            <p><strong>Inhaltlich:</strong> Journalistisch relevant, dokumentarisch. Keine Manipulation, Retusche oder künstliche Elemente. Bearbeitung nur: Belichtung, Kontrast, Weißabgleich, moderate Schärfung. Keine dramatisierende Nachbearbeitung.</p>
            <p><strong>Rechtlich:</strong> Urheber und Nutzungsrechte geklärt, Persönlichkeitsrechte gewahrt, bei Minderjährigen Einwilligung prüfen. Symbolfotos kennzeichnen.</p>
            <p><strong>Metadaten (IPTC):</strong> Creator (Fotograf), Credit „Erftkreis News“, Copyright, Aufnahmedatum, Ort (Stadt, Bundesland), sachliche Bildbeschreibung (wer, was, wann, wo), Quelle.</p>
            <p class="text-gray-600">Bilder unter 4.000 Pixel lange Kante gelten als Dokumentationsbilder und nicht als Master-Pressebild. Kein Bild wird veröffentlicht oder weitergegeben, wenn eine Pflichtvorgabe nicht erfüllt ist.</p>
        </div>
    </details>

    <div class="px-4 sm:px-6 py-4 space-y-4">
        @if ($newsItemImages->isEmpty())
            <p class="text-sm text-gray-500">Noch keine Bilder für diese Meldung hochgeladen.</p>
        @else
            <div
                class="space-y-4 min-w-0"
                x-data="{
                    overlayImage: null,
                    overlayTitle: '',
                    orderedIds: {{ json_encode($newsItemImages->pluck('id')->values()->all()) }},
                    selected: {},
                    lastClickedIndex: null,
                    init() {
                        this.orderedIds.forEach((id) => { this.selected[id] = false; });
                    },
                    toggleAt(index, event) {
                        const id = this.orderedIds[index];
                        if (event.shiftKey && this.lastClickedIndex !== null) {
                            const start = Math.min(this.lastClickedIndex, index);
                            const end = Math.max(this.lastClickedIndex, index);
                            for (let i = start; i <= end; i++) {
                                this.selected[this.orderedIds[i]] = true;
                            }
                            this.lastClickedIndex = index;
                        } else {
                            this.selected[id] = !this.selected[id];
                            this.lastClickedIndex = index;
                        }
                    },
                    selectedIdsList() {
                        return this.orderedIds.filter((id) => this.selected[id]);
                    },
                    selectedCount() {
                        return this.selectedIdsList().length;
                    },
                    clearSelection() {
                        this.orderedIds.forEach((id) => { this.selected[id] = false; });
                    }
                }"
                @keydown.escape.window="overlayImage = null"
            >
                <form
                    id="bulk-delete-images-form"
                    method="POST"
                    action="{{ route('admin.news.media.bulk-destroy', $newsItem) }}"
                    class="flex flex-wrap items-center gap-2 rounded-xl border border-gray-200 bg-gray-50/80 px-3 py-2"
                    x-show="selectedCount() > 0"
                    x-cloak
                    onsubmit="return window.confirm('Die ausgewählten Bilder wirklich endgültig löschen?');"
                >
                    @csrf
                    <template x-for="id in selectedIdsList()" :key="id">
                        <input type="hidden" name="delete_media[]" :value="id">
                    </template>
                    <span class="text-sm font-medium text-gray-800" x-text="selectedCount() + ' ausgewählt'"></span>
                    <button
                        type="submit"
                        class="inline-flex items-center gap-1 rounded-xl border border-red-200 bg-white px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50"
                    >
                        Ausgewählte löschen
                    </button>
                    <button
                        type="button"
                        class="text-sm text-gray-600 hover:text-gray-900 underline"
                        @click="clearSelection()"
                    >
                        Auswahl aufheben
                    </button>
                    <span class="text-xs text-gray-500 w-full sm:w-auto sm:ml-2">Shift+Klick: Bereich markieren · Klick: einzeln an/abwählen</span>
                </form>

                @if ($bulkEventMotivAssignEnabled)
                <form
                    method="POST"
                    action="{{ route('admin.news.media.bulk-assign-event-motiv', $newsItem) }}"
                    class="flex flex-col gap-3 rounded-xl border border-emerald-200/80 bg-emerald-50/40 px-3 py-3"
                    x-show="selectedCount() > 0"
                    x-cloak
                >
                    @csrf
                    <template x-for="id in selectedIdsList()" :key="'bulk-assign-'+id">
                        <input type="hidden" name="bulk_assign_media_ids[]" :value="id">
                    </template>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-semibold text-emerald-900">Künstler für alle Ausgewählten</span>
                        <span class="text-xs text-gray-600">Name wie auf der Medienseite vor den Motiv-Text der Unterschrift</span>
                    </div>
                    <p class="text-xs text-gray-700 max-w-prose">
                        Erspart Einzelbearbeitung: Einmal Künstler/Team aus der Liste wählen –
                        gilt für alle markierten Bilder. Bereits eingefügte Namen oder Vorkommen im Motiv werden nicht verdoppelt.
                        Ort/Datum-Schwanz (wenn möglich) bleibt erhalten.
                    </p>
                    <div class="flex flex-col sm:flex-row flex-wrap gap-2 sm:items-end">
                        <div class="min-w-[12rem] flex-1">
                            <label for="bulk_event_motiv_assign" class="block text-xs font-medium text-gray-700 mb-1">
                                Künstler / Team
                                @if (filled($bulkEventMotivLabel))
                                    <span class="font-normal text-gray-500">(„{{ Str::limit($bulkEventMotivLabel, 64) }}“)</span>
                                @endif
                            </label>
                            <select
                                name="event_motiv_assign"
                                id="bulk_event_motiv_assign"
                                required
                                class="block w-full max-w-xl rounded-xl border-gray-300 shadow-sm focus:border-emerald-700 focus:ring-emerald-600 text-sm bg-white"
                            >
                                <option value="">— Künstler wählen —</option>
                                @foreach ($bulkEventMotivPicks as $pick)
                                    @php
                                        $row = is_array($pick) ? $pick : ['value' => $pick, 'label' => $pick];
                                    @endphp
                                    <option value="{{ $row['value'] }}">{{ Str::limit($row['label'], 120) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button
                            type="submit"
                            name="bulk_motiv_action"
                            value="caption_only"
                            class="inline-flex justify-center items-center px-4 py-2 text-sm font-medium rounded-2xl border border-emerald-700 bg-white text-emerald-900 hover:bg-emerald-50 min-h-[2.75rem]"
                        >
                            In Unterschriften speichern
                        </button>
                        @if ($bulkPlannedEventImageAiEnabled)
                            <button
                                type="submit"
                                name="bulk_motiv_action"
                                value="caption_and_ai"
                                onclick="return window.confirm('Unterschriften anpassen und KI für alle ausgewählten Bilder starten?');"
                                class="inline-flex justify-center items-center px-4 py-2 text-sm font-medium rounded-2xl text-white bg-emerald-800 hover:bg-emerald-900 min-h-[2.75rem]"
                            >
                                Speichern &amp; KI
                            </button>
                        @endif
                    </div>
                </form>
                @endif

                @if ($bulkPlannedEventImageAiEnabled)
                <form
                    id="bulk-planned-event-ai-form"
                    method="POST"
                    action="{{ route('admin.news.media.bulk-planned-event-ai', $newsItem) }}"
                    class="flex flex-col gap-2 rounded-xl border border-[#092E48]/25 bg-[#092E48]/[0.06] px-3 py-3"
                    x-show="selectedCount() > 0"
                    x-cloak
                >
                    @csrf
                    <template x-for="id in selectedIdsList()" :key="'bulk-ai-'+id">
                        <input type="hidden" name="bulk_ai_media_ids[]" :value="id">
                    </template>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-semibold text-[#092E48]">KI-Nachbearbeitung (Veranstaltung)</span>
                        <span class="text-xs text-gray-600">für die Auswahl in die Warteschlange legen</span>
                    </div>
                    <p class="text-xs text-gray-600 max-w-prose">
                        Nutzt die dieser Meldung zugewiesene <strong class="font-medium text-gray-800">geplante Veranstaltung</strong> (Teams, Kontext) sowie pro Bild die
                        <strong class="font-medium text-gray-800">gespeicherte</strong> Unterschrift und die Schlagwörter aus den IPTC-/Metadatenfeldern (auf der Bearbeitungsseite jedes Bildes).
                        Vorher ggf. dort speichern.
                        <strong class="font-medium text-gray-800">Fotograf bleibt unverändert.</strong>
                    </p>
                    @if (! empty($bulkEventMotivPicks))
                        <div class="flex flex-col sm:flex-row sm:flex-wrap sm:items-end gap-2">
                            <div class="min-w-[12rem] flex-1">
                                <label for="bulk_event_motiv_focus" class="block text-xs font-medium text-gray-700 mb-1">
                                    Künstler / Team aus der Veranstaltung
                                    @if (filled($bulkEventMotivLabel))
                                        <span class="font-normal text-gray-500">(„{{ Str::limit($bulkEventMotivLabel, 64) }}“)</span>
                                    @endif
                                </label>
                                <select
                                    name="event_motiv_focus"
                                    id="bulk_event_motiv_focus"
                                    class="block w-full max-w-xl rounded-xl border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] text-sm bg-white"
                                >
                                    <option value="">Ohne zusätzlichen Künstler-Fokus</option>
                                    @foreach ($bulkEventMotivPicks as $pick)
                                        @php
                                            $row = is_array($pick) ? $pick : ['value' => $pick, 'label' => $pick];
                                        @endphp
                                        <option value="{{ $row['value'] }}">{{ Str::limit($row['label'], 120) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button
                                type="submit"
                                onclick="return window.confirm('KI für die ausgewählten Bilder starten? Gespeicherte Unterschrift und Schlagwörter pro Bild werden wie auf der Medienseite an die KI übergeben.');"
                                class="inline-flex justify-center items-center px-4 py-2 text-sm font-medium rounded-2xl text-white bg-[#092E48] hover:bg-[#0b3858] min-h-[2.75rem]"
                            >
                                KI für Auswahl starten
                            </button>
                        </div>
                    @else
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-xs text-gray-600">Es sind keine Teams/Künstler an der Veranstaltung hinterlegt – die KI nutzt nur den allgemeinen Event-Kontext.</p>
                            <button
                                type="submit"
                                onclick="return window.confirm('KI für die ausgewählten Bilder starten? Gespeicherte Unterschrift und Schlagwörter pro Bild werden wie auf der Medienseite an die KI übergeben.');"
                                class="inline-flex justify-center items-center px-4 py-2 text-sm font-medium rounded-2xl text-white bg-[#092E48] hover:bg-[#0b3858] min-h-[2.75rem]"
                            >
                                KI für Auswahl starten
                            </button>
                        </div>
                    @endif
                </form>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 min-w-0">
                {{-- Lightbox: nur Bild, Schließen rechts über dem Bild --}}
                <div x-show="overlayImage" x-cloak
                    x-effect="document.body.classList.toggle('overflow-hidden', !!overlayImage)"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60"
                    x-transition:enter="ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    @click.self="overlayImage = null">
                    <div class="relative inline-block max-w-full rounded-xl shadow-2xl overflow-hidden" @click.stop>
                        <img :src="overlayImage" :alt="overlayTitle" class="block w-auto h-auto max-h-[50vh] object-contain max-w-[min(100vw-2rem,42rem)]">
                        <button type="button" @click="overlayImage = null"
                            class="absolute right-2 top-2 px-3 py-1.5 text-sm font-medium rounded bg-black/60 text-white hover:bg-black/70 focus:outline-none focus:ring-2 focus:ring-white/50">
                            Schließen
                        </button>
                    </div>
                </div>
                @foreach ($newsItemImages as $m)
                    <div
                        class="border border-gray-200 rounded-2xl overflow-hidden bg-white flex flex-col relative transition-shadow"
                        :class="selected[{{ $m->id }}] ? 'ring-2 ring-[#092E48] ring-offset-2' : ''"
                    >
                        <div class="absolute top-2 right-2 z-30" @click.stop>
                            <label class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-white/90 bg-white/95 shadow-sm cursor-pointer hover:bg-white"
                                   title="Zur Lösch-Auswahl markieren (Shift+Klick für Bereich)">
                                <input
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-400 text-[#092E48] focus:ring-[#092E48]"
                                    :checked="selected[{{ $m->id }}]"
                                    @click.prevent="toggleAt({{ $loop->index }}, $event)"
                                >
                            </label>
                        </div>
                        {{-- Ampel: farbiger Balken am linken Bildrand (grün / orange / rot) + Badge links oben --}}
                        <div class="aspect-video bg-gray-100 relative flex border-l-4 cursor-zoom-in
                            @if ($m->quality_status === 'ok') border-l-green-500
                            @elseif ($m->quality_status === 'fail') border-l-red-500
                            @endif"
                            @if ($m->quality_status === 'warning') style="border-left-color: #d97706 !important;"
                            @endif
                            data-image-url="{{ $m->preview_url ?? $m->url }}" data-image-title="{{ e($m->display_name) }}"
                            @click="overlayImage = $event.currentTarget.dataset.imageUrl; overlayTitle = $event.currentTarget.dataset.imageTitle || ''">
                            @php $imgDisplayUrl = $m->thumb_url ?: ($m->preview_url ?: $m->url); @endphp
                            @if($imgDisplayUrl)
                                <img src="{{ $imgDisplayUrl }}" alt="{{ $newsItem->title ?: ($newsItem->subheadline ?: 'Bildmaterial von Erftkreis News Media') }}" class="w-full h-full object-cover pointer-events-none">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-gray-500 text-sm text-center px-2">Redaction ausstehend</div>
                            @endif
                            @if ($m->quality_status)
                                <span class="absolute top-2 left-2 z-10 px-2 py-1 text-xs font-medium rounded shadow-sm"
                                    @if ($m->quality_status === 'ok') style="background-color: #16a34a; color: #fff;"
                                    @elseif ($m->quality_status === 'warning') style="background-color: #d97706; color: #fff;"
                                    @else style="background-color: #dc2626; color: #fff;"
                                    @endif
                                    title="{{ $m->quality_notes }}">
                                    @if ($m->quality_status === 'ok') Presse-OK
                                    @elseif ($m->quality_status === 'warning') Warnung
                                    @else Mängel @endif
                                </span>
                            @endif
                        </div>
                        <div class="p-3 space-y-2 flex-1 flex flex-col">
                            <p class="text-xs font-mono text-gray-500">{{ sprintf('%06d', $m->id) }}</p>
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-medium text-gray-900 truncate min-w-0" title="{{ $m->display_name }}">{{ $m->display_name }}</p>
                                @if ($m->is_teaser)
                                    <span class="flex-shrink-0 px-2 py-0.5 text-xs font-medium rounded bg-[#092E48] text-white">Teaser</span>
                                @endif
                            </div>
                            <p class="text-xs text-gray-500">#{{ $m->id }}</p>

                            {{-- 3 einzelne Status-Kästchen --}}
                            <div class="mt-2 flex flex-wrap items-center justify-center gap-2">
                                {{-- Status: Sichtbarkeit (inverse Logik) --}}
                                <label
                                    class="relative inline-flex items-center justify-center cursor-pointer w-12 h-9 rounded-lg border transition-colors
                                        min-h-[2.1rem]
                                        {{ !$m->is_visible ? 'bg-rose-50 border-rose-200 text-rose-700' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50' }}"
                                    title="Bild nicht sichtbar"
                                    data-status-label="1"
                                    data-status-kind="not_visible"
                                >
                                    <input type="hidden" name="media[{{ $m->id }}][is_visible]" value="1" form="newsEditForm">
                                    <input type="checkbox" name="media[{{ $m->id }}][is_visible]" value="0" form="newsEditForm" class="absolute inset-0 opacity-0 cursor-pointer" data-status-input="1"
                                        @checked(!$m->is_visible)>

                                    {{-- Status-Icon --}}
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-5.523 0-10-4.477-10-10 0-1.017.152-1.998.435-2.925M9.88 9.88A3 3 0 0012 15a3 3 0 002.12-.88M9.88 9.88l4.24 4.24m3.005-3.005A9.953 9.953 0 0122 9c0 5.523-4.477 10-10 10a9.953 9.953 0 01-3.005-.965M3 3l18 18"/>
                                    </svg>

                                    {{-- Häkchen (nur wenn aktiv) --}}
                                    <svg
                                        data-status-icon="on"
                                        class="absolute -top-2 -right-2 w-5 h-5 {{ !$m->is_visible ? 'block' : 'hidden' }}"
                                        fill="none"
                                        stroke="white"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                        style="background-color: rgba(220,38,38,0.95); border-radius: 9999px; padding: 3px;"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                    </svg>

                                    {{-- X (wenn deaktiviert/aktiv=false) --}}
                                    <svg
                                        data-status-icon="off"
                                        class="absolute -top-2 -right-2 w-5 h-5 {{ !$m->is_visible ? 'hidden' : 'block' }}"
                                        fill="none"
                                        stroke="rgb(220 38 38)"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                        style="background-color: rgba(255,255,255,1); border-radius: 0.5rem; padding: 3px; border:1px solid rgba(220,38,38,0.25);"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 6l12 12M18 6L6 18"/>
                                    </svg>

                                    <span class="sr-only">Nicht sichtbar</span>
                                </label>

                                {{-- Status: Versand --}}
                                <label
                                    class="relative inline-flex items-center justify-center cursor-pointer w-12 h-9 rounded-lg border transition-colors
                                        min-h-[2.1rem]
                                        {{ $m->versand ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50' }}"
                                    title="Für Versand aktivieren"
                                    data-status-label="1"
                                    data-status-kind="versand"
                                >
                                    <input type="hidden" name="media[{{ $m->id }}][versand]" value="0" form="newsEditForm">
                                    <input type="checkbox" name="media[{{ $m->id }}][versand]" value="1" form="newsEditForm" class="absolute inset-0 opacity-0 cursor-pointer" data-status-input="1"
                                        @checked($m->versand)>
                                    {{-- Status-Icon --}}
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 17h2l2-8h10l2 8h2M7 17v2M17 17v2M9 9h6"/>
                                    </svg>

                                    {{-- Häkchen (nur wenn aktiv) --}}
                                    <svg
                                        data-status-icon="on"
                                        class="absolute -top-2 -right-2 w-5 h-5 {{ $m->versand ? 'block' : 'hidden' }}"
                                        fill="none"
                                        stroke="white"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                        style="background-color: rgba(16,185,129,0.95); border-radius: 9999px; padding: 3px;"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                    </svg>

                                    {{-- X (wenn deaktiviert) --}}
                                    <svg
                                        data-status-icon="off"
                                        class="absolute -top-2 -right-2 w-5 h-5 {{ $m->versand ? 'hidden' : 'block' }}"
                                        fill="none"
                                        stroke="rgb(16 185 129)"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                        style="background-color: rgba(255,255,255,1); border-radius: 0.5rem; padding: 3px; border:1px solid rgba(16,185,129,0.25);"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 6l12 12M18 6L6 18"/>
                                    </svg>

                                    <span class="sr-only">Versand</span>
                                </label>

                                {{-- Status: Teaser --}}
                                <label
                                    class="relative inline-flex items-center justify-center cursor-pointer w-12 h-9 rounded-lg border transition-colors
                                        min-h-[2.1rem]
                                        {{ $m->is_teaser ? 'bg-[#092E48] border-[#092E48] text-white' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50' }}"
                                    title="Teaser-Bild"
                                    data-status-label="1"
                                    data-status-kind="teaser"
                                >
                                    <input type="radio" name="teaser_media_id" value="{{ $m->id }}" form="newsEditForm" class="absolute inset-0 opacity-0 cursor-pointer" data-status-input="1"
                                        @checked($m->is_teaser)>
                                    {{-- Status-Icon --}}
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                                    </svg>

                                    {{-- Häkchen (nur wenn aktiv) --}}
                                    <svg
                                        data-status-icon="on"
                                        class="absolute -top-2 -right-2 w-5 h-5 {{ $m->is_teaser ? 'block' : 'hidden' }}"
                                        fill="none"
                                        stroke="white"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                        style="background-color: rgba(9,46,72,0.95); border-radius: 9999px; padding: 3px;"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                    </svg>

                                    {{-- X (wenn deaktiviert) --}}
                                    <svg
                                        data-status-icon="off"
                                        class="absolute -top-2 -right-2 w-5 h-5 {{ $m->is_teaser ? 'hidden' : 'block' }}"
                                        fill="none"
                                        stroke="rgb(9 46 72)"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                        style="background-color: rgba(255,255,255,1); border-radius: 0.5rem; padding: 3px; border:1px solid rgba(9,46,72,0.25);"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 6l12 12M18 6L6 18"/>
                                    </svg>

                                    <span class="sr-only">Teaser</span>
                                </label>
                            </div>

                            @include('admin.news.partials.media-delivery-org-checkboxes', ['newsItem' => $newsItem, 'm' => $m, 'organizationsForMediaDelivery' => $organizationsForMediaDelivery ?? collect()])

                            <div class="flex flex-wrap items-center justify-center gap-2 pt-2 border-t border-gray-100">
                                <a href="{{ route('admin.news.media.edit', [$newsItem, $m->id]) }}"
                                   class="inline-flex items-center justify-center w-9 h-9 rounded-xl border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 hover:border-[#092E48] hover:text-[#092E48] transition-colors"
                                   title="Bearbeiten">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>

                                <button type="submit" form="unlink-media-{{ $m->id }}"
                                        class="inline-flex items-center justify-center w-9 h-9 rounded-xl border border-amber-300 bg-white text-amber-700 hover:bg-amber-50 transition-colors"
                                        title="Verknüpfung löschen (Bild bleibt)">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 12l9 9v-7h7l-7-7z"/>
                                    </svg>
                                </button>

                                @if ($m->is_unkentlich)
                                    <button type="submit" form="unkentlich-media-{{ $m->id }}"
                                            class="inline-flex items-center justify-center w-9 h-9 rounded-xl border border-amber-400 bg-amber-50 text-amber-800 hover:bg-amber-100 transition-colors"
                                            title="Unkenntlich aufheben">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </button>
                                @else
                                    @if ($redactionBlockedByPlannedEvent)
                                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl border border-gray-200 bg-gray-50 text-gray-400 cursor-not-allowed"
                                              title="Anonymisierung nicht verfügbar: Meldung hat eine zugewiesene geplante Veranstaltung."
                                              role="img" aria-label="Anonymisierung deaktiviert">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                            </svg>
                                        </span>
                                    @else
                                        <a href="{{ route('admin.news.media.edit', [$newsItem, $m->id]) }}#redaction"
                                           class="inline-flex items-center justify-center w-9 h-9 rounded-xl border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 hover:border-amber-400 text-amber-600 transition-colors"
                                           title="Anonymisierung (Kennzeichen & Gesichter)">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                            </svg>
                                        </a>
                                    @endif
                                @endif

                                <a href="{{ $m->url }}" download="{{ $m->original_name }}"
                                   class="inline-flex items-center justify-center w-9 h-9 rounded-xl border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 hover:border-[#092E48] hover:text-[#092E48] transition-colors"
                                   title="Download">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                </a>

                                <button type="submit" form="destroy-media-{{ $m->id }}"
                                        class="inline-flex items-center justify-center w-9 h-9 rounded-xl border border-red-200 bg-white text-red-600 hover:bg-red-50 hover:border-red-300 transition-colors"
                                        title="Bild endgültig löschen">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7h6m2 0H7m4-3h2a2 2 0 012 2v1H9V6a2 2 0 012-2z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
                </div>
            </div>
        @endif

        <div class="pt-4 mt-4 border-t border-gray-200 text-xs text-gray-600 space-y-1">
            <p><strong>Bearbeiten:</strong> Öffnet eine eigene Seite zur Prüfung und Anpassung der Metadaten (Dateiname, Pfad, Größe, Sichtbarkeit, Versand, Beschriftung).</p>
            <p><strong>Mehrfach löschen:</strong> Bilder per Kästchen auswählen, mit <strong>Shift+Klick</strong> einen Bereich markieren, dann <strong>„Ausgewählte löschen“</strong>.</p>
            @if ($bulkEventMotivAssignEnabled)
                <p><strong>Schnell Künstler:</strong> Bei Veranstaltung mit hinterlegten Acts genügt <strong>In Unterschriften speichern</strong> für alle markierten Fotos eines Darstellers – ohne jedes Bild einzeln zu öffnen. Optional <strong>Speichern &amp; KI</strong> kombiniert das mit der Veranstaltungs-KI.</p>
            @endif
            @if ($bulkPlannedEventImageAiEnabled)
                <p><strong>KI-Veranstaltung:</strong> Mehrere Bilder markieren und <strong>KI für Auswahl starten</strong> nutzt die gespeicherte Unterschrift und Schlagwörter je Bild.</p>
            @endif
            <p><strong>Verknüpfung löschen:</strong> Bild wird von dieser Meldung getrennt und aus der Liste entfernt (nicht mehr der Meldung zugeordnet).</p>
            <p><strong>Anonymisierung:</strong> Öffnet die Bearbeitung mit der Karte „Bereiche unkenntlich machen“ (Kennzeichen, Gesichter, manuelle Boxen). Original bleibt erhalten; ausgeliefert wird die redigierte Version.</p>
            <p><strong>Löschen:</strong> Bild wird endgültig gelöscht.</p>
        </div>
    </div>
</div>
