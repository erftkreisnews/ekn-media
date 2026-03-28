@extends('layouts.admin')

@section('content')
    <div class="space-y-6">
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

        <div>
            <a href="{{ route('admin.settings.index') }}" class="text-sm text-gray-600 hover:text-[#092E48] mb-2 inline-block">← Einstellungen</a>
            <h1 class="text-2xl font-semibold text-gray-900">Jobs / Warteschlange</h1>
            <p class="mt-1 text-sm text-gray-600">
                Falls sich Jobs aufgehängt haben, können hier KI-Auswertungen, Video-Analysen und FTP-Uploads manuell neu gestartet werden.
            </p>
        </div>

        {{-- Ampel: Cron und Queue-Worker --}}
        <div class="bg-white rounded-2xl ring-1 ring-slate-200 overflow-hidden shadow-sm lg:col-span-2">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-sm font-semibold tracking-wide text-gray-900 uppercase">Status: Cron &amp; Queue-Worker</h2>
                <p class="mt-1 text-xs text-gray-500">Grün = zuletzt innerhalb von 2 Minuten aktiv. Rot = kein Signal oder älter als 2 Minuten.</p>
            </div>
            <div class="px-6 py-4 flex flex-wrap items-center gap-8">
                <div class="flex items-center gap-3">
                    <span class="relative flex h-6 w-6 shrink-0 rounded-full shadow-md" style="background-color: {{ $cronOk ? '#16a34a' : '#dc2626' }};" aria-hidden="true" title="{{ $cronOk ? 'Cron aktiv' : 'Cron inaktiv' }}"></span>
                    <div>
                        <p class="text-sm font-medium text-gray-900">Cron / Zeitplaner</p>
                        <p class="text-xs text-gray-500">
                            @if($cronLastAt)
                                Zuletzt: {{ $cronLastAt->format('d.m.Y H:i:s') }} {{ $cronOk ? '· OK' : '· zu lange her' }}
                            @else
                                Noch kein Heartbeat
                            @endif
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="relative flex h-6 w-6 shrink-0 rounded-full shadow-md" style="background-color: {{ $queueWorkerOk ? '#16a34a' : '#dc2626' }};" aria-hidden="true" title="{{ $queueWorkerOk ? 'Queue-Worker aktiv' : 'Queue-Worker inaktiv' }}"></span>
                    <div>
                        <p class="text-sm font-medium text-gray-900">Queue-Worker</p>
                        <p class="text-xs text-gray-500">
                            @if($queueWorkerLastAt)
                                Zuletzt: {{ $queueWorkerLastAt->format('d.m.Y H:i:s') }} {{ $queueWorkerOk ? '· OK' : '· zu lange her' }}
                            @else
                                Noch kein Heartbeat
                            @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="px-6 pb-4 text-xs text-gray-500 border-t border-gray-100 pt-3 mt-1">
                <p><strong>Cron:</strong> Läuft der Server-Cron (z. B. <code>php artisan queue:heartbeat</code> oder <code>php artisan schedule:run</code> jede Minute), wird hier ein Zeitstempel gesetzt.</p>
                <p class="mt-1"><strong>Queue-Worker:</strong> Der Scheduler stellt jede Minute einen Heartbeat-Job in die Queue. Läuft <code>queue:work</code>, wird der Job ausgeführt und der Zeitstempel aktualisiert.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- KI (Bild-Metadaten) --}}
            <div class="bg-white rounded-2xl ring-1 ring-slate-200 overflow-hidden shadow-sm">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between gap-4">
                    <h2 class="text-sm font-semibold tracking-wide text-gray-900 uppercase">KI / Bild-Metadaten</h2>
                    <span class="text-sm text-gray-500">{{ $countAiPending }} ausstehend</span>
                </div>
                <div class="px-6 py-4">
                    <p class="text-sm text-gray-600 mb-4">
                        Bilder ohne oder mit fehlgeschlagener KI-Auswertung (Titel, Bildunterschrift, Schlagwörter) erneut in die Warteschlange stellen. Max. 100 pro Anstoß.
                    </p>
                    <form method="post" action="{{ route('admin.settings.jobs.run') }}" class="inline" onsubmit="return confirm('Wirklich alle ausstehenden KI-Jobs (max. 100) neu in die Warteschlange stellen?');">
                        @csrf
                        <input type="hidden" name="type" value="ai">
                        <button type="submit" class="px-4 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0a3a5c]">KI-Jobs neu anstoßen</button>
                    </form>
                </div>
            </div>

            {{-- Video-Metadaten --}}
            <div class="bg-white rounded-2xl ring-1 ring-slate-200 overflow-hidden shadow-sm">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between gap-4">
                    <h2 class="text-sm font-semibold tracking-wide text-gray-900 uppercase">Video-Metadaten</h2>
                    <span class="text-sm text-gray-500">{{ $countVideo }} Video(s)</span>
                </div>
                <div class="px-6 py-4">
                    <p class="text-sm text-gray-600 mb-4">
                        Video-Metadaten (Dauer, Codec, Bitrate usw.) erneut extrahieren. Alle Videos werden in die Warteschlange gestellt (max. 100 pro Anstoß).
                    </p>
                    <form method="post" action="{{ route('admin.settings.jobs.run') }}" class="inline" onsubmit="return confirm('Wirklich alle Videos (max. 100) für Metadaten-Extraktion in die Warteschlange stellen?');">
                        @csrf
                        <input type="hidden" name="type" value="video">
                        <button type="submit" class="px-4 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0a3a5c]">Video-Jobs neu anstoßen</button>
                    </form>
                </div>
            </div>

            {{-- Audio-Metadaten --}}
            <div class="bg-white rounded-2xl ring-1 ring-slate-200 overflow-hidden shadow-sm">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between gap-4">
                    <h2 class="text-sm font-semibold tracking-wide text-gray-900 uppercase">Audio-Metadaten</h2>
                    <span class="text-sm text-gray-500">{{ $countAudio }} Audio(s)</span>
                </div>
                <div class="px-6 py-4">
                    <p class="text-sm text-gray-600 mb-4">
                        Audio-Metadaten (Dauer, Bitrate usw.) erneut extrahieren. Alle Audios werden in die Warteschlange gestellt (max. 100 pro Anstoß).
                    </p>
                    <form method="post" action="{{ route('admin.settings.jobs.run') }}" class="inline" onsubmit="return confirm('Wirklich alle Audios (max. 100) für Metadaten-Extraktion in die Warteschlange stellen?');">
                        @csrf
                        <input type="hidden" name="type" value="audio">
                        <button type="submit" class="px-4 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0a3a5c]">Audio-Jobs neu anstoßen</button>
                    </form>
                </div>
            </div>

            {{-- FTP-Hinweis --}}
            <div class="bg-white rounded-2xl ring-1 ring-slate-200 overflow-hidden shadow-sm lg:col-span-2">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-sm font-semibold tracking-wide text-gray-900 uppercase">FTP-Uploads</h2>
                </div>
                <div class="px-6 py-4">
                    <p class="text-sm text-gray-600">
                        FTP-Uploads werden pro Versandziel (Produkt → Ziel) angestoßen. Dazu in der Versand- und Zielverwaltung bzw. beim Versand einer Meldung den Upload erneut auslösen.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
