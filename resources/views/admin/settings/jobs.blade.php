@extends('layouts.admin')

@section('content')
    <div class="space-y-6 min-w-0 max-w-full w-full">
        @if (session('status'))
            <div class="rounded-md bg-green-50 p-4 border border-green-200">
                <p class="text-sm text-green-800 break-words">{{ session('status') }}</p>
            </div>
        @endif
        @if (session('error'))
            <div class="rounded-md bg-red-50 p-4 border border-red-200">
                <p class="text-sm text-red-800 break-words">{{ session('error') }}</p>
            </div>
        @endif

        <div class="min-w-0">
            <a href="{{ route('admin.settings.index') }}" class="inline-flex items-center min-h-[2.75rem] text-sm font-medium text-gray-600 hover:text-[#092E48] mb-1 rounded-lg px-1 -ml-1 hover:bg-gray-100">← Einstellungen</a>
            <h1 class="text-xl sm:text-2xl font-semibold text-gray-900 break-words">Jobs / Warteschlange</h1>
            <p class="mt-1 text-sm text-gray-600 break-words">
                Falls sich Jobs aufgehängt haben, können hier KI-Auswertungen, Video-Analysen und FTP-Uploads manuell neu gestartet werden.
            </p>
            @if ($cronOk && $queueWorkerOk)
                <p class="mt-3 text-sm font-medium {{ !empty($queueWorkerBusy) ? 'text-amber-900 bg-amber-50 border-amber-200' : 'text-emerald-900 bg-emerald-50 border-emerald-200' }} border rounded-xl px-3 py-2.5 break-words" role="status">
                    @if (!empty($queueWorkerBusy))
                        <span class="font-semibold">Hinweis:</span> Queue-Worker bearbeitet gerade einen langen Job (z. B. Ingest-Sendefassung). Das ist normal — der Heartbeat auf der Standard-Queue kann dabei kurz pausieren.
                    @else
                        <span class="font-semibold">Gesamt:</span> Cron und Queue-Worker melden zuletzt ein aktives Signal (innerhalb der letzten 2 Minuten).
                    @endif
                </p>
            @else
                <p class="mt-3 text-sm font-medium text-rose-900 bg-rose-50 border border-rose-200 rounded-xl px-3 py-2.5 break-words" role="alert">
                    <span class="font-semibold">Achtung:</span> Cron und/oder Queue-Worker zeigen kein aktuelles Signal — bitte Heartbeat-Zeiten prüfen.
                </p>
            @endif
        </div>

        {{-- Ampel: Cron und Queue-Worker --}}
        <div class="bg-white rounded-2xl ring-1 ring-slate-200 overflow-hidden shadow-sm border-l-4 {{ ($cronOk && $queueWorkerOk) ? 'border-l-emerald-500' : 'border-l-amber-500' }} min-w-0 max-w-full">
            <div class="px-4 sm:px-6 py-4 border-b border-gray-200 bg-gray-50/80">
                <h2 class="text-sm font-semibold tracking-wide text-gray-900 uppercase">Status: Cron &amp; Queue-Worker</h2>
                <p class="mt-1 text-xs text-gray-500 break-words">Grün = zuletzt innerhalb von 2 Minuten aktiv. Rot = kein Signal oder älter als 2 Minuten.</p>
                <p class="mt-1 text-xs text-gray-500 break-words">Zeiten unten und in den Tabellen: <strong class="font-medium text-gray-600">Anzeige in {{ $jobsDisplayTimezone ?? config('app.timezone', 'Europe/Berlin') }}</strong> (APP_TIMEZONE).</p>
            </div>
            <div class="px-4 sm:px-6 py-4 grid grid-cols-1 sm:grid-cols-2 gap-6 sm:gap-8">
                <div class="flex items-start gap-3 min-w-0">
                    <span class="relative flex h-8 w-8 sm:h-7 sm:w-7 shrink-0 rounded-full shadow-md ring-2 ring-white mt-0.5" style="background-color: {{ $cronOk ? '#16a34a' : '#dc2626' }};" aria-hidden="true" title="{{ $cronOk ? 'Cron aktiv' : 'Cron inaktiv' }}"></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-gray-900">Cron / Zeitplaner</p>
                        <p class="mt-0.5 text-xs text-gray-600 break-words">
                            @if($cronLastAt)
                                <span class="text-gray-500">Zuletzt:</span> {{ $cronLastAt->format('d.m.Y H:i:s') }}
                                <span class="font-medium {{ $cronOk ? 'text-emerald-700' : 'text-rose-700' }}">{{ $cronOk ? '· OK' : '· zu lange her' }}</span>
                            @else
                                <span class="text-amber-800 font-medium">Noch kein Heartbeat</span>
                            @endif
                        </p>
                    </div>
                </div>
                <div class="flex items-start gap-3 min-w-0">
                    @php
                        $queueWorkerDot = '#dc2626';
                        if ($queueWorkerOk) {
                            $queueWorkerDot = !empty($queueWorkerBusy) ? '#d97706' : '#16a34a';
                        }
                    @endphp
                    <span class="relative flex h-8 w-8 sm:h-7 sm:w-7 shrink-0 rounded-full shadow-md ring-2 ring-white mt-0.5" style="background-color: {{ $queueWorkerDot }};" aria-hidden="true" title="{{ $queueWorkerOk ? (!empty($queueWorkerBusy) ? 'Worker beschäftigt' : 'Queue-Worker aktiv') : 'Queue-Worker inaktiv' }}"></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-gray-900">Queue-Worker</p>
                        <p class="mt-0.5 text-xs text-gray-600 break-words">
                            @if (!empty($queueWorkerBusy))
                                <span class="text-amber-800 font-medium">Beschäftigt (langer Job, z. B. Render)</span>
                            @elseif($queueWorkerLastAt)
                                <span class="text-gray-500">Zuletzt:</span> {{ $queueWorkerLastAt->format('d.m.Y H:i:s') }}
                                <span class="font-medium {{ $queueWorkerOk ? 'text-emerald-700' : 'text-rose-700' }}">{{ $queueWorkerOk ? '· OK' : '· zu lange her' }}</span>
                            @else
                                <span class="text-amber-800 font-medium">Noch kein Heartbeat</span>
                            @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="px-4 sm:px-6 pb-4 text-xs text-gray-500 border-t border-gray-100 pt-3 mt-0 space-y-2 break-words">
                <p><strong class="text-gray-700">Cron:</strong> Läuft der Server-Cron (z. B. <code class="break-all rounded bg-gray-100 px-1 text-[11px]">php artisan queue:heartbeat</code> oder <code class="break-all rounded bg-gray-100 px-1 text-[11px]">php artisan schedule:run</code> jede Minute), wird hier ein Zeitstempel gesetzt.</p>
                <p><strong class="text-gray-700">Queue-Worker:</strong> Cron führt <code class="break-all rounded bg-gray-100 px-1 text-[11px]">queue:worker-heartbeat</code> aus (eigene Queue <code class="break-all rounded bg-gray-100 px-1 text-[11px]">heartbeat</code>). Lange Ingest-Renders auf <code class="break-all rounded bg-gray-100 px-1 text-[11px]">default</code> blockieren den Heartbeat nicht mehr. Bei aktivem Render ohne frischen Heartbeat: Ampel gelb = Worker beschäftigt.</p>
            </div>
        </div>

        {{-- Datenbank: offene + fehlgeschlagene Queue-Jobs --}}
        <div class="bg-white rounded-2xl ring-1 ring-slate-200 overflow-hidden shadow-sm border-l-4 border-l-slate-400 min-w-0 max-w-full">
            <div class="px-4 sm:px-6 py-4 border-b border-gray-200 bg-gray-50/80">
                <h2 class="text-sm font-semibold tracking-wide text-gray-900 uppercase">Warteschlange (Datenbank)</h2>
                <p class="mt-1 text-xs text-gray-500 break-words">
                    Verbindung: <code class="rounded bg-gray-100 px-1 text-[11px]">{{ $queueDb['connectionName'] }}</code>
                    (Treiber: <code class="rounded bg-gray-100 px-1 text-[11px]">{{ $queueDb['driver'] ?: '—' }}</code>).
                    @if (! $queueDb['usesDbPending'])
                        <span class="font-medium text-amber-800">Hinweis:</span> Kein <code class="rounded bg-gray-100 px-1 text-[11px]">database</code>-Treiber für die Standard-Queue — die Tabelle <code class="rounded bg-gray-100 px-1 text-[11px]">jobs</code> zeigt daher keine wartenden Jobs (z. B. bei Redis). Fehlgeschlagene Einträge in <code class="rounded bg-gray-100 px-1 text-[11px]">failed_jobs</code> gelten weiter.
                    @endif
                </p>
            </div>
            <div class="px-4 sm:px-6 py-4 space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="rounded-xl border border-gray-200 bg-slate-50/50 p-4 min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-600">Offen (Tabelle jobs)</p>
                        <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">{{ $queueDb['pendingTotal'] }}</p>
                        @if (count($queueDb['pendingByQueue']) > 0)
                            <ul class="mt-2 text-xs text-gray-600 space-y-0.5">
                                @foreach ($queueDb['pendingByQueue'] as $row)
                                    <li><span class="font-medium">{{ $row['queue'] }}</span>: {{ $row['c'] }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-rose-50/40 p-4 min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-600">Fehlgeschlagen (failed_jobs)</p>
                        <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">{{ $queueDb['failedTotal'] }}</p>
                        @if (count($queueDb['failedByQueue']) > 0)
                            <ul class="mt-2 text-xs text-gray-600 space-y-0.5">
                                @foreach ($queueDb['failedByQueue'] as $row)
                                    <li><span class="font-medium">{{ $row['queue'] }}</span>: {{ $row['c'] }}</li>
                                @endforeach
                            </ul>
                        @endif
                        <div class="mt-4 flex flex-col sm:flex-row gap-2 sm:gap-3 flex-wrap">
                            <form method="post" action="{{ route('admin.settings.jobs.failed.retry-all') }}" class="inline w-full sm:w-auto" onsubmit="return confirm('Alle fehlgeschlagenen Jobs erneut in die Warteschlange stellen? (Entspricht Artisan queue:retry all)');">
                                @csrf
                                <button type="submit" class="w-full min-h-[2.75rem] inline-flex justify-center items-center px-4 py-2.5 text-sm font-medium rounded-lg text-white bg-[#092E48] hover:bg-[#0a3a5c]">Alle erneut anstoßen</button>
                            </form>
                            <form method="post" action="{{ route('admin.settings.jobs.failed.flush') }}" class="inline w-full sm:w-auto" onsubmit="return confirm('Liste der fehlgeschlagenen Jobs wirklich leeren? Entspricht Artisan queue:flush — die Jobs werden nicht erneut ausgeführt, nur der Eintrag in failed_jobs entfernt.');">
                                @csrf
                                <button type="submit" class="w-full min-h-[2.75rem] inline-flex justify-center items-center px-4 py-2.5 text-sm font-medium rounded-lg border border-rose-300 bg-white text-rose-900 hover:bg-rose-50">Fehlgeschlagene bereinigen</button>
                            </form>
                        </div>
                    </div>
                </div>

                @if (count($queueDb['pendingSample']) > 0)
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-gray-700 mb-2">Offene Jobs (max. 50, älteste zuerst)</p>
                        <div class="overflow-x-auto rounded-lg border border-gray-200">
                            <table class="min-w-full text-xs text-left">
                                <thead class="bg-gray-50 text-gray-600">
                                    <tr>
                                        <th class="px-3 py-2 font-medium">ID</th>
                                        <th class="px-3 py-2 font-medium">Queue</th>
                                        <th class="px-3 py-2 font-medium">Job</th>
                                        <th class="px-3 py-2 font-medium whitespace-nowrap">Verfügbar ab</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($queueDb['pendingSample'] as $row)
                                        <tr class="bg-white">
                                            <td class="px-3 py-2 tabular-nums">{{ $row['id'] }}</td>
                                            <td class="px-3 py-2">{{ $row['queue'] }}</td>
                                            <td class="px-3 py-2 break-all font-mono text-[11px]">{{ $row['name'] }}</td>
                                            <td class="px-3 py-2 whitespace-nowrap">{{ $row['available_at']?->format('d.m.Y H:i:s') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                @if (count($queueDb['failedSample']) > 0)
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-gray-700 mb-2">Fehlgeschlagen (max. 30, neueste zuerst)</p>
                        <div class="overflow-x-auto rounded-lg border border-gray-200">
                            <table class="min-w-full text-xs text-left">
                                <thead class="bg-gray-50 text-gray-600">
                                    <tr>
                                        <th class="px-3 py-2 font-medium">Zeit</th>
                                        <th class="px-3 py-2 font-medium">Queue</th>
                                        <th class="px-3 py-2 font-medium">Job</th>
                                        <th class="px-3 py-2 font-medium">Ausschnitt</th>
                                        <th class="px-3 py-2 font-medium whitespace-nowrap">Aktion</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($queueDb['failedSample'] as $row)
                                        <tr class="bg-white align-top">
                                            <td class="px-3 py-2 whitespace-nowrap">{{ $row['failed_at']?->format('d.m.Y H:i:s') }}</td>
                                            <td class="px-3 py-2">{{ $row['queue'] }}</td>
                                            <td class="px-3 py-2 break-all font-mono text-[11px]">{{ $row['name'] }}</td>
                                            <td class="px-3 py-2 text-gray-600 break-words max-w-md">{{ $row['exception_preview'] }}</td>
                                            <td class="px-3 py-2 whitespace-nowrap">
                                                <form method="post" action="{{ route('admin.settings.jobs.failed.retry-one') }}" class="inline" onsubmit="return confirm('Diesen einen Job erneut in die Warteschlange stellen?');">
                                                    @csrf
                                                    <input type="hidden" name="uuid" value="{{ $row['uuid'] }}">
                                                    <button type="submit" class="min-h-[2.25rem] inline-flex items-center px-2.5 py-1.5 text-xs font-medium rounded-md text-[#092E48] border border-slate-300 bg-white hover:bg-slate-50">Erneut anstoßen</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:gap-6 lg:grid-cols-2 min-w-0">
            {{-- KI (Bild-Metadaten) --}}
            <div class="bg-white rounded-2xl ring-1 ring-slate-200 overflow-hidden shadow-sm min-w-0 max-w-full">
                <div class="px-4 sm:px-6 py-4 border-b border-gray-200 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between sm:gap-4 min-w-0">
                    <h2 class="text-sm font-semibold tracking-wide text-gray-900 uppercase break-words">KI / Bild-Metadaten</h2>
                    <span class="text-sm font-medium tabular-nums text-gray-600 shrink-0">{{ $countAiPending }} ausstehend</span>
                </div>
                <div class="px-4 sm:px-6 py-4">
                    <p class="text-sm text-gray-600 mb-4 break-words">
                        Bilder ohne oder mit fehlgeschlagener KI-Auswertung (Titel, Bildunterschrift, Schlagwörter) erneut in die Warteschlange stellen. Max. 100 pro Anstoß.
                    </p>
                    <form method="post" action="{{ route('admin.settings.jobs.run') }}" class="block w-full sm:inline sm:w-auto" onsubmit="return confirm('Wirklich alle ausstehenden KI-Jobs (max. 100) neu in die Warteschlange stellen?');">
                        @csrf
                        <input type="hidden" name="type" value="ai">
                        <button type="submit" class="w-full sm:w-auto min-h-[2.75rem] inline-flex justify-center items-center px-4 py-2.5 text-sm font-medium rounded-lg text-white bg-[#092E48] hover:bg-[#0a3a5c]">KI-Jobs neu anstoßen</button>
                    </form>
                </div>
            </div>

            {{-- Video-Metadaten --}}
            <div class="bg-white rounded-2xl ring-1 ring-slate-200 overflow-hidden shadow-sm min-w-0 max-w-full">
                <div class="px-4 sm:px-6 py-4 border-b border-gray-200 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between sm:gap-4 min-w-0">
                    <h2 class="text-sm font-semibold tracking-wide text-gray-900 uppercase break-words">Video-Metadaten</h2>
                    <span class="text-sm font-medium tabular-nums text-gray-600 shrink-0">{{ $countVideo }} Video(s)</span>
                </div>
                <div class="px-4 sm:px-6 py-4">
                    <p class="text-sm text-gray-600 mb-4 break-words">
                        Video-Metadaten (Dauer, Codec, Bitrate usw.) erneut extrahieren. Alle Videos werden in die Warteschlange gestellt (max. 100 pro Anstoß).
                    </p>
                    <form method="post" action="{{ route('admin.settings.jobs.run') }}" class="block w-full sm:inline sm:w-auto" onsubmit="return confirm('Wirklich alle Videos (max. 100) für Metadaten-Extraktion in die Warteschlange stellen?');">
                        @csrf
                        <input type="hidden" name="type" value="video">
                        <button type="submit" class="w-full sm:w-auto min-h-[2.75rem] inline-flex justify-center items-center px-4 py-2.5 text-sm font-medium rounded-lg text-white bg-[#092E48] hover:bg-[#0a3a5c]">Video-Jobs neu anstoßen</button>
                    </form>
                </div>
            </div>

            {{-- Audio-Metadaten --}}
            <div class="bg-white rounded-2xl ring-1 ring-slate-200 overflow-hidden shadow-sm min-w-0 max-w-full">
                <div class="px-4 sm:px-6 py-4 border-b border-gray-200 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between sm:gap-4 min-w-0">
                    <h2 class="text-sm font-semibold tracking-wide text-gray-900 uppercase break-words">Audio-Metadaten</h2>
                    <span class="text-sm font-medium tabular-nums text-gray-600 shrink-0">{{ $countAudio }} Audio(s)</span>
                </div>
                <div class="px-4 sm:px-6 py-4">
                    <p class="text-sm text-gray-600 mb-4 break-words">
                        Audio-Metadaten (Dauer, Bitrate usw.) erneut extrahieren. Alle Audios werden in die Warteschlange gestellt (max. 100 pro Anstoß).
                    </p>
                    <form method="post" action="{{ route('admin.settings.jobs.run') }}" class="block w-full sm:inline sm:w-auto" onsubmit="return confirm('Wirklich alle Audios (max. 100) für Metadaten-Extraktion in die Warteschlange stellen?');">
                        @csrf
                        <input type="hidden" name="type" value="audio">
                        <button type="submit" class="w-full sm:w-auto min-h-[2.75rem] inline-flex justify-center items-center px-4 py-2.5 text-sm font-medium rounded-lg text-white bg-[#092E48] hover:bg-[#0a3a5c]">Audio-Jobs neu anstoßen</button>
                    </form>
                </div>
            </div>

            {{-- FTP-Hinweis --}}
            <div class="bg-white rounded-2xl ring-1 ring-slate-200 overflow-hidden shadow-sm lg:col-span-2 min-w-0 max-w-full">
                <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
                    <h2 class="text-sm font-semibold tracking-wide text-gray-900 uppercase break-words">FTP-Uploads</h2>
                </div>
                <div class="px-4 sm:px-6 py-4">
                    <p class="text-sm text-gray-600 break-words">
                        FTP-Uploads werden pro Versandziel (Produkt → Ziel) angestoßen. Dazu in der Versand- und Zielverwaltung bzw. beim Versand einer Meldung den Upload erneut auslösen.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
