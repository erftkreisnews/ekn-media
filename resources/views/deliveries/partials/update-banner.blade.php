@if($isUpdateDeliveryView ?? false)
    <div class="mt-4 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 mb-4">
        <p class="text-sm font-semibold text-indigo-900">UPDATE zur NewsID {{ $updateReferenceNewsId }}</p>
        <p class="mt-1 text-sm text-indigo-800">
            Neu in diesem Update: {{ (int) ($newMediaCounts['images'] ?? 0) }} Fotos · {{ (int) ($newMediaCounts['videos'] ?? 0) }} Videos · {{ (int) ($newMediaCounts['audios'] ?? 0) }} Audios
        </p>
        <p class="mt-1 text-xs text-indigo-700">Die neuen Dateien sind unten jeweils deutlich markiert. Bereits gesendetes Material bleibt weiterhin verfügbar.</p>
    </div>
@endif
