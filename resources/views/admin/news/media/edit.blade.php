@extends('layouts.admin')

@section('content')
    @php
        $galleryImagesJson = isset($galleryImages) ? $galleryImages->toJson() : '[]';
        $galleryIndexJson = isset($galleryIndex) ? (int) $galleryIndex : 0;
    @endphp
    <div
        class="space-y-6"
        x-data="{
            imageOverlay: false,
            gallery: {{ $galleryImagesJson }},
            index: {{ $galleryIndexJson }},
            get current() { return this.gallery[this.index] || null },
            prev() { this.index = (this.index - 1 + this.gallery.length) % this.gallery.length },
            next() { this.index = (this.index + 1) % this.gallery.length }
        }"
        x-init="$watch('imageOverlay', v => document.body.classList.toggle('overflow-hidden', v))"
        @keydown.escape.window="imageOverlay = false"
        @keydown.arrow-left.window="if (imageOverlay && gallery.length > 1) { prev() }"
        @keydown.arrow-right.window="if (imageOverlay && gallery.length > 1) { next() }"
    >
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">
                    @if ($medium->type === 'video')
                        Video bearbeiten
                    @elseif ($medium->type === 'audio')
                        Audio bearbeiten
                    @elseif ($medium->type === 'image')
                        Bild bearbeiten
                    @else
                        Medium bearbeiten
                    @endif
                </h1>
                <p class="mt-1 text-sm text-gray-600">
                    Metadaten prüfen und anpassen · Meldung: {{ $newsItem->title }}
                    @if ($medium->type === 'image' && ($prevMedia || $nextMedia))
                        <span class="hidden md:inline text-gray-400">·</span>
                        <span class="hidden md:inline text-xs text-gray-400">Tastatur: <kbd class="px-1 py-0.5 bg-gray-100 border border-gray-200 rounded text-[10px] font-mono">←</kbd> <kbd class="px-1 py-0.5 bg-gray-100 border border-gray-200 rounded text-[10px] font-mono">→</kbd> blättert (speichert davor), <kbd class="px-1 py-0.5 bg-gray-100 border border-gray-200 rounded text-[10px] font-mono">Strg</kbd>+<kbd class="px-1 py-0.5 bg-gray-100 border border-gray-200 rounded text-[10px] font-mono">S</kbd> speichert</span>
                    @endif
                </p>
            </div>
            <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                <a href="{{ route('admin.news.send', ['newsItem' => $newsItem, 'preselect_media_id' => $medium->id]) }}"
                   class="inline-flex justify-center items-center px-4 py-2 text-sm font-medium rounded-2xl border border-emerald-300 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 w-full sm:w-auto">
                    Zum Versand
                </a>
                <a href="{{ route('admin.news.edit', $newsItem) }}"
                   class="inline-flex justify-center items-center px-4 py-2 text-sm font-medium rounded-2xl border border-gray-300 text-gray-700 hover:bg-gray-50 w-full sm:w-auto">
                    Zurück zur Meldung
                </a>
            </div>
        </div>

        {{-- Status-Überblick: alle wichtigen Zustände auf einen Blick.
             Pillen sind klickbar und scrollen zur jeweiligen Detail-Sektion. --}}
        @php
            $badgeBase = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-full border text-xs font-medium';
            $badgeOk   = 'bg-emerald-50 border-emerald-200 text-emerald-800';
            $badgeWarn = 'bg-amber-50 border-amber-200 text-amber-800';
            $badgeErr  = 'bg-red-50 border-red-200 text-red-800';
            $badgeInfo = 'bg-[#092E48]/5 border-[#092E48]/30 text-[#092E48]';
            $badgeMute = 'bg-gray-50 border-gray-200 text-gray-600';
            $aiStatus  = $medium->ai_status;
            $rdStatus  = $medium->redaction_status;
        @endphp
        <div class="flex flex-wrap items-center gap-2">
            {{-- Sichtbar --}}
            @if ($medium->is_visible)
                <span class="{{ $badgeBase }} {{ $badgeOk }}">Sichtbar</span>
            @else
                <span class="{{ $badgeBase }} {{ $badgeMute }}">Nicht sichtbar</span>
            @endif

            {{-- Versand --}}
            @if ($medium->versand)
                <span class="{{ $badgeBase }} {{ $badgeOk }}">Versand</span>
            @else
                <span class="{{ $badgeBase }} {{ $badgeMute }}">Kein Versand</span>
            @endif

            @if ($medium->is_teaser)
                <span class="{{ $badgeBase }} {{ $badgeInfo }}">Teaser</span>
            @endif

            {{-- Unkenntlich (nur wenn aktiv) --}}
            @if ($medium->is_unkentlich)
                <span class="{{ $badgeBase }} {{ $badgeWarn }}">Unkenntlich</span>
            @endif

            {{-- KI-Status (nur Bilder) --}}
            @if ($medium->type === 'image')
                @if ($aiStatus === 'done')
                    <a href="#ki-panel" class="{{ $badgeBase }} {{ $badgeOk }} hover:opacity-90">KI: fertig</a>
                @elseif (in_array($aiStatus, ['queued', 'running'], true))
                    <a href="#ki-panel" class="{{ $badgeBase }} {{ $badgeWarn }} hover:opacity-90">KI: läuft …</a>
                @elseif (in_array($aiStatus, ['failed', 'error'], true))
                    <a href="#ki-panel" class="{{ $badgeBase }} {{ $badgeErr }} hover:opacity-90">KI: Fehler</a>
                @else
                    <a href="#ki-panel" class="{{ $badgeBase }} {{ $badgeMute }} hover:opacity-90">KI: offen</a>
                @endif
            @endif

            {{-- Redaction-Status (nur Bilder) --}}
            @if ($medium->type === 'image')
                @switch($rdStatus)
                    @case('done')
                        <a href="#redaction" class="{{ $badgeBase }} {{ $badgeOk }} hover:opacity-90">Redigiert</a>
                        @break
                    @case('pending')
                        <a href="#redaction" class="{{ $badgeBase }} {{ $badgeWarn }} hover:opacity-90">Redaction ausstehend</a>
                        @break
                    @case('failed')
                        <a href="#redaction" class="{{ $badgeBase }} {{ $badgeErr }} hover:opacity-90">Redaction fehlgeschlagen</a>
                        @break
                    @case('disabled')
                        <a href="#redaction" class="{{ $badgeBase }} {{ $badgeInfo }} hover:opacity-90">Anonymisierung deaktiviert</a>
                        @break
                    @default
                        <a href="#redaction" class="{{ $badgeBase }} {{ $badgeMute }} hover:opacity-90">Redaction: {{ $rdStatus ?: '—' }}</a>
                @endswitch
            @endif
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

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1">
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
                    @if ($medium->type === 'image')
                        <div class="relative">
                            <button type="button" @click="imageOverlay = true" class="block w-full text-left focus:outline-none focus:ring-2 focus:ring-[#092E48] focus:ring-inset rounded-lg overflow-hidden cursor-zoom-in" aria-label="Bild vergrößern">
                                <img src="{{ $medium->preview_url ?: $medium->url }}" alt="{{ $medium->display_name }}" class="w-full h-auto pointer-events-none">
                            </button>
                            <a href="{{ route('admin.news.media.image-editor', [$newsItem, $medium->id]) }}" class="absolute right-2 top-2 rounded-lg bg-[#092E48] px-2.5 py-1 text-xs font-semibold text-white shadow hover:bg-[#0b3858]">
                                ✎ Bearbeiten
                            </a>
                        </div>
                        {{-- Overlay: nur Bild mit Bild-ID links und Schließen rechts; Galerie Vor/Zurück und Zähler nur bei mehreren Bildern unter dem Bild --}}
                        <div x-show="imageOverlay" x-cloak
                            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60"
                            @click.self="imageOverlay = false"
                            x-transition:enter="ease-out duration-200"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            x-transition:leave="ease-in duration-150"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0">
                            <div class="flex flex-col items-center gap-3" @click.stop>
                                <template x-if="current">
                                    <div class="relative inline-block rounded-xl shadow-2xl overflow-hidden" :key="index">
                                        <img :src="current.url" :alt="current.display_name" class="block max-w-full w-auto h-auto object-contain" style="max-width: 42rem; max-height: 50vh;">
                                        <button type="button" @click="imageOverlay = false" class="absolute right-2 top-2 px-3 py-1.5 text-sm font-medium rounded bg-black/60 text-white hover:bg-black/70 focus:outline-none focus:ring-2 focus:ring-white/50">Schließen</button>
                                    </div>
                                </template>
                                <div x-show="gallery.length > 1" class="flex items-center gap-4 text-white/90 text-sm">
                                    <button type="button" @click="prev()" class="p-2 rounded-full bg-black/60 text-white hover:bg-black/70 focus:outline-none focus:ring-2 focus:ring-white/50" aria-label="Vorheriges Bild">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                    </button>
                                    <span x-text="(index + 1) + ' / ' + gallery.length"></span>
                                    <button type="button" @click="next()" class="p-2 rounded-full bg-black/60 text-white hover:bg-black/70 focus:outline-none focus:ring-2 focus:ring-white/50" aria-label="Nächstes Bild">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @elseif ($medium->type === 'video')
                        @php
                            $routePlayback = route('admin.news.media.playback', [$newsItem, $medium->id]);
                            $rel = $medium->resolveDeliveryDownloadRelativePath();
                            $presigned = $rel && config('media_storage.prefer_presigned_streaming', true)
                                ? app(\App\Services\MediaStorage::class)->temporaryPlaybackUrlForPath($rel, now()->addMinutes(120))
                                : null;
                            $playbackUrl = $presigned ?? $routePlayback;
                            $downloadPlaybackUrl = $routePlayback.(str_contains($routePlayback, '?') ? '&' : '?').'download=1';
                        @endphp
                        <div class="bg-gray-900 flex items-center justify-center">
                            <video
                                src="{{ $playbackUrl }}"
                                controls
                                playsinline
                                class="w-full max-h-[min(50vh,28rem)] object-contain"
                                preload="metadata"
                                @if($medium->preview_url) poster="{{ $medium->preview_url }}" @endif
                            >
                                Ihr Browser unterstützt das Video-Tag nicht.
                                <a href="{{ $downloadPlaybackUrl }}" class="text-white underline">Video herunterladen</a>
                            </video>
                        </div>
                    @elseif ($medium->type === 'audio')
                        @php
                            $routePlaybackAudio = route('admin.news.media.playback', [$newsItem, $medium->id]);
                            $relAudio = $medium->resolveDeliveryDownloadRelativePath();
                            $presignedAudio = $relAudio && config('media_storage.prefer_presigned_streaming', true)
                                ? app(\App\Services\MediaStorage::class)->temporaryPlaybackUrlForPath($relAudio, now()->addMinutes(120))
                                : null;
                            $playbackUrlAudio = $presignedAudio ?? $routePlaybackAudio;
                        @endphp
                        <div class="p-4 bg-slate-50 border-b border-gray-100">
                            <audio src="{{ $playbackUrlAudio }}" controls class="w-full" preload="metadata"></audio>
                        </div>
                    @else
                        <div class="aspect-video bg-gray-100 flex items-center justify-center text-gray-500 text-sm text-center px-3">Keine Vorschau für diesen Medientyp.</div>
                    @endif
                    @if ($medium->is_unkentlich)
                        <div class="px-3 py-2 bg-amber-50 border-t border-amber-200 text-amber-800 text-sm font-medium">Als unkenntlich markiert</div>
                    @endif
                    {{-- Pfeile: Vorheriges / Nächstes Bild – Autospeichern im Hintergrund; beim Klick wird sicher gespeichert und gewechselt --}}
                    @if ($medium->type === 'image' && ($prevMedia || $nextMedia))
                        <div class="flex items-center justify-between gap-2 px-3 py-3 border-t border-gray-200 bg-gray-50">
                            @if ($prevMedia)
                                @php $prevUrl = route('admin.news.media.edit', [$newsItem, $prevMedia]); @endphp
                                <a id="media-edit-nav-prev" href="{{ $prevUrl }}"
                                   class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-xl border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 hover:border-[#092E48] hover:text-[#092E48] transition-colors save-and-go"
                                   data-redirect="{{ $prevUrl }}"
                                   aria-label="Vorheriges Bild bearbeiten (speichert und wechselt; Eingaben werden auch automatisch gespeichert)">
                                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                    <span>Vorheriges Bild</span>
                                </a>
                            @else
                                <span></span>
                            @endif
                            @if ($nextMedia)
                                @php $nextUrl = route('admin.news.media.edit', [$newsItem, $nextMedia]); @endphp
                                <a id="media-edit-nav-next" href="{{ $nextUrl }}"
                                   class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-xl border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 hover:border-[#092E48] hover:text-[#092E48] transition-colors save-and-go"
                                   data-redirect="{{ $nextUrl }}"
                                   aria-label="Nächstes Bild bearbeiten (speichert und wechselt; Eingaben werden auch automatisch gespeichert)">
                                    <span>Nächstes Bild</span>
                                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </a>
                            @else
                                <span></span>
                            @endif
                        </div>
                        <script>
                            document.querySelectorAll('a.save-and-go').forEach(function(el) {
                                el.addEventListener('click', function(e) {
                                    e.preventDefault();
                                    var form = document.getElementById('mediaMetaForm');
                                    if (form) {
                                        document.getElementById('redirect_after').value = this.getAttribute('data-redirect') || '';
                                        form.submit();
                                    } else {
                                        window.location.href = this.getAttribute('href');
                                    }
                                });
                            });
                        </script>
                    @endif
                </div>
            </div>

            <div class="lg:col-span-2 space-y-6" x-data="{
                ai: @js($medium->ai_payload),
                applySuggestions() {
                    if (!this.ai) return;
                    const map = {
                        image_title: 'image_title',
                        photographer: 'photographer',
                        caption: 'caption',
                        description: 'description',
                        media_keywords: 'media_keywords',
                    };
                    if (this.ai.image_title && !document.getElementById('image_title')?.value) {
                        document.getElementById('image_title').value = this.ai.image_title;
                    }
                    if (Object.prototype.hasOwnProperty.call(this.ai, 'photographer')
                        && !document.getElementById('photographer')?.value
                        && this.ai.photographer) {
                        document.getElementById('photographer').value = this.ai.photographer;
                    }
                    if (this.ai.caption && !document.getElementById('caption')?.value) {
                        const capEl = document.getElementById('caption');
                        capEl.value = this.ai.caption;
                        capEl.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                    if (Array.isArray(this.ai.keywords)
                        && this.ai.keywords.length
                        && !document.getElementById('media_keywords')?.value) {
                        document.getElementById('media_keywords').value = this.ai.keywords.join(', ');
                    }
                    if (Object.prototype.hasOwnProperty.call(this.ai, 'description')
                        && !document.getElementById('description')?.value
                        && this.ai.description) {
                        document.getElementById('description').value = this.ai.description;
                    }
                }
            }">
                <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-3 border-b border-gray-200 bg-[#092E48]/5">
                        <h2 class="text-sm font-semibold tracking-wide text-gray-900 uppercase">Bildinformationen</h2>
                    </div>
                    @php
                        $meta = $metadataFromFile ?? [];
                        $captionInitialForAlpine = old('caption', $medium->caption ?? $meta['caption'] ?? '');
                        $captionLenInitial = mb_strlen((string) $captionInitialForAlpine, 'UTF-8');
                    @endphp
                    @if (! empty($refineCaptionFromEventAi))
                        <form id="mediaAiRefineForm" method="post" action="{{ route('admin.news.media.request-ai', [$newsItem, $medium]) }}" class="hidden" aria-hidden="true">
                            @csrf
                            <input type="hidden" name="ai_refinement_from_planned_event" value="1">
                        </form>
                    @endif
                    <form
                        id="mediaMetaForm"
                        method="post"
                        action="{{ route('admin.news.media.update', [$newsItem, $medium]) }}"
                        class="p-6 space-y-4"
                        x-data="{ captionLen: {{ (int) $captionLenInitial }} }"
                    >
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="redirect_after" id="redirect_after" value="">
                        @php
                            $authorCredit = trim((string) ($newsItem->author_credit ?? ''));
                            $creditOptions = $newsItem->images
                                ->pluck('photographer')
                                ->filter(fn ($v) => trim((string) $v) !== '')
                                ->map(fn ($v) => trim((string) $v))
                                ->unique()
                                ->values()
                                ->toArray();
                            $userNames = $creditUsers->pluck('name')->map(fn ($n) => trim((string) $n))->filter()->unique()->all();
                            $peerCredits = array_values(array_filter($creditOptions, function ($opt) use ($authorCredit, $userNames) {
                                if ($opt === '') {
                                    return false;
                                }
                                if ($authorCredit !== '' && $opt === $authorCredit) {
                                    return false;
                                }
                                if (in_array($opt, $userNames, true)) {
                                    return false;
                                }

                                return true;
                            }));
                            $defaultPhotographer = $medium->photographer ?? ($meta['photographer'] ?? '');
                            if (trim((string) $defaultPhotographer) === '' && $authorCredit !== '') {
                                $defaultPhotographer = $authorCredit;
                            }
                            // Neu: EXIF/IPTC Aufnahmezeit aus Datei (readonly), Ort/Rechte-Defaults
                            $exifCapture = $meta['datetime_original'] ?? null;
                            $hasExifCapture = filled($exifCapture);
                            $storedCapture = $medium->capture_time?->format('Y-m-d\TH:i');
                            $defaultCaptureEditable = old('capture_time', $storedCapture ?? '');
                            $plannedVenueCity = filled($newsItem->plannedEvent?->venue_city ?? null)
                                ? trim((string) $newsItem->plannedEvent->venue_city)
                                : null;
                            $plannedVenueState = filled($newsItem->plannedEvent?->venue_state ?? null)
                                ? trim((string) $newsItem->plannedEvent->venue_state)
                                : null;
                            $plannedVenueCountry = filled($newsItem->plannedEvent?->venue_country ?? null)
                                ? trim((string) $newsItem->plannedEvent->venue_country)
                                : null;
                            $plannedVenueCc = filled($newsItem->plannedEvent?->venue_country_code ?? null)
                                ? strtoupper(trim((string) $newsItem->plannedEvent->venue_country_code))
                                : null;
                            $defaultCity = old('city', $medium->city ?? $meta['city'] ?? $plannedVenueCity ?? $newsItem->city);
                            $defaultState = old('state', $medium->state ?? $meta['state'] ?? $plannedVenueState ?? $newsItem->federal_state);
                            $defaultCountry = old('country', $medium->country ?? $meta['country'] ?? $plannedVenueCountry ?? $newsItem->country ?? 'Deutschland');
                            $defaultCountryCode = old('country_code', $medium->country_code ?? $meta['country_code'] ?? $plannedVenueCc ?? 'DE');
                            $defaultCredit = old('credit', $medium->credit ?? $meta['credit'] ?? config('newsdesk.iptc_credit'));
                            $defaultCopyright = old('copyright', $medium->copyright ?? $meta['copyright'] ?? config('newsdesk.iptc_copyright'));
                            $defaultSource = old('source', $medium->source ?? $meta['source'] ?? config('newsdesk.iptc_source'));
                            $eventMotivPicks = $plannedEventMotivPicks ?? [];
                            $eventMotivLabel = $plannedEventMotivLabel ?? null;
                        @endphp

                        {{-- Block 1: Bildinformationen --}}
                        <div>
                            <label for="image_title" class="block text-sm font-medium text-gray-700 mb-1">Titel</label>
                            <input type="text" name="image_title" id="image_title"
                                value="{{ old('image_title', $medium->image_title ?? $meta['title'] ?? '') }}"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                                maxlength="255" placeholder="Aus Metadaten übernommen, wenn vorhanden">
                            <p class="mt-1 text-xs text-gray-500">IPTC Object Name (2#005), sachlicher Bildtitel.</p>
                        </div>
                        <div class="space-y-2">
                            <label for="photographer_quick_pick" class="block text-sm font-medium text-gray-700 mb-1">Namenvorschläge</label>
                            <select
                                id="photographer_quick_pick"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] text-sm"
                                autocomplete="off"
                                aria-describedby="media-edit-photographer-hint"
                            >
                                <option value="">Anderen Namen manuell eintragen …</option>
                                @if ($authorCredit !== '')
                                    <optgroup label="Credit der Meldung (Autor / Byline)">
                                        <option value="{{ $authorCredit }}">{{ $authorCredit }}</option>
                                    </optgroup>
                                @endif
                                @if ($creditUsers->isNotEmpty())
                                    <optgroup label="Benutzer">
                                        @foreach ($creditUsers as $u)
                                            @php $uname = trim((string) $u->name); @endphp
                                            @if ($uname !== '')
                                                <option value="{{ $uname }}">{{ $uname }}</option>
                                            @endif
                                        @endforeach
                                    </optgroup>
                                @endif
                                @if (! empty($peerCredits))
                                    <optgroup label="Weitere Credits in dieser Meldung">
                                        @foreach ($peerCredits as $opt)
                                            <option value="{{ $opt }}">{{ $opt }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            </select>
                            <label for="photographer" class="block text-sm font-medium text-gray-700 mb-1">Fotograf</label>
                            <input type="text" name="photographer" id="photographer"
                                value="{{ old('photographer', $defaultPhotographer) }}"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                                maxlength="255"
                                placeholder="Name oder Credit; optional leer für Vorgabe"
                                aria-describedby="media-edit-photographer-hint">
                            <p id="media-edit-photographer-hint" class="mt-1 text-xs text-gray-500">Gespeichert wird nur dieses Feld (IPTC By-line 2#080). Die Liste darüber trägt einen Vorschlag ein. Leer lassen: Vorgabe aus Meldung (Autor/Credit) oder aus den Bildmetadaten.</p>
                        </div>
                        <script>
                            (function () {
                                var sel = document.getElementById('photographer_quick_pick');
                                var inp = document.getElementById('photographer');
                                if (!sel || !inp) {
                                    return;
                                }
                                function syncSelectFromInput() {
                                    var v = String(inp.value || '').trim();
                                    if (!v) {
                                        sel.value = '';
                                        return;
                                    }
                                    for (var i = 0; i < sel.options.length; i++) {
                                        var o = sel.options[i];
                                        if (o.disabled || o.value === '') {
                                            continue;
                                        }
                                        if (String(o.value).trim() === v) {
                                            sel.selectedIndex = i;
                                            return;
                                        }
                                    }
                                    sel.value = '';
                                }
                                sel.addEventListener('change', function () {
                                    if (sel.value !== '') {
                                        inp.value = sel.value;
                                        inp.dispatchEvent(new Event('input', { bubbles: true }));
                                    }
                                });
                                inp.addEventListener('input', syncSelectFromInput);
                                inp.addEventListener('change', syncSelectFromInput);
                                syncSelectFromInput();
                            })();
                        </script>
                        <div>
                            <label for="caption" class="block text-sm font-medium text-gray-700 mb-1">Bildunterschrift <span class="text-xs font-normal text-gray-500">(IPTC Caption 2#120)</span></label>
                            @if (! empty($eventMotivPicks))
                                <div class="mb-2">
                                    <label for="caption_event_motiv_pick" class="block text-xs font-medium text-gray-600 mb-1">
                                        Künstler / Motiv aus Veranstaltung @if ($eventMotivLabel)(„{{ $eventMotivLabel }}“) @endif in die Unterschrift einfügen
                                    </label>
                                    <input
                                        type="search"
                                        id="caption_event_motiv_filter"
                                        placeholder="Startnummer oder Team suchen …"
                                        class="mb-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] text-sm"
                                        autocomplete="off"
                                    />
                                    <select
                                        id="caption_event_motiv_pick"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] text-sm"
                                        autocomplete="off"
                                        size="8"
                                    >
                                        <option value="">— Künstler wählen, Einfügen an Cursorposition (nicht Fotograf) —</option>
                                        @foreach ($eventMotivPicks as $opt)
                                            @php
                                                $pick = is_array($opt) ? $opt : ['value' => $opt, 'label' => $opt];
                                            @endphp
                                            <option value="{{ $pick['value'] }}">{{ $pick['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <textarea name="caption" id="caption" rows="4" maxlength="1800"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                                placeholder="Zuerst: sachliche Motivbeschreibung (Wer/Was/Kontext). Am Ende: Ort, TT.MM.JJJJ"
                                x-ref="captionField"
                                @input="captionLen = $event.target.value.length">{{ old('caption', $medium->caption ?? $meta['caption'] ?? '') }}</textarea>
                            <p class="mt-1 text-xs text-gray-500" x-text="'Noch ' + (1800 - captionLen) + ' von 1.800 Zeichen'"></p>
                            @if (! empty($eventMotivPicks))
                                <script>
                                    (function () {
                                        function bindCaptionEventMotivPick() {
                                            var sel = document.getElementById('caption_event_motiv_pick');
                                            var filter = document.getElementById('caption_event_motiv_filter');
                                            var ta = document.getElementById('caption');
                                            if (!sel || !ta) {
                                                return;
                                            }
                                            if (filter) {
                                                filter.addEventListener('input', function () {
                                                    var q = String(filter.value || '').trim().toLowerCase();
                                                    Array.prototype.forEach.call(sel.options, function (opt, idx) {
                                                        if (idx === 0) {
                                                            opt.hidden = false;
                                                            return;
                                                        }
                                                        var text = String(opt.textContent || '').toLowerCase();
                                                        opt.hidden = q !== '' && text.indexOf(q) === -1;
                                                    });
                                                });
                                            }
                                            sel.addEventListener('change', function () {
                                                var v = String(sel.value || '').trim();
                                                if (v === '') {
                                                    return;
                                                }
                                                var start = typeof ta.selectionStart === 'number' ? ta.selectionStart : ta.value.length;
                                                var end = typeof ta.selectionEnd === 'number' ? ta.selectionEnd : ta.value.length;
                                                var before = ta.value.substring(0, start);
                                                var after = ta.value.substring(end);
                                                var insert = v;
                                                if (before.length > 0 && !/\s$/.test(before)) {
                                                    insert = ' ' + insert;
                                                }
                                                if (after.length > 0 && !/^\s/.test(after)) {
                                                    insert = insert + ' ';
                                                }
                                                var combined = before + insert + after;
                                                if (combined.length > 1800) {
                                                    combined = combined.substring(0, 1800);
                                                }
                                                ta.value = combined;
                                                var newPos = Math.min(before.length + insert.length, combined.length);
                                                ta.selectionStart = ta.selectionEnd = newPos;
                                                ta.focus();
                                                ta.dispatchEvent(new Event('input', { bubbles: true }));
                                                sel.value = '';
                                            });
                                        }
                                        if (document.readyState === 'loading') {
                                            document.addEventListener('DOMContentLoaded', bindCaptionEventMotivPick);
                                        } else {
                                            bindCaptionEventMotivPick();
                                        }
                                    })();
                                </script>
                            @endif
                            <p class="text-xs text-gray-500">Redaktionsüblich: zuerst Motiv/Was passiert, <span class="font-medium text-gray-700">Ort und Datum nur einmal am Satzende</span> (nach einem Punkt), z. B. „… vor Publikum. Köln, LANXESS arena, 09.05.2026“ – nicht „Ort, Datum: …“ am Anfang und nicht doppeln.</p>
                            @if (! empty($refineCaptionFromEventAi))
                                <input type="hidden" form="mediaAiRefineForm" name="caption_context" id="refine_caption_context" value="">
                                <input type="hidden" form="mediaAiRefineForm" name="keywords_context" id="refine_keywords_context" value="">
                                <div class="mt-3 pt-3 border-t border-dashed border-gray-200 space-y-2">
                                    <button
                                        type="submit"
                                        form="mediaAiRefineForm"
                                        class="inline-flex items-center gap-2 px-3 py-2 text-xs font-medium rounded-xl border border-[#092E48] bg-white text-[#092E48] hover:bg-[#092E48]/5"
                                    >
                                        Unterschrift &amp; Schlagwörter per KI überarbeiten
                                    </button>
                                    <p class="text-xs text-gray-500">Nutzt die <span class="font-medium text-gray-700">zugewiesene Veranstaltung</span> (Teams, Kontext) sowie die aktuelle Unterschrift und die Schlagwörter aus dem IPTC-Block unten. Fotograf bleibt unverändert. Die Bearbeitung läuft <span class="font-medium text-gray-700">im Hintergrund</span> (Seite danach neu laden oder kurz warten).</p>
                                </div>
                                <script>
                                    (function () {
                                        var refineForm = document.getElementById('mediaAiRefineForm');
                                        if (!refineForm) {
                                            return;
                                        }
                                        refineForm.addEventListener('submit', function () {
                                            var cap = document.getElementById('caption');
                                            var kw = document.getElementById('media_keywords');
                                            var hc = document.getElementById('refine_caption_context');
                                            var hk = document.getElementById('refine_keywords_context');
                                            if (hc) {
                                                hc.value = cap ? String(cap.value || '') : '';
                                            }
                                            if (hk) {
                                                hk.value = kw ? String(kw.value || '') : '';
                                            }
                                        });
                                    })();
                                </script>
                            @endif
                        </div>

                        @if (! $medium->isImage())
                            <div>
                                <label for="media_keywords" class="block text-sm font-medium text-gray-700 mb-1">Schlagwörter</label>
                                <input type="text" name="media_keywords" id="media_keywords"
                                    value="{{ old('media_keywords', $medium->media_keywords ?? $meta['keywords'] ?? '') }}"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                                    maxlength="512" placeholder="Kommagetrennt">
                                <p class="mt-1 text-xs text-gray-500">Kommagetrennt; werden intern verarbeitet.</p>
                            </div>
                        @endif

                        @if ($medium->isImage())
                            {{-- Block 2: IPTC-Daten (neu) --}}
                            <div class="pt-4 border-t border-gray-200 space-y-4">
                                <h3 class="text-sm font-semibold text-gray-900">IPTC-Daten</h3>
                                <div>
                                    <label for="media_keywords" class="block text-sm font-medium text-gray-700 mb-1">Schlagwörter</label>
                                    <input type="text" name="media_keywords" id="media_keywords"
                                        value="{{ old('media_keywords', $medium->media_keywords ?? $meta['keywords'] ?? '') }}"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                                        maxlength="512" placeholder="Kommagetrennt, aus IPTC-Keywords übernommen">
                                    <p class="mt-1 text-xs text-gray-500">Kommagetrennt eingeben; werden intern verarbeitet und als IPTC-Liste (2#025) geschrieben.</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Aufnahmezeit</label>
                                    @if ($hasExifCapture)
                                        <input type="text" readonly
                                            class="block w-full rounded-md border border-gray-200 bg-gray-50 text-gray-700 shadow-sm px-3 py-2 text-sm"
                                            value="{{ $exifCapture }}">
                                        <input type="hidden" name="capture_time" value="{{ old('capture_time', $exifCapture) }}">
                                        <p class="mt-1 text-xs text-gray-500">Aus EXIF/IPTC der Datei (nur lesend). Wird beim Speichern übernommen.</p>
                                    @else
                                        <input type="datetime-local" name="capture_time" id="capture_time"
                                            value="{{ $defaultCaptureEditable }}"
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                                        <p class="mt-1 text-xs text-gray-500">Keine Aufnahmezeit in der Datei: bitte manuell setzen (nicht das Meldungsdatum verwenden). IPTC 2#055 / 2#060.</p>
                                    @endif
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label for="city" class="block text-sm font-medium text-gray-700 mb-1">Stadt</label>
                                        <input type="text" name="city" id="city" value="{{ $defaultCity }}"
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                                            maxlength="255" placeholder="z. B. Bergheim">
                                        <p class="mt-1 text-xs text-gray-500">IPTC City (2#090).</p>
                                    </div>
                                    <div>
                                        <label for="state" class="block text-sm font-medium text-gray-700 mb-1">Bundesland / Region</label>
                                        <input type="text" name="state" id="state" value="{{ $defaultState }}"
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                                            maxlength="255" placeholder="z. B. Nordrhein-Westfalen">
                                        <p class="mt-1 text-xs text-gray-500">IPTC Province/State (2#095).</p>
                                    </div>
                                    <div>
                                        <label for="country" class="block text-sm font-medium text-gray-700 mb-1">Land</label>
                                        <input type="text" name="country" id="country" value="{{ $defaultCountry }}"
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                                            maxlength="255" placeholder="Deutschland">
                                        <p class="mt-1 text-xs text-gray-500">IPTC Country (2#101).</p>
                                    </div>
                                    <div>
                                        <label for="country_code" class="block text-sm font-medium text-gray-700 mb-1">Ländercode</label>
                                        <input type="text" name="country_code" id="country_code" value="{{ $defaultCountryCode }}"
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                                            maxlength="2" placeholder="DE">
                                        <p class="mt-1 text-xs text-gray-500">ISO 3166-1 Alpha-2, z. B. DE (IPTC 2#100).</p>
                                    </div>
                                </div>
                            </div>

                            {{-- Block 3: Rechte & Quelle (neu) --}}
                            <div class="pt-4 border-t border-gray-200 space-y-4">
                                <h3 class="text-sm font-semibold text-gray-900">Rechte &amp; Quelle</h3>
                                <div>
                                    <label for="credit" class="block text-sm font-medium text-gray-700 mb-1">Credit</label>
                                    <input type="text" name="credit" id="credit" value="{{ $defaultCredit }}"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                                        maxlength="255" placeholder="z. B. Erftkreis News">
                                    <p class="mt-1 text-xs text-gray-500">IPTC Credit (2#110) – z. B. Name der Agentur/Redaktion.</p>
                                </div>
                                <div>
                                    <label for="copyright" class="block text-sm font-medium text-gray-700 mb-1">Copyright</label>
                                    <input type="text" name="copyright" id="copyright" value="{{ $defaultCopyright }}"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                                        maxlength="512" placeholder="© …">
                                    <p class="mt-1 text-xs text-gray-500">IPTC Copyright Notice (2#116).</p>
                                </div>
                                <div>
                                    <label for="source" class="block text-sm font-medium text-gray-700 mb-1">Quelle</label>
                                    <input type="text" name="source" id="source" value="{{ $defaultSource }}"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                                        maxlength="255" placeholder="z. B. Erftkreis News Redaktion">
                                    <p class="mt-1 text-xs text-gray-500">IPTC Source (2#115).</p>
                                </div>
                            </div>
                        @endif

                        {{-- Block 4: Interne Daten (nicht IPTC-Pflichtfeld) --}}
                        <div class="pt-4 border-t border-gray-200">
                            <h3 class="text-sm font-semibold text-gray-900 mb-2">Interne Daten</h3>
                            <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Beschreibung <span class="text-xs font-normal text-gray-500">(intern)</span></label>
                            <p class="text-xs text-gray-500 mb-1">Internes Redaktionsfeld, nicht die offizielle IPTC-Bildunterschrift (dafür „Bildunterschrift“ oben).</p>
                            <textarea name="description" id="description" rows="4"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                                placeholder="z. B. aus WordPress-Import">{{ old('description', $medium->description ?? $meta['description'] ?? '') }}</textarea>
                        </div>

                        <div class="pt-4 border-t border-gray-200">
                            <h3 class="text-sm font-medium text-gray-700 mb-2">Optionen</h3>
                            <div class="flex flex-wrap gap-6">
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="hidden" name="is_visible" value="0">
                                    <input type="checkbox" name="is_visible" value="1" class="rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]"
                                        @checked($medium->is_visible)>
                                    <span class="text-sm text-gray-700">Sichtbar</span>
                                </label>
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="hidden" name="versand" value="0">
                                    <input type="checkbox" name="versand" value="1" class="rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]"
                                        @checked($medium->versand)>
                                    <span class="text-sm text-gray-700">Versand</span>
                                </label>
                            </div>
                        </div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-2xl text-white bg-[#092E48] hover:bg-[#0b3858] min-h-[2.75rem]">
                            Metadaten speichern
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center px-4 py-2 ml-2 text-sm font-medium rounded-2xl border border-emerald-300 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 min-h-[2.75rem]"
                            onclick="document.getElementById('redirect_after').value='{{ route('admin.news.send', ['newsItem' => $newsItem, 'preselect_media_id' => $medium->id]) }}'; document.getElementById('mediaMetaForm').submit();"
                        >
                            Speichern &amp; zum Versand
                        </button>
                    </form>
                </div>

                <div class="bg-white rounded-2xl border border-emerald-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-3 border-b border-emerald-200 bg-emerald-50">
                        <h2 class="text-sm font-semibold tracking-wide text-emerald-900 uppercase">Sofortversand</h2>
                    </div>
                    <form method="post" action="{{ route('admin.news.media.quick-send', [$newsItem, $medium->id]) }}" class="p-6 space-y-4">
                        @csrf
                        <p class="text-sm text-gray-600">
                            Für eilige Fälle: Ziel wählen und optional kurze Einsatz-Info mitschicken.
                        </p>
                        <div>
                            <label for="quick-send-destination" class="block text-sm font-medium text-gray-700 mb-1">Versandziel</label>
                            <select name="delivery_destination_id" id="quick-send-destination" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                                <option value="">— Ziel wählen —</option>
                                @foreach (($quickSendDestinations ?? collect()) as $d)
                                    <option value="{{ $d->id }}" @selected((string) old('delivery_destination_id') === (string) $d->id)>
                                        {{ $d->label }}@if($d->organization) · {{ $d->organization->name }}@endif
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Optional kannst du statt Ziel auch manuelle E-Mail-Adressen nutzen.</p>
                        </div>
                        <div>
                            <label for="quick-send-recipient-email" class="block text-sm font-medium text-gray-700 mb-1">Manuelle E-Mail (optional)</label>
                            <input
                                type="text"
                                name="recipient_email"
                                id="quick-send-recipient-email"
                                value="{{ old('recipient_email') }}"
                                placeholder="z. B. desk@agentur.de, bildredaktion@..."
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                            >
                        </div>
                        <div>
                            <label for="quick-send-subject" class="block text-sm font-medium text-gray-700 mb-1">Betreff (optional)</label>
                            <input
                                type="text"
                                name="subject"
                                id="quick-send-subject"
                                value="{{ old('subject') }}"
                                maxlength="180"
                                placeholder="z. B. EIL: Einsatzbilder Kerpen – 2 Fotos"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                            >
                        </div>
                        <div>
                            <label for="quick-send-editor-note" class="block text-sm font-medium text-gray-700 mb-1">Info für Redaktion</label>
                            <textarea
                                name="editor_note"
                                id="quick-send-editor-note"
                                rows="4"
                                maxlength="3000"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                                placeholder="Kurze Einsatz-Info, was wichtig ist, Hinweise zur Nutzung ..."
                            >{{ old('editor_note') }}</textarea>
                        </div>
                        <div class="pt-1">
                            <button
                                type="submit"
                                class="inline-flex w-full sm:w-auto items-center justify-center px-4 py-2 text-sm font-semibold rounded-2xl border-2 border-[#092E48] bg-[#092E48] text-white hover:bg-[#0b3858] shadow-sm min-h-[2.75rem] tracking-wide focus:outline-none focus:ring-2 focus:ring-[#092E48]/40"
                                style="background-color:#092E48;color:#ffffff;"
                            >
                                Sofortversand per Mail starten
                            </button>
                            <p class="mt-1 text-xs text-gray-500">Button bleibt aktiv, sobald Ziel oder E-Mail eingetragen ist.</p>
                        </div>
                    </form>
                </div>

                @if($medium->type === 'image')
                @php
                    $redactionBoxes = $medium->redaction_boxes ?? [];
                    $redactionBlockedByPlannedEvent = \Illuminate\Support\Facades\Schema::hasColumn('news_items', 'planned_event_id')
                        && filled($newsItem->planned_event_id);
                @endphp
                <div id="redaction" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden scroll-mt-4" @if(! $redactionBlockedByPlannedEvent) x-data="{
                    boxes: @js($redactionBoxes),
                    method: @js($medium->redaction_method ?? 'blur'),
                    newBox: [0, 0, 100, 50],
                    removeBox(i) { this.boxes.splice(i, 1); },
                    addBox() {
                        const n = this.newBox;
                        if (n[2] > n[0] && n[3] > n[1]) this.boxes.push([Number(n[0]), Number(n[1]), Number(n[2]), Number(n[3])]);
                    }
                }" @endif>
                    <div class="px-6 py-3 border-b border-gray-200 bg-[#092E48]/5 flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold tracking-wide text-gray-900 uppercase">Bereiche unkenntlich machen (Kennzeichen &amp; Gesichter)</h2>
                        @php $rs = $medium->redaction_status; @endphp
                        @if($redactionBlockedByPlannedEvent)
                            <span class="text-xs font-medium text-gray-600 bg-gray-100 px-2.5 py-1 rounded-full border border-gray-300">Deaktiviert (Veranstaltung zugewiesen)</span>
                        @elseif($rs === 'done')
                            <span class="text-xs font-medium text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full border border-emerald-200">Redigiert</span>
                        @elseif($rs === 'pending')
                            <span class="text-xs font-medium text-amber-700 bg-amber-50 px-2.5 py-1 rounded-full border border-amber-200">Ausstehend</span>
                        @elseif($rs === 'failed')
                            <span class="text-xs font-medium text-red-700 bg-red-50 px-2.5 py-1 rounded-full border border-red-200">Fehlgeschlagen</span>
                        @elseif($rs === 'disabled')
                            <span class="text-xs font-medium text-[#092E48] bg-[#092E48]/10 px-2.5 py-1 rounded-full border border-[#092E48]/30">Keine Anonymisierung (z.&nbsp;B. Feuerwehr/Polizei)</span>
                        @else
                            <span class="text-xs font-medium text-gray-500 bg-gray-50 px-2.5 py-1 rounded-full border border-gray-200">Legacy</span>
                        @endif
                    </div>
                    <div class="p-6 space-y-4">
                        @if($redactionBlockedByPlannedEvent)
                        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 space-y-2">
                            <p class="font-medium">Diese Funktion ist bei zugewiesener geplanter Veranstaltung nicht verfügbar.</p>
                            <p>Die Anonymisierung von Kennzeichen und Gesichtern ist gesperrt, solange dieser Meldung eine Veranstaltung aus der Planung zugeordnet ist. Entfernen Sie die Zuweisung in der Meldungsbearbeitung (Feld „Geplante Veranstaltung“), falls Sie hier arbeiten müssen.</p>
                        </div>
                        @if($medium->redacted_url)
                            <p class="text-xs text-gray-500">Bisherige redigierte Vorschau (nur Anzeige):</p>
                            <img src="{{ $medium->redacted_url }}" alt="Redigierte Version" class="max-w-full h-auto rounded border border-gray-200 max-h-48">
                        @endif
                        @else
                        <p class="text-sm text-gray-600">Öffentlich wird nur die redigierte Version ausgeliefert. Original bleibt unverändert archiviert. Kennzeichen werden bei vorhandenem Modell automatisch erkannt; Gesichter und weitere Bereiche können Sie manuell als Box hinzufügen. <strong>Ausnahme:</strong> Feuerwehr- und Polizeifahrzeuge können von der Anonymisierung ausgenommen werden.</p>
                        <form method="post" action="{{ route('admin.news.media.redaction.update', [$newsItem, $medium]) }}" class="mb-4">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="redaction_disabled" value="0">
                            <input type="hidden" name="boxes" value="[]">
                            <input type="hidden" name="method" value="blur">
                            <label class="inline-flex items-center gap-2 cursor-pointer p-3 rounded-lg border {{ $rs === 'disabled' ? 'border-[#092E48] bg-[#092E48]/5' : 'border-gray-200 bg-gray-50' }}">
                                <input type="checkbox" name="redaction_disabled" value="1" class="h-4 w-4 rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]"
                                    {{ $rs === 'disabled' ? 'checked' : '' }}>
                                <span class="text-sm font-medium text-gray-900">Keine Kennzeichen-Anonymisierung (z.&nbsp;B. Feuerwehr, Polizei)</span>
                            </label>
                            <p class="text-xs text-gray-500 mt-1 ml-1">Wenn aktiviert, wird das Bild unverändert (Original) ausgeliefert. Nur für offizielle Fahrzeuge nutzen.</p>
                            <div class="mt-2">
                                <button type="submit" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-xl border border-gray-300 text-gray-700 bg-white hover:bg-gray-50">Einstellung speichern</button>
                            </div>
                        </form>
                        @if($rs === 'disabled')
                            <p class="text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2">Dieses Bild wird <strong>ohne</strong> Kennzeichen-Blur ausgeliefert (z.&nbsp;B. Feuerwehr/Polizei).</p>
                        @endif
                        @if($rs === 'failed')
                            <div class="text-sm text-red-800 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
                                @if(!empty($medium->redaction_error))
                                    @if(str_contains(mb_strtolower($medium->redaction_error), 'keine boxen') || str_contains(mb_strtolower($medium->redaction_error), 'no boxes'))
                                        <p class="mb-0">Es wurden keine Bereiche automatisch erkannt. Bitte <strong>Boxen manuell</strong> eintragen (x1,y1 = linke obere, x2,y2 = rechte untere Ecke in Pixel) und danach „Speichern &amp; Redaction neu rendern“ ausführen.</p>
                                    @else
                                        <p class="mb-0"><strong>Grund:</strong> {{ $medium->redaction_error }} Bei weiteren Fehlern: <code class="text-xs bg-red-100 px-1">storage/logs/laravel.log</code> prüfen.</p>
                                    @endif
                                @else
                                    <p class="mb-0">Noch kein Lauf mit aktuellem Stand. „Speichern &amp; Redaction neu rendern“ klicken, 1–2 Min. warten (Queue), Seite neu laden. Sonst Queue-Worker prüfen oder <code class="text-xs bg-red-100 px-1">storage/logs/laravel.log</code> (ProcessMediaRedaction).</p>
                                @endif
                            </div>
                        @endif
                        @if($medium->redacted_url)
                            <p class="text-xs text-gray-500">Redigierte Vorschau:</p>
                            <img src="{{ $medium->redacted_url }}" alt="Redigierte Version" class="max-w-full h-auto rounded border border-gray-200 max-h-48">
                        @endif
                        @if($rs !== 'disabled')
                        <form method="post" action="{{ route('admin.news.media.redaction.update', [$newsItem, $medium]) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="boxes" :value="JSON.stringify(boxes)">
                            <input type="hidden" name="method" :value="method">
                            <div class="flex flex-wrap gap-2 items-center">
                                <label class="text-sm font-medium text-gray-700">Methode:</label>
                                <select x-model="method" class="rounded border-gray-300 text-sm">
                                    <option value="blur">Blur</option>
                                    <option value="black_box">Schwarze Maske</option>
                                </select>
                            </div>
                            <div class="mt-3">
                                <p class="text-sm font-medium text-gray-700 mb-2">Erkannte Bereiche (Boxen)</p>
                                <ul class="space-y-2" x-show="boxes.length">
                                    <template x-for="(b, i) in boxes" :key="i">
                                        <li class="flex items-center gap-2 text-sm">
                                            <span class="font-mono text-gray-600" x-text="'[' + b[0].toFixed(0) + ',' + b[1].toFixed(0) + ',' + b[2].toFixed(0) + ',' + b[3].toFixed(0) + ']'"></span>
                                            <button type="button" @click="removeBox(i)" class="text-red-600 hover:text-red-800 text-xs py-1 px-2 rounded border border-red-200">Entfernen</button>
                                        </li>
                                    </template>
                                </ul>
                                <p x-show="!boxes.length" class="text-xs text-gray-500">Keine Boxen. Auto-Redaction ausführen oder unten manuell hinzufügen.</p>
                            </div>
                            <details class="mt-3 group" x-data="{ open: false }">
                                <summary class="p-3 bg-gray-50 rounded-lg border border-gray-200 cursor-pointer list-none flex items-center justify-between gap-2 hover:bg-gray-100">
                                    <span class="text-xs font-medium text-gray-700">Box manuell hinzufügen (Pixel: x1, y1, x2, y2)</span>
                                    <span class="text-gray-400 group-open:rotate-180 transition-transform shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </span>
                                </summary>
                                <div class="mt-2 p-3 bg-gray-50 rounded-lg border border-gray-200 border-t-0 rounded-t-none">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <input type="number" x-model.number="newBox[0]" placeholder="x1" class="w-20 rounded border-gray-300 text-sm" min="0" step="1">
                                        <input type="number" x-model.number="newBox[1]" placeholder="y1" class="w-20 rounded border-gray-300 text-sm" min="0" step="1">
                                        <input type="number" x-model.number="newBox[2]" placeholder="x2" class="w-20 rounded border-gray-300 text-sm" min="0" step="1">
                                        <input type="number" x-model.number="newBox[3]" placeholder="y2" class="w-20 rounded border-gray-300 text-sm" min="0" step="1">
                                        <button type="button" @click="addBox()" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg bg-[#092E48] text-white hover:bg-[#0b3858]">Box hinzufügen</button>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-2">x1,y1 = linke obere Ecke, x2,y2 = rechte untere Ecke (z.&nbsp;B. Kennzeichen oder Gesicht). Kennzeichen oft unten im Bild; Gesichter nach Bedarf manuell umrahmen.</p>
                                </div>
                            </details>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <button type="submit" name="run_after" value="1" class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-xl bg-[#092E48] text-white hover:bg-[#0b3858] min-h-[2.75rem]">
                                    Speichern &amp; Redaction neu rendern
                                </button>
                            </div>
                        </form>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <form method="post" action="{{ route('admin.news.media.redaction.run', [$newsItem, $medium]) }}" class="inline">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-xl border border-gray-300 text-gray-700 hover:bg-gray-50 min-h-[2.75rem]">
                                    Auto-Redaction ausführen (Warteschlange)
                                </button>
                            </form>
                            <form method="post" action="{{ route('admin.news.media.redaction.run-now', [$newsItem, $medium]) }}" class="inline" x-data="{ loading: false }" @submit="loading = true">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-xl bg-emerald-600 text-white hover:bg-emerald-700 min-h-[2.75rem] disabled:opacity-70" :disabled="loading">
                                    <span x-show="!loading">Redaction jetzt ausführen</span>
                                    <span x-show="loading" x-cloak>Bitte warten …</span>
                                </button>
                            </form>
                        </div>
                        @endif
                        @endif
                    </div>
                </div>
                @endif

                <div id="ki-panel" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden scroll-mt-4">
                    @if($medium->type === 'image' && in_array($medium->ai_status, ['queued', 'running'], true))
                    <div x-data="{
                        statusUrl: @js(route('admin.news.media.ai-status', [$newsItem, $medium])),
                        startPolling() {
                            setInterval(async () => {
                                try {
                                    const r = await fetch(this.statusUrl);
                                    const d = await r.json();
                                    if (d.ai_status === 'done' || d.ai_status === 'error' || d.ai_status === 'failed') window.location.reload();
                                } catch (_) {}
                            }, 4000);
                        }
                    }" x-init="startPolling()"></div>
                    @endif
                    <div class="px-6 py-3 border-b border-gray-200 bg-[#092E48]/5 flex items-center justify-between">
                        <h2 class="text-sm font-semibold tracking-wide text-gray-900 uppercase">KI-Unterstützung</h2>
                        @php $status = $medium->ai_status; @endphp
                        @if($status === 'queued' || $status === 'running')
                            <span class="text-xs font-medium text-amber-700 bg-amber-50 px-2.5 py-1 rounded-full border border-amber-200">
                                KI analysiert …
                            </span>
                        @elseif($status === 'done')
                            <span class="text-xs font-medium text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full border border-emerald-200">
                                KI abgeschlossen
                            </span>
                        @elseif($status === 'failed' || $status === 'error')
                            <span class="text-xs font-medium text-red-700 bg-red-50 px-2.5 py-1 rounded-full border border-red-200">
                                KI-Fehler
                            </span>
                        @else
                            <span class="text-xs font-medium text-gray-500 bg-gray-50 px-2.5 py-1 rounded-full border border-gray-200">
                                Noch nicht angefragt
                            </span>
                        @endif
                    </div>
                    <div class="px-6 py-4 space-y-2">
                        @if($medium->ai_status === 'done' && $medium->ai_payload)
                            <p class="text-sm text-gray-600">
                                Die KI hat einen Vorschlag für Bildtitel, Fotograf, Bildunterschrift, Schlagwörter und Beschreibung erzeugt.
                                Mit einem Klick kannst du die Felder vorbefüllen und anschließend prüfen. Bildunterschrift: Motiv zuerst, Ort und Datum am Ende.
                            </p>
                            @if($medium->ai_finished_at || $medium->ai_suggested_at)
                                <p class="text-xs text-gray-500">
                                    Stand: {{ optional($medium->ai_finished_at ?? $medium->ai_suggested_at)->format('d.m.Y H:i') }} Uhr
                                </p>
                            @endif
                            <button type="button"
                                @click="applySuggestions()"
                                class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium rounded-full bg-[#092E48] text-white hover:bg-[#0b3858]">
                                Vorschläge übernehmen
                            </button>
                        @elseif($medium->ai_status === 'failed' || $medium->ai_status === 'error')
                            <p class="text-sm text-red-700">
                                KI-Vorschlag konnte nicht erzeugt werden.
                            </p>
                            @if($medium->ai_error)
                                <p class="text-xs text-red-500 truncate" title="{{ $medium->ai_error }}">{{ \Illuminate\Support\Str::limit($medium->ai_error, 140) }}</p>
                            @endif
                            <form method="post" action="{{ route('admin.news.media.request-ai', [$newsItem, $medium]) }}" class="inline mt-2">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium rounded-full bg-[#092E48] text-white hover:bg-[#0b3858]">
                                    Erneut analysieren
                                </button>
                            </form>
                        @elseif($medium->ai_status === 'queued' || $medium->ai_status === 'running')
                            <p class="text-sm text-gray-600">
                                Analyse läuft im Hintergrund. Bitte die Seite in ein paar Sekunden neu laden.
                            </p>
                        @else
                            <p class="text-sm text-gray-600 mb-2">
                                Noch kein KI-Vorschlag erzeugt.
                            </p>
                            <form method="post" action="{{ route('admin.news.media.request-ai', [$newsItem, $medium]) }}" class="inline">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium rounded-full bg-[#092E48] text-white hover:bg-[#0b3858]">
                                    Jetzt KI-Vorschlag anfordern
                                </button>
                            </form>
                            <p class="text-xs text-gray-500 mt-2">
                                Alternativ: beim nächsten Upload oder per
                                <code class="bg-gray-100 px-1 rounded">php artisan media:ai-images {{ $newsItem->id }}</code>
                            </p>
                        @endif
                    </div>
                </div>

                @if ($medium->type === 'video')
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-3 border-b border-gray-200 bg-[#092E48]/5">
                        <h2 class="text-sm font-semibold tracking-wide text-gray-900 uppercase">Video-Metadaten (ffprobe)</h2>
                    </div>
                    <dl class="px-6 py-4 divide-y divide-gray-100">
                        @if($medium->width !== null || $medium->height !== null || $medium->video_metazeile)
                            @if($medium->width !== null || $medium->height !== null)
                            <div class="py-3 flex justify-between gap-4">
                                <dt class="text-sm text-gray-500">Auflösung</dt>
                                <dd class="text-sm text-gray-700">{{ $medium->width ?? '–' }}×{{ $medium->height ?? '–' }}</dd>
                            </div>
                            @endif
                            @if($medium->fps !== null)
                            <div class="py-3 flex justify-between gap-4">
                                <dt class="text-sm text-gray-500">FPS</dt>
                                <dd class="text-sm text-gray-700">{{ $medium->fps }}</dd>
                            </div>
                            @endif
                            @if($medium->codec)
                            <div class="py-3 flex justify-between gap-4">
                                <dt class="text-sm text-gray-500">Codec</dt>
                                <dd class="text-sm text-gray-700">{{ $medium->codec }}</dd>
                            </div>
                            @endif
                            @if($medium->duration_s !== null)
                            <div class="py-3 flex justify-between gap-4">
                                <dt class="text-sm text-gray-500">Dauer</dt>
                                <dd class="text-sm text-gray-700">{{ number_format($medium->duration_s, 1) }} s</dd>
                            </div>
                            @endif
                            @if($medium->bitrate_bps !== null)
                            <div class="py-3 flex justify-between gap-4">
                                <dt class="text-sm text-gray-500">Bitrate</dt>
                                <dd class="text-sm text-gray-700">{{ round($medium->bitrate_bps / 1000) }} kbps</dd>
                            </div>
                            @endif
                            @if($medium->field_order)
                            <div class="py-3 flex justify-between gap-4">
                                <dt class="text-sm text-gray-500">Field order</dt>
                                <dd class="text-sm text-gray-700">{{ $medium->field_order }}</dd>
                            </div>
                            @endif
                            @if($medium->video_metazeile)
                            <div class="py-3 flex justify-between gap-4">
                                <dt class="text-sm text-gray-500">Kurzzeile</dt>
                                <dd class="text-sm text-gray-700 font-mono">{{ $medium->video_metazeile }}</dd>
                            </div>
                            @endif
                        @else
                            <div class="py-3">
                                <p class="text-sm text-gray-500">Noch nicht analysiert. Metadaten werden per Queue-Job (ffprobe) ermittelt.</p>
                                <p class="text-xs text-gray-400 mt-1">Befehl: <code class="bg-gray-100 px-1 rounded">php artisan media:analyze-videos</code> dann <code class="bg-gray-100 px-1 rounded">php artisan queue:work --stop-when-empty</code></p>
                            </div>
                        @endif
                    </dl>
                </div>
                @endif

                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-3 border-b border-gray-200 bg-[#092E48]/5">
                        <h2 class="text-sm font-semibold tracking-wide text-gray-900 uppercase">Metadaten</h2>
                    </div>
                    <dl class="px-6 py-4 divide-y divide-gray-100">
                        <div class="py-3 flex justify-between gap-4">
                            <dt class="text-sm text-gray-500">Dateiname (Speicher)</dt>
                            <dd class="text-sm font-medium text-gray-900 truncate font-mono" title="{{ $medium->path }}">{{ basename($medium->path) }}</dd>
                        </div>
                        @if ($medium->original_name)
                        <div class="py-3 flex justify-between gap-4">
                            <dt class="text-sm text-gray-500">Original-Dateiname (Download)</dt>
                            <dd class="text-sm text-gray-700 truncate" title="{{ $medium->original_name }}">{{ $medium->original_name }}</dd>
                        </div>
                        @endif
                        <div class="py-3 flex justify-between gap-4">
                            <dt class="text-sm text-gray-500">Speicherpfad</dt>
                            <dd class="text-sm text-gray-700 font-mono truncate" title="{{ $medium->path }}">{{ $medium->path }}</dd>
                        </div>
                        <div class="py-3 flex justify-between gap-4">
                            <dt class="text-sm text-gray-500">Größe</dt>
                            <dd class="text-sm text-gray-700">{{ number_format($medium->file_size_kb) }} KB</dd>
                        </div>
                        <div class="py-3 flex justify-between gap-4">
                            <dt class="text-sm text-gray-500">Sichtbar</dt>
                            <dd class="text-sm">{{ $medium->is_visible ? 'Ja' : 'Nein' }}</dd>
                        </div>
                        <div class="py-3 flex justify-between gap-4">
                            <dt class="text-sm text-gray-500">Versand</dt>
                            <dd class="text-sm">{{ $medium->versand ? 'Ja' : 'Nein' }}</dd>
                        </div>
                        <div class="py-3 flex justify-between gap-4">
                            <dt class="text-sm text-gray-500">Teaser</dt>
                            <dd class="text-sm">{{ $medium->is_teaser ? 'Ja' : 'Nein' }}</dd>
                        </div>
                        <div class="py-3 flex justify-between gap-4">
                            <dt class="text-sm text-gray-500">Unkenntlich</dt>
                            <dd class="text-sm">{{ $medium->is_unkentlich ? 'Ja' : 'Nein' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

    </div>

    {{-- Mobile/Tablet: Bottom-Bar nur < lg (wie Admin-Layout) --}}
    <div class="lg:hidden fixed inset-x-0 bottom-0 z-30">
        <div class="bg-white/95 border-t border-slate-200/80 px-4 pt-2 pb-2.5 shadow-lg backdrop-blur-md pb-[max(0.5rem,env(safe-area-inset-bottom))]">
            <button
                type="submit"
                form="mediaMetaForm"
                class="inline-flex justify-center items-center w-full min-h-[2.75rem] px-4 py-2 text-sm font-medium rounded-2xl text-white bg-[#092E48] hover:bg-[#0b3858] focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#092E48]"
            >
                Metadaten speichern
            </button>
        </div>
    </div>

    {{-- Tastatur-Shortcuts + Warnung bei ungespeicherten Änderungen.
         Pure JS, kein Alpine-Konflikt: greift nur, wenn KEIN Eingabefeld fokussiert
         ist und das Bild-Overlay NICHT geöffnet ist (overflow-hidden auf body). --}}
    <script>
        (function () {
            var form = document.getElementById('mediaMetaForm');
            if (!form) return;

            function serialize(f) {
                try {
                    var fd = new FormData(f);
                    var parts = [];
                    fd.forEach(function (v, k) { parts.push(k + '=' + String(v)); });
                    return parts.sort().join('&');
                } catch (_) { return ''; }
            }

            var snapshot = serialize(form);
            var submitting = false;
            var autosaveTimer = null;
            var autosaving = false;

            function scheduleAutosave() {
                if (submitting || autosaving) return;
                if (serialize(form) === snapshot) return;
                clearTimeout(autosaveTimer);
                autosaveTimer = setTimeout(runAutosave, 2500);
            }

            function runAutosave() {
                autosaveTimer = null;
                if (submitting || autosaving) return;
                if (serialize(form) === snapshot) return;
                autosaving = true;
                var fd = new FormData(form);
                fd.set('autosave', '1');
                fd.set('redirect_after', '');
                fetch(form.action, {
                    method: 'POST',
                    body: fd,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                    credentials: 'same-origin',
                })
                    .then(function (res) {
                        autosaving = false;
                        if (!res.ok) return null;
                        return res.json().catch(function () { return null; });
                    })
                    .then(function (data) {
                        if (data && data.ok) {
                            snapshot = serialize(form);
                        }
                    })
                    .catch(function () {
                        autosaving = false;
                    });
            }

            form.addEventListener('input', scheduleAutosave);
            form.addEventListener('change', scheduleAutosave);

            form.addEventListener('submit', function () {
                submitting = true;
                clearTimeout(autosaveTimer);
            });
            // Save-and-go: form.submit() löst kein "submit"-Event aus, daher wird
            // submitting hier gesetzt — in der Capture-Phase VOR dem Inline-Handler,
            // der form.submit() aufruft, sonst feuert beforeunload noch mit submitting=false.
            document.querySelectorAll('a.save-and-go').forEach(function (a) {
                a.addEventListener(
                    'click',
                    function () {
                        submitting = true;
                        clearTimeout(autosaveTimer);
                    },
                    true
                );
            });

            window.addEventListener('beforeunload', function (e) {
                if (submitting) return;
                if (serialize(form) === snapshot) return;
                e.preventDefault();
                e.returnValue = '';
                return '';
            });

            function isTypingTarget(el) {
                if (!el) return false;
                var tag = (el.tagName || '').toLowerCase();
                if (tag === 'input' || tag === 'textarea' || tag === 'select') return true;
                if (el.isContentEditable) return true;
                return false;
            }

            document.addEventListener('keydown', function (e) {
                // Strg/Cmd+S → speichern
                if ((e.ctrlKey || e.metaKey) && !e.altKey && (e.key === 's' || e.key === 'S')) {
                    e.preventDefault();
                    submitting = true;
                    if (typeof form.requestSubmit === 'function') form.requestSubmit();
                    else form.submit();
                    return;
                }

                if (e.altKey || e.ctrlKey || e.metaKey || e.shiftKey) return;
                if (isTypingTarget(e.target)) return;
                // Wenn das Bild-Overlay (Lightbox) offen ist, hat dessen Alpine-Handler Vorrang.
                if (document.body.classList.contains('overflow-hidden')) return;

                if (e.key === 'ArrowLeft') {
                    var prev = document.querySelector('a.save-and-go[aria-label^="Vorheriges"]');
                    if (prev) { e.preventDefault(); prev.click(); }
                } else if (e.key === 'ArrowRight') {
                    var next = document.querySelector('a.save-and-go[aria-label^="Nächstes"]');
                    if (next) { e.preventDefault(); next.click(); }
                }
            });
        })();
    </script>
@endsection
