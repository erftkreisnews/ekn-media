@extends('layouts.admin')

@section('title', 'Video-Ingest')

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

        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Video-Ingest (MC60)</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Rohclips aus dem lokalen Ingest-Bereich – erst nach redaktioneller Auswahl wird eine sendefähige MP4 erzeugt und ins Medienarchiv übernommen.
                </p>
                @php($src = config('ingest.inbox_path_source'))
                <p class="mt-2 text-xs text-gray-500">
                    Lokaler Scan-Ordner (Inbox):
                    <code class="rounded bg-gray-100 px-1 py-0.5 text-gray-800">{{ config('ingest.paths.inbox') }}</code>
                    @if ($src === 'explicit_inbox')
                        <span class="text-gray-400">(aus <code class="text-gray-500">INGEST_INBOX_PATH</code>)</span>
                    @elseif ($src === 'root_upload')
                        <span class="text-gray-400">(abgeleitet: <code class="text-gray-500">INGEST_ROOT_PATH</code> + <code class="text-gray-500">/upload</code>)</span>
                    @else
                        <span class="text-gray-400">(Standard unter <code class="text-gray-500">storage/app/ingest/inbox</code>)</span>
                    @endif
                </p>
            </div>
        </div>

        @isset ($stats)
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
                <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm">
                    <p class="text-xs font-medium text-gray-500">Gesamt</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-gray-900">{{ number_format($stats['total'], 0, ',', '.') }}</p>
                </div>
                <div class="rounded-lg border border-sky-200 bg-sky-50/80 p-3 shadow-sm">
                    <p class="text-xs font-medium text-sky-800">Heute</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-sky-950">{{ number_format($stats['today'], 0, ',', '.') }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm">
                    <p class="text-xs font-medium text-gray-500">Validiert+</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-gray-900">{{ number_format($stats['validated'], 0, ',', '.') }}</p>
                    <p class="mt-0.5 text-[10px] leading-tight text-gray-400">ab Status „validiert“</p>
                </div>
                <div class="rounded-lg border border-amber-200 bg-amber-50/60 p-3 shadow-sm">
                    <p class="text-xs font-medium text-amber-900">Ohne Preview</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-amber-950">{{ number_format($stats['without_preview'], 0, ',', '.') }}</p>
                </div>
                <div class="rounded-lg border border-red-200 bg-red-50/60 p-3 shadow-sm">
                    <p class="text-xs font-medium text-red-900">Abgelehnt / Fehler</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-red-950">{{ number_format($stats['rejected_or_failed'], 0, ',', '.') }}</p>
                </div>
                <div class="rounded-lg border border-indigo-200 bg-indigo-50/60 p-3 shadow-sm">
                    <p class="text-xs font-medium text-indigo-900">Ausgewählt</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-indigo-950">{{ number_format($stats['is_selected'], 0, ',', '.') }}</p>
                </div>
                <div class="rounded-lg border border-emerald-200 bg-emerald-50/60 p-3 shadow-sm">
                    <p class="text-xs font-medium text-emerald-900">Mit News</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-emerald-950">{{ number_format($stats['with_news'], 0, ',', '.') }}</p>
                </div>
            </div>
        @endisset

        @if (! config('ingest.enabled'))
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                <p class="font-medium">Ingest ist deaktiviert</p>
                <p class="mt-1 text-amber-900/90">
                    Ohne <code class="rounded bg-amber-100 px-1 py-0.5 text-xs">INGEST_ENABLED=true</code> in der <code class="rounded bg-amber-100 px-1 py-0.5 text-xs">.env</code> importieren
                    <code class="rounded bg-amber-100 px-1 py-0.5 text-xs">ingest:scan-inbox</code> bzw. <code class="rounded bg-amber-100 px-1 py-0.5 text-xs">ingest:scan-upload</code> keine neuen Dateien.
                    @isset($stats)
                        @if ($stats['total'] > 0)
                            <span class="block mt-2">In der Datenbank liegen dennoch <strong>{{ number_format($stats['total'], 0, ',', '.') }}</strong> gespeicherte Einträge (Historie).</span>
                        @else
                            <span class="block mt-2">Es sind noch keine Einträge in <code class="text-xs">ingest_files</code> vorhanden.</span>
                        @endif
                    @endisset
                </p>
            </div>
        @elseif (isset($stats) && $stats['total'] === 0)
            <div class="rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm text-sky-950">
                <p class="font-medium">Keine Ingest-Einträge in der Datenbank</p>
                <ul class="mt-2 list-disc list-inside space-y-1 text-sky-900/90">
                    <li>Video-Dateien (Endungen laut <code class="text-xs">config/ingest.php</code> → <code class="text-xs">allowed_extensions</code>) in die Inbox legen – nur die oberste Ordnerebene wird gescannt (keine Unterordner).</li>
                    <li>Auf dem Server: <code class="rounded bg-white/80 px-1 py-0.5 text-xs">php artisan ingest:scan-upload</code> oder <code class="rounded bg-white/80 px-1 py-0.5 text-xs">php artisan ingest:scan-inbox</code> (Cron). Unvollständige Dateien werden übersprungen, bis sie stabil sind.</li>
                    <li>Eingangspfad: <code class="text-xs">INGEST_ROOT_PATH</code> (→ …/upload) oder direkt <code class="text-xs">INGEST_INBOX_PATH</code> – siehe <code class="text-xs">config/ingest.php</code> / <code class="text-xs">docs/VIDEO_INGEST.md</code>.</li>
                </ul>
            </div>
        @endif

        @if (config('ingest.enabled') && isset($stats) && $stats['total'] > 0 && ($filterActive ?? false) && $files->isEmpty())
            <div class="rounded-lg border border-violet-200 bg-violet-50 p-4 text-sm text-violet-950">
                <p class="font-medium">Keine Treffer für die aktuellen Filter</p>
                <p class="mt-1 text-violet-900/90">
                    Es gibt Datenbankeinträge, aber die Kombination aus Schnellfiltern, Zusatzfiltern, Status, Quelle und Datumsfilter liefert auf dieser Seite keine Zeilen.
                    <a href="{{ route('admin.ingest.index') }}" class="font-medium text-violet-900 underline decoration-violet-400 hover:decoration-violet-700">Alle Filter zurücksetzen</a>
                    oder Einschränkungen lockern.
                </p>
            </div>
        @endif

        @php($ingestQ = fn (array $p = []) => array_merge(request()->except('page'), $p))

        <x-admin.card>
            <p class="text-xs font-medium text-gray-700 mb-2">Schnellfilter <span class="font-normal text-gray-500">(GET, aktuelle Auswahl bleibt erhalten)</span></p>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.ingest.index', $ingestQ(['today' => 1])) }}" class="inline-flex items-center rounded-lg border border-sky-300 bg-sky-50 px-2.5 py-1 text-xs font-medium text-sky-900 hover:bg-sky-100">Heute</a>
                <a href="{{ route('admin.ingest.index', $ingestQ(['validated' => 1])) }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-800 hover:bg-gray-50">Validiert+</a>
                <a href="{{ route('admin.ingest.index', $ingestQ(['no_preview' => 1])) }}" class="inline-flex items-center rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-950 hover:bg-amber-100">Ohne Preview</a>
                <a href="{{ route('admin.ingest.index', $ingestQ(['errors' => 1])) }}" class="inline-flex items-center rounded-lg border border-red-300 bg-red-50 px-2.5 py-1 text-xs font-medium text-red-900 hover:bg-red-100">Fehler</a>
                <a href="{{ route('admin.ingest.index', $ingestQ(['with_news' => 1])) }}" class="inline-flex items-center rounded-lg border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-900 hover:bg-emerald-100">Mit News</a>
                <a href="{{ route('admin.ingest.index', $ingestQ(['without_news' => 1])) }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-800 hover:bg-gray-50">Ohne News</a>
                <a href="{{ route('admin.ingest.index', $ingestQ(['selected' => 1])) }}" class="inline-flex items-center rounded-lg border border-indigo-300 bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-900 hover:bg-indigo-100">Ausgewählt</a>
                <a href="{{ route('admin.ingest.index', $ingestQ(['not_selected' => 1])) }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-800 hover:bg-gray-50">Nicht ausgewählt</a>
                <a href="{{ route('admin.ingest.index') }}" class="inline-flex items-center rounded-lg border border-gray-400 bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-800 hover:bg-gray-200">Reset</a>
            </div>
        </x-admin.card>

        @if (($filterActive ?? false))
            <x-admin.card>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold text-gray-800 uppercase tracking-wide">Aktive Filter</p>
                        <p class="mt-1 text-xs text-gray-500">Zum Entfernen eines einzelnen Kriteriums auf das × klicken.</p>
                    </div>
                    <a href="{{ route('admin.ingest.index') }}" class="shrink-0 text-xs font-medium text-[#092E48] underline hover:no-underline">Alle zurücksetzen</a>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    @if (request()->filled('status'))
                        <a href="{{ route('admin.ingest.index', request()->except(['page', 'status'])) }}" class="inline-flex items-center gap-1 rounded-full bg-gray-200 pl-2.5 pr-1 py-0.5 text-xs text-gray-900 hover:bg-gray-300" title="Status-Filter entfernen">
                            <span>Status: {{ __('ingest.file_status.'.request('status')) }}</span>
                            <span class="rounded-full bg-gray-600 px-1.5 py-0.5 text-[10px] font-bold text-white">×</span>
                        </a>
                    @endif
                    @if (request()->filled('source_id'))
                        @php($srcActive = $sources->firstWhere('id', (int) request('source_id')))
                        <a href="{{ route('admin.ingest.index', request()->except(['page', 'source_id'])) }}" class="inline-flex items-center gap-1 rounded-full bg-gray-200 pl-2.5 pr-1 py-0.5 text-xs text-gray-900 hover:bg-gray-300">
                            <span>Quelle: {{ $srcActive?->name ?? 'ID '.request('source_id') }}</span>
                            <span class="rounded-full bg-gray-600 px-1.5 py-0.5 text-[10px] font-bold text-white">×</span>
                        </a>
                    @endif
                    @if (request()->filled('from'))
                        <a href="{{ route('admin.ingest.index', request()->except(['page', 'from'])) }}" class="inline-flex items-center gap-1 rounded-full bg-gray-200 pl-2.5 pr-1 py-0.5 text-xs text-gray-900 hover:bg-gray-300">
                            <span>Ab {{ request('from') }}</span>
                            <span class="rounded-full bg-gray-600 px-1.5 py-0.5 text-[10px] font-bold text-white">×</span>
                        </a>
                    @endif
                    @if (request()->filled('to'))
                        <a href="{{ route('admin.ingest.index', request()->except(['page', 'to'])) }}" class="inline-flex items-center gap-1 rounded-full bg-gray-200 pl-2.5 pr-1 py-0.5 text-xs text-gray-900 hover:bg-gray-300">
                            <span>Bis {{ request('to') }}</span>
                            <span class="rounded-full bg-gray-600 px-1.5 py-0.5 text-[10px] font-bold text-white">×</span>
                        </a>
                    @endif
                    @if (request()->boolean('today'))
                        <a href="{{ route('admin.ingest.index', request()->except(['page', 'today'])) }}" class="inline-flex items-center gap-1 rounded-full bg-sky-200 pl-2.5 pr-1 py-0.5 text-xs text-sky-950 hover:bg-sky-300">
                            <span>Heute eingegangen</span>
                            <span class="rounded-full bg-sky-800 px-1.5 py-0.5 text-[10px] font-bold text-white">×</span>
                        </a>
                    @endif
                    @if (request()->boolean('validated'))
                        <a href="{{ route('admin.ingest.index', request()->except(['page', 'validated'])) }}" class="inline-flex items-center gap-1 rounded-full bg-gray-200 pl-2.5 pr-1 py-0.5 text-xs text-gray-900 hover:bg-gray-300">
                            <span>Validiert+</span>
                            <span class="rounded-full bg-gray-600 px-1.5 py-0.5 text-[10px] font-bold text-white">×</span>
                        </a>
                    @endif
                    @if (request()->boolean('no_preview'))
                        <a href="{{ route('admin.ingest.index', request()->except(['page', 'no_preview'])) }}" class="inline-flex items-center gap-1 rounded-full bg-amber-200 pl-2.5 pr-1 py-0.5 text-xs text-amber-950 hover:bg-amber-300">
                            <span>Ohne Preview</span>
                            <span class="rounded-full bg-amber-800 px-1.5 py-0.5 text-[10px] font-bold text-white">×</span>
                        </a>
                    @endif
                    @if (request()->boolean('errors'))
                        <a href="{{ route('admin.ingest.index', request()->except(['page', 'errors'])) }}" class="inline-flex items-center gap-1 rounded-full bg-red-200 pl-2.5 pr-1 py-0.5 text-xs text-red-950 hover:bg-red-300">
                            <span>Abgelehnt / Fehler</span>
                            <span class="rounded-full bg-red-800 px-1.5 py-0.5 text-[10px] font-bold text-white">×</span>
                        </a>
                    @endif
                    @if (request()->boolean('with_news'))
                        <a href="{{ route('admin.ingest.index', request()->except(['page', 'with_news'])) }}" class="inline-flex items-center gap-1 rounded-full bg-emerald-200 pl-2.5 pr-1 py-0.5 text-xs text-emerald-950 hover:bg-emerald-300">
                            <span>Mit News</span>
                            <span class="rounded-full bg-emerald-800 px-1.5 py-0.5 text-[10px] font-bold text-white">×</span>
                        </a>
                    @endif
                    @if (request()->boolean('without_news'))
                        <a href="{{ route('admin.ingest.index', request()->except(['page', 'without_news'])) }}" class="inline-flex items-center gap-1 rounded-full bg-gray-200 pl-2.5 pr-1 py-0.5 text-xs text-gray-900 hover:bg-gray-300">
                            <span>Ohne News</span>
                            <span class="rounded-full bg-gray-600 px-1.5 py-0.5 text-[10px] font-bold text-white">×</span>
                        </a>
                    @endif
                    @if (request()->boolean('selected'))
                        <a href="{{ route('admin.ingest.index', request()->except(['page', 'selected'])) }}" class="inline-flex items-center gap-1 rounded-full bg-indigo-200 pl-2.5 pr-1 py-0.5 text-xs text-indigo-950 hover:bg-indigo-300">
                            <span>Ausgewählt</span>
                            <span class="rounded-full bg-indigo-800 px-1.5 py-0.5 text-[10px] font-bold text-white">×</span>
                        </a>
                    @endif
                    @if (request()->boolean('not_selected'))
                        <a href="{{ route('admin.ingest.index', request()->except(['page', 'not_selected'])) }}" class="inline-flex items-center gap-1 rounded-full bg-gray-200 pl-2.5 pr-1 py-0.5 text-xs text-gray-900 hover:bg-gray-300">
                            <span>Nicht ausgewählt</span>
                            <span class="rounded-full bg-gray-600 px-1.5 py-0.5 text-[10px] font-bold text-white">×</span>
                        </a>
                    @endif
                </div>
            </x-admin.card>
        @endif

        <x-admin.card>
            <form method="get" action="{{ route('admin.ingest.index') }}" class="flex flex-wrap gap-3 items-end">
                <div>
                    <label class="block text-xs font-medium text-gray-500">Status</label>
                    <select name="status" class="mt-1 rounded-lg border-gray-300 text-sm">
                        <option value="">alle</option>
                        @foreach (['imported','validating','validated','preview_generating','preview_ready','assigned','rendering','used_in_render','rejected','failed'] as $st)
                            <option value="{{ $st }}" @selected(request('status') === $st)>{{ __('ingest.file_status.'.$st) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500">Quelle</label>
                    <select name="source_id" class="mt-1 rounded-lg border-gray-300 text-sm">
                        <option value="">alle</option>
                        @foreach ($sources as $s)
                            <option value="{{ $s->id }}" @selected((string) request('source_id') === (string) $s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500">Von</label>
                    <input type="date" name="from" value="{{ request('from') }}" class="mt-1 rounded-lg border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500">Bis</label>
                    <input type="date" name="to" value="{{ request('to') }}" class="mt-1 rounded-lg border-gray-300 text-sm">
                </div>
                <div class="w-full basis-full border-t border-gray-100 pt-3 mt-1">
                    <p class="text-xs font-medium text-gray-600 mb-2">Zusatzfilter <span class="font-normal text-gray-400">(mehrfach kombinierbar, GET)</span></p>
                    <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm text-gray-800">
                        <label class="inline-flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" name="today" value="1" class="rounded border-gray-300" @checked(request()->boolean('today'))>
                            <span>Heute eingegangen</span>
                        </label>
                        <label class="inline-flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" name="validated" value="1" class="rounded border-gray-300" @checked(request()->boolean('validated'))>
                            <span>Validiert+</span>
                        </label>
                        <label class="inline-flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" name="no_preview" value="1" class="rounded border-gray-300" @checked(request()->boolean('no_preview'))>
                            <span>Ohne Preview</span>
                        </label>
                        <label class="inline-flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" name="errors" value="1" class="rounded border-gray-300" @checked(request()->boolean('errors'))>
                            <span>Abgelehnt / Fehler</span>
                        </label>
                        <label class="inline-flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" name="with_news" value="1" class="rounded border-gray-300" @checked(request()->boolean('with_news'))>
                            <span>Mit News</span>
                        </label>
                        <label class="inline-flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" name="without_news" value="1" class="rounded border-gray-300" @checked(request()->boolean('without_news'))>
                            <span>Ohne News</span>
                        </label>
                        <label class="inline-flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" name="selected" value="1" class="rounded border-gray-300" @checked(request()->boolean('selected'))>
                            <span>Ausgewählt</span>
                        </label>
                        <label class="inline-flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" name="not_selected" value="1" class="rounded border-gray-300" @checked(request()->boolean('not_selected'))>
                            <span>Nicht ausgewählt</span>
                        </label>
                    </div>
                </div>
                <button type="submit" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg bg-[#092E48] text-white hover:bg-[#0b3858]">
                    Filtern
                </button>
            </form>
        </x-admin.card>

        @if (isset($browserPlayableClips) && $browserPlayableClips->isNotEmpty())
            <x-admin.card>
                <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Schnell-Sichtung (browserfähig)</h2>
                        <p class="mt-1 text-xs text-gray-600 max-w-3xl">
                            MP4/WebM auf dieser Seite – eingebetteter Player über die bestehende Route
                            <code class="rounded bg-gray-100 px-1 text-[11px]">admin.ingest.playback</code>
                            (lokale Original-/Proxy-Datei). Nicht-browserfähige Formate erscheinen nur in der Tabelle darunter.
                        </p>
                    </div>
                    <p class="text-xs font-medium text-gray-500">{{ $browserPlayableClips->count() }} von {{ $files->count() }} auf dieser Seite</p>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    @foreach ($browserPlayableClips as $f)
                        @php
                            $isTodayCard = false;
                            if ($f->created_at) {
                                try {
                                    $isTodayCard = $f->created_at->format('Y-m-d') === now()->toDateString();
                                } catch (\Throwable $e) {
                                    $isTodayCard = false;
                                }
                            }
                        @endphp
                        <article @class([
                            'flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm ring-1 ring-black/5',
                            'ring-sky-300/60' => $isTodayCard,
                        ])>
                            <div class="relative aspect-video bg-gray-950">
                                <video
                                    class="h-full w-full object-contain"
                                    controls
                                    playsinline
                                    preload="metadata"
                                    src="{{ route('admin.ingest.playback', $f) }}"
                                    title="{{ $f->original_name }}"
                                >
                                </video>
                                @if ($isTodayCard)
                                    <span class="absolute left-2 top-2 rounded bg-sky-600/90 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">Heute</span>
                                @endif
                            </div>
                            <div class="flex flex-1 flex-col gap-2 p-3">
                                <p class="text-xs font-semibold text-gray-900 line-clamp-2 break-all" title="{{ $f->original_name }}">{{ $f->original_name }}</p>
                                <div class="flex flex-wrap gap-1.5 text-[11px]">
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-gray-800">#{{ $f->id }}</span>
                                    @php
                                        $cardStatus = __('ingest.file_status.'.$f->status);
                                        if ($cardStatus === 'ingest.file_status.'.$f->status) {
                                            $cardStatus = (string) $f->status;
                                        }
                                    @endphp
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-gray-800">{{ $cardStatus }}</span>
                                </div>
                                <dl class="grid grid-cols-2 gap-x-2 gap-y-1 text-[11px] text-gray-600">
                                    <div><dt class="text-gray-400">Dauer</dt><dd class="font-medium text-gray-800">{{ $f->duration_s ? number_format((float) $f->duration_s, 1, ',', '').' s' : '—' }}</dd></div>
                                    <div>
                                        <dt class="text-gray-400">Größe</dt>
                                        <dd class="font-medium text-gray-800">
                                            @if ($f->file_size !== null)
                                                @php($szc = (int) $f->file_size)
                                                @if ($szc >= 1048576)
                                                    {{ number_format($szc / 1048576, 1, ',', '.') }} MB
                                                @elseif ($szc >= 1024)
                                                    {{ number_format($szc / 1024, 0, ',', '.') }} KB
                                                @else
                                                    {{ $szc }} B
                                                @endif
                                            @else
                                                —
                                            @endif
                                        </dd>
                                    </div>
                                    <div><dt class="text-gray-400">News</dt><dd class="font-medium {{ $f->news_item_id ? 'text-emerald-800' : 'text-gray-500' }}">{{ $f->news_item_id ? 'ja (#'.$f->news_item_id.')' : 'nein' }}</dd></div>
                                    <div><dt class="text-gray-400">Auswahl</dt><dd class="font-medium {{ ($f->is_selected ?? false) ? 'text-indigo-800' : 'text-gray-500' }}">{{ ($f->is_selected ?? false) ? 'ja' : 'nein' }}</dd></div>
                                </dl>
                                <div class="mt-auto pt-1">
                                    <a href="{{ route('admin.ingest.show', $f) }}" class="inline-flex w-full items-center justify-center rounded-lg border border-[#092E48] bg-[#092E48] px-3 py-2 text-xs font-medium text-white hover:bg-[#0b3858]">
                                        Details
                                    </a>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </x-admin.card>
        @endif

        <x-admin.card>
            <h2 class="text-base font-semibold text-gray-900 mb-1">Alle Einträge</h2>
            <p class="mb-3 text-xs text-gray-500">Vollständige tabellarische Übersicht inkl. nicht-browserfähiger Formate (z. B. MXF).</p>
            <p class="mb-3 text-xs text-gray-500">
                <span class="font-medium text-gray-700">Sortierung:</span> Neueste zuerst (höchste ID).
                Zeilen mit <span class="rounded bg-sky-100 px-1 py-0.5 text-sky-900">heutigem Eingang</span> sind hervorgehoben (Eingangszeit = heute).
            </p>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Datei</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">MIME</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Größe</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Eingang</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Vorschau</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Dauer</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">News</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Auswahl</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Hinweis / Fehler</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Aktion</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse ($files as $f)
                            @php
                                $isToday = false;
                                if ($f->created_at) {
                                    try {
                                        $isToday = $f->created_at->format('Y-m-d') === now()->toDateString();
                                    } catch (\Throwable $e) {
                                        $isToday = false;
                                    }
                                }
                            @endphp
                            <tr @class([
                                'bg-sky-50/90' => $isToday,
                            ])>
                                <td class="px-3 py-2 text-sm text-gray-900 align-top">
                                    <span class="whitespace-nowrap">#{{ $f->id }}</span>
                                    @if ($isToday)
                                        <span class="ml-1 inline-flex rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide bg-sky-200 text-sky-950">Heute</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-sm text-gray-800 align-top break-all max-w-[14rem]">{{ $f->original_name }}</td>
                                <td class="px-3 py-2 text-xs text-gray-600 align-top break-all max-w-[8rem]">{{ $f->mime ?: '—' }}</td>
                                <td class="px-3 py-2 text-sm text-gray-600 align-top whitespace-nowrap">
                                    @if ($f->file_size !== null)
                                        @php($sz = (int) $f->file_size)
                                        @if ($sz >= 1073741824)
                                            {{ number_format($sz / 1073741824, 2, ',', '.') }} GB
                                        @elseif ($sz >= 1048576)
                                            {{ number_format($sz / 1048576, 2, ',', '.') }} MB
                                        @elseif ($sz >= 1024)
                                            {{ number_format($sz / 1024, 1, ',', '.') }} KB
                                        @else
                                            {{ $sz }} B
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-sm text-gray-600 align-top whitespace-nowrap">
                                    @if ($f->created_at)
                                        {{ $f->created_at->format('d.m.Y H:i') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-sm align-top">
                                    @php
                                        $__rowStatus = __('ingest.file_status.'.$f->status);
                                        if ($__rowStatus === 'ingest.file_status.'.$f->status) {
                                            $__rowStatus = (string) $f->status;
                                        }
                                    @endphp
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-800">{{ $__rowStatus }}</span>
                                </td>
                                <td class="px-3 py-2 text-sm align-top">
                                    @if ($f->preview_status === 'ready' || (filled($f->preview_path) && $f->preview_status === null))
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium bg-green-100 text-green-800" title="Vorschau-MP4 vorhanden">MP4</span>
                                    @elseif ($f->preview_status === 'generating' || $f->status === 'preview_generating')
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium bg-amber-100 text-amber-900">… läuft</span>
                                    @elseif ($f->preview_status === 'failed')
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium bg-red-100 text-red-800" title="{{ Str::limit($f->preview_error_message ?? '', 400) }}">Fehler</span>
                                    @elseif (in_array($f->status, ['validated', 'preview_ready', 'assigned'], true))
                                        <span class="text-xs text-gray-500">offen</span>
                                    @else
                                        <span class="text-xs text-gray-300">—</span>
                                    @endif
                                    @if (filled($f->preview_path))
                                        <span class="block text-[10px] text-gray-400 mt-0.5">Datei vorhanden</span>
                                    @elseif (! in_array($f->status, [\App\Models\IngestFile::STATUS_IMPORTED, \App\Models\IngestFile::STATUS_VALIDATING, \App\Models\IngestFile::STATUS_REJECTED, \App\Models\IngestFile::STATUS_FAILED], true))
                                        <span class="block text-[10px] text-amber-700 mt-0.5">kein Pfad</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-sm text-gray-600 align-top whitespace-nowrap">
                                    @if ($f->duration_s)
                                        {{ number_format((float) $f->duration_s, 2, ',', '') }} s
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-sm text-gray-600 align-top">
                                    @if ($f->news_item_id)
                                        <span class="text-green-800 font-medium">ja</span>
                                        <span class="block text-xs text-gray-500">#{{ $f->news_item_id }}
                                            @if ($f->newsItem)
                                                ({{ Str::limit($f->newsItem->title, 28) }})
                                            @endif
                                        </span>
                                    @else
                                        <span class="text-gray-400">nein</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-sm align-top">
                                    @if ($f->is_selected ?? false)
                                        <span class="text-indigo-800 font-medium">ja</span>
                                    @else
                                        <span class="text-gray-400">nein</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-xs text-gray-700 align-top max-w-[16rem]">
                                    @php($em = $f->error_message)
                                    @php($pem = $f->preview_error_message)
                                    @if (filled($em) || filled($pem))
                                        @if (filled($em))
                                            <p class="text-red-800" title="{{ $em }}"><span class="font-medium">Workflow:</span> {{ Str::limit($em, 120) }}</p>
                                        @endif
                                        @if (filled($pem))
                                            <p class="mt-1 text-red-800" title="{{ $pem }}"><span class="font-medium">Vorschau:</span> {{ Str::limit($pem, 120) }}</p>
                                        @endif
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-sm text-right align-top">
                                    <a href="{{ route('admin.ingest.show', $f) }}" class="text-[#092E48] hover:underline">Details</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="px-3 py-6 text-sm text-gray-500 text-center">
                                    @if (($filterActive ?? false) && isset($stats) && $stats['total'] > 0)
                                        Keine Einträge für die aktuellen Filter (siehe Hinweis oben).
                                    @elseif (isset($stats) && $stats['total'] === 0)
                                        Keine Einträge – es sind noch keine Clips in der Datenbank.
                                    @else
                                        Keine Einträge auf dieser Seite.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $files->links() }}
            </div>
        </x-admin.card>
    </div>
@endsection
