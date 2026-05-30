{{-- Tab „Audios“ auf Create: Upload, mit Speichern zuordnen --}}
<div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-base font-semibold text-gray-900">Audios</h2>
        <div class="flex items-center gap-2 flex-wrap">
            <label for="newsCreateAudiosFile" class="inline-flex items-center justify-center px-3 py-1.5 text-sm font-medium rounded text-white bg-[#092E48] hover:bg-[#0b3858] cursor-pointer">
                Audiodateien wählen
            </label>
            <span id="newsCreateAudiosFileHint" class="text-sm text-gray-600">Keine Dateien ausgewählt.</span>
            <span class="text-sm text-gray-500">Mit „Speichern“ werden die Audios der Nachricht zugeordnet.</span>
        </div>
    </div>
    @error('audios.*')
        <p class="px-6 py-2 text-sm text-red-600">{{ $message }}</p>
    @enderror
    <div class="px-6 py-4">
        <p class="text-sm text-gray-600">Wählen Sie Audio-Dateien aus und speichern Sie die Nachricht – die Audios werden dann hochgeladen und der Meldung zugeordnet.</p>
    </div>
</div>
