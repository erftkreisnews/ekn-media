@extends('layouts.admin')

@section('content')
    <div class="space-y-6 max-w-3xl mx-auto">
        <div>
            <a href="{{ route('admin.backoffice.publication-findings.index') }}" class="text-sm text-gray-600 hover:text-[#092E48] mb-2 inline-block">← Fundstellen</a>
            <h1 class="text-2xl font-semibold text-gray-900">Fundstelle bearbeiten</h1>
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 p-4 border border-green-200">
                <p class="text-sm text-green-800">{{ session('status') }}</p>
            </div>
        @endif
        @if (session('error'))
            <div class="rounded-md bg-red-50 p-4 border border-red-200">
                <p class="text-sm text-red-800">{{ session('error') }}</p>
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-md bg-red-50 p-4 border border-red-200">
                <ul class="text-sm text-red-700 list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="post" action="{{ route('admin.backoffice.publication-findings.update', $finding) }}" class="bg-white rounded-lg border border-gray-200 shadow-sm p-6 space-y-6">
            @csrf
            @method('PUT')
            @include('admin.backoffice.publication-findings._form', [
                'finding' => $finding,
                'kindLabels' => $kindLabels,
                'organizations' => $organizations,
                'newsItem' => $finding->newsItem,
                'newsImages' => $newsImages,
                'newsImagesTitle' => $newsImagesTitle,
                'selectedMediaIds' => $selectedMediaIds,
            ])
            <div class="flex flex-wrap gap-3 pt-2 border-t border-gray-100 items-center">
                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]">Speichern</button>
                @if($finding->evidence_dossier_path)
                    <a href="{{ route('admin.backoffice.publication-findings.evidence-dossier.download', $finding) }}"
                       class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-emerald-300 text-emerald-800 bg-emerald-50 hover:bg-emerald-100">
                        Beweismittelmappe (ZIP)
                    </a>
                @endif
                <form method="post" action="{{ route('admin.backoffice.publication-findings.evidence-dossier.build', $finding) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-gray-300 text-gray-700 bg-white hover:bg-gray-50">
                        Beweismittelmappe {{ $finding->evidence_dossier_path ? 'neu erstellen' : 'erstellen' }}
                    </button>
                </form>
                <a href="{{ route('admin.backoffice.publication-findings.index') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-gray-300 text-gray-700 bg-white hover:bg-gray-50">Abbrechen</a>
            </div>
            @php
                $displayChecklist = $finding->evidenceChecklistForDisplay();
                $needsRebuild = $finding->evidenceDossierNeedsRebuild();
            @endphp
            @if($needsRebuild)
                <div class="rounded-md bg-amber-50 p-4 border border-amber-200">
                    <p class="text-sm text-amber-900">
                        Es gibt neuere manuelle Uploads als die letzte ZIP
                        ({{ $finding->evidence_dossier_built_at?->format('d.m.Y H:i') ?? 'noch nie' }}).
                        Die Mappe wird nach Uploads automatisch neu erstellt – ggf. Queue-Worker starten oder unten „neu erstellen“ klicken.
                    </p>
                </div>
            @endif
            @if($displayChecklist !== [] || is_array($finding->evidence_dossier_manifest))
                <div class="rounded-md bg-gray-50 border border-gray-200 p-4 text-xs text-gray-700 space-y-1">
                    <p class="font-medium text-gray-900">Checkliste Beweismittelmappe</p>
                    @if($finding->evidence_dossier_built_at)
                        <p class="text-gray-500">Stand ZIP: {{ $finding->evidence_dossier_built_at->format('d.m.Y H:i') }}</p>
                    @endif
                    @if(!empty($finding->evidence_dossier_manifest['archive_storage']))
                        <p class="font-medium text-gray-800 mt-2">Dauerarchiv (S3)</p>
                        <ul class="list-disc list-inside text-gray-600">
                            @foreach($finding->evidence_dossier_manifest['archive_storage'] as $label => $path)
                                <li>{{ $label }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @foreach($displayChecklist as $key => $ok)
                        @php
                            $isManualOnly = $ok && empty($finding->evidence_dossier_manifest['checklist'][$key]);
                        @endphp
                        <p>
                            {{ $ok ? '✅' : '❌' }} {{ str_replace('_', ' ', $key) }}
                            @if($isManualOnly)
                                <span class="text-emerald-700">(manuell hochgeladen{{ $needsRebuild ? ', ZIP folgt' : '' }})</span>
                            @endif
                        </p>
                    @endforeach
                    @foreach($finding->evidenceNotesForDisplay() as $note)
                        <p class="text-gray-600">ℹ {{ $note }}</p>
                    @endforeach
                    @foreach($finding->evidenceWarningsForDisplay() as $warning)
                        <p class="text-amber-800">⚠ {{ $warning }}</p>
                    @endforeach
                </div>
            @endif
        </form>

        @include('admin.backoffice.publication-findings._manual_evidence', ['finding' => $finding])
        @include('admin.backoffice.publication-findings._authority_access', ['finding' => $finding])
    </div>
@endsection
