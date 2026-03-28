@extends('layouts.admin')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <a href="{{ route('admin.settings.index') }}" class="text-sm text-gray-600 hover:text-[#092E48] mb-2 inline-block">← Einstellungen</a>
                <h1 class="text-2xl font-semibold text-gray-900">Backup</h1>
                <p class="mt-1 text-sm text-gray-600">Sicherungen lokal und optional in Dropbox. Automatische Backups per Cron.</p>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex gap-6" aria-label="Tabs">
                <a href="{{ route('admin.settings.backup') }}?tab=backups"
                   class="@if (request('tab', 'backups') === 'backups') border-[#092E48] text-[#092E48] @else border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 @endif whitespace-nowrap border-b-2 py-3 px-1 text-sm font-medium">
                    Backups
                </a>
                <a href="{{ route('admin.settings.backup') }}?tab=settings"
                   class="@if (request('tab') === 'settings') border-[#092E48] text-[#092E48] @else border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 @endif whitespace-nowrap border-b-2 py-3 px-1 text-sm font-medium">
                    Einstellungen
                </a>
            </nav>
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

        @if (request('tab') === 'settings')
            {{-- Tab: Einstellungen (System, Dropbox, Zeiten) --}}
            <div class="space-y-6">
                <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
                        <h2 class="text-sm font-medium text-gray-700">Backup jetzt ausführen</h2>
                    </div>
                    <div class="p-4 flex flex-wrap gap-3">
                        <form action="{{ route('admin.settings.backup.run') }}" method="POST" class="inline">
                            @csrf
                            <input type="hidden" name="tab" value="settings">
                            <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]">
                                Volles Backup (lokal + Dropbox)
                            </button>
                        </form>
                        <form action="{{ route('admin.settings.backup.run') }}" method="POST" class="inline">
                            @csrf
                            <input type="hidden" name="tab" value="settings">
                            <input type="hidden" name="only_db" value="1">
                            <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-gray-300 text-gray-700 bg-white hover:bg-gray-50">
                                Nur Datenbank
                            </button>
                        </form>
                        <p class="text-xs text-gray-500 w-full mt-1">Backup wird im Hintergrund ausgeführt – die Seite antwortet sofort. Ein Queue-Worker muss laufen (z. B. <code class="bg-gray-100 px-1 rounded">php artisan queue:work</code> oder Supervisor).</p>
                    </div>
                </div>

                <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
                        <h2 class="text-sm font-medium text-gray-700">System &amp; Ordner</h2>
                    </div>
                    <div class="p-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <span class="text-gray-600">Aktive Backup-Disk</span>
                            <code class="text-xs bg-gray-100 px-2 py-1 rounded text-gray-800 break-all">{{ $settings['backup_disk'] ?? 'backups' }}</code>
                        </div>
                        <div class="flex justify-between gap-4">
                            <span class="text-gray-600">Lokales Backup-Verzeichnis</span>
                            <code class="text-xs bg-gray-100 px-2 py-1 rounded text-gray-800 break-all">{{ $settings['local_path'] }}</code>
                        </div>
                        <div class="flex justify-between gap-4">
                            <span class="text-gray-600">Temporärverzeichnis (ZIP-Erstellung)</span>
                            <code class="text-xs bg-gray-100 px-2 py-1 rounded text-gray-800 break-all">{{ $settings['temp_path'] }}</code>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Änderungen über <code class="bg-gray-100 px-1 rounded">config/backup.php</code> und <code class="bg-gray-100 px-1 rounded">config/filesystems.php</code> bzw. <code class="bg-gray-100 px-1 rounded">.env</code> (BACKUP_TEMP_DIR).</p>
                    </div>
                </div>

                <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
                        <h2 class="text-sm font-medium text-gray-700">Dropbox-Anbindung</h2>
                    </div>
                    <div class="p-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-4 items-center">
                            <span class="text-gray-600">Status</span>
                            @if ($settings['dropbox_configured'])
                                <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">Aktiv</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">Nicht konfiguriert</span>
                            @endif
                        </div>
                        <div class="flex justify-between gap-4">
                            <span class="text-gray-600">Unterordner auf Dropbox (Path-Prefix)</span>
                            <span class="text-gray-800">{{ $settings['dropbox_path_prefix'] }}</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Token und Prefix in <code class="bg-gray-100 px-1 rounded">.env</code>: <code class="bg-gray-100 px-1 rounded">DROPBOX_AUTH_TOKEN</code>, <code class="bg-gray-100 px-1 rounded">DROPBOX_PATH_PREFIX</code>. Details siehe BACKUP.md.</p>
                    </div>
                </div>

                <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
                        <h2 class="text-sm font-medium text-gray-700">Automatisierte Backups (Zeiten)</h2>
                    </div>
                    <div class="p-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <span class="text-gray-600">Backup ausführen (täglich)</span>
                            <span class="font-medium text-gray-900">{{ $settings['run_at'] }} Uhr</span>
                        </div>
                        <div class="flex justify-between gap-4">
                            <span class="text-gray-600">Alte Backups aufräumen (täglich)</span>
                            <span class="font-medium text-gray-900">{{ $settings['clean_at'] }} Uhr</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Änderung in <code class="bg-gray-100 px-1 rounded">.env</code>: <code class="bg-gray-100 px-1 rounded">BACKUP_RUN_AT=02:00</code>, <code class="bg-gray-100 px-1 rounded">BACKUP_CLEAN_AT=03:00</code>. Danach <code class="bg-gray-100 px-1 rounded">php artisan config:clear</code>. Cron muss <code class="bg-gray-100 px-1 rounded">schedule:run</code> minütlich aufrufen.</p>
                    </div>
                </div>

                <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
                        <h2 class="text-sm font-medium text-gray-700">Aufbewahrung (Retention)</h2>
                    </div>
                    <div class="p-4 text-sm text-gray-700 space-y-2">
                        <p>{{ $settings['retention']['daily'] }} tägliche Backups, {{ $settings['retention']['weekly'] }} wöchentliche, {{ $settings['retention']['monthly'] }} monatliche. Konfiguration in <code class="bg-gray-100 px-1 rounded">config/backup.php</code> (cleanup.default_strategy).</p>
                        <p class="text-gray-600">Kompression: <strong>maximal</strong> (Level 9), um Speicher zu sparen. Optional in .env <code class="bg-gray-100 px-1 rounded">BACKUP_COMPRESSION_METHOD=bzip2</code> für noch kleinere ZIPs (falls PHP bz2 aktiv).</p>
                    </div>
                </div>
            </div>
        @else
            {{-- Tab: Backups (bisheriger Inhalt) --}}
            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
                    <h2 class="text-sm font-medium text-gray-700">Backup jetzt ausführen</h2>
                </div>
                <div class="p-4 flex flex-wrap gap-3">
                    <form action="{{ route('admin.settings.backup.run') }}" method="POST" class="inline">
                        @csrf
                        <input type="hidden" name="tab" value="backups">
                        <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]">
                            Volles Backup (Datenbank + Dateien)
                        </button>
                    </form>
                    <form action="{{ route('admin.settings.backup.run') }}" method="POST" class="inline">
                        @csrf
                        <input type="hidden" name="tab" value="backups">
                        <input type="hidden" name="only_db" value="1">
                        <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-gray-300 text-gray-700 bg-white hover:bg-gray-50">
                            Nur Datenbank
                        </button>
                    </form>
                    <p class="text-xs text-gray-500 w-full mt-1">Backup wird im Hintergrund ausgeführt – die Seite antwortet sofort. Ein Queue-Worker muss laufen.</p>
                </div>
            </div>

            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
                    <h2 class="text-sm font-medium text-gray-700">Vorhandene Backups</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Dateiname</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Datum</th>
                                <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Größe</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($backups as $b)
                                <tr>
                                    <td class="px-4 py-3 text-sm font-mono text-gray-700">{{ $b['name'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $b['date'] ?? '–' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 text-right">{{ $b['size_mb'] }} MB</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-4 text-sm text-gray-500">Keine Backups vorhanden.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
@endsection
