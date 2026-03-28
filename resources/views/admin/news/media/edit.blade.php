@extends('layouts.admin')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Bild bearbeiten</h1>
                <p class="mt-1 text-sm text-gray-600">Metadaten prüfen und anpassen · Meldung: {{ $newsItem->title }}</p>
            </div>
            <a href="{{ route('admin.news.edit', $newsItem) }}"
               class="inline-flex justify-center items-center px-4 py-2 text-sm font-medium rounded-2xl border border-gray-300 text-gray-700 hover:bg-gray-50 w-full sm:w-auto">
                Zurück zur Meldung
            </a>
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 p-4 border border-green-200">
                <p class="text-sm text-green-800">{{ session('status') }}</p>
            </div>
        @endif

        @php
            $galleryImagesJson = isset($galleryImages) ? $galleryImages->toJson() : '[]';
            $galleryIndexJson = isset($galleryIndex) ? (int) $galleryIndex : 0;
        @endphp
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6" x-data="{
            imageOverlay: false,
            gallery: {{ $galleryImagesJson }},
            index: {{ $galleryIndexJson }},
            get current() { return this.gallery[this.index] || null },
            prev() { this.index = (this.index - 1 + this.gallery.length) % this.gallery.length },
            next() { this.index = (this.index + 1) % this.gallery.length },
            hasPrev() { return this.gallery.length > 1 },
            hasNext() { return this.gallery.length > 1 }
        }" x-init="$watch('imageOverlay', v => document.body.classList.toggle('overflow-hidden', v))" @keydown.escape.window="imageOverlay = false" @keydown.arrow-left.window="if (imageOverlay && gallery.length > 1) { prev() }" @keydown.arrow-right.window="if (imageOverlay && gallery.length > 1) { next() }">
            <div class="lg:col-span-1">
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
                    @if ($medium->type === 'image')
                        <button type="button" @click="imageOverlay = true" class="block w-full text-left focus:outline-none focus:ring-2 focus:ring-[#092E48] focus:ring-inset rounded-lg overflow-hidden cursor-zoom-in" aria-label="Bild vergrößern">
                            <img src="{{ $medium->preview_url ?: $medium->url }}" alt="{{ $medium->display_name }}" class="w-full h-auto pointer-events-none">
                        </button>
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
                    @else
                        <div class="aspect-video bg-gray-100 flex items-center justify-center text-gray-500">Vorschau (nur Bilder)</div>
                    @endif
                    @if ($medium->is_unkentlich)
                        <div class="px-3 py-2 bg-amber-50 border-t border-amber-200 text-amber-800 text-sm font-medium">Als unkenntlich markiert</div>
                    @endif
                    {{-- Pfeile: Vorheriges / Nächstes Bild – speichert Metadaten automatisch und wechselt dann --}}
                    @if ($medium->type === 'image' && ($prevMedia || $nextMedia))
                        <div class="flex items-center justify-between gap-2 px-3 py-3 border-t border-gray-200 bg-gray-50">
                            @if ($prevMedia)
                                @php $prevUrl = route('admin.news.media.edit', [$newsItem, $prevMedia]); @endphp
                                <a href="{{ $prevUrl }}"
                                   class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-xl border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 hover:border-[#092E48] hover:text-[#092E48] transition-colors save-and-go"
                                   data-redirect="{{ $prevUrl }}"
                                   aria-label="Vorheriges Bild bearbeiten (speichert zuerst)">
                                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                    <span>Vorheriges Bild</span>
                                </a>
                            @else
                                <span></span>
                            @endif
                            @if ($nextMedia)
                                @php $nextUrl = route('admin.news.media.edit', [$newsItem, $nextMedia]); @endphp
                                <a href="{{ $nextUrl }}"
                                   class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-xl border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 hover:border-[#092E48] hover:text-[#092E48] transition-colors save-and-go"
                                   data-redirect="{{ $nextUrl }}"
                                   aria-label="Nächstes Bild bearbeiten (speichert zuerst)">
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
                        document.getElementById('caption').value = this.ai.caption;
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
                    @php $meta = $metadataFromFile ?? []; @endphp
                    <form
                        id="mediaMetaForm"
                        method="post"
                        action="{{ route('admin.news.media.update', [$newsItem, $medium]) }}"
                        class="p-6 space-y-4"
                        x-data="{ caption: {{ json_encode(old('caption', $medium->caption ?? $meta['caption'] ?? '')) }} }"
                    >
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="redirect_after" id="redirect_after" value="">
                        @php
                            $authorCredit = trim((string) ($newsItem->author_credit ?? ''));
                            // Auswahl/Autocomplete-Optionen: mindestens Byline, plus ggf. vorhandene Bild-Credits.
                            $creditOptions = $newsItem->images
                                ->pluck('photographer')
                                ->filter(fn ($v) => trim((string) $v) !== '')
                                ->unique()
                                ->values()
                                ->toArray();
                            $defaultPhotographer = $medium->photographer ?? ($meta['photographer'] ?? '');
                            if (trim((string) $defaultPhotographer) === '' && $authorCredit !== '') {
                                // Wenn das Bild keinen eigenen Credit hat, verwenden wir die Byline als Standard.
                                $defaultPhotographer = $authorCredit;
                            }
                        @endphp
                        <div>
                            <label for="image_title" class="block text-sm font-medium text-gray-700 mb-1">Titel</label>
                            <input type="text" name="image_title" id="image_title"
                                value="{{ old('image_title', $medium->image_title ?? $meta['title'] ?? '') }}"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                                maxlength="255" placeholder="Aus Metadaten übernommen, wenn vorhanden">
                        </div>
                        <div>
                            <label for="photographer" class="block text-sm font-medium text-gray-700 mb-1">Fotograf / Credit</label>
                            <input type="text" name="photographer" id="photographer"
                                list="photographer_credit_options"
                                value="{{ old('photographer', $defaultPhotographer) }}"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                                maxlength="255" placeholder="Standard: Credit des Autors; sonst Metadaten (IPTC/EXIF)">
                            <datalist id="photographer_credit_options">
                                @if($authorCredit !== '')
                                    <option value="{{ $authorCredit }}"></option>
                                @endif
                                @foreach($creditOptions as $opt)
                                    @if(trim((string) $opt) !== '' && trim((string) $opt) !== $authorCredit)
                                        <option value="{{ (string) $opt }}"></option>
                                    @endif
                                @endforeach
                            </datalist>
                        </div>
                        <div>
                            <label for="caption" class="block text-sm font-medium text-gray-700 mb-1">Bildunterschrift</label>
                            <textarea name="caption" id="caption" rows="4" maxlength="1800"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                                placeholder="Max. 1.800 Zeichen (wird aus Metadaten übernommen, wenn vorhanden)"
                                x-model="caption"
                                x-ref="captionField">{{ old('caption', $medium->caption ?? $meta['caption'] ?? '') }}</textarea>
                            <p class="mt-1 text-xs text-gray-500" x-text="'Noch ' + (1800 - (caption || '').length) + ' von 1.800 Zeichen'"></p>
                        </div>
                        <div>
                            <label for="media_keywords" class="block text-sm font-medium text-gray-700 mb-1">Schlagwörter</label>
                            <input type="text" name="media_keywords" id="media_keywords"
                                value="{{ old('media_keywords', $medium->media_keywords ?? $meta['keywords'] ?? '') }}"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                                maxlength="512" placeholder="Kommagetrennt, aus IPTC-Keywords übernommen">
                        </div>
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Beschreibung</label>
                            <p class="text-xs text-gray-500 mb-1">Wird u. a. aus WordPress übernommen (dort werden Bildunterschriften in Beschreibung abgelegt).</p>
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
                    </form>
                </div>

                @if($medium->type === 'image')
                @php $redactionBoxes = $medium->redaction_boxes ?? []; @endphp
                <div id="redaction" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden scroll-mt-4" x-data="{
                    boxes: @js($redactionBoxes),
                    method: @js($medium->redaction_method ?? 'blur'),
                    newBox: [0, 0, 100, 50],
                    removeBox(i) { this.boxes.splice(i, 1); },
                    addBox() {
                        const n = this.newBox;
                        if (n[2] > n[0] && n[3] > n[1]) this.boxes.push([Number(n[0]), Number(n[1]), Number(n[2]), Number(n[3])]);
                    }
                }">
                    <div class="px-6 py-3 border-b border-gray-200 bg-[#092E48]/5 flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold tracking-wide text-gray-900 uppercase">Bereiche unkenntlich machen (Kennzeichen &amp; Gesichter)</h2>
                        @php $rs = $medium->redaction_status; @endphp
                        @if($rs === 'done')
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
                    </div>
                </div>
                @endif

                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
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
                                Mit einem Klick kannst du die Felder vorbefüllen und anschließend prüfen.
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
                                <code class="bg-gray-100 px-1 rounded">php artisan media:ai-images --news={{ $newsItem->id }}</code>
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

    {{-- Mobile: Sticky Bottom Actionbar für Bild-Metadaten --}}
    <div class="sm:hidden fixed inset-x-0 bottom-0 z-30">
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
@endsection
