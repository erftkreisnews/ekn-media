@extends('layouts.admin')

@section('title', 'Ingest-Schnitt')

@section('content')
    <div class="space-y-6 text-left">
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
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Ingest-News-Workspace</p>
            <h1 class="mt-1 text-2xl font-semibold text-gray-900">Sendefassung aus Ingest-Clips</h1>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800">News #{{ $newsItem->id }}</span>
                <span class="inline-flex rounded-full bg-sky-100 px-2.5 py-0.5 text-xs font-medium text-sky-900">Status: {{ $newsStatusLabel }}</span>
            </div>
            <p class="mt-2 text-base text-gray-800 leading-snug">{{ $newsItem->title }}</p>
            <div class="mt-4 flex flex-wrap gap-4 text-sm">
                <a href="{{ route('admin.news.edit', $newsItem) }}" class="text-[#092E48] font-medium hover:underline">Meldung bearbeiten</a>
                <a href="{{ route('admin.ingest.index') }}" class="text-gray-600 hover:underline">Alle Ingest-Clips</a>
            </div>
        </div>

        {{-- Kennzahlen --}}
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
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
            <div class="rounded-md bg-amber-50 border border-amber-200 p-4 text-sm text-amber-950">
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
        @endif

        <x-admin.card>
            @if ($clips->isEmpty())
                <h2 class="text-sm font-semibold text-gray-900 mb-2">Zugeordnete Ingest-Clips</h2>
                <p class="text-sm text-gray-700">Für diese Meldung sind <strong>keine</strong> Ingest-Clips in den Status „zugeordnet“, „rendering“ oder „übernommen“ vorhanden.</p>
                <p class="mt-2 text-sm text-gray-600">Weise Clips in der <a href="{{ route('admin.ingest.index') }}" class="text-[#092E48] underline">Ingest-Liste</a> oder in der <strong>Detailansicht</strong> eines Ingest-Clips einer Meldung zu.</p>
                <p class="mt-1 text-xs text-gray-500">Hinweis: Nur zugeordnete und weiterverarbeitbare Clips erscheinen hier.</p>
            @else
                <div class="mb-4">
                    <h2 class="text-sm font-semibold text-gray-900">Clips dieser Meldung</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Auswahl und Reihenfolge wie bisher: kleinere Reihenfolge-Zahl = früher im Schnitt. Nur Clips im Status „zugeordnet“ können für den Final angehakt und für die MP4-Erzeugung genutzt werden.
                    </p>
                </div>
                <form method="post" action="{{ route('admin.ingest.news-render', $newsItem) }}" class="space-y-4">
                    @csrf
                    <div class="overflow-x-auto">
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
                                                <input type="checkbox" name="selected[]" value="{{ $c->id }}" class="rounded border-gray-300" aria-label="Clip {{ $c->id }} für Render wählen">
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
                                        <td class="px-3 py-2 align-top text-xs">
                                            @isset($c->preview_path)
                                                @if (filled($c->preview_path))
                                                    <span class="text-emerald-800">Datei</span>
                                                @else
                                                    <span class="text-amber-800">keine</span>
                                                @endif
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endisset
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
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="submit" class="inline-flex items-center px-4 py-2.5 text-sm font-medium rounded-lg bg-[#092E48] text-white hover:bg-[#0b3858] disabled:opacity-50"
                            @disabled($activeJob !== null)>
                            Sendefähige MP4 erzeugen
                        </button>
                        @if ($activeJob)
                            <span class="text-xs text-gray-500">Button gesperrt bis der laufende Job fertig ist.</span>
                        @endif
                    </div>
                </form>
            @endif
        </x-admin.card>
    </div>
@endsection
