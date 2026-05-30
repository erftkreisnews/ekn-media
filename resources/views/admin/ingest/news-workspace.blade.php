@extends('layouts.admin')

@section('title', 'Ingest-Schnitt')

@section('content')
    <div class="space-y-6 text-left min-w-0 max-w-full">
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

        {{-- News-Kopf --}}
        <div class="rounded-xl border border-gray-200 bg-white p-4 sm:p-5 shadow-sm min-w-0 max-w-full">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Ingest-News-Workspace</p>
            <h1 class="mt-1 text-2xl font-semibold text-gray-900">Sendefassung aus Ingest-Clips</h1>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800">News #{{ $newsItem->id }}</span>
                <span class="inline-flex rounded-full bg-sky-100 px-2.5 py-0.5 text-xs font-medium text-sky-900">Status: {{ $newsStatusLabel }}</span>
            </div>
            <p class="mt-2 text-base text-gray-800 leading-snug">{{ $newsItem->title }}</p>
            <div class="mt-4 flex flex-col sm:flex-row sm:flex-wrap gap-2 sm:gap-4 text-sm sm:items-center">
                <a href="{{ route('admin.news.edit', $newsItem) }}" class="inline-flex min-h-[2.75rem] items-center text-[#092E48] font-medium hover:underline py-1">Meldung bearbeiten</a>
                <a href="{{ route('admin.ingest.index') }}" class="inline-flex min-h-[2.75rem] items-center text-gray-600 hover:underline py-1">Alle Ingest-Clips</a>
            </div>
        </div>

        {{-- Kennzahlen --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 min-w-0">
            <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm">
                <p class="text-xs font-medium text-gray-500">Clips gesamt</p>
                <p class="mt-1 text-lg font-semibold tabular-nums text-gray-900">{{ $workspaceSummary['total'] }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm">
                <p class="text-xs font-medium text-gray-500">Zugeordnet</p>
                <p class="mt-1 text-lg font-semibold tabular-nums text-gray-900">{{ $workspaceSummary['assigned'] }}</p>
            </div>
            <div class="rounded-lg border border-amber-200 bg-amber-50/70 p-3 shadow-sm">
                <p class="text-xs font-medium text-amber-900">Im Render</p>
                <p class="mt-1 text-lg font-semibold tabular-nums text-amber-950">{{ $workspaceSummary['rendering'] }}</p>
            </div>
            <div class="rounded-lg border border-emerald-200 bg-emerald-50/70 p-3 shadow-sm">
                <p class="text-xs font-medium text-emerald-900">Übernommen</p>
                <p class="mt-1 text-lg font-semibold tabular-nums text-emerald-950">{{ $workspaceSummary['used'] }}</p>
            </div>
            <div class="rounded-lg border border-indigo-200 bg-indigo-50/70 p-3 shadow-sm">
                <p class="text-xs font-medium text-indigo-900">Für Final markiert</p>
                <p class="mt-1 text-lg font-semibold tabular-nums text-indigo-950">{{ $workspaceSummary['marked_for_final'] }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm">
                <p class="text-xs font-medium text-gray-500">Mit Preview-Datei</p>
                <p class="mt-1 text-lg font-semibold tabular-nums text-gray-900">{{ $workspaceSummary['with_preview_file'] }}</p>
            </div>
        </div>

        @if ($activeJob)
            <div class="rounded-md bg-amber-50 border border-amber-200 p-4 text-sm text-amber-950 min-w-0 max-w-full break-words">
                <p class="font-medium">Aktiver Render-/Upload-Job</p>
                <p class="mt-1 text-amber-900/95">
                    Status: <strong>{{ $activeJob->status }}</strong>
                    @if (isset($activeJob->status_label) && filled($activeJob->status_label))
                        <span class="text-amber-800">({{ $activeJob->status_label }})</span>
                    @endif
                    · Job-ID <strong>#{{ $activeJob->id }}</strong>
                </p>
                <p class="mt-2 text-xs text-amber-900/90">Bitte warten, bis der Vorgang abgeschlossen ist. Der Button „Sendefähige MP4 erzeugen“ bleibt gesperrt.</p>
                @if (filled($activeJob->error_message ?? null))
                    <p class="mt-3 text-sm text-red-900 bg-red-50 border border-red-100 rounded-lg p-2" title="{{ $activeJob->error_message }}">{{ Str::limit($activeJob->error_message, 400) }}</p>
                @endif
            </div>
        @elseif (!empty($lastJob) && ($lastJob->status ?? null) === \App\Models\IngestRenderJob::STATUS_FAILED)
            <div class="rounded-md bg-red-50 border border-red-200 p-4 text-sm text-red-900 min-w-0 max-w-full break-words">
                <p class="font-medium">Letzter Render-Job fehlgeschlagen</p>
                <p class="mt-1 text-xs text-red-900/90">
                    Job-ID <strong>#{{ $lastJob->id }}</strong> · Status: <strong>{{ $lastJob->status }}</strong>
                </p>
                @if (filled($lastJob->error_message ?? null))
                    <p class="mt-3 text-sm text-red-900 bg-white border border-red-100 rounded-lg p-2 whitespace-pre-wrap" title="{{ $lastJob->error_message }}">
                        {{ Str::limit($lastJob->error_message, 900) }}
                    </p>
                @else
                    <p class="mt-3 text-sm text-red-900 bg-white border border-red-100 rounded-lg p-2">
                        Kein Fehlertext hinterlegt.
                    </p>
                @endif
            </div>
        @endif

        <x-admin.card class="min-w-0 max-w-full">
            @if ($clips->isEmpty())
                <h2 class="text-sm font-semibold text-gray-900 mb-2">Offene Clips für den nächsten Schnitt</h2>
                <p class="text-sm text-gray-700">
                    @if (($usedClipsCount ?? 0) > 0)
                        Für diese Meldung sind <strong>keine weiteren</strong> zugeordneten Video-Clips offen.
                        <strong>{{ $usedClipsCount }}</strong> Clip(s) wurden bereits in die Sendefassung übernommen — der Schnitt-Workspace ist damit <strong>leer</strong> für neues Material.
                    @else
                        Für diese Meldung sind <strong>keine</strong> zugeordneten Video-Clips vorhanden.
                    @endif
                </p>
                <p class="mt-2 text-sm text-gray-600">Neue Rohclips in der <a href="{{ route('admin.ingest.index') }}" class="text-[#092E48] underline">Ingest-Sichtung</a> dieser Meldung zuordnen, dann hier den Finalrender starten.</p>
                <p class="mt-1 text-xs text-gray-500">Nach erfolgreichem Render werden übernommene Clips automatisch aus dem Workspace entfernt (kein Mischen mit neuem Material).</p>
            @else
                <div class="mb-4">
                    <h2 class="text-sm font-semibold text-gray-900">Offene Clips (nächster Schnitt)</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Nur <strong>zugeordnete</strong> Video-Clips — bereits übernommene erscheinen hier nicht mehr (automatisches Leeren nach Render).
                        Checkbox „Nutzen“ wählt Clips für die sendefähige MP4; Reihenfolge = Spalte „Reihenfolge“.
                    </p>
                </div>
                <form method="post" action="{{ route('admin.ingest.news-render', $newsItem) }}" class="space-y-4 min-w-0">
                    @csrf
                    <div class="overflow-x-auto min-w-0 max-w-full [scrollbar-width:thin]">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nutzen</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Datei</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Final</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Reihenfolge</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Preview</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Trim (s)</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Hinweise</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @foreach ($clips as $c)
                                    @php
                                        $stKey = 'ingest.file_status.'.$c->status;
                                        $stLabel = __($stKey);
                                        if ($stLabel === $stKey) {
                                            $stLabel = $c->status;
                                        }
                                    @endphp
                                    <tr class="{{ $c->status === \App\Models\IngestFile::STATUS_USED ? 'bg-gray-50/80' : '' }}">
                                        <td class="px-3 py-2 align-top">
                                            @if ($c->status === \App\Models\IngestFile::STATUS_ASSIGNED)
                                                <input type="checkbox" name="selected[]" value="{{ $c->id }}"
                                                    @checked($c->is_selected ?? false)
                                                    class="rounded border-gray-300" aria-label="Clip {{ $c->id }} für Render wählen">
                                            @else
                                                <span class="text-xs text-gray-400" title="Nur im Status „zugeordnet“ wählbar">—</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 align-top whitespace-nowrap font-mono text-xs">#{{ $c->id }}</td>
                                        <td class="px-3 py-2 align-top max-w-[12rem]">
                                            <a href="{{ route('admin.ingest.show', $c) }}" class="text-[#092E48] hover:underline break-all">{{ $c->original_name }}</a>
                                        </td>
                                        <td class="px-3 py-2 align-top">
                                            <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-900">{{ $stLabel }}</span>
                                            <span class="block text-[10px] text-gray-400 mt-0.5 font-mono">{{ $c->status }}</span>
                                        </td>
                                        <td class="px-3 py-2 align-top">
                                            @isset($c->is_selected)
                                                @if ($c->is_selected)
                                                    <span class="text-indigo-800 font-medium text-xs">ja</span>
                                                @else
                                                    <span class="text-gray-500 text-xs">nein</span>
                                                @endif
                                            @else
                                                <span class="text-gray-400 text-xs">—</span>
                                            @endisset
                                        </td>
                                        <td class="px-3 py-2 align-top">
                                            @if ($c->status === \App\Models\IngestFile::STATUS_ASSIGNED)
                                                <input type="number" name="order[{{ $c->id }}]" min="0" max="65535" value="{{ $c->selection_order ?? 0 }}"
                                                    class="w-24 rounded-lg border-gray-300 text-sm" aria-label="Reihenfolge Clip {{ $c->id }}">
                                            @else
                                                <span class="text-sm text-gray-600">{{ $c->selection_order ?? '—' }}</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 align-top text-xs min-w-[8rem]">
                                            @if ($c->isIngestVideoFile())
                                                <div class="w-36 max-w-full rounded overflow-hidden bg-gray-950 border border-gray-200">
                                                    @include('admin.ingest.partials.ingest-video-preview', [
                                                        'f' => $c,
                                                        'controls' => true,
                                                        'preload' => 'metadata',
                                                        'mediaClass' => 'w-full max-h-24 object-contain bg-black',
                                                    ])
                                                </div>
                                                @if ($c->status === \App\Models\IngestFile::STATUS_PREVIEW_GENERATING && ! $c->hasIngestPreviewVideo())
                                                    <span class="block mt-1 text-[10px] text-sky-800">MP4 folgt…</span>
                                                @endif
                                            @elseif (isset($c->preview_path))
                                                @if (filled($c->preview_path))
                                                    <span class="text-emerald-800">Datei</span>
                                                @else
                                                    <span class="text-amber-800">keine</span>
                                                @endif
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                            @if (filled($c->preview_status ?? null))
                                                <span class="block text-[10px] text-gray-500 mt-0.5">{{ $c->preview_status }}</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 align-top text-xs font-mono text-gray-700">
                                            @if (isset($c->trim_in_seconds) || isset($c->trim_out_seconds))
                                                {{ $c->trim_in_seconds ?? '—' }} / {{ $c->trim_out_seconds ?? '—' }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 align-top text-xs max-w-[14rem]">
                                            @if (filled($c->error_message))
                                                <p class="text-red-800" title="{{ $c->error_message }}">{{ Str::limit($c->error_message, 80) }}</p>
                                            @endif
                                            @if (isset($c->preview_error_message) && filled($c->preview_error_message))
                                                <p class="text-red-800 mt-1" title="{{ $c->preview_error_message }}">Vorschau: {{ Str::limit($c->preview_error_message, 60) }}</p>
                                            @endif
                                            @if (! filled($c->error_message) && (! isset($c->preview_error_message) || ! filled($c->preview_error_message)))
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="flex flex-col sm:flex-row sm:flex-wrap gap-3 sm:items-center">
                        <button type="submit" class="inline-flex justify-center items-center w-full sm:w-auto min-h-[2.75rem] px-4 py-2.5 text-sm font-medium rounded-lg bg-[#092E48] text-white hover:bg-[#0b3858] disabled:opacity-50"
                            @disabled($activeJob !== null)>
                            Sendefähige MP4 erzeugen
                        </button>
                        @if ($activeJob)
                            <form method="POST" action="{{ route('admin.ingest.news-render-cancel', [$newsItem, $activeJob]) }}" class="inline-flex"
                                onsubmit="return confirm('Render-Job #{{ $activeJob->id }} wirklich abbrechen? Danach kannst du einen neuen Job starten.');">
                                @csrf
                                <button type="submit" class="inline-flex justify-center items-center min-h-[2.75rem] px-4 py-2.5 text-sm font-medium rounded-lg border border-amber-300 bg-amber-50 text-amber-950 hover:bg-amber-100">
                                    Hängenden Job beenden
                                </button>
                            </form>
                            <span class="text-xs text-gray-500 sm:max-w-md">„Sendefähige MP4“ ist gesperrt, solange ein Job läuft oder noch queued ist. Bei Fehlern: Job beenden oder Seite neu laden (alte Jobs werden nach Zeit automatisch freigegeben).</span>
                        @endif
                    </div>
                </form>
            @endif
        </x-admin.card>
    </div>
@endsection
