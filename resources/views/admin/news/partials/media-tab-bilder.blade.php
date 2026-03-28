{{-- Tab „Bilder“: Drop-Zone (Drag & Drop + Klick) + Karten mit Thumbnail, Toggles, Aktionen (Nachricht → Bilder → Videos → Audios) --}}
@php
    $newsItemImages = $newsItem->images;
    $existingImageNames = $newsItemImages->pluck('original_name')->filter()->values()->toArray();
@endphp
<div class="bg-white shadow-sm rounded-2xl border border-gray-200 overflow-hidden" x-data="imageUploadDropzone({{ json_encode($existingImageNames) }}, {{ (int) $newsItemImages->count() }})">
    <div class="px-4 sm:px-6 py-4 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <h2 class="text-base font-semibold text-gray-900">Bilder</h2>
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
            @if (!$newsItemImages->isEmpty() && trim((string) ($newsItem->author_credit ?? '')) !== '')
                <form action="{{ route('admin.news.apply-author-credit', $newsItem) }}" method="post" class="inline" onsubmit="return confirm('Credit des Autors wirklich auf alle {{ $newsItemImages->count() }} Bilder dieses Beitrags übernehmen?');">
                    @csrf
                    <button type="submit" class="inline-flex justify-center items-center px-4 py-2 text-sm font-medium rounded-2xl border border-[#092E48] text-[#092E48] bg-white hover:bg-[#092E48]/5 min-h-[2.75rem]">
                        Credit des Autors auf alle Bilder übernehmen
                    </button>
                </form>
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

    {{-- Drop-Zone: Dateien ablegen oder klicken zum Auswählen (keine Doppelten) --}}
    <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
        <input x-ref="fileInput" type="file" name="images[]" accept="image/jpeg,.jpg,.jpeg" multiple
            class="sr-only"
            @change="addFiles($event.target.files); $event.target.value = ''">
        <div
            @click="$refs.fileInput.click()"
            @dragover.prevent="dragOver = true"
            @dragleave.prevent="dragOver = false"
            @drop.prevent="addFilesFromDrop($event.dataTransfer.files); dragOver = false"
            :class="dragOver ? 'border-[#092E48] bg-[#092E48]/5' : 'border-gray-300'"
            class="border-2 border-dashed rounded-2xl p-8 text-center cursor-pointer transition-colors hover:border-[#092E48] hover:bg-gray-50/80">
            <p class="text-sm font-medium text-gray-700">Dateien hier ablegen oder klicken zum Auswählen</p>
            <p class="mt-1 text-xs text-gray-500">Nur JPEG. Doppelte Dateinamen werden ignoriert.</p>
            <p x-show="pendingCount > 0" x-cloak class="mt-2 text-sm text-[#092E48]" x-text="pendingCount + ' Datei(en) zum Hochladen ausgewählt'"></p>
            <p x-show="skippedCount > 0" x-cloak class="mt-1 text-xs text-amber-600" x-text="skippedCount + ' Doppelte übersprungen'"></p>
        </div>
    </div>
    @error('images.*')
        <p class="px-6 py-2 text-sm text-red-600">{{ $message }}</p>
    @enderror

    <details class="px-4 sm:px-6 py-2">
        <summary class="text-sm font-medium text-gray-700 cursor-pointer list-none">Anforderungen an Pressebilder (aufklappen)</summary>
        <div class="mt-3 p-4 rounded-lg bg-gray-50 border border-gray-200 text-sm text-gray-700 space-y-3 max-h-64 overflow-y-auto">
            <p><strong>Technisch:</strong> Ziel: 4.500 Pixel an der langen Kante (keine Hochskalierung). Upload mind. 3.500 Pixel (Altbestand zugelassen). Nur JPEG, Qualität 85–90 %, sRGB, 300 dpi. Keine Kompressionsartefakte, keine Über-/Unterschärfung, keine Filter/Presets/Effekte, keine Wasserzeichen oder Logos.</p>
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
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-4" x-data="{ overlayImage: null, overlayTitle: '' }" @keydown.escape.window="overlayImage = null">
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
                    <div class="relative inline-block rounded-xl shadow-2xl overflow-hidden" @click.stop>
                        <img :src="overlayImage" :alt="overlayTitle" class="block max-w-full w-auto h-auto object-contain" style="max-width: 42rem; max-height: 50vh;">
                        <button type="button" @click="overlayImage = null"
                            class="absolute right-2 top-2 px-3 py-1.5 text-sm font-medium rounded bg-black/60 text-white hover:bg-black/70 focus:outline-none focus:ring-2 focus:ring-white/50">
                            Schließen
                        </button>
                    </div>
                </div>
                @foreach ($newsItemImages as $m)
                    <div class="border border-gray-200 rounded-2xl overflow-hidden bg-white flex flex-col">
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
                                <img src="{{ $imgDisplayUrl }}" alt="{{ $m->display_name }}" class="w-full h-full object-cover pointer-events-none">
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
                            <p class="text-xs text-gray-500">{{ number_format($m->file_size_kb) }} KB · #{{ $m->id }}</p>

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
                                    <input type="hidden" name="media[{{ $m->id }}][is_visible]" value="1">
                                    <input type="checkbox" name="media[{{ $m->id }}][is_visible]" value="0" class="absolute inset-0 opacity-0 cursor-pointer" data-status-input="1"
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
                                    <input type="hidden" name="media[{{ $m->id }}][versand]" value="0">
                                    <input type="checkbox" name="media[{{ $m->id }}][versand]" value="1" class="absolute inset-0 opacity-0 cursor-pointer" data-status-input="1"
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
                                    <input type="radio" name="teaser_media_id" value="{{ $m->id }}" class="absolute inset-0 opacity-0 cursor-pointer" data-status-input="1"
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

                            <div class="flex flex-wrap items-center justify-center gap-2 pt-2 border-t border-gray-100">
                                <a href="{{ route('admin.news.media.edit', [$newsItem, $m->id]) }}"
                                   class="inline-flex items-center justify-center w-9 h-9 rounded-xl border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 hover:border-[#092E48] hover:text-[#092E48] transition-colors"
                                   title="Bearbeiten">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>

                                <form action="{{ route('admin.news.media.unlink', [$newsItem, $m->id]) }}" method="POST" class="inline"
                                      onsubmit="return confirm('Verknüpfung wirklich löschen? Bild bleibt gespeichert.');">
                                    @csrf
                                    <button type="submit"
                                            class="inline-flex items-center justify-center w-9 h-9 rounded-xl border border-amber-300 bg-white text-amber-700 hover:bg-amber-50 transition-colors"
                                            title="Verknüpfung löschen (Bild bleibt)">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 12l9 9v-7h7l-7-7z"/>
                                        </svg>
                                    </button>
                                </form>

                                @if ($m->is_unkentlich)
                                    <form action="{{ route('admin.news.media.unkentlich.aufheben', [$newsItem, $m->id]) }}" method="POST" class="inline"
                                          onsubmit="return confirm('Markierung „Unkenntlich“ aufheben? Die pixelierten Bereiche bleiben verändert.');">
                                        @csrf
                                        <button type="submit"
                                                class="inline-flex items-center justify-center w-9 h-9 rounded-xl border border-amber-400 bg-amber-50 text-amber-800 hover:bg-amber-100 transition-colors"
                                                title="Unkenntlich aufheben">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </button>
                                    </form>
                                @else
                                    <a href="{{ route('admin.news.media.edit', [$newsItem, $m->id]) }}#redaction"
                                       class="inline-flex items-center justify-center w-9 h-9 rounded-xl border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 hover:border-amber-400 text-amber-600 transition-colors"
                                       title="Anonymisierung (Kennzeichen & Gesichter)">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                        </svg>
                                    </a>
                                @endif

                                <a href="{{ $m->url }}" download="{{ $m->original_name }}"
                                   class="inline-flex items-center justify-center w-9 h-9 rounded-xl border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 hover:border-[#092E48] hover:text-[#092E48] transition-colors"
                                   title="Download">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                </a>

                                <form action="{{ route('admin.news.media.destroy', [$newsItem, $m->id]) }}" method="POST" class="inline"
                                      onsubmit="return confirm('Bild endgültig löschen?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="inline-flex items-center justify-center w-9 h-9 rounded-xl border border-red-200 bg-white text-red-600 hover:bg-red-50 hover:border-red-300 transition-colors"
                                            title="Bild endgültig löschen">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7h6m2 0H7m4-3h2a2 2 0 012 2v1H9V6a2 2 0 012-2z"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="pt-4 mt-4 border-t border-gray-200 text-xs text-gray-600 space-y-1">
            <p><strong>Bearbeiten:</strong> Öffnet eine eigene Seite zur Prüfung und Anpassung der Metadaten (Dateiname, Pfad, Größe, Sichtbarkeit, Versand, Beschriftung).</p>
            <p><strong>Verknüpfung löschen:</strong> Bild wird von dieser Meldung getrennt und aus der Liste entfernt (nicht mehr der Meldung zugeordnet).</p>
            <p><strong>Anonymisierung:</strong> Öffnet die Bearbeitung mit der Karte „Bereiche unkenntlich machen“ (Kennzeichen, Gesichter, manuelle Boxen). Original bleibt erhalten; ausgeliefert wird die redigierte Version.</p>
            <p><strong>Löschen:</strong> Bild wird endgültig gelöscht.</p>
        </div>
    </div>
</div>
