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

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Sendefassung aus Ingest-Clips</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Meldung <strong>#{{ $newsItem->id }}</strong> · {{ Str::limit($newsItem->title, 100) }}
                </p>
            </div>
            <div class="flex gap-3 text-sm">
                <a href="{{ route('admin.news.edit', $newsItem) }}" class="text-[#092E48] hover:underline">Meldung bearbeiten</a>
                <a href="{{ route('admin.ingest.index') }}" class="text-gray-600 hover:underline">Alle Ingest-Clips</a>
            </div>
        </div>

        @if ($activeJob)
            <div class="rounded-md bg-amber-50 border border-amber-200 p-4 text-sm text-amber-900">
                Es läuft bereits ein Job (Status: <strong>{{ $activeJob->status }}</strong>, #{{ $activeJob->id }}). Bitte warten, bis der Vorgang abgeschlossen ist.
            </div>
        @endif

        <x-admin.card>
            @if ($clips->isEmpty())
                <p class="text-sm text-gray-600">Für diese Meldung sind keine Ingest-Clips zugeordnet. Weise Clips in der Detailansicht einer Meldung zu.</p>
            @else
                <form method="post" action="{{ route('admin.ingest.news-render', $newsItem) }}" class="space-y-4">
                    @csrf
                    <p class="text-sm text-gray-600">
                        Wähle die Clips und setze die Schnittreihenfolge (kleinere Zahl = früher). Nur Clips im Status „zugeordnet“ werden verarbeitet.
                    </p>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nutzen</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Datei</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Reihenfolge</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @foreach ($clips as $c)
                                    <tr class="{{ $c->status === \App\Models\IngestFile::STATUS_USED ? 'opacity-60' : '' }}">
                                        <td class="px-3 py-2">
                                            @if ($c->status === \App\Models\IngestFile::STATUS_ASSIGNED)
                                                <input type="checkbox" name="selected[]" value="{{ $c->id }}" class="rounded border-gray-300">
                                            @else
                                                <span class="text-xs text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-sm">#{{ $c->id }}</td>
                                        <td class="px-3 py-2 text-sm">
                                            <a href="{{ route('admin.ingest.show', $c) }}" class="text-[#092E48] hover:underline">{{ $c->original_name }}</a>
                                        </td>
                                        <td class="px-3 py-2 text-sm">{{ $c->status }}</td>
                                        <td class="px-3 py-2">
                                            @if ($c->status === \App\Models\IngestFile::STATUS_ASSIGNED)
                                                <input type="number" name="order[{{ $c->id }}]" min="0" max="65535" value="{{ $c->selection_order ?? 0 }}"
                                                    class="w-24 rounded-lg border-gray-300 text-sm">
                                            @else
                                                <span class="text-sm text-gray-500">{{ $c->selection_order ?? '—' }}</span>
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
