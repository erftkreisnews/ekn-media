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

        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Ingest-Clip #{{ $ingestFile->id }}</h1>
                <p class="mt-1 text-sm font-medium text-gray-800 break-all">{{ $ingestFile->original_name }}</p>
                <p class="mt-1 text-xs text-gray-500">
                    Eingang: {{ $ingestFile->created_at?->format('d.m.Y H:i') ?? '—' }}
                    @if ($ingestFile->updated_at && $ingestFile->updated_at->ne($ingestFile->created_at))
                        · zuletzt geändert: {{ $ingestFile->updated_at->format('d.m.Y H:i') }}
                    @endif
                </p>
            </div>
            <a href="{{ route('admin.ingest.index') }}" class="text-sm text-[#092E48] hover:underline shrink-0">← Zur Liste</a>
        </div>

        {{-- Überblick --}}
        <x-admin.card>
            <h2 class="text-sm font-semibold text-gray-900 mb-3">Überblick</h2>
            <div class="flex flex-wrap items-center gap-2 mb-4">
                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-900">{{ $ingestStatusLabel }}</span>
                <span class="text-xs text-gray-500">(technisch: <code class="rounded bg-gray-100 px-1">{{ $ingestFile->status }}</code>)</span>
            </div>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                <div>
                    <dt class="text-gray-500">News-Zuordnung</dt>
                    <dd class="mt-0.5 font-medium">
                        @if ($ingestFile->news_item_id)
                            <span class="text-emerald-800">ja</span>
                            <span class="block text-xs font-normal text-gray-600">#{{ $ingestFile->news_item_id }}
                                @if ($ingestFile->newsItem)
                                    — {{ Str::limit($ingestFile->newsItem->title, 72) }}
                                @endif
                            </span>
                        @else
                            <span class="text-amber-800">keine Meldung zugeordnet</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500">Für Finalschnitt ausgewählt</dt>
                    <dd class="mt-0.5 font-medium">
                        @isset($ingestFile->is_selected)
                            {{ $ingestFile->is_selected ? 'ja' : 'nein' }}
                        @else
                            <span class="text-gray-400">nicht hinterlegt</span>
                        @endisset
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500">Reihenfolge (Schnitt)</dt>
                    <dd class="mt-0.5">{{ $ingestFile->selection_order !== null ? $ingestFile->selection_order : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Vorschau-Datei (Speicher)</dt>
                    <dd class="mt-0.5">
                        @isset($ingestFile->preview_path)
                            @if (filled($ingestFile->preview_path))
                                <span class="text-emerald-800">vorhanden</span>
                            @else
                                <span class="text-amber-800">keine Preview hinterlegt</span>
                            @endif
                        @else
                            <span class="text-gray-400">—</span>
                        @endisset
                    </dd>
                </div>
            </dl>
            @if ($activeRenderJobForClip)
                <p class="mt-3 text-xs text-amber-900 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                    Hinweis: Zu dieser Meldung gibt es einen aktiven Render-Job (Status: <strong>{{ $activeRenderJobForClip->status }}</strong>) — nur zur Orientierung.
                </p>
            @endif
        </x-admin.card>

        {{-- Fehler & Hinweise --}}
        @php($hasWorkflowError = filled($ingestFile->error_message))
        @php($hasPreviewError = isset($ingestFile->preview_error_message) && filled($ingestFile->preview_error_message))
        @if ($hasWorkflowError || $hasPreviewError || in_array($ingestFile->status, ['rejected', 'failed'], true))
            <x-admin.card>
                <h2 class="text-sm font-semibold text-red-900 mb-3">Fehler &amp; Hinweise</h2>
                @if (in_array($ingestFile->status, ['rejected', 'failed'], true))
                    <p class="mb-3 text-sm text-red-800 bg-red-50 border border-red-100 rounded-lg px-3 py-2">
                        Workflow-Status ist <strong>{{ $ingestStatusLabel }}</strong> — bitte Meldung und technische Daten prüfen.
                    </p>
                @endif
                @if ($hasWorkflowError)
                    <div class="mb-3">
                        <p class="text-xs font-semibold text-red-800 mb-1">Workflow / Validierung</p>
                        <p class="text-sm text-red-900 bg-red-50 border border-red-100 rounded-lg p-3 whitespace-pre-wrap break-words" title="{{ $ingestFile->error_message }}">{{ Str::limit($ingestFile->error_message, 2000) }}</p>
                        @if (strlen($ingestFile->error_message) > 2000)
                            <p class="mt-1 text-xs text-gray-500">Vollständiger Text im Tooltip (Titel) dieses Kastens.</p>
                        @endif
                    </div>
                @endif
                @if ($hasPreviewError)
                    <div>
                        <p class="text-xs font-semibold text-red-800 mb-1">Vorschau-Erzeugung</p>
                        <p class="text-sm text-red-900 bg-red-50 border border-red-100 rounded-lg p-3 whitespace-pre-wrap break-words" title="{{ $ingestFile->preview_error_message }}">{{ Str::limit($ingestFile->preview_error_message, 2000) }}</p>
                    </div>
                @endif
            </x-admin.card>
        @endif

        {{-- Datei & Identifikation --}}
        <x-admin.card>
            <h2 class="text-sm font-semibold text-gray-900 mb-3">Datei &amp; Eingang</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                <div class="sm:col-span-2">
                    <dt class="text-gray-500">Dateiname (Original)</dt>
                    <dd class="mt-0.5 font-medium break-all">{{ $ingestFile->original_name }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Datensatz-ID</dt>
                    <dd class="mt-0.5 font-mono text-xs">#{{ $ingestFile->id }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Content-Hash</dt>
                    <dd class="mt-0.5 font-mono text-xs break-all">{{ $ingestFile->content_hash ?? '—' }}</dd>
                </div>
            </dl>
        </x-admin.card>

        {{-- Technische Daten --}}
        <x-admin.card>
            <h2 class="text-sm font-semibold text-gray-900 mb-3">Technische Daten</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                <div><dt class="text-gray-500">MIME-Typ</dt><dd class="mt-0.5 break-all">{{ $ingestFile->mime ?? '—' }}</dd></div>
                <div>
                    <dt class="text-gray-500">Dateigröße</dt>
                    <dd class="mt-0.5">
                        @if ($ingestFile->file_size !== null)
                            @php($sz = (int) $ingestFile->file_size)
                            @if ($sz >= 1073741824)
                                {{ number_format($sz / 1073741824, 2, ',', '.') }} GB
                            @elseif ($sz >= 1048576)
                                {{ number_format($sz / 1048576, 2, ',', '.') }} MB
                            @elseif ($sz >= 1024)
                                {{ number_format($sz / 1024, 1, ',', '.') }} KB
                            @else
                                {{ $sz }} B
                            @endif
                            <span class="text-xs text-gray-400">({{ number_format($sz, 0, ',', '.') }} B)</span>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div><dt class="text-gray-500">Auflösung</dt><dd class="mt-0.5">
                    @if ($ingestFile->width && $ingestFile->height)
                        {{ $ingestFile->width }}×{{ $ingestFile->height }} px
                    @else
                        —
                    @endif
                </dd></div>
                <div><dt class="text-gray-500">FPS</dt><dd class="mt-0.5">{{ $ingestFile->fps ?? '—' }}</dd></div>
                <div><dt class="text-gray-500">Dauer</dt><dd class="mt-0.5">{{ $ingestFile->duration_s ? number_format((float) $ingestFile->duration_s, 3, ',', '').' s' : '—' }}</dd></div>
                <div><dt class="text-gray-500">Codec</dt><dd class="mt-0.5 break-all">{{ $ingestFile->codec ?? '—' }}</dd></div>
            </dl>
        </x-admin.card>

        {{-- Workflow / Vorschau-Status (nur Anzeige) --}}
        @if (isset($ingestFile->preview_path) || isset($ingestFile->preview_status) || $ingestPreviewStatusLabel)
            <x-admin.card>
                <h2 class="text-sm font-semibold text-gray-900 mb-3">Vorschau &amp; Bearbeitung <span class="font-normal text-gray-500">(nur Anzeige)</span></h2>
                <dl class="grid grid-cols-1 gap-3 text-sm">
                    @isset($ingestFile->preview_path)
                        <div>
                            <dt class="text-gray-500">preview_path</dt>
                            <dd class="mt-0.5 font-mono text-xs break-all text-gray-800">{{ filled($ingestFile->preview_path) ? $ingestFile->preview_path : '— (leer)' }}</dd>
                        </div>
                    @endisset
                    @if ($ingestPreviewStatusLabel)
                        <div>
                            <dt class="text-gray-500">preview_status</dt>
                            <dd class="mt-0.5">{{ $ingestPreviewStatusLabel }} <span class="text-xs text-gray-400">(<code class="rounded bg-gray-100 px-1">{{ $ingestFile->preview_status }}</code>)</span></dd>
                        </div>
                    @elseif (isset($ingestFile->preview_status))
                        <div>
                            <dt class="text-gray-500">preview_status</dt>
                            <dd class="mt-0.5">{{ $ingestFile->preview_status !== null && $ingestFile->preview_status !== '' ? $ingestFile->preview_status : '—' }}</dd>
                        </div>
                    @endif
                </dl>
            </x-admin.card>
        @endif

        {{-- Trim (nur Anzeige) --}}
        @if (isset($ingestFile->trim_in_seconds) || isset($ingestFile->trim_out_seconds))
            <x-admin.card>
                <h2 class="text-sm font-semibold text-gray-900 mb-3">Trim <span class="font-normal text-gray-500">(nur Anzeige)</span></h2>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-gray-500">trim_in_seconds</dt><dd class="mt-0.5 font-mono">{{ $ingestFile->trim_in_seconds ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">trim_out_seconds</dt><dd class="mt-0.5 font-mono">{{ $ingestFile->trim_out_seconds ?? '—' }}</dd></div>
                </dl>
            </x-admin.card>
        @endif

        {{-- Pfade & Quelle --}}
        <x-admin.card>
            <h2 class="text-sm font-semibold text-gray-900 mb-3">Pfade &amp; Herkunft</h2>
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-gray-500">Quelle</dt>
                    <dd class="mt-0.5">{{ $ingestFile->source->name ?? '—' }}</dd>
                </div>
                @if ($ingestFile->batch)
                    <div>
                        <dt class="text-gray-500">Batch</dt>
                        <dd class="mt-0.5">
                            #{{ $ingestFile->batch->id }}
                            @if (filled($ingestFile->batch->reference_label))
                                — {{ $ingestFile->batch->reference_label }}
                            @endif
                        </dd>
                    </div>
                @endif
                <div>
                    <dt class="text-gray-500">relative_path</dt>
                    <dd class="mt-0.5 font-mono text-xs break-all bg-gray-50 rounded px-2 py-1.5 text-gray-800">{{ $ingestFile->relative_path ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">absolute_path</dt>
                    <dd class="mt-0.5 font-mono text-xs break-all bg-gray-50 rounded px-2 py-1.5 text-gray-800">{{ $ingestFile->absolute_path ?? '—' }}</dd>
                </div>
            </dl>
        </x-admin.card>

        @if (filled($ingestFile->ffprobe_json))
            <x-admin.card>
                <h2 class="text-sm font-semibold text-gray-900 mb-2">FFprobe (Rohdaten)</h2>
                <p class="text-xs text-gray-500 mb-2">Gekürzt; vollständiger Inhalt im Tooltip.</p>
                <pre class="text-xs bg-gray-900 text-gray-100 rounded-lg p-3 overflow-x-auto max-h-48 overflow-y-auto whitespace-pre-wrap break-words" title="{{ Str::limit($ingestFile->ffprobe_json, 50000) }}">{{ Str::limit($ingestFile->ffprobe_json, 1200) }}</pre>
            </x-admin.card>
        @endif

        @if (in_array($ingestFile->status, ['validated','assigned','used_in_render'], true) && \Illuminate\Support\Facades\File::exists($ingestFile->absolute_path))
            <x-admin.card>
                <h2 class="text-sm font-semibold text-gray-900 mb-3">Abspielen (Server-Stream)</h2>
                <p class="text-xs text-gray-500 mb-2">Wiedergabe wie bisher über die geschützte Playback-Route (keine neue Aktion).</p>
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
