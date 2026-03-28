@extends('layouts.admin')

@section('content')
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 p-4 border border-green-200">
                <p class="text-sm text-green-800">{{ session('status') }}</p>
            </div>
        @endif

        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Einstellungen</h1>
            <p class="mt-2 text-sm text-gray-600">
                System- und Benutzer-Einstellungen.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
                <div class="p-4">
                    <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">SEO</h2>
                    <p class="mt-2 text-gray-900 font-semibold">
                        Sitemaps, robots.txt &amp; Suchmaschinen
                    </p>
                    <p class="mt-1 text-sm text-gray-600">
                        URLs für Google Search Console, Sitemap- und robots.txt-Prüfung, Hinweise zu GZIP und Indexierung.
                    </p>
                    <div class="mt-4">
                        <a
                            href="{{ route('admin.settings.seo') }}"
                            class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]"
                        >
                            Zur SEO-Verwaltung
                        </a>
                    </div>
                </div>
            </div>
            <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
                <div class="p-4">
                    <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Backup</h2>
                    <p class="mt-2 text-gray-900 font-semibold">
                        Sicherungen verwalten
                    </p>
                    <p class="mt-1 text-sm text-gray-600">
                        Datenbank und Dateien sichern, Backups anzeigen. Automatische Backups laufen täglich um 02:00 Uhr.
                    </p>
                    <div class="mt-4">
                        <a
                            href="{{ route('admin.settings.backup') }}"
                            class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]"
                        >
                            Zur Backup-Verwaltung
                        </a>
                    </div>
                </div>
            </div>
            <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
                <div class="p-4">
                    <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">KI / ChatGPT</h2>
                    <p class="mt-2 text-gray-900 font-semibold">
                        OpenAI-Anbindung
                    </p>
                    <p class="mt-1 text-sm text-gray-600">
                        Verbindungsstatus, Konfiguration und Test für automatische Bild-Metadaten (Vision).
                    </p>
                    <div class="mt-4">
                        <a
                            href="{{ route('admin.settings.ai') }}"
                            class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]"
                        >
                            Zu KI / ChatGPT
                        </a>
                    </div>
                </div>
            </div>
            <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
                <div class="p-4">
                    <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Bilder / Upload</h2>
                    <p class="mt-2 text-gray-900 font-semibold">
                        Master-Größe und Upload-Limits
                    </p>
                    <p class="mt-1 text-sm text-gray-600">
                        Max. Bildgröße 8064×6048 px, max. Dateigröße, Mindestlänge. Nur Anzeige; Änderung über .env / config.
                    </p>
                    <div class="mt-4">
                        <a
                            href="{{ route('admin.settings.media') }}"
                            class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]"
                        >
                            Zu Bilder / Upload
                        </a>
                    </div>
                </div>
            </div>
            <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
                <div class="p-4">
                    <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Jobs / Warteschlange</h2>
                    <p class="mt-2 text-gray-900 font-semibold">
                        KI, Videoanalyse &amp; FTP manuell anstoßen
                    </p>
                    <p class="mt-1 text-sm text-gray-600">
                        Falls sich Jobs aufgehängt haben, können hier KI-Auswertungen, Video-Analysen und FTP-Uploads manuell neu gestartet werden.
                    </p>
                    <div class="mt-4">
                        <a
                            href="{{ route('admin.settings.jobs') }}"
                            class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]"
                        >
                            Zur Job-Steuerung
                        </a>
                    </div>
                </div>
            </div>
            <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
                <div class="p-4">
                    <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Backoffice</h2>
                    <p class="mt-2 text-gray-900 font-semibold">
                        Nutzer, Abrechnung &amp; Nachverfolgung
                    </p>
                    <p class="mt-1 text-sm text-gray-600">
                        Zugriff auf den internen Backoffice‑Bereich (z.&nbsp;B. Benutzerverwaltung, Kunden‑Abrechnung, Reports).
                    </p>
                    <div class="mt-4">
                        <a
                            href="{{ route('admin.backoffice.index') }}"
                            class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]"
                        >
                            Backoffice öffnen
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
