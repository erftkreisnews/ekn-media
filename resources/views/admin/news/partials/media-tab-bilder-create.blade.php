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
    @error('images.*')
        <p class="px-6 py-2 text-sm text-red-600">{{ $message }}</p>
    @enderror

    {{-- Drop-Zone: Dateien ablegen oder klicken zum Auswählen (keine Doppelten) --}}
    <div class="px-6 py-4 border-b border-gray-200">
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
            <p x-show="pendingCount > 0" x-cloak class="mt-2 text-sm text-[#092E48]" x-text="pendingCount + ' Datei(en) ausgewählt'"></p>
            <p x-show="skippedCount > 0" x-cloak class="mt-1 text-xs text-amber-600" x-text="skippedCount + ' Doppelte übersprungen'"></p>
        </div>
    </div>

    <details class="px-6 py-2">
        <summary class="text-sm font-medium text-gray-700 cursor-pointer list-none">Anforderungen an Pressebilder (aufklappen)</summary>
        <div class="mt-3 p-4 rounded-lg bg-gray-50 border border-gray-200 text-sm text-gray-700 space-y-3 max-h-64 overflow-y-auto">
            <p><strong>Technisch:</strong> Ziel: 4.500 Pixel an der langen Kante (keine Hochskalierung). Upload mind. 3.500 Pixel (Altbestand zugelassen). Nur JPEG, Qualität 85–90 %, sRGB, 300 dpi. Keine Kompressionsartefakte, keine Über-/Unterschärfung, keine Filter/Presets/Effekte, keine Wasserzeichen oder Logos.</p>
            <p><strong>Inhaltlich:</strong> Journalistisch relevant, dokumentarisch. Keine Manipulation, Retusche oder künstliche Elemente. Bearbeitung nur: Belichtung, Kontrast, Weißabgleich, moderate Schärfung. Keine dramatisierende Nachbearbeitung.</p>
            <p><strong>Rechtlich:</strong> Urheber und Nutzungsrechte geklärt, Persönlichkeitsrechte gewahrt, bei Minderjährigen Einwilligung prüfen. Symbolfotos kennzeichnen.</p>
            <p><strong>Metadaten (IPTC):</strong> Creator (Fotograf), Credit „Erftkreis News“, Copyright, Aufnahmedatum, Ort (Stadt, Bundesland), sachliche Bildbeschreibung (wer, was, wann, wo), Quelle.</p>
            <p class="text-gray-600">Bilder unter 4.000 Pixel lange Kante gelten als Dokumentationsbilder und nicht als Master-Pressebild. Kein Bild wird veröffentlicht oder weitergegeben, wenn eine Pflichtvorgabe nicht erfüllt ist.</p>
        </div>
    </details>
    <div class="px-6 py-4">
        <p class="text-sm text-gray-600">Wählen Sie Bilder aus und speichern Sie die Nachricht – die Bilder werden dann hochgeladen und der Meldung zugeordnet. <strong>Nur JPEG, mind. 3.500 Pixel lange Kante</strong> (Ziel für neue Pressebilder: 4.500 Pixel).</p>
    </div>
</div>
