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

        @if (! config('ingest.enabled'))
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                <p class="font-medium">Ingest ist deaktiviert</p>
                <p class="mt-1 text-amber-900/90">
                    Ohne <code class="rounded bg-amber-100 px-1 py-0.5 text-xs">INGEST_ENABLED=true</code> in der <code class="rounded bg-amber-100 px-1 py-0.5 text-xs">.env</code> importieren
                    <code class="rounded bg-amber-100 px-1 py-0.5 text-xs">ingest:scan-inbox</code> bzw. <code class="rounded bg-amber-100 px-1 py-0.5 text-xs">ingest:scan-upload</code> keine Dateien – die Liste bleibt leer.
                </p>
            </div>
        @elseif ($files->isEmpty())
            <div class="rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm text-sky-950">
                <p class="font-medium">Noch keine importierten Clips</p>
                <ul class="mt-2 list-disc list-inside space-y-1 text-sky-900/90">
                    <li>Video-Dateien (Endungen laut <code class="text-xs">config/ingest.php</code> → <code class="text-xs">allowed_extensions</code>) in die Inbox legen – nur die oberste Ordnerebene wird gescannt (keine Unterordner).</li>
                    <li>Auf dem Server: <code class="rounded bg-white/80 px-1 py-0.5 text-xs">php artisan ingest:scan-upload</code> oder <code class="rounded bg-white/80 px-1 py-0.5 text-xs">php artisan ingest:scan-inbox</code> (Cron). Unvollständige Dateien werden übersprungen, bis sie stabil sind.</li>
                    <li>Eingangspfad: <code class="text-xs">INGEST_ROOT_PATH</code> (→ …/upload) oder direkt <code class="text-xs">INGEST_INBOX_PATH</code> – siehe <code class="text-xs">config/ingest.php</code> / <code class="text-xs">docs/VIDEO_INGEST.md</code>.</li>
                </ul>
            </div>
        @endif

        <x-admin.card>
            <form method="get" action="{{ route('admin.ingest.index') }}" class="flex flex-wrap gap-3 items-end">
                <div>
                    <label class="block text-xs font-medium text-gray-500">Status</label>
                    <select name="status" class="mt-1 rounded-lg border-gray-300 text-sm">
                        <option value="">alle</option>
                        @foreach (['imported','validating','validated','assigned','used_in_render','rejected','failed'] as $st)
                            <option value="{{ $st }}" @selected(request('status') === $st)>{{ $st }}</option>
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
                <button type="submit" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg bg-[#092E48] text-white hover:bg-[#0b3858]">
                    Filtern
                </button>
            </form>
        </x-admin.card>

        <x-admin.card>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Datei</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Dauer</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Meldung</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Aktion</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse ($files as $f)
                            <tr>
                                <td class="px-3 py-2 text-sm text-gray-900">#{{ $f->id }}</td>
                                <td class="px-3 py-2 text-sm text-gray-800">{{ $f->original_name }}</td>
                                <td class="px-3 py-2 text-sm">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-800">{{ $f->status }}</span>
                                </td>
                                <td class="px-3 py-2 text-sm text-gray-600">
                                    @if ($f->duration_s)
                                        {{ number_format((float) $f->duration_s, 2, ',', '') }} s
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-sm text-gray-600">
                                    @if ($f->news_item_id)
                                        #{{ $f->news_item_id }}
                                        @if ($f->newsItem)
                                            <span class="text-gray-500">({{ Str::limit($f->newsItem->title, 32) }})</span>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-sm text-right">
                                    <a href="{{ route('admin.ingest.show', $f) }}" class="text-[#092E48] hover:underline">Details</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-6 text-sm text-gray-500 text-center">Keine Einträge.</td>
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
