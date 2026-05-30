@extends('layouts.admin')

@section('title', 'Ingest-Clip #'.$ingestFile->id)

@section('content')
    <div class="space-y-5 text-left w-full max-w-4xl lg:max-w-5xl xl:max-w-6xl mx-auto min-w-0">
        @if (session('status'))
            <div id="ingest-flash-status" class="rounded-md bg-green-50 p-4 border border-green-200 scroll-mt-24">
                <p class="text-sm text-green-800">{{ session('status') }}</p>
            </div>
        @endif
        @if (session('error'))
            <div class="rounded-md bg-red-50 p-4 border border-red-200">
                <p class="text-sm text-red-800">{{ session('error') }}</p>
            </div>
        @endif

        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between min-w-0">
            <div class="min-w-0">
                <h1 class="text-xl sm:text-2xl font-semibold text-gray-900 break-words">Ingest-Clip #{{ $ingestFile->id }}</h1>
                <p class="mt-1 text-sm font-medium text-gray-800 break-all">{{ $ingestFile->original_name }}</p>
                <p class="mt-1 text-xs text-gray-500 break-words">
                    Eingang: {{ $ingestFile->created_at?->format('d.m.Y H:i') ?? '—' }}
                    @if ($ingestFile->updated_at && $ingestFile->updated_at->ne($ingestFile->created_at))
                        · zuletzt geändert: {{ $ingestFile->updated_at->format('d.m.Y H:i') }}
                    @endif
                </p>
            </div>
            <div class="flex flex-col sm:flex-row gap-2 shrink-0">
                <a href="{{ route('admin.ingest.index') }}" class="inline-flex items-center justify-center min-h-[2.75rem] px-3 text-sm font-medium text-[#092E48] hover:underline rounded-lg border border-transparent hover:border-[#092E48]/20 hover:bg-[#092E48]/5">← Zur Liste</a>
                @if ($previousIngestFile)
                    <a href="{{ route('admin.ingest.show', $previousIngestFile) }}" class="inline-flex items-center justify-center min-h-[2.75rem] px-3 text-sm font-medium rounded-lg border border-gray-300 bg-white text-gray-800 hover:bg-gray-50">← Vorheriger</a>
                @endif
                @if ($nextIngestFile)
                    <a href="{{ route('admin.ingest.show', $nextIngestFile) }}" class="inline-flex items-center justify-center min-h-[2.75rem] px-3 text-sm font-medium rounded-lg border border-gray-300 bg-white text-gray-800 hover:bg-gray-50">Nächster →</a>
                @endif
            </div>
        </div>

        {{-- Vorschau zuerst, dann Aktionen --}}
        @if (in_array($ingestFile->status, ['validated','assigned','used_in_render','preview_generating','preview_ready'], true) && is_string($ingestFile->absolute_path) && $ingestFile->absolute_path !== '' && \Illuminate\Support\Facades\File::exists($ingestFile->absolute_path))
            <x-admin.card class="min-w-0 max-w-full p-5 sm:p-6">
                <h2 class="text-sm font-semibold text-gray-900 mb-3">{{ $ingestFile->isIngestImageFile() ? 'Bild (Thumbnail-Vorschau)' : 'Video-Vorschau' }}</h2>
                @if (! $ingestFile->isIngestImageFile() && $ingestFile->status === \App\Models\IngestFile::STATUS_PREVIEW_GENERATING && ! $ingestFile->hasIngestPreviewVideo())
                    <p class="text-xs text-sky-800 bg-sky-50 border border-sky-100 rounded-lg px-3 py-2 mb-3">
                        Die leichte Kurzvorschau (MP4) wird gerade erzeugt. Bis dahin sehen Sie das Poster-Standbild; Zuordnung zur Meldung ist bereits möglich.
                    </p>
                @endif
                <p class="text-xs text-gray-500 mb-2">Wiedergabe über die geschützte Playback-Route.</p>
                <div class="w-full max-w-full min-w-0 overflow-hidden rounded-lg {{ $ingestFile->isIngestImageFile() ? 'bg-gray-100' : 'bg-black' }}">
                    @if ($ingestFile->isIngestImageFile())
                        <button type="button" id="ingest-open-original-overlay" class="block w-full outline-none focus-visible:ring-2 focus-visible:ring-[#092E48] focus-visible:ring-offset-2 rounded-lg text-left" title="Original in Overlay öffnen">
                            <img
                                class="block h-auto max-w-full max-h-[min(92vh,1600px)] object-contain object-center rounded-lg mx-auto"
                                src="{{ route('admin.ingest.thumbnail', $ingestFile) }}"
                                alt="{{ $ingestFile->newsItem?->title ?: ($ingestFile->newsItem?->subheadline ?: ($ingestFile->original_name ?: 'Bildmaterial von Erftkreis News Media')) }}"
                                loading="eager"
                                decoding="async"
                            >
                        </button>
                        <p class="text-xs text-gray-500 mt-2 flex flex-wrap items-center gap-x-2 gap-y-1">
                            <button type="button" id="ingest-open-original-overlay-inline" class="font-medium text-[#092E48] underline hover:no-underline">Original im Overlay öffnen</button>
                            <span class="text-gray-400">·</span>
                            <a href="{{ route('admin.ingest.playback', $ingestFile) }}" target="_blank" rel="noopener noreferrer" class="font-medium text-[#092E48] underline hover:no-underline">In neuem Tab öffnen</a>
                            <span class="text-gray-400"> · Original in voller Auflösung; Vorschau hier ist Thumbnail für schnelleren Workflow</span>
                        </p>

                        <div id="ingest-original-overlay" class="fixed inset-0 z-[120] hidden bg-black/90 p-3 sm:p-6">
                            <div class="mx-auto flex h-full w-full max-w-[96vw] flex-col gap-3">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs sm:text-sm text-white/90 break-all">{{ $ingestFile->original_name }}</p>
                                    <button type="button" data-overlay-close="1" class="inline-flex items-center justify-center rounded-md border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-medium text-white hover:bg-white/20">Schließen ✕</button>
                                </div>
                                <div class="flex-1 min-h-0 overflow-auto">
                                    <img
                                        id="ingest-original-overlay-image"
                                        data-src="{{ route('admin.ingest.playback', $ingestFile) }}"
                                        alt="{{ $ingestFile->newsItem?->title ?: ($ingestFile->newsItem?->subheadline ?: ($ingestFile->original_name ?: 'Bildmaterial von Erftkreis News Media')) }}"
                                        class="mx-auto block h-auto max-h-full w-auto max-w-full object-contain rounded"
                                        decoding="async"
                                    >
                                </div>
                            </div>
                        </div>
                    @else
                        @php
                            $ingestShowVideoSrc = $ingestFile->hasIngestPreviewVideo()
                                ? route('admin.ingest.preview-playback', $ingestFile)
                                : ($ingestFile->isBrowserPlayableOriginal() ? route('admin.ingest.playback', $ingestFile) : null);
                        @endphp
                        @if ($ingestShowVideoSrc)
                            <video controls class="w-full max-w-full max-h-[min(85vh,960px)] rounded-lg bg-black" playsinline preload="auto" src="{{ $ingestShowVideoSrc }}"></video>
                            @if ($ingestFile->hasIngestPreviewVideo())
                                <p class="text-xs text-gray-500 mt-2">
                                    Schnelle Kurzvorschau (FFmpeg). <a href="{{ route('admin.ingest.playback', $ingestFile) }}" target="_blank" rel="noopener noreferrer" class="font-medium text-[#092E48] underline hover:no-underline">Originaldatei im neuen Tab</a> (volle Auflösung, ggf. langsamer).
                                </p>
                            @endif
                        @else
                            <div class="relative rounded-lg bg-black overflow-hidden">
                                @include('admin.ingest.partials.ingest-video-preview', [
                                    'f' => $ingestFile,
                                    'controls' => false,
                                    'preload' => 'none',
                                    'linkClass' => 'relative block w-full focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#092E48]',
                                    'mediaClass' => 'block w-full max-w-full max-h-[min(85vh,960px)] object-contain mx-auto bg-black',
                                ])
                            </div>
                            <p class="text-xs text-gray-500 mt-2">
                                Poster-Standbild (sofort nach Import). Kurz-MP4 folgt automatisch
                                @if ($ingestFile->status === \App\Models\IngestFile::STATUS_PREVIEW_GENERATING)
                                    <strong>(wird erzeugt…)</strong>
                                @elseif (! $ingestFile->hasIngestPreviewVideo())
                                    – <form method="post" action="{{ route('admin.ingest.queue-preview', $ingestFile) }}" class="inline">@csrf<button type="submit" class="font-medium text-[#092E48] underline hover:no-underline">jetzt anstoßen</button></form>
                                @endif
                                .
                            </p>
                        @endif
                    @endif
                </div>
            </x-admin.card>
        @endif

        @if ($ingestFile->status === \App\Models\IngestFile::STATUS_USED && $ingestFile->final_news_item_media_id)
            <x-admin.card class="min-w-0 max-w-full p-5 sm:p-6">
                <p class="text-sm text-gray-700 break-words">
                    @if ($ingestFile->isIngestImageFile())
                        Dieses Bild wurde in die <strong>Bildergalerie</strong> der Meldung übernommen (nicht Sendefassung).
                    @else
                        Dieser Clip wurde in die Sendefassung übernommen.
                    @endif
                    Medien-ID: <strong>#{{ $ingestFile->final_news_item_media_id }}</strong>
                    @if ($ingestFile->news_item_id)
                        · <a class="inline-flex items-center min-h-[2.75rem] px-1 -mx-1 rounded-md text-[#092E48] hover:underline hover:bg-[#092E48]/5" href="{{ route('admin.news.edit', $ingestFile->news_item_id) }}">Meldung bearbeiten</a>
                    @endif
                </p>
            </x-admin.card>
        @endif

        @if (! in_array($ingestFile->status, [\App\Models\IngestFile::STATUS_USED], true))
            <x-admin.card id="ingest-zuordnung" class="min-w-0 max-w-full p-5 sm:p-6">
                <h2 class="text-sm font-semibold text-gray-900 mb-3">Meldung zuordnen</h2>
                @if ($ingestFile->isIngestImageFile())
                    <p class="text-sm text-gray-600 mb-3">
                        Beim Speichern mit gewählter Meldung wird das Bild in die <strong>Bildergalerie</strong> übernommen und verschwindet aus der Ingest-Sichtung. Es fließt <strong>nicht</strong> in den Finalschnitt / MP4 ein.
                    </p>
                @endif
                <form method="post" action="{{ route('admin.ingest.assign', $ingestFile) }}" class="space-y-4 min-w-0">
                    @csrf
                    <input type="hidden" name="_ingest_return" value="show">
                    <div class="min-w-0">
                        <label for="news_item_id" class="block text-xs font-medium text-gray-500">Nachricht</label>
                        <select name="news_item_id" id="news_item_id" class="mt-1 block w-full max-w-full min-w-0 rounded-lg border-gray-300 text-sm">
                            <option value="">— keine Zuordnung —</option>
                            @foreach ($newsChoices as $n)
                                <option value="{{ $n->id }}" @selected((int) $ingestFile->news_item_id === (int) $n->id)>
                                    #{{ $n->id }}@if($n->brand) · {{ Str::limit($n->brand->name, 16) }}@endif · {{ Str::limit($n->title, 72) }} ({{ $n->status }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @if (! $ingestFile->isIngestImageFile())
                        <div class="min-w-0">
                            <label for="selection_order" class="block text-xs font-medium text-gray-500">Reihenfolge (optional, für Schnitt)</label>
                            <input type="number" name="selection_order" id="selection_order" min="0" max="65535" value="{{ old('selection_order', $ingestFile->selection_order) }}"
                                class="mt-1 w-full max-w-[12rem] rounded-lg border-gray-300 text-sm" placeholder="z. B. 1">
                        </div>
                    @endif
                    <div class="flex flex-col sm:flex-row sm:flex-wrap gap-3">
                        <button type="submit" class="inline-flex justify-center items-center w-full sm:w-auto min-h-[2.75rem] px-4 py-2 text-sm font-medium rounded-lg bg-[#092E48] text-white hover:bg-[#0b3858]">
                            Speichern
                        </button>
                        @if ($ingestFile->news_item_id && ! $ingestFile->isIngestImageFile())
                            <a href="{{ route('admin.ingest.news-workspace', $ingestFile->news_item_id) }}" class="inline-flex justify-center items-center w-full sm:w-auto min-h-[2.75rem] px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 text-gray-800 hover:bg-gray-50">
                                Schnitt / Sendefassung
                            </a>
                        @endif
                    </div>
                </form>
                @if (! $ingestFile->isIngestImageFile() && in_array($ingestFile->status, ['validated', 'preview_generating', 'preview_ready', 'assigned'], true) && ! $ingestFile->hasIngestPreviewVideo())
                    <form method="post" action="{{ route('admin.ingest.queue-preview', $ingestFile) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="inline-flex justify-center items-center w-full sm:w-auto min-h-[2.75rem] px-4 py-2 text-sm font-medium rounded-lg border border-sky-300 bg-sky-50 text-sky-900 hover:bg-sky-100"
                            @if ($ingestFile->status === \App\Models\IngestFile::STATUS_PREVIEW_GENERATING) disabled @endif>
                            @if ($ingestFile->status === \App\Models\IngestFile::STATUS_PREVIEW_GENERATING)
                                Kurzvorschau wird erzeugt…
                            @else
                                Kurzvorschau erzeugen (schnelleres Abspielen)
                            @endif
                        </button>
                    </form>
                @endif
                @if (! $ingestFile->isIngestImageFile() && $ingestFile->news_item_id && in_array($ingestFile->status, ['validated', 'preview_ready', 'assigned'], true))
                    <form method="post" action="{{ route('admin.ingest.finalize-video', $ingestFile) }}" class="mt-3"
                        onsubmit="return window.confirm('Fertige MP4 jetzt ohne Render direkt in die Meldung übernehmen?')">
                        @csrf
                        <input type="hidden" name="news_item_id" value="{{ $ingestFile->news_item_id }}">
                        <input type="hidden" name="_ingest_return" value="show">
                        <button type="submit" class="inline-flex justify-center items-center w-full sm:w-auto min-h-[2.75rem] px-4 py-2 text-sm font-medium rounded-lg border border-emerald-300 bg-emerald-50 text-emerald-900 hover:bg-emerald-100">
                            Fertige MP4 ohne Render übernehmen
                        </button>
                    </form>
                @endif
            </x-admin.card>
        @endif

        @if ($ingestFile->isIngestImageFile() && ! in_array($ingestFile->status, [\App\Models\IngestFile::STATUS_USED], true))
            <x-admin.card class="min-w-0 max-w-full p-5 sm:p-6 border-emerald-200 bg-emerald-50/40">
                <h2 class="text-sm font-semibold text-emerald-900 mb-2">Direktvermarktung (ohne Meldung)</h2>
                <p class="text-sm text-emerald-900/90 mb-3">
                    Für Bilder, die direkt in die Vermarktung gehen sollen (z. B. Imago), ohne eigene redaktionelle Meldung.
                    Es wird als eigenständiger Mediathek-Eintrag angelegt (ohne News-Erstellung).
                </p>
                <form method="post" action="{{ route('admin.ingest.direct-marketing', $ingestFile) }}" class="space-y-3">
                    @csrf
                    <div>
                        <p class="text-xs font-medium text-emerald-900 mb-1">Medienpaket / FTP: nur für (optional)</p>
                        @if (isset($deliveryOrganizations) && $deliveryOrganizations->isNotEmpty())
                            <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-emerald-900">
                                @foreach ($deliveryOrganizations as $org)
                                    <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            name="delivery_org_ids[]"
                                            value="{{ $org->id }}"
                                            class="rounded border-emerald-300 text-emerald-700 focus:ring-emerald-600"
                                            @checked(in_array((int) $org->id, array_map('intval', old('delivery_org_ids', [])), true))
                                        >
                                        <span>{{ $org->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <p class="text-[11px] text-emerald-900/80 mt-1">
                                Keine Auswahl = für alle Versandziele sichtbar. Auswahl = nur diese Medienhäuser.
                            </p>
                        @else
                            <p class="text-xs text-emerald-900/80">Keine Organisationen gefunden – Standard bleibt „für alle Versandziele“.</p>
                        @endif
                    </div>
                    <button type="submit" class="inline-flex justify-center items-center min-h-[2.75rem] px-4 py-2 text-sm font-medium rounded-lg bg-emerald-700 text-white hover:bg-emerald-800">
                        In Direktvermarktung übernehmen
                    </button>
                </form>
            </x-admin.card>
        @endif

        @if (! in_array($ingestFile->status, [\App\Models\IngestFile::STATUS_USED], true))
            <x-admin.card class="min-w-0 max-w-full border-red-100 p-5 sm:p-6">
                <h2 class="text-sm font-semibold text-red-900 mb-2">Ingest-Eintrag löschen</h2>
                <p class="text-sm text-gray-600 mb-3">Wenn Sie dieses Material nicht verwenden, können Sie den Eintrag samt Datei entfernen.</p>
                <form method="POST" action="{{ route('admin.ingest.destroy', $ingestFile) }}" class="inline"
                    onsubmit="return window.adminConfirmDelete(this)"
                    data-delete-prompt="Diesen Ingest-Eintrag und die Datei wirklich löschen? Geben Sie zur Bestätigung „ja“ ein.">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex justify-center items-center min-h-[2.75rem] px-4 py-2 text-sm font-medium rounded-lg border border-red-300 text-red-800 bg-red-50 hover:bg-red-100">
                        Löschen
                    </button>
                </form>
            </x-admin.card>
        @endif

        {{-- Fehler & Hinweise (direkt nach Aktionen) --}}
        @php($hasWorkflowError = filled($ingestFile->error_message))
        @php($hasPreviewError = isset($ingestFile->preview_error_message) && filled($ingestFile->preview_error_message))
        @if ($hasWorkflowError || $hasPreviewError || in_array($ingestFile->status, ['rejected', 'failed'], true))
            <x-admin.card class="min-w-0 max-w-full p-5 sm:p-6">
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

        {{-- Überblick --}}
        <x-admin.card class="min-w-0 max-w-full p-5 sm:p-6">
            <h2 class="text-sm font-semibold text-gray-900 mb-3">Überblick</h2>
            <div class="flex flex-wrap items-center gap-2 mb-4 min-w-0">
                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-900 break-words">{{ $ingestStatusLabel }}</span>
                <span class="text-xs text-gray-500 break-words min-w-0">(technisch: <code class="rounded bg-gray-100 px-1 break-all">{{ $ingestFile->status }}</code>)</span>
            </div>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm min-w-0">
                <div>
                    <dt class="text-gray-500">News-Zuordnung</dt>
                    <dd class="mt-0.5 font-medium min-w-0 break-words">
                        @if ($ingestFile->news_item_id)
                            <span class="text-emerald-800">ja</span>
                            <span class="block text-xs font-normal text-gray-600 break-words">#{{ $ingestFile->news_item_id }}
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
                        @if ($ingestFile->isIngestImageFile())
                            <span class="text-gray-500">— (gilt nur für Video)</span>
                        @else
                            @isset($ingestFile->is_selected)
                                {{ $ingestFile->is_selected ? 'ja' : 'nein' }}
                            @else
                                <span class="text-gray-400">nicht hinterlegt</span>
                            @endisset
                        @endif
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
                <p class="mt-3 text-xs text-amber-900 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 break-words">
                    Hinweis: Zu dieser Meldung gibt es einen aktiven Render-Job (Status: <strong>{{ $activeRenderJobForClip->status }}</strong>) — nur zur Orientierung.
                </p>
            @endif
        </x-admin.card>

        {{-- Datei & Identifikation --}}
        <x-admin.card class="min-w-0 max-w-full p-5 sm:p-6">
            <h2 class="text-sm font-semibold text-gray-900 mb-3">Datei &amp; Eingang</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm min-w-0">
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
        <x-admin.card class="min-w-0 max-w-full p-5 sm:p-6">
            <h2 class="text-sm font-semibold text-gray-900 mb-3">Technische Daten</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm min-w-0">
                <div><dt class="text-gray-500">MIME-Typ</dt><dd class="mt-0.5 break-all">{{ $ingestFile->mime ?? '—' }}</dd></div>
                <div>
                    <dt class="text-gray-500">Original-Erstellungszeit</dt>
                    <dd class="mt-0.5">
                        @if ($originalCreatedAtInfo['at'])
                            {{ $originalCreatedAtInfo['at']->format('d.m.Y H:i:s') }}
                            @if (! empty($originalCreatedAtInfo['source']))
                                <span class="text-xs text-gray-400">({{ $originalCreatedAtInfo['source'] }})</span>
                            @endif
                        @else
                            —
                        @endif
                    </dd>
                </div>
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
            <x-admin.card class="min-w-0 max-w-full p-5 sm:p-6">
                <h2 class="text-sm font-semibold text-gray-900 mb-3">Vorschau &amp; Bearbeitung <span class="font-normal text-gray-500">(nur Anzeige)</span></h2>
                <dl class="grid grid-cols-1 gap-3 text-sm min-w-0">
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
            <x-admin.card class="min-w-0 max-w-full p-5 sm:p-6">
                <h2 class="text-sm font-semibold text-gray-900 mb-3">Trim <span class="font-normal text-gray-500">(nur Anzeige)</span></h2>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm min-w-0">
                    <div><dt class="text-gray-500">trim_in_seconds</dt><dd class="mt-0.5 font-mono">{{ $ingestFile->trim_in_seconds ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">trim_out_seconds</dt><dd class="mt-0.5 font-mono">{{ $ingestFile->trim_out_seconds ?? '—' }}</dd></div>
                </dl>
            </x-admin.card>
        @endif

        {{-- Pfade & Quelle --}}
        <x-admin.card class="min-w-0 max-w-full p-5 sm:p-6">
            <h2 class="text-sm font-semibold text-gray-900 mb-3">Pfade &amp; Herkunft</h2>
            <dl class="space-y-3 text-sm min-w-0">
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
            <x-admin.card class="min-w-0 max-w-full p-5 sm:p-6">
                <h2 class="text-sm font-semibold text-gray-900 mb-2">FFprobe (Rohdaten)</h2>
                <p class="text-xs text-gray-500 mb-2">Gekürzt; vollständiger Inhalt im Tooltip.</p>
                <pre class="text-xs bg-gray-900 text-gray-100 rounded-lg p-3 overflow-x-auto max-h-48 overflow-y-auto whitespace-pre-wrap break-words min-w-0 max-w-full" title="{{ Str::limit($ingestFile->ffprobe_json, 50000) }}">{{ Str::limit($ingestFile->ffprobe_json, 1200) }}</pre>
            </x-admin.card>
        @endif

    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var overlay = document.getElementById('ingest-original-overlay');
            var img = document.getElementById('ingest-original-overlay-image');
            var openMain = document.getElementById('ingest-open-original-overlay');
            var openInline = document.getElementById('ingest-open-original-overlay-inline');
            if (!overlay || !img || (!openMain && !openInline)) return;

            function openOverlay() {
                if (!img.getAttribute('src')) {
                    img.setAttribute('src', img.getAttribute('data-src') || '');
                }
                overlay.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            }

            function closeOverlay() {
                overlay.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }

            if (openMain) openMain.addEventListener('click', openOverlay);
            if (openInline) openInline.addEventListener('click', openOverlay);

            overlay.addEventListener('click', function (event) {
                if (event.target === overlay || event.target.closest('[data-overlay-close="1"]')) {
                    closeOverlay();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !overlay.classList.contains('hidden')) {
                    closeOverlay();
                }
            });
        })();
    </script>
@endpush
