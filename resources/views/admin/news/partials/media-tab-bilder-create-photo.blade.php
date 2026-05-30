<div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden" x-data="imageUploadDropzone([], 0)">
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="text-base font-semibold text-gray-900">Fotos</h2>
        <p class="mt-1 text-sm text-gray-500">Mit „Speichern“ werden die Fotos hochgeladen und dem Beitrag zugeordnet.</p>
        <p class="mt-2 text-xs text-gray-500"
           x-text="existingCount + ' Fotos bereits zugeordnet' + (pendingCount > 0 ? ' · ' + pendingCount + ' ausgewählt' : '')">
            0 Fotos bereits zugeordnet
        </p>
    </div>

    @error('images.*')
        <p class="px-6 py-2 text-sm text-red-600">{{ $message }}</p>
    @enderror

    <div class="px-6 py-4 border-b border-gray-200">
        <input
            x-ref="fileInput"
            type="file"
            name="images[]"
            accept="image/jpeg,.jpg,.jpeg"
            multiple
            class="sr-only"
            @change="addFiles($event.target.files); $event.target.value = ''"
        >
        <div
            @click="$refs.fileInput.click()"
            @dragover.prevent="dragOver = true"
            @dragleave.prevent="dragOver = false"
            @drop.prevent="addFilesFromDrop($event.dataTransfer.files); dragOver = false"
            :class="dragOver ? 'border-[#092E48] bg-[#092E48]/5' : 'border-gray-300'"
            class="border-2 border-dashed rounded-2xl p-8 text-center cursor-pointer transition-colors hover:border-[#092E48] hover:bg-gray-50/80"
        >
            <p class="text-sm font-medium text-gray-700">Fotos hier ablegen oder klicken zum Auswählen</p>
            <p class="mt-1 text-xs text-gray-500">Nur JPEG. Doppelte Dateinamen werden in der aktuellen Auswahl ignoriert.</p>
            <p x-show="pendingCount > 0" x-cloak class="mt-2 text-sm text-[#092E48]" x-text="pendingCount + ' Datei(en) ausgewählt'"></p>
            <p x-show="skippedCount > 0" x-cloak class="mt-1 text-xs text-amber-600" x-text="skippedCount + ' Doppelte übersprungen'"></p>
        </div>
    </div>

    <div class="px-6 py-4">
        <p class="text-sm text-gray-600">
            Empfohlen: kurze Bildunterschriften und Schlagwörter nach dem Upload in der Bearbeitung ergänzen.
        </p>
    </div>
</div>
