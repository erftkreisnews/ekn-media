@extends('layouts.admin')

@section('title', 'Ingest-Clip #'.$ingestFile->id)

@section('content')
    <div class="space-y-6 text-left max-w-4xl">
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

        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Ingest-Clip #{{ $ingestFile->id }}</h1>
                <p class="mt-1 text-sm text-gray-600">{{ $ingestFile->original_name }}</p>
            </div>
            <a href="{{ route('admin.ingest.index') }}" class="text-sm text-[#092E48] hover:underline">← Zur Liste</a>
        </div>

        <x-admin.card>
            <h2 class="text-sm font-semibold text-gray-900 mb-3">Technische Daten</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                <div><dt class="text-gray-500">Status</dt><dd class="font-medium">{{ $ingestFile->status }}</dd></div>
                <div><dt class="text-gray-500">Quelle</dt><dd>{{ $ingestFile->source->name ?? '—' }}</dd></div>
                <div><dt class="text-gray-500">Größe</dt><dd>{{ $ingestFile->file_size ? number_format($ingestFile->file_size).' B' : '—' }}</dd></div>
                <div><dt class="text-gray-500">MIME</dt><dd>{{ $ingestFile->mime ?? '—' }}</dd></div>
                <div><dt class="text-gray-500">Auflösung</dt><dd>
                    @if ($ingestFile->width && $ingestFile->height)
                        {{ $ingestFile->width }}×{{ $ingestFile->height }}
                    @else
                        —
                    @endif
                </dd></div>
                <div><dt class="text-gray-500">FPS</dt><dd>{{ $ingestFile->fps ?? '—' }}</dd></div>
                <div><dt class="text-gray-500">Dauer</dt><dd>{{ $ingestFile->duration_s ? number_format((float) $ingestFile->duration_s, 3, ',', '').' s' : '—' }}</dd></div>
                <div><dt class="text-gray-500">Codec</dt><dd>{{ $ingestFile->codec ?? '—' }}</dd></div>
            </dl>
            @if ($ingestFile->error_message)
                <p class="mt-3 text-sm text-red-700 bg-red-50 border border-red-100 rounded-lg p-3">{{ $ingestFile->error_message }}</p>
            @endif
        </x-admin.card>

        @if (in_array($ingestFile->status, ['validated','assigned','used_in_render'], true) && \Illuminate\Support\Facades\File::exists($ingestFile->absolute_path))
            <x-admin.card>
                <h2 class="text-sm font-semibold text-gray-900 mb-3">Vorschau</h2>
                <video controls class="w-full max-h-[480px] rounded-lg bg-black" src="{{ route('admin.ingest.playback', $ingestFile) }}"></video>
            </x-admin.card>
        @endif

        @if ($ingestFile->status === \App\Models\IngestFile::STATUS_USED && $ingestFile->final_news_item_media_id)
            <x-admin.card>
                <p class="text-sm text-gray-700">
                    Dieser Clip wurde in die Sendefassung übernommen.
                    Medien-ID: <strong>#{{ $ingestFile->final_news_item_media_id }}</strong>
                    @if ($ingestFile->news_item_id)
                        · <a class="text-[#092E48] hover:underline" href="{{ route('admin.news.edit', $ingestFile->news_item_id) }}">Meldung bearbeiten</a>
                    @endif
                </p>
            </x-admin.card>
        @endif

        @if (! in_array($ingestFile->status, [\App\Models\IngestFile::STATUS_USED], true))
            <x-admin.card>
                <h2 class="text-sm font-semibold text-gray-900 mb-3">Meldung zuordnen</h2>
                <form method="post" action="{{ route('admin.ingest.assign', $ingestFile) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label for="news_item_id" class="block text-xs font-medium text-gray-500">Nachricht</label>
                        <select name="news_item_id" id="news_item_id" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
                            <option value="">— keine Zuordnung —</option>
                            @foreach ($newsChoices as $n)
                                <option value="{{ $n->id }}" @selected((int) $ingestFile->news_item_id === (int) $n->id)>
                                    #{{ $n->id }} · {{ Str::limit($n->title, 80) }} ({{ $n->status }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="selection_order" class="block text-xs font-medium text-gray-500">Reihenfolge (optional, für Schnitt)</label>
                        <input type="number" name="selection_order" id="selection_order" min="0" max="65535" value="{{ old('selection_order', $ingestFile->selection_order) }}"
                            class="mt-1 w-32 rounded-lg border-gray-300 text-sm" placeholder="z. B. 1">
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg bg-[#092E48] text-white hover:bg-[#0b3858]">
                            Speichern
                        </button>
                        @if ($ingestFile->news_item_id)
                            <a href="{{ route('admin.ingest.news-workspace', $ingestFile->news_item_id) }}" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 text-gray-800 hover:bg-gray-50">
                                Schnitt / Sendefassung
                            </a>
                        @endif
                    </div>
                </form>
            </x-admin.card>
        @endif
    </div>
@endsection
