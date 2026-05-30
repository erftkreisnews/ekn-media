@php
    use App\Services\Publication\PublicationFindingManualEvidenceService;

    $manualTypes = PublicationFindingManualEvidenceService::typeLabels();
    $manualFiles = (array) ($finding->evidence_manual_files ?? []);
@endphp

<div class="bg-white rounded-lg border border-gray-200 shadow-sm p-6 space-y-4">
    <div>
        <h2 class="text-lg font-semibold text-gray-900">Manuelle Beweismittel</h2>
        <p class="mt-1 text-sm text-gray-600">
            Z. B. Screenshot des Kanals, wenn YouTube das Video gelöscht hat. Nach dem Upload bitte die Beweismittelmappe neu erstellen.
        </p>
    </div>

    @foreach($manualTypes as $type => $label)
        @php
            $meta = is_array($manualFiles[$type] ?? null) ? $manualFiles[$type] : null;
        @endphp
        <div class="rounded-md border border-gray-200 p-4 space-y-3">
            <p class="text-sm font-medium text-gray-900">{{ $label }}</p>

            @if($meta)
                <div class="flex flex-wrap items-start gap-4">
                    @if(in_array($type, [PublicationFindingManualEvidenceService::TYPE_SCREENSHOT_KANAL, PublicationFindingManualEvidenceService::TYPE_SCREENSHOT_EINBLENDUNG], true))
                        <a href="{{ route('admin.backoffice.publication-findings.manual-evidence.show', [$finding, $type]) }}" target="_blank" rel="noopener">
                            <img src="{{ route('admin.backoffice.publication-findings.manual-evidence.show', [$finding, $type]) }}"
                                 alt="{{ $label }}"
                                 class="h-28 rounded border border-gray-200 object-cover bg-gray-50">
                        </a>
                    @else
                        <a href="{{ route('admin.backoffice.publication-findings.manual-evidence.show', [$finding, $type]) }}"
                           class="text-sm text-[#092E48] hover:underline" target="_blank" rel="noopener">
                            {{ $meta['original_name'] ?? 'Datei ansehen' }}
                        </a>
                    @endif
                    <div class="text-xs text-gray-500 space-y-1">
                        <p>Hochgeladen: {{ isset($meta['uploaded_at']) ? \Carbon\Carbon::parse($meta['uploaded_at'])->format('d.m.Y H:i') : '—' }}</p>
                        <form method="post" action="{{ route('admin.backoffice.publication-findings.manual-evidence.destroy', [$finding, $type]) }}"
                              onsubmit="return confirm('Manuelles Beweismittel löschen?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">Entfernen</button>
                        </form>
                    </div>
                </div>
            @endif

            <form method="post" action="{{ route('admin.backoffice.publication-findings.manual-evidence.store', $finding) }}"
                  enctype="multipart/form-data" class="flex flex-wrap items-end gap-3">
                @csrf
                <input type="hidden" name="evidence_type" value="{{ $type }}">
                <div class="flex-1 min-w-[12rem]">
                    <label class="block text-xs text-gray-500 mb-1">{{ $meta ? 'Ersetzen' : 'Hochladen' }}</label>
                    <input type="file" name="file" required accept="{{ $type === PublicationFindingManualEvidenceService::TYPE_YOUTUBE_VIDEO ? 'video/mp4,video/webm,.mp4,.webm,.mkv' : 'image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp' }}"
                           class="block w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                </div>
                <button type="submit" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]">
                    Speichern
                </button>
            </form>
        </div>
    @endforeach
</div>
