@extends('layouts.admin')

@section('title', ($ingestListOnly ?? false) ? 'Ingest – Alle Einträge' : 'Video-Ingest')

@section('content')
    @php
        $ingestRouteName = ($ingestListOnly ?? false) ? 'admin.ingest.entries' : 'admin.ingest.index';
    @endphp
    <div class="space-y-6 text-left min-w-0 max-w-full w-full">
        @if (session('status'))
            <div id="ingest-flash-status" class="rounded-md bg-green-50 p-4 border border-green-200 scroll-mt-24">
                <p class="text-sm text-green-800 break-words">{{ session('status') }}</p>
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-md bg-red-50 p-4 border border-red-200">
                <p class="text-sm text-red-800 break-words">{{ session('error') }}</p>
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-md bg-red-50 p-4 border border-red-200" role="alert">
                <p class="text-sm font-medium text-red-900">Eingabe nicht verarbeitet</p>
                <ul class="mt-2 list-disc list-inside text-sm text-red-800 space-y-1">
                    @foreach ($errors->all() as $err)
                        <li class="break-words">{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between min-w-0">
            <div class="min-w-0">
                @if ($ingestListOnly ?? false)
                    <nav class="mb-2 text-sm">
                        <a href="{{ route('admin.ingest.index') }}" class="inline-flex items-center min-h-[2.75rem] font-medium text-[#092E48] hover:underline -ml-1 px-1 rounded-lg hover:bg-gray-100">← Sichtung</a>
                        <span class="mx-2 text-gray-300">/</span>
                        <span class="text-gray-600">Alle Einträge</span>
                    </nav>
                    <h1 class="text-xl sm:text-2xl font-semibold text-gray-900 break-words">Alle Ingest-Einträge</h1>
                    <p class="mt-1 text-sm text-gray-600 max-w-3xl break-words">
                        Tabellarische Übersicht mit Zuordnung. Zur Karten-Sichtung wechseln Sie über „Sichtung“ oben oder das Menü <span class="whitespace-nowrap">Ingest</span>.
                    </p>
                @else
                    <h1 class="text-xl sm:text-2xl font-semibold text-gray-900 break-words">Video-Ingest · Sichtung</h1>
                    <p class="mt-1 text-sm text-gray-600 max-w-3xl break-words">
                        Sichtung, Auswahl und Zuordnung zu Meldungen. Standard: <strong>alle</strong> offenen Clips (nicht nur „heute“). Filter „Heute“ schränkt auf den Eingang von heute ein. Kennzahlen: <a href="{{ route('admin.ingest.statistics') }}" class="font-medium text-[#092E48] underline hover:no-underline">Statistik</a>.
                        Nicht im Player darstellbare Clips und die Tabelle stehen weiter unten.
                    </p>
                @endif
            </div>
        </div>

        @if (! config('ingest.enabled'))
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 min-w-0 break-words">
                <p class="font-medium">Ingest ist deaktiviert</p>
                <p class="mt-1 text-amber-900/90 break-words">
                    Ohne <code class="rounded bg-amber-100 px-1 py-0.5 text-xs">INGEST_ENABLED=true</code> in der <code class="rounded bg-amber-100 px-1 py-0.5 text-xs">.env</code> importieren
                    <code class="rounded bg-amber-100 px-1 py-0.5 text-xs">ingest:scan-inbox</code> bzw.
                    <code class="rounded bg-amber-100 px-1 py-0.5 text-xs">ingest:scan-upload</code> keine neuen Dateien.

                    @if (($ingestTotalInDb ?? 0) > 0)
                        <span class="block mt-2">
                            In der Datenbank liegen dennoch
                            <strong>{{ number_format($ingestTotalInDb, 0, ',', '.') }}</strong>
                            gespeicherte Einträge (Historie).
                        </span>
                    @else
                        <span class="block mt-2">
                            Es sind noch keine Einträge in <code class="text-xs">ingest_files</code> vorhanden.
                        </span>
                    @endif
                </p>
            </div>
        @elseif (($ingestTotalInDb ?? 0) === 0)
            <div class="rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm text-sky-950 min-w-0 break-words">
                <p class="font-medium">Noch keine Clips in der Übersicht</p>
                <p class="mt-2 text-sky-900/90 break-words">
                    Neue Proxy-Videos erscheinen hier nach dem Eingang und Import. Einrichtung des Ingest-Ordners und Scan-Jobs ist in der technischen Dokumentation bzw. Detailansicht beschrieben.
                </p>
            </div>
        @endif

        @if (config('ingest.enabled') && ($ingestTotalInDb ?? 0) > 0 && ($filterActive ?? false) && $files->isEmpty())
            <div class="rounded-lg border border-violet-200 bg-violet-50 p-4 text-sm text-violet-950 min-w-0 break-words">
                <p class="font-medium">Keine Treffer für die aktuellen Filter</p>
                <p class="mt-1 text-violet-900/90 break-words">
                    Es gibt Datenbankeinträge, aber die aktuelle Filterkombination liefert auf dieser Seite keine Treffer.
                    <a href="{{ route($ingestRouteName) }}" class="font-medium text-violet-900 underline decoration-violet-400 hover:decoration-violet-700">Alle Filter zurücksetzen</a>
                    oder Einschränkungen lockern.
                </p>
            </div>
        @endif

        @if (config('ingest.enabled') && request()->boolean('today') && $files->isEmpty() && ($ingestTotalInDb ?? 0) > 0)
            <div class="rounded-lg border border-emerald-200 bg-emerald-50/80 p-4 text-sm text-emerald-950 min-w-0 break-words">
                <p class="font-medium">Heute liegt nichts zur Sichtung an</p>
                <p class="mt-1 text-emerald-900/95">
                    In der Datenbank gibt es <strong>{{ number_format($ingestTotalInDb, 0, ',', '.') }}</strong> Einträge.
                    <a href="{{ route('admin.ingest.index', request()->except(['page', 'today'])) }}" class="font-semibold text-emerald-950 underline decoration-emerald-400 hover:decoration-emerald-800">Filter „Heute“ entfernen</a>
                    oder <a href="{{ route('admin.ingest.entries') }}" class="font-semibold text-emerald-950 underline decoration-emerald-400 hover:decoration-emerald-800">Alle Einträge</a>,
                    <a href="{{ route('admin.ingest.statistics') }}" class="font-semibold text-emerald-950 underline decoration-emerald-400 hover:decoration-emerald-800">Statistik</a>.
                </p>
            </div>
        @endif

        @php
            $ingestQ = function (array $p = []) {
                $base = request()->except('page');
                foreach ($p as $k => $v) {
                    if ($v === null) {
                        unset($base[$k]);
                    } else {
                        $base[$k] = $v;
                    }
                }

                return $base;
            };
        @endphp

        <x-admin.section title="Schnellfilter" subtitle="Häufige Einschränkungen; beim Blättern bleibt die Auswahl erhalten.">
            <div class="flex flex-wrap gap-2 min-w-0">
                <a href="{{ route($ingestRouteName, $ingestQ(['today' => 1, 'news_item_id' => null, 'with_news' => null, 'without_news' => null])) }}" class="inline-flex items-center justify-center min-h-[2.25rem] rounded-lg border border-sky-300 bg-sky-50 px-2.5 py-1 text-xs font-medium text-sky-900 hover:bg-sky-100">Heute</a>
                <a href="{{ route($ingestRouteName, $ingestQ(['news_item_id' => 'any', 'with_news' => null, 'without_news' => null])) }}" class="inline-flex items-center justify-center min-h-[2.25rem] rounded-lg border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-900 hover:bg-emerald-100">Mit Meldung</a>
                <a href="{{ route($ingestRouteName, $ingestQ(['news_item_id' => 'none', 'with_news' => null, 'without_news' => null])) }}" class="inline-flex items-center justify-center min-h-[2.25rem] rounded-lg border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-800 hover:bg-gray-50">Ohne Meldung</a>
                <a href="{{ route($ingestRouteName, $ingestQ(['errors' => 1])) }}" class="inline-flex items-center justify-center min-h-[2.25rem] rounded-lg border border-red-300 bg-red-50 px-2.5 py-1 text-xs font-medium text-red-900 hover:bg-red-100">Fehler</a>
                <a href="{{ route($ingestRouteName) }}" class="inline-flex items-center justify-center min-h-[2.25rem] rounded-lg border border-gray-400 bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-800 hover:bg-gray-200">Alle zurücksetzen</a>
            </div>
        </x-admin.section>

        @if (($filterActive ?? false))
            <x-admin.section title="Aktive Filter" subtitle="Einzelnes Kriterium über × entfernen.">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between sm:mb-3">
                    <a href="{{ route($ingestRouteName) }}" class="shrink-0 text-xs font-medium text-[#092E48] underline hover:no-underline sm:ml-auto order-first sm:order-last">Alle zurücksetzen</a>
                </div>

                <div class="flex flex-wrap gap-2 min-w-0">
                    @if (request()->filled('status'))
                        <a href="{{ route($ingestRouteName, request()->except(['page', 'status'])) }}" class="inline-flex items-center gap-1 rounded-full bg-gray-200 pl-2.5 pr-1 py-0.5 text-xs text-gray-900 hover:bg-gray-300" title="Status-Filter entfernen">
                            <span>Status: {{ __('ingest.file_status.'.request('status')) }}</span>
                            <span class="rounded-full bg-gray-600 px-1.5 py-0.5 text-[10px] font-bold text-white">×</span>
                        </a>
                    @endif

                    @if (request()->filled('source_id'))
                        @php
                            $srcActive = $sources->firstWhere('id', (int) request('source_id'));
                        @endphp
                        <a href="{{ route($ingestRouteName, request()->except(['page', 'source_id'])) }}" class="inline-flex items-center gap-1 rounded-full bg-gray-200 pl-2.5 pr-1 py-0.5 text-xs text-gray-900 hover:bg-gray-300">
                            <span>Quelle: {{ $srcActive?->name ?? 'ID '.request('source_id') }}</span>
                            <span class="rounded-full bg-gray-600 px-1.5 py-0.5 text-[10px] font-bold text-white">×</span>
                        </a>
                    @endif

                    @if (request()->filled('from'))
                        <a href="{{ route($ingestRouteName, request()->except(['page', 'from'])) }}" class="inline-flex items-center gap-1 rounded-full bg-gray-200 pl-2.5 pr-1 py-0.5 text-xs text-gray-900 hover:bg-gray-300">
                            <span>Ab {{ request('from') }}</span>
                            <span class="rounded-full bg-gray-600 px-1.5 py-0.5 text-[10px] font-bold text-white">×</span>
                        </a>
                    @endif

                    @if (request()->filled('to'))
                        <a href="{{ route($ingestRouteName, request()->except(['page', 'to'])) }}" class="inline-flex items-center gap-1 rounded-full bg-gray-200 pl-2.5 pr-1 py-0.5 text-xs text-gray-900 hover:bg-gray-300">
                            <span>Bis {{ request('to') }}</span>
                            <span class="rounded-full bg-gray-600 px-1.5 py-0.5 text-[10px] font-bold text-white">×</span>
                        </a>
                    @endif

                    @if (request()->boolean('today'))
                        <a href="{{ route($ingestRouteName, request()->except(['page', 'today'])) }}" class="inline-flex items-center gap-1 rounded-full bg-sky-200 pl-2.5 pr-1 py-0.5 text-xs text-sky-950 hover:bg-sky-300">
                            <span>Heute eingegangen</span>
                            <span class="rounded-full bg-sky-800 px-1.5 py-0.5 text-[10px] font-bold text-white">×</span>
                        </a>
                    @endif

                    @if (request()->filled('news_item_id'))
                        @php
                            $nv = (string) request('news_item_id');
                            $newsChipLabel = match ($nv) {
                                'none' => 'Zuordnung: ohne Meldung',
                                'any' => 'Zuordnung: mit Meldung (alle)',
                                default => null,
                            };
                        if ($newsChipLabel === null && is_numeric($nv)) {
                            $n = $ingestNewsChoices->firstWhere('id', (int) $nv);
                            $newsChipLabel = $n ? 'Meldung: #'.$n->id.' · '.Str::limit($n->title, 36) : 'Meldung #'.$nv;
                        }
                        $newsChipLabel ??= 'Zuordnung: '.Str::limit($nv, 32);
                        @endphp
                        <a href="{{ route($ingestRouteName, request()->except(['page', 'news_item_id', 'with_news', 'without_news'])) }}" class="inline-flex items-center gap-1 rounded-full bg-emerald-200 pl-2.5 pr-1 py-0.5 text-xs text-emerald-950 hover:bg-emerald-300">
                            <span>{{ $newsChipLabel }}</span>
                            <span class="rounded-full bg-emerald-800 px-1.5 py-0.5 text-[10px] font-bold text-white">×</span>
                        </a>
                    @endif

                    @if (request()->boolean('validated'))
                        <a href="{{ route($ingestRouteName, request()->except(['page', 'validated'])) }}" class="inline-flex items-center gap-1 rounded-full bg-gray-200 pl-2.5 pr-1 py-0.5 text-xs text-gray-900 hover:bg-gray-300">
                            <span>Validiert+</span>
                            <span class="rounded-full bg-gray-600 px-1.5 py-0.5 text-[10px] font-bold text-white">×</span>
                        </a>
                    @endif

                    @if (request()->boolean('no_preview'))
                        <a href="{{ route($ingestRouteName, request()->except(['page', 'no_preview'])) }}" class="inline-flex items-center gap-1 rounded-full bg-amber-200 pl-2.5 pr-1 py-0.5 text-xs text-amber-950 hover:bg-amber-300">
                            <span>Ohne Preview</span>
                            <span class="rounded-full bg-amber-800 px-1.5 py-0.5 text-[10px] font-bold text-white">×</span>
                        </a>
                    @endif

                    @if (request()->boolean('errors'))
                        <a href="{{ route($ingestRouteName, request()->except(['page', 'errors'])) }}" class="inline-flex items-center gap-1 rounded-full bg-red-200 pl-2.5 pr-1 py-0.5 text-xs text-red-950 hover:bg-red-300">
                            <span>Abgelehnt / Fehler</span>
                            <span class="rounded-full bg-red-800 px-1.5 py-0.5 text-[10px] font-bold text-white">×</span>
                        </a>
                    @endif

                    @if (! request()->filled('news_item_id') && request()->boolean('with_news'))
                        <a href="{{ route($ingestRouteName, request()->except(['page', 'with_news'])) }}" class="inline-flex items-center gap-1 rounded-full bg-emerald-200 pl-2.5 pr-1 py-0.5 text-xs text-emerald-950 hover:bg-emerald-300">
                            <span>Mit News</span>
                            <span class="rounded-full bg-emerald-800 px-1.5 py-0.5 text-[10px] font-bold text-white">×</span>
                        </a>
                    @endif

                    @if (! request()->filled('news_item_id') && request()->boolean('without_news'))
                        <a href="{{ route($ingestRouteName, request()->except(['page', 'without_news'])) }}" class="inline-flex items-center gap-1 rounded-full bg-gray-200 pl-2.5 pr-1 py-0.5 text-xs text-gray-900 hover:bg-gray-300">
                            <span>Ohne News</span>
                            <span class="rounded-full bg-gray-600 px-1.5 py-0.5 text-[10px] font-bold text-white">×</span>
                        </a>
                    @endif

                    @if (request()->boolean('selected'))
                        <a href="{{ route($ingestRouteName, request()->except(['page', 'selected'])) }}" class="inline-flex items-center gap-1 rounded-full bg-indigo-200 pl-2.5 pr-1 py-0.5 text-xs text-indigo-950 hover:bg-indigo-300">
                            <span>Ausgewählt</span>
                            <span class="rounded-full bg-indigo-800 px-1.5 py-0.5 text-[10px] font-bold text-white">×</span>
                        </a>
                    @endif

                    @if (request()->boolean('not_selected'))
                        <a href="{{ route($ingestRouteName, request()->except(['page', 'not_selected'])) }}" class="inline-flex items-center gap-1 rounded-full bg-gray-200 pl-2.5 pr-1 py-0.5 text-xs text-gray-900 hover:bg-gray-300">
                            <span>Nicht ausgewählt</span>
                            <span class="rounded-full bg-gray-600 px-1.5 py-0.5 text-[10px] font-bold text-white">×</span>
                        </a>
                    @endif
                </div>
            </x-admin.section>
        @endif

        @php
            $ingestNewsFilterSel = '';
            if (request()->filled('news_item_id')) {
                $ingestNewsFilterSel = (string) request('news_item_id');
            } elseif (request()->boolean('with_news')) {
                $ingestNewsFilterSel = 'any';
            } elseif (request()->boolean('without_news')) {
                $ingestNewsFilterSel = 'none';
            }
        @endphp

        <x-admin.section title="Detailfilter" subtitle="Zuordnung zu einer Meldung, optional Quelle und Zeitraum. Technische Statusfilter sind per URL weiterhin möglich; Standard ist die volle Liste.">
            <form method="get" action="{{ route($ingestRouteName) }}" class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end min-w-0">
                @foreach (['today', 'errors', 'validated', 'no_preview', 'selected', 'not_selected', 'alle'] as $ingestPreserveFlag)
                    @if (request()->boolean($ingestPreserveFlag))
                        <input type="hidden" name="{{ $ingestPreserveFlag }}" value="1">
                    @endif
                @endforeach
                @if (request()->filled('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif

                <div class="w-full min-w-0 sm:max-w-md">
                    <label class="block text-xs font-medium text-gray-500" for="ingest-filter-news">Zuordnung</label>
                    <select name="news_item_id" id="ingest-filter-news" class="mt-1 w-full max-w-full min-w-0 rounded-lg border-gray-300 text-sm">
                        <option value="">Alle Einträge</option>
                        <option value="none" @selected($ingestNewsFilterSel === 'none')>Ohne Meldung</option>
                        <option value="any" @selected($ingestNewsFilterSel === 'any')>Mit Meldung (beliebig)</option>
                        @foreach ($ingestNewsChoices as $n)
                            <option value="{{ $n->id }}" @selected($ingestNewsFilterSel !== '' && $ingestNewsFilterSel !== 'none' && $ingestNewsFilterSel !== 'any' && (string) $ingestNewsFilterSel === (string) $n->id)>
                                #{{ $n->id }}@if($n->brand) · {{ Str::limit($n->brand->name, 14) }}@endif · {{ Str::limit($n->title, 56) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="w-full min-w-0 sm:max-w-[12rem]">
                    <label class="block text-xs font-medium text-gray-500" for="ingest-filter-source">Quelle</label>
                    <select name="source_id" id="ingest-filter-source" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                        <option value="">alle</option>
                        @foreach ($sources as $s)
                            <option value="{{ $s->id }}" @selected((string) request('source_id') === (string) $s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="w-full min-w-0 sm:max-w-[11rem]">
                    <label class="block text-xs font-medium text-gray-500" for="ingest-filter-from">Von</label>
                    <input type="date" name="from" id="ingest-filter-from" value="{{ request('from') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                </div>

                <div class="w-full min-w-0 sm:max-w-[11rem]">
                    <label class="block text-xs font-medium text-gray-500" for="ingest-filter-to">Bis</label>
                    <input type="date" name="to" id="ingest-filter-to" value="{{ request('to') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                </div>

                <button type="submit" class="inline-flex w-full sm:w-auto justify-center items-center min-h-[2.5rem] px-4 py-2 text-sm font-medium rounded-lg bg-[#092E48] text-white hover:bg-[#0b3858] lg:shrink-0">
                    Filtern
                </button>
            </form>
        </x-admin.section>

        <x-admin.section title="Notfall-Werkzeug" subtitle="Löscht den gesamten Ingest-Bestand (Dateien + Datenbankeinträge). Nur für echte Ausnahmefälle.">
            <form
                method="POST"
                action="{{ route('admin.ingest.emergency-purge') }}"
                class="rounded-lg border border-red-200 bg-red-50 p-4 space-y-3"
                onsubmit="return window.ingestEmergencyPurgeConfirm(this)"
            >
                @csrf

                <p class="text-sm text-red-900">
                    Dieser Notfall-Button leert den kompletten Ingest-Bereich.
                    Aktuell in der Datenbank: <strong>{{ number_format((int) ($ingestTotalInDb ?? 0), 0, ',', '.') }}</strong> Einträge.
                </p>

                <div class="grid gap-3 md:grid-cols-3">
                    <div>
                        <label for="ingest-emergency-count" class="block text-xs font-semibold text-red-900">Aktuelle Anzahl eingeben</label>
                        <input
                            id="ingest-emergency-count"
                            name="confirm_count"
                            type="number"
                            min="0"
                            required
                            value="{{ old('confirm_count') }}"
                            class="mt-1 w-full rounded-lg border-red-300 text-sm"
                            placeholder="{{ (int) ($ingestTotalInDb ?? 0) }}"
                        >
                    </div>

                    <div>
                        <label for="ingest-emergency-phrase" class="block text-xs font-semibold text-red-900">Sicherheitsphrase eingeben</label>
                        <input
                            id="ingest-emergency-phrase"
                            name="confirm_phrase"
                            type="text"
                            required
                            value="{{ old('confirm_phrase') }}"
                            class="mt-1 w-full rounded-lg border-red-300 text-sm font-mono"
                            placeholder="INGEST ALLES LOESCHEN"
                        >
                    </div>

                    <div>
                        <label for="ingest-emergency-yes" class="block text-xs font-semibold text-red-900">Zusätzlich „ja“ eingeben</label>
                        <input
                            id="ingest-emergency-yes"
                            name="confirmation"
                            type="text"
                            required
                            value="{{ old('confirmation') }}"
                            class="mt-1 w-full rounded-lg border-red-300 text-sm"
                            placeholder="ja"
                        >
                    </div>
                </div>

                <p class="text-xs text-red-800">
                    Erwartete Phrase: <code class="rounded bg-red-100 px-1 py-0.5">INGEST ALLES LOESCHEN</code>
                </p>

                <button type="submit" class="inline-flex items-center justify-center min-h-[2.75rem] rounded-lg border border-red-300 bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                    Notfall: Ingest komplett löschen
                </button>
            </form>
        </x-admin.section>

        @if ($files->isNotEmpty())
            @php
                $ingestBulkRowSelectable = function (\App\Models\IngestFile $f): bool {
                    return ! in_array($f->status, ['used_in_render', 'rendering'], true);
                };
            @endphp

            @include('admin.ingest.partials.ingest-news-datalist')

            <x-admin.section title="Mehrfachauswahl" subtitle="Markieren in der Arbeitsansicht, in der Tabelle oder in den mobilen Karten – dann gemeinsam zuordnen oder löschen. Finalrender: Reihenfolge im Schnitt = Eingangszeit der Clips (ältester zuerst).">
                <form id="ingest-bulk-form" method="POST" action="{{ route('admin.ingest.bulk') }}" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm space-y-3">
                    @csrf
                    <input type="hidden" name="return_to" value="{{ url()->full() }}">
                    <div class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" class="ingest-bulk-select-all rounded border-gray-300" id="ingest-bulk-select-all-bar" aria-label="Alle auf dieser Seite markieren">
                        <label for="ingest-bulk-select-all-bar" class="cursor-pointer">Alle auf dieser Seite markieren</label>
                    </div>
                    <div class="flex flex-col sm:flex-row sm:flex-wrap gap-3 sm:items-end">
                        <div class="w-full min-w-0 sm:max-w-md">
                            <label for="ingest-bulk-news" class="block text-xs font-medium text-gray-500">Gemeinsame Meldung (Zuordnung)</label>
                            <input
                                type="number"
                                name="news_item_id"
                                id="ingest-bulk-news"
                                class="mt-1 block w-full rounded-lg border-gray-300 text-sm"
                                list="ingest-news-assign-datalist"
                                min="1"
                                step="1"
                                inputmode="numeric"
                                autocomplete="off"
                                placeholder="Meldungs-ID wählen oder eingeben …"
                                aria-label="Gemeinsame Meldung für Mehrfachzuordnung"
                                value=""
                            >
                        </div>
                        <button type="submit" name="action" value="assign_news" class="inline-flex justify-center items-center min-h-[2.75rem] px-4 py-2 text-sm font-medium rounded-lg bg-[#092E48] text-white hover:bg-[#0b3858]"
                            onclick="return window.ingestBulkAssignPrep(document.getElementById('ingest-bulk-form'))">
                            Ausgewählte zuordnen
                        </button>
                        <button type="submit" name="action" value="render_final" class="inline-flex justify-center items-center min-h-[2.75rem] px-4 py-2 text-sm font-medium rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-900 hover:bg-emerald-100 hover:border-emerald-300"
                            title="Wählt die markierten (Video-)Clips für eine gemeinsame sendefähige MP4 aus und startet den Render-Queue-Job"
                            onclick="return window.ingestBulkRenderPrep(document.getElementById('ingest-bulk-form'))">
                            Ausgewählte zum Finalrender
                        </button>
                        <button type="submit" name="action" value="delete" class="inline-flex justify-center items-center min-h-[2.75rem] px-4 py-2 text-sm font-medium rounded-lg border border-red-300 bg-red-50 text-red-900 hover:bg-red-100"
                            data-ingest-bulk-delete="1">
                            Ausgewählte löschen
                        </button>
                    </div>
                </form>
            </x-admin.section>
        @endif

        @if (! ($ingestListOnly ?? false) && isset($browserPlayableClips) && $browserPlayableClips->isNotEmpty())
            <x-admin.section
                title="Arbeitsansicht"
                :subtitle="'Vorschau (Video oder JPEG) · '.$browserPlayableClips->count().' von '.$files->total().' auf dieser Seite · Seite '.$files->currentPage().'/' . $files->lastPage().' (60 pro Seite)'"
            >
                <p class="text-xs text-slate-600 mb-3 max-w-3xl">
                    Kompakte Kacheln – Meldung per <strong>Meldungs-ID</strong> zuordnen, <strong>ohne</strong> auf die FFmpeg-Kurzvorschau zu warten. Jedes Video erhält sofort ein <strong>Poster-Standbild</strong> (auch MOV/MXF); die leichte MP4-Vorschau folgt automatisch in der Queue. Videos nutzen die Kurzvorschau sobald fertig, sonst Poster mit Link zur Detailansicht.
                </p>

                @php
                    $videoPlayableClips = $browserPlayableClips->filter(fn ($clip) => ! $clip->isIngestImageFile())->values();
                    $imagePlayableClips = $browserPlayableClips->filter(fn ($clip) => $clip->isIngestImageFile())->values();
                @endphp

                @foreach ([
                    ['label' => 'Videos', 'clips' => $videoPlayableClips],
                    ['label' => 'Bilder', 'clips' => $imagePlayableClips],
                ] as $sec)
                    @php $clipsForSection = $sec['clips']; @endphp
                    @if ($clipsForSection->isNotEmpty())
                        <h3 class="text-sm font-semibold text-gray-900 mt-5 mb-2">{{ $sec['label'] }}</h3>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 min-w-0">
                    @foreach ($clipsForSection as $f)
                        @php
                            $isTodayCard = false;
                            if ($f->created_at) {
                                try {
                                    $isTodayCard = $f->created_at->format('Y-m-d') === now()->toDateString();
                                } catch (\Throwable $e) {
                                    $isTodayCard = false;
                                }
                            }

                            $cardClasses = 'flex flex-col overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm ring-1 ring-black/5';
                            if ($isTodayCard) {
                                $cardClasses .= ' ring-sky-300/60';
                            }

                            $ingestCanQuickAssign = in_array($f->status, ['validated', 'preview_generating', 'preview_ready', 'assigned'], true);
                            $ingestAssignLocked = in_array($f->status, ['used_in_render', 'rendering'], true);
                        @endphp

                        <article id="ingest-clip-{{ $f->id }}" class="{{ $cardClasses }} scroll-mt-24 min-w-0 max-w-full">
                            @if ($ingestBulkRowSelectable($f))
                                <div class="flex items-center justify-end gap-1 border-b border-gray-100 bg-gray-50/80 px-1.5 py-1">
                                    <label class="inline-flex cursor-pointer items-center gap-1 text-[10px] font-medium text-gray-800">
                                        <input type="checkbox" name="ids[]" value="{{ $f->id }}" form="ingest-bulk-form" class="ingest-bulk-cb rounded border-gray-300" aria-label="Eintrag {{ $f->id }} für Mehrfachaktion markieren">
                                        <span class="hidden sm:inline">Mehrfach</span>
                                    </label>
                                </div>
                            @endif
                            <div class="{{ $f->isIngestImageFile() ? 'relative flex w-full max-w-full min-w-0 items-center justify-center overflow-hidden bg-gray-950 min-h-[5.5rem] max-h-32' : 'relative aspect-video max-h-32 bg-gray-950 max-w-full min-w-0 overflow-hidden' }}">
                                @if ($f->isIngestImageFile())
                                    @php
                                        $ingestImageCardPreloadStrong = $loop->index < 12;
                                    @endphp
                                    <a href="{{ route('admin.ingest.show', $f) }}" aria-label="Ingest-Eintrag #{{ $f->id }} öffnen" class="flex w-full items-center justify-center min-h-[5.5rem] max-h-32 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#092E48]" title="Detailansicht mit großer Vorschau">
                                        <span class="sr-only">Ingest-Eintrag #{{ $f->id }} öffnen</span>
                                        <img
                                            class="h-auto w-full max-h-32 object-contain"
                                            src="{{ route('admin.ingest.thumbnail', $f) }}"
                                            alt="{{ $f->newsItem?->title ?: ($f->newsItem?->subheadline ?: ($f->original_name ?: 'Bildmaterial von Erftkreis News Media')) }}"
                                            loading="{{ $ingestImageCardPreloadStrong ? 'eager' : 'lazy' }}"
                                            @if ($ingestImageCardPreloadStrong && $loop->index < 4)
                                                fetchpriority="high"
                                            @endif
                                            decoding="async"
                                        >
                                    </a>
                                @else
                                    @php
                                        $ingestCardVideoPreload = $loop->index < 10 ? 'auto' : 'metadata';
                                    @endphp
                                    <div class="relative aspect-video max-h-32 bg-gray-950 max-w-full min-w-0 overflow-hidden">
                                        @include('admin.ingest.partials.ingest-video-preview', [
                                            'f' => $f,
                                            'controls' => true,
                                            'preload' => $ingestCardVideoPreload,
                                            'mediaClass' => 'h-full w-full max-w-full object-contain max-h-32',
                                        ])
                                    </div>
                                @endif

                                @if ($isTodayCard)
                                    <span class="absolute left-2 top-2 rounded bg-sky-600/90 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">Heute</span>
                                @endif
                            </div>

                            <div class="flex flex-1 flex-col gap-1 p-2">
                                <p class="text-[11px] font-semibold text-gray-900 line-clamp-2 break-all leading-snug" title="{{ $f->original_name }}">
                                    {{ $f->original_name }}
                                </p>

                                <div class="flex flex-wrap gap-1 text-[10px]">
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-gray-800">#{{ $f->id }}</span>

                                    @php
                                        $cardStatus = __('ingest.file_status.'.$f->status);
                                        if ($cardStatus === 'ingest.file_status.'.$f->status) {
                                            $cardStatus = (string) $f->status;
                                        }
                                    @endphp

                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-gray-800">{{ $cardStatus }}</span>
                                </div>

                                <dl class="grid grid-cols-2 gap-x-1 gap-y-0.5 text-[10px] text-gray-600">
                                    <div>
                                        <dt class="text-gray-400">Dauer</dt>
                                        <dd class="font-medium text-gray-800">
                                            {{ $f->duration_s ? number_format((float) $f->duration_s, 1, ',', '').' s' : '—' }}
                                        </dd>
                                    </div>

                                    <div>
                                        <dt class="text-gray-400">Eingang</dt>
                                        <dd class="font-medium text-gray-800">
                                            @if ($f->created_at)
                                                {{ $f->created_at->format('d.m.Y H:i') }}
                                            @else
                                                —
                                            @endif
                                        </dd>
                                    </div>

                                </dl>

                                @if ($ingestCanQuickAssign)
                                    <form method="post" action="{{ route('admin.ingest.assign', $f) }}" class="mt-1 space-y-1 border-t border-gray-100 pt-1.5">
                                        @csrf
                                        <input type="hidden" name="_ingest_return" value="{{ ($ingestListOnly ?? false) ? 'entries' : 'index' }}">
                                        <div>
                                            <label class="block text-[9px] font-medium text-gray-500" for="ingest-news-assign-{{ $f->id }}-card">Meldung (ID)</label>
                                            @include('admin.ingest.partials.ingest-news-assign-input', [
                                                'f' => $f,
                                                'suffix' => 'card',
                                                'inputClass' => 'mt-0.5 block w-full rounded-md border-gray-300 text-[10px] leading-tight py-0.5',
                                            ])
                                        </div>
                                        <button type="submit" class="inline-flex w-full items-center justify-center min-h-[2rem] rounded-md bg-[#092E48] px-2 py-1 text-[10px] font-medium text-white hover:bg-[#0b3858]">
                                            Speichern
                                        </button>
                                    </form>
                                @elseif ($ingestAssignLocked)
                                    <p class="mt-1 border-t border-gray-100 pt-1 text-[9px] text-amber-800 leading-tight">Zuordnung gesperrt (Render aktiv).</p>
                                @else
                                    <p class="mt-1 border-t border-gray-100 pt-1 text-[9px] text-gray-500 leading-tight">Nach Validierung zuordenbar.</p>
                                @endif

                                <div class="mt-auto pt-0.5 flex flex-col gap-1">
                                    <a href="{{ route('admin.ingest.show', $f) }}" class="inline-flex w-full items-center justify-center min-h-[2rem] rounded border border-gray-300 bg-white px-2 py-1 text-[10px] font-medium text-gray-800 hover:bg-gray-50">
                                        Details
                                    </a>
                                    @if ($f->isIngestImageFile() && ! in_array($f->status, ['used_in_render', 'rendering', 'preview_generating'], true))
                                        <form method="POST" action="{{ route('admin.ingest.direct-marketing', $f) }}" class="w-full"
                                            onsubmit="return window.confirm('Bild direkt in die Vermarktung übernehmen?')">
                                            @csrf
                                            <button type="submit" class="inline-flex w-full items-center justify-center min-h-[2rem] rounded border border-emerald-200 bg-emerald-50 px-2 py-1 text-[10px] font-medium text-emerald-800 hover:bg-emerald-100">
                                                Direktvermarktung
                                            </button>
                                        </form>
                                    @endif
                                    @if (! in_array($f->status, ['used_in_render', 'rendering', 'preview_generating'], true))
                                        <form method="POST" action="{{ route('admin.ingest.destroy', $f) }}" class="w-full"
                                            onsubmit="return window.adminConfirmDelete(this)"
                                            data-delete-prompt="Eintrag #{{ $f->id }} wirklich löschen? Geben Sie zur Bestätigung „ja“ ein.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex w-full items-center justify-center min-h-[2rem] rounded border border-red-200 bg-red-50 px-2 py-1 text-[10px] font-medium text-red-800 hover:bg-red-100">
                                                Löschen
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                        </div>
                    @endif
                @endforeach
                @if ($files->isNotEmpty())
                    <div class="mt-6 overflow-x-auto min-w-0 pb-1 [scrollbar-width:thin]">
                        <div class="flex justify-center sm:justify-end min-w-0">
                            {{ $files->links() }}
                        </div>
                    </div>
                @endif
            </x-admin.section>
        @endif

        <x-admin.section
            id="ingest-tabelle"
            :title="($ingestListOnly ?? false) ? 'Alle Einträge' : 'Tabellenübersicht'"
            :subtitle="($ingestListOnly ?? false) ? 'Tabellarische Übersicht mit Inline-Zuordnung.' : 'Clips dieser Seite (inkl. nicht abspielbarer Formate); Zuordnung wie in der Arbeitsansicht. Technik: Link Details.'"
            :collapsible="! ($ingestListOnly ?? false)"
            :expanded="false"
        >
            @if ($files->isEmpty())
                <div class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-4 py-8 text-sm text-gray-500 text-center break-words">
                    @if (($filterActive ?? false) && ($ingestTotalInDb ?? 0) > 0)
                        Keine Einträge für die aktuellen Filter (siehe Hinweis oben).
                    @elseif (($ingestTotalInDb ?? 0) === 0)
                        Keine Einträge – es sind noch keine Clips in der Datenbank.
                    @else
                        Keine Einträge auf dieser Seite.
                    @endif
                </div>
            @else
                {{-- Mobil: Karten (Status, Datei, Eingang, Zuordnung, Aktion zuerst lesbar) --}}
                <div class="md:hidden space-y-4 min-w-0">
                    @foreach ($files as $f)
                        @php
                            $isToday = false;
                            if ($f->created_at) {
                                try {
                                    $isToday = $f->created_at->format('Y-m-d') === now()->toDateString();
                                } catch (\Throwable $e) {
                                    $isToday = false;
                                }
                            }
                            $rowIngestCanQuickAssign = in_array($f->status, ['validated', 'preview_generating', 'preview_ready', 'assigned'], true);
                            $rowIngestAssignLocked = in_array($f->status, ['used_in_render', 'rendering'], true);
                            $__rowStatus = __('ingest.file_status.'.$f->status);
                            if ($__rowStatus === 'ingest.file_status.'.$f->status) {
                                $__rowStatus = (string) $f->status;
                            }
                            $__statusBad = in_array($f->status, ['rejected', 'failed'], true);
                        @endphp
                        <article class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm space-y-3 min-w-0 max-w-full {{ $isToday ? 'ring-1 ring-sky-200 bg-sky-50/40' : '' }}">
                            <div class="flex flex-wrap items-center gap-3 min-w-0 border-b border-gray-100 pb-2">
                                @if ($ingestBulkRowSelectable($f))
                                    <label class="inline-flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                                        <input type="checkbox" name="ids[]" value="{{ $f->id }}" form="ingest-bulk-form" class="ingest-bulk-cb rounded border-gray-300">
                                        <span>Markieren</span>
                                    </label>
                                @endif
                            </div>
                            <div class="flex flex-wrap items-start justify-between gap-2 min-w-0">
                                <div class="flex flex-wrap items-center gap-2 min-w-0">
                                    <span class="font-mono text-sm font-semibold text-gray-900">#{{ $f->id }}</span>
                                    @if ($isToday)
                                        <span class="inline-flex rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide bg-sky-200 text-sky-950">Heute</span>
                                    @endif
                                </div>
                                <span class="inline-flex shrink-0 rounded-full px-2.5 py-1 text-xs font-medium {{ $__statusBad ? 'bg-red-100 text-red-900' : 'bg-gray-100 text-gray-800' }}">{{ $__rowStatus }}</span>
                            </div>
                            <div class="flex items-start">
                                @include('admin.ingest.partials.ingest-row-thumb', ['f' => $f])
                            </div>
                            @if ($__statusBad)
                                <p class="text-[11px] text-red-700">Details für Hinweis</p>
                            @endif
                            <p class="text-sm font-medium text-gray-900 break-all">{{ $f->original_name }}</p>
                            <p class="text-xs text-gray-500">
                                Eingang:
                                @if ($f->created_at)
                                    {{ $f->created_at->format('d.m.Y H:i') }}
                                @else
                                    —
                                @endif
                            </p>
                            <div class="border-t border-gray-100 pt-3 space-y-2">
                                @if ($rowIngestCanQuickAssign)
                                    <form method="post" action="{{ route('admin.ingest.assign', $f) }}" class="space-y-2">
                                        @csrf
                                        <input type="hidden" name="_ingest_return" value="{{ ($ingestListOnly ?? false) ? 'entries' : 'index' }}">
                                        <div>
                                            <label class="block text-[11px] font-medium text-gray-500" for="ingest-news-assign-{{ $f->id }}-m">Nachricht (Meldungs-ID)</label>
                                            @include('admin.ingest.partials.ingest-news-assign-input', [
                                                'f' => $f,
                                                'suffix' => 'm',
                                                'inputClass' => 'mt-1 block w-full min-w-0 rounded-md border-gray-300 text-sm',
                                            ])
                                        </div>
                                        <button type="submit" class="w-full min-h-[2.75rem] inline-flex justify-center items-center rounded-lg bg-[#092E48] px-3 py-2 text-sm font-medium text-white hover:bg-[#0b3858]">Speichern</button>
                                    </form>
                                @elseif ($rowIngestAssignLocked)
                                    <p class="text-sm text-amber-800">Zuordnung gesperrt (Render aktiv).</p>
                                @else
                                    <p class="text-sm text-gray-500">Zuordnung nach Validierung – oder in der Detailansicht.</p>
                                @endif
                            </div>
                            <div class="flex flex-col gap-2">
                                <a href="{{ route('admin.ingest.show', $f) }}" class="inline-flex w-full justify-center items-center min-h-[2.75rem] rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-[#092E48] hover:bg-gray-50">
                                    Details
                                </a>
                                @if ($f->isIngestImageFile() && ! in_array($f->status, ['used_in_render', 'rendering', 'preview_generating'], true))
                                    <form method="POST" action="{{ route('admin.ingest.direct-marketing', $f) }}" class="w-full"
                                        onsubmit="return window.confirm('Bild direkt in die Vermarktung übernehmen?')">
                                        @csrf
                                        <button type="submit" class="inline-flex w-full justify-center items-center min-h-[2.75rem] rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800 hover:bg-emerald-100">
                                            Direktvermarktung
                                        </button>
                                    </form>
                                @endif
                                @if (! in_array($f->status, ['used_in_render', 'rendering', 'preview_generating'], true))
                                    <form method="POST" action="{{ route('admin.ingest.destroy', $f) }}" class="w-full"
                                        onsubmit="return window.adminConfirmDelete(this)"
                                        data-delete-prompt="Eintrag #{{ $f->id }} wirklich löschen? Geben Sie zur Bestätigung „ja“ ein.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex w-full justify-center items-center min-h-[2.75rem] rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-800 hover:bg-red-100">
                                            Löschen
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>

                {{-- Desktop / Tablet: Tabelle (horizontal scrollbar im Block) --}}
                <div class="hidden md:block overflow-x-auto min-w-0 max-w-full [scrollbar-width:thin] touch-pan-x">
                    <table class="min-w-[48rem] w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase w-10">
                                    <label class="inline-flex items-center gap-1 cursor-pointer" title="Alle auf dieser Seite">
                                        <input type="checkbox" class="ingest-bulk-select-all rounded border-gray-300" id="ingest-bulk-select-all" aria-label="Alle auf dieser Seite markieren">
                                    </label>
                                </th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Datei</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Eingang</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase min-w-[12rem]">Zuordnung</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Aktion</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach ($files as $f)
                                @php
                                    $isToday = false;
                                    if ($f->created_at) {
                                        try {
                                            $isToday = $f->created_at->format('Y-m-d') === now()->toDateString();
                                        } catch (\Throwable $e) {
                                            $isToday = false;
                                        }
                                    }

                                    $rowClass = $isToday ? 'bg-sky-50/90' : '';
                                    $rowIngestCanQuickAssign = in_array($f->status, ['validated', 'preview_generating', 'preview_ready', 'assigned'], true);
                                    $rowIngestAssignLocked = in_array($f->status, ['used_in_render', 'rendering'], true);
                                @endphp

                                <tr id="ingest-clip-{{ $f->id }}" class="{{ $rowClass }} scroll-mt-24">
                                    <td class="px-2 py-2 align-top">
                                        @if ($ingestBulkRowSelectable($f))
                                            <input type="checkbox" name="ids[]" value="{{ $f->id }}" form="ingest-bulk-form" class="ingest-bulk-cb rounded border-gray-300" aria-label="Eintrag {{ $f->id }} markieren">
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-sm text-gray-900 align-top">
                                        <div class="flex flex-col items-start gap-1.5">
                                            <div>
                                                <span class="whitespace-nowrap">#{{ $f->id }}</span>
                                                @if ($isToday)
                                                    <span class="ml-1 inline-flex rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide bg-sky-200 text-sky-950">Heute</span>
                                                @endif
                                            </div>
                                            @include('admin.ingest.partials.ingest-row-thumb', ['f' => $f])
                                        </div>
                                    </td>

                                    <td class="px-3 py-2 text-sm text-gray-800 align-top break-all max-w-[14rem]">
                                        {{ $f->original_name }}
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
                                            $__statusBad = in_array($f->status, ['rejected', 'failed'], true);
                                        @endphp
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $__statusBad ? 'bg-red-100 text-red-900' : 'bg-gray-100 text-gray-800' }}">{{ $__rowStatus }}</span>
                                        @if ($__statusBad)
                                            <span class="block text-[10px] text-red-700 mt-0.5">Details für Hinweis</span>
                                        @endif
                                    </td>

                                    <td class="px-3 py-2 text-xs align-top text-gray-800 max-w-[16rem]">
                                        @if ($rowIngestCanQuickAssign)
                                            <form method="post" action="{{ route('admin.ingest.assign', $f) }}" class="space-y-1.5">
                                                @csrf
                                                <input type="hidden" name="_ingest_return" value="{{ ($ingestListOnly ?? false) ? 'entries' : 'index' }}">
                                                @include('admin.ingest.partials.ingest-news-assign-input', [
                                                    'f' => $f,
                                                    'suffix' => 'd',
                                                    'inputClass' => 'block w-full max-w-full min-w-0 md:max-w-[15rem] rounded-md border-gray-300 text-xs',
                                                ])
                                                <button type="submit" class="inline-flex min-h-[2.75rem] items-center justify-center rounded-md bg-[#092E48] px-3 py-1.5 text-xs font-medium text-white hover:bg-[#0b3858]">Speichern</button>
                                            </form>
                                        @elseif ($rowIngestAssignLocked)
                                            <span class="text-amber-800">gesperrt</span>
                                        @else
                                            <span class="text-gray-400">nach Validierung</span>
                                        @endif
                                    </td>

                                    <td class="px-3 py-2 text-sm text-right align-top">
                                        <div class="flex flex-col items-end gap-1">
                                            <a href="{{ route('admin.ingest.show', $f) }}" class="inline-flex min-h-[2.75rem] items-center justify-end px-2 font-medium text-[#092E48] hover:underline">Details</a>
                                            @if ($f->isIngestImageFile() && ! in_array($f->status, ['used_in_render', 'rendering', 'preview_generating'], true))
                                                <form method="POST" action="{{ route('admin.ingest.direct-marketing', $f) }}" class="inline"
                                                    onsubmit="return window.confirm('Bild direkt in die Vermarktung übernehmen?')">
                                                    @csrf
                                                    <button type="submit" class="text-xs font-medium text-emerald-700 hover:underline">Direktvermarktung</button>
                                                </form>
                                            @endif
                                            @if (! in_array($f->status, ['used_in_render', 'rendering', 'preview_generating'], true))
                                                <form method="POST" action="{{ route('admin.ingest.destroy', $f) }}" class="inline"
                                                    onsubmit="return window.adminConfirmDelete(this)"
                                                    data-delete-prompt="Eintrag #{{ $f->id }} wirklich löschen? Geben Sie zur Bestätigung „ja“ ein.">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-xs font-medium text-red-700 hover:underline">Löschen</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($files->isNotEmpty())
                <div class="mt-6 overflow-x-auto min-w-0 pb-1 [scrollbar-width:thin]">
                    <div class="flex justify-center sm:justify-end min-w-0">
                        {{ $files->links() }}
                    </div>
                </div>
            @endif
        </x-admin.section>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var form = document.getElementById('ingest-bulk-form');
            if (!form) return;

            document.querySelectorAll('.ingest-bulk-select-all').forEach(function (master) {
                master.addEventListener('change', function () {
                    var on = master.checked;
                    document.querySelectorAll('input.ingest-bulk-cb').forEach(function (cb) {
                        cb.checked = on;
                    });
                    document.querySelectorAll('.ingest-bulk-select-all').forEach(function (m) {
                        m.checked = on;
                    });
                });
            });

            window.ingestBulkAssignPrep = function (f) {
                if (!f) return false;
                var n = document.querySelectorAll('input.ingest-bulk-cb:checked').length;
                if (n === 0) {
                    alert('Bitte mindestens einen Eintrag auswählen.');
                    return false;
                }
                var news = document.getElementById('ingest-bulk-news');
                if (!news || !news.value) {
                    alert('Bitte eine Meldung für die Zuordnung wählen.');
                    return false;
                }
                return true;
            };

            window.ingestBulkRenderPrep = function (f) {
                if (!f) return false;
                var n = document.querySelectorAll('input.ingest-bulk-cb:checked').length;
                if (n === 0) {
                    alert('Bitte mindestens einen Video-Clip auswählen (Mehrfachauswahl).');
                    return false;
                }
                var news = document.getElementById('ingest-bulk-news');
                if (!news || !news.value) {
                    alert('Bitte im Feld „Gemeinsame Meldung“ die Meldung wählen, für die der Finalrender erzeugt werden soll.');
                    return false;
                }
                return true;
            };

            form.addEventListener('submit', function (e) {
                var btn = e.submitter;
                if (!btn || btn.getAttribute('name') !== 'action' || btn.value !== 'delete') {
                    return;
                }
                var n = document.querySelectorAll('input.ingest-bulk-cb:checked').length;
                if (n === 0) {
                    e.preventDefault();
                    alert('Bitte mindestens einen Eintrag auswählen.');
                    return;
                }
                form.setAttribute('data-delete-prompt', n + ' Einträge wirklich löschen? Geben Sie zur Bestätigung „ja“ ein.');
                if (typeof window.adminConfirmDelete === 'function' && !window.adminConfirmDelete(form)) {
                    e.preventDefault();
                }
            });

            window.ingestEmergencyPurgeConfirm = function () {
                return window.confirm('Notfall-Löschung wirklich starten? Dieser Vorgang entfernt den kompletten Ingest-Bestand.');
            };
        })();
    </script>
@endpush