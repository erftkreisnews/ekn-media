@extends('layouts.admin')

@section('content')
<div class="py-6 max-w-3xl mx-auto px-4">
    <h1 class="text-2xl font-semibold text-gray-900 mb-1">Versand abgeschlossen</h1>
    <p class="text-sm text-gray-600 mb-4">{{ $newsItem->title }}</p>

    <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-6 space-y-4">
        <div>
            <h2 class="text-sm font-semibold text-gray-800 mb-1">Zusammenfassung dieses Schritts</h2>
            <p class="text-sm text-gray-700">
                @if($lastEmailCount > 0 || $lastFtpFiles > 0)
                    @if($lastEmailCount > 0)
                        In diesem Schritt wurde das Medienpaket an <strong>{{ $lastEmailCount }}</strong> E-Mail-Empfänger versendet.
                    @endif
                    @if($lastEmailCount > 0 && $lastFtpFiles > 0)
                        <br>
                    @endif
                    @if($lastFtpFiles > 0)
                        In diesem Schritt wurde ein FTP-/SFTP-Upload mit <strong>{{ $lastFtpFiles }}</strong> markierten Medien durchgeführt.
                    @endif
                @else
                    Für diese Nachricht liegen bereits Versandvorgänge vor (siehe unten).
                @endif
            </p>
        </div>

        <div class="border-t border-gray-200 pt-4">
            <h2 class="text-sm font-semibold text-gray-800 mb-1">Bisherige Versände dieser Nachricht</h2>
            <dl class="text-sm text-gray-700 space-y-1">
                <div class="flex justify-between">
                    <dt>E-Mail-Versände (Token-Mails):</dt>
                    <dd><strong>{{ $totalEmailDeliveries }}</strong></dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt>FTP-/SFTP-Läufe (Versandziele/Zähler):</dt>
                    <dd class="shrink-0"><strong>{{ $totalFtpRuns }}</strong></dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt>Medien dieser Meldung (in mindestens einem Lauf vorgesehen):</dt>
                    <dd class="shrink-0"><strong>{{ $totalFtpDistinctMedia }}</strong></dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt>Upload-Einträge gesamt (alle Läufe):</dt>
                    <dd class="shrink-0"><strong>{{ $totalFtpRunItems }}</strong></dd>
                </div>
                @if($totalFtpSuccessfulItems !== $totalFtpRunItems)
                <div class="flex justify-between gap-4">
                    <dt>Davon erfolgreich übertragen:</dt>
                    <dd class="shrink-0"><strong>{{ $totalFtpSuccessfulItems }}</strong></dd>
                </div>
                @endif
            </dl>
            <p class="mt-2 text-xs text-gray-500">
                „Upload-Einträge“ zählen pro <em>Lauf</em> und <em>Medium</em> (z.&nbsp;B. mehrere Ziele = mehrere Läufe = höhere Zahl als eindeutige Medien). Keine Zählung der Sidecar-Datei <code>delivery.meta.json</code>.
            </p>
        </div>

        <form method="POST" action="{{ route('admin.news.send-summary.apply', $newsItem) }}" class="border-t border-gray-200 pt-4 space-y-4">
            @csrf
            <div>
                <h2 class="text-sm font-semibold text-gray-800 mb-2">Sichtbarkeit im Portal</h2>
                <p class="text-xs text-gray-500 mb-3">
                    Entscheide hier, ob die Nachricht im öffentlichen Portal sichtbar sein soll. Danach gelangst du zurück zur Nachrichten-Übersicht.
                </p>
                <div class="space-y-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            type="radio"
                            name="portal_visibility"
                            value="published"
                            class="h-4 w-4 text-[#092E48] border-gray-300 focus:ring-[#092E48]"
                            @checked(old('portal_visibility', $isCurrentlyPublic ? 'published' : 'hidden') === 'published')
                        >
                        <span class="text-sm text-gray-800">Im Portal anzeigen (veröffentlichen)</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            type="radio"
                            name="portal_visibility"
                            value="hidden"
                            class="h-4 w-4 text-[#092E48] border-gray-300 focus:ring-[#092E48]"
                            @checked(old('portal_visibility', $isCurrentlyPublic ? 'published' : 'hidden') === 'hidden')
                        >
                        <span class="text-sm text-gray-800">Nicht im Portal anzeigen (Entwurf)</span>
                    </label>
                </div>
                @error('portal_visibility')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <a href="{{ route('admin.news.index') }}" class="px-4 py-2 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                    Zur Übersicht ohne Änderung
                </a>
                <button type="submit" class="px-4 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]">
                    Auswahl speichern &amp; zur Übersicht
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

