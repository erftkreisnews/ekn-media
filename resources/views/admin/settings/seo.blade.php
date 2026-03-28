@extends('layouts.admin')

@section('content')
    <div class="space-y-6">
        <div>
            <a href="{{ route('admin.settings.index') }}" class="text-sm text-gray-600 hover:text-[#092E48] mb-2 inline-block">← Einstellungen</a>
            <h1 class="text-2xl font-semibold text-gray-900">SEO-Verwaltung</h1>
            <p class="mt-1 text-sm text-gray-600">
                Wichtige URLs für Suchmaschinen und die Google Search Console. Sitemaps und robots.txt werden dynamisch ausgeliefert.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- URLs zum Kopieren / Prüfen --}}
            <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
                <div class="p-4">
                    <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide mb-3">Wichtige SEO-URLs</h2>
                    <p class="text-sm text-gray-600 mb-4">
                        Diese Adressen in der <a href="{{ $searchConsoleUrl }}" target="_blank" rel="noopener noreferrer" class="text-[#092E48] hover:underline">Google Search Console</a> unter „Sitemaps“ eintragen bzw. zum Prüfen verwenden.
                    </p>
                    <ul class="space-y-3 text-sm">
                        <li class="flex flex-col sm:flex-row sm:items-center gap-2">
                            <span class="font-medium text-gray-700 sm:w-32">robots.txt</span>
                            <a href="{{ $robotsUrl }}" target="_blank" rel="noopener noreferrer" class="text-[#092E48] hover:underline break-all">{{ $robotsUrl }}</a>
                        </li>
                        <li class="flex flex-col sm:flex-row sm:items-center gap-2">
                            <span class="font-medium text-gray-700 sm:w-32">Sitemap</span>
                            <a href="{{ $sitemapUrl }}" target="_blank" rel="noopener noreferrer" class="text-[#092E48] hover:underline break-all">{{ $sitemapUrl }}</a>
                        </li>
                        <li class="flex flex-col sm:flex-row sm:items-center gap-2">
                            <span class="font-medium text-gray-700 sm:w-32">News-Sitemap</span>
                            <a href="{{ $sitemapNewsUrl }}" target="_blank" rel="noopener noreferrer" class="text-[#092E48] hover:underline break-all">{{ $sitemapNewsUrl }}</a>
                        </li>
                    </ul>
                    <p class="mt-4 text-xs text-gray-500">
                        Sitemap enthält die Startseite und alle veröffentlichten Artikel. Die News-Sitemap enthält nur Meldungen der letzten 48 Stunden (Google-News-Format).
                    </p>
                </div>
            </div>

            {{-- Google Search Console --}}
            <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
                <div class="p-4">
                    <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide mb-3">Google Search Console</h2>
                    <p class="text-sm text-gray-600 mb-4">
                        Eigenschaft <strong>{{ $baseUrl }}</strong> anlegen, Besitz bestätigen und die Sitemap-URL eintragen:
                    </p>
                    <ol class="list-decimal list-inside space-y-2 text-sm text-gray-700">
                        <li><a href="{{ $searchConsoleUrl }}" target="_blank" rel="noopener noreferrer" class="text-[#092E48] hover:underline">Search Console öffnen</a></li>
                        <li>Eigenschaft hinzufügen (URL-Präfix: {{ $baseUrl }})</li>
                        <li>Besitz per HTML-Datei oder DNS bestätigen</li>
                        <li>Unter „Sitemaps“ eintragen: <code class="bg-gray-100 px-1 rounded text-xs">sitemap.xml</code></li>
                        <li>Optional „URL prüfen“ → „Indizierung beantragen“ für die Startseite</li>
                    </ol>
                    <div class="mt-4">
                        <a href="{{ $searchConsoleUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]">
                            Google Search Console öffnen
                        </a>
                    </div>
                </div>
            </div>

            {{-- Hinweise --}}
            <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200 lg:col-span-2">
                <div class="p-4">
                    <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide mb-3">Technische Hinweise</h2>
                    <ul class="space-y-2 text-sm text-gray-700">
                        <li><strong>Title &amp; Meta-Description:</strong> Werden im Frontend (Startseite, Artikel) aus den Views gesetzt. Titel der Startseite: „Erftkreis News Media – Bild- und Videomaterial für Redaktionen“ (Trennung zur .de: Nachrichtenportal = erftkreis-news.de, Medienportal = .media).</li>
                        <li><strong>GZIP-Kompression:</strong> Muss am Webserver (Nginx/Apache) aktiviert werden. Anleitung liegt in <code class="bg-gray-100 px-1 rounded text-xs">app/seo/GZIP-HINWEIS.md</code>.</li>
                        <li><strong>Structured Data (JSON-LD):</strong> WebSite auf der Startseite, NewsArticle auf Artikelseiten inkl. areaServed (Köln, Bonn, Rhein-Erft-Kreis).</li>
                        <li><strong>AI-Crawler:</strong> In der robots.txt sind GPTBot, Google-Extended, Claude-Web u.&#8239;a. für öffentliche Seiten erlaubt.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
