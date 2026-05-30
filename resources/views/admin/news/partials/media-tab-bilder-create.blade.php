{{-- Tab „Bilder“ auf Create: Drop-Zone (Drag & Drop + Klick), mit Speichern zuordnen (Nachricht → Bilder → Videos → Audios) --}}
<div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden" x-data="imageUploadDropzone([], 0)">
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="text-base font-semibold text-gray-900">Bilder</h2>
        <p class="mt-1 text-sm text-gray-500">Mit „Speichern“ werden die Bilder der Nachricht zugeordnet.</p>
        <p class="mt-2 text-xs text-gray-500"
           x-text="existingCount + ' Bilder bereits zugeordnet' + (pendingCount > 0 ? ' · ' + pendingCount + ' ausgewählt' : '')">
            0 Bilder bereits zugeordnet
        </p>
    </div>
    @error('images')
        <p class="px-6 py-2 text-sm text-red-600">{{ $message }}</p>
    @enderror
    @error('images.*')
        <p class="px-6 py-2 text-sm text-red-600">{{ $message }}</p>
    @enderror

    {{-- Drop-Zone: Dateien ablegen oder klicken zum Auswählen (Duplikate nur in aktueller Auswahl) --}}
    <div class="px-6 py-4 border-b border-gray-200">
        <input x-ref="fileInput" type="file" name="images[]" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" multiple
            class="sr-only"
            @change="pendingFiles = Array.from($event.target.files || []); _skipped = 0">
        <input x-ref="cameraInput" type="file" name="images[]" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" capture="environment"
            class="sr-only"
            @change="pendingFiles = Array.from($event.target.files || []); _skipped = 0">
        <div class="mb-3 sm:hidden grid grid-cols-1 gap-2">
            <button
                type="button"
                @click="$refs.cameraInput.click()"
                class="inline-flex w-full items-center justify-center rounded-xl border border-[#092E48] bg-[#092E48] px-4 py-2.5 text-sm font-medium text-white hover:bg-[#0b3858] min-h-[2.75rem]"
            >
                Foto aufnehmen
            </button>
            <button
                type="button"
                @click="$refs.fileInput.click()"
                class="inline-flex w-full items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 min-h-[2.75rem]"
            >
                Dateien auswählen
            </button>
            <button
                type="submit"
                form="news-form"
                name="save_images_and_continue"
                value="1"
                class="inline-flex w-full items-center justify-center rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-800 hover:bg-emerald-100 min-h-[2.75rem]"
            >
                Bilder hochladen
            </button>
        </div>
        <div
            @click="$refs.fileInput.click()"
            @dragover.prevent="dragOver = true"
            @dragleave.prevent="dragOver = false"
            @drop.prevent="addFilesFromDrop($event.dataTransfer.files); dragOver = false"
            :class="dragOver ? 'border-[#092E48] bg-[#092E48]/5' : 'border-gray-300'"
            class="border-2 border-dashed rounded-2xl p-8 text-center cursor-pointer transition-colors hover:border-[#092E48] hover:bg-gray-50/80">
            <p class="text-sm font-medium text-gray-700">Dateien hier ablegen oder klicken zum Auswählen</p>
            <p class="mt-1 text-xs text-gray-500">Erlaubt: JPG, PNG, WEBP. Doppelte Dateinamen werden nur innerhalb der aktuellen Auswahl ignoriert.</p>
            <p x-show="pendingCount > 0" x-cloak class="mt-2 text-sm text-[#092E48]" x-text="pendingCount + ' Datei(en) ausgewählt'"></p>
            <p x-show="skippedCount > 0" x-cloak class="mt-1 text-xs text-amber-600" x-text="skippedCount + ' Doppelte übersprungen'"></p>
        </div>
    </div>

    <details class="px-6 py-2">
        <summary class="text-sm font-medium text-gray-700 cursor-pointer list-none">Anforderungen an Pressebilder (aufklappen)</summary>
        <div class="mt-3 p-4 rounded-lg bg-gray-50 border border-gray-200 text-sm text-gray-700 space-y-3 max-h-64 overflow-y-auto">
            <p><strong>Technisch:</strong> Ziel: 4.500 Pixel an der langen Kante (keine Hochskalierung). Mindestgröße für Upload: 1.800 Pixel an der langen Kante (mobil-tauglich). Erlaubt: JPG/PNG/WEBP. Keine Kompressionsartefakte, keine Über-/Unterschärfung, keine Filter/Presets/Effekte, keine Wasserzeichen oder Logos.</p>
            <p><strong>Inhaltlich:</strong> Journalistisch relevant, dokumentarisch. Keine Manipulation, Retusche oder künstliche Elemente. Bearbeitung nur: Belichtung, Kontrast, Weißabgleich, moderate Schärfung. Keine dramatisierende Nachbearbeitung.</p>
            <p><strong>Rechtlich:</strong> Urheber und Nutzungsrechte geklärt, Persönlichkeitsrechte gewahrt, bei Minderjährigen Einwilligung prüfen. Symbolfotos kennzeichnen.</p>
            <p><strong>Metadaten (IPTC):</strong> Creator (Fotograf), Credit „Erftkreis News“, Copyright, Aufnahmedatum, Ort (Stadt, Bundesland), sachliche Bildbeschreibung (wer, was, wann, wo), Quelle.</p>
            <p class="text-gray-600">Bilder unter 4.000 Pixel lange Kante gelten als Dokumentationsbilder und nicht als Master-Pressebild. Kein Bild wird veröffentlicht oder weitergegeben, wenn eine Pflichtvorgabe nicht erfüllt ist.</p>
        </div>
    </details>
    <div class="px-6 py-4">
        <p class="text-sm text-gray-600">Wählen Sie Bilder aus und speichern Sie die Nachricht – die Bilder werden dann hochgeladen und der Meldung zugeordnet. <strong>Erlaubt: JPG/PNG/WEBP, mind. 1.800 Pixel lange Kante</strong> (Presse-Zielqualität weiterhin 4.500 Pixel).</p>
    </div>
</div>
