@extends('layouts.admin')

@section('content')
    @php
        $prefill = $prefill ?? [];
        $sourceMedia = $sourceMedia ?? collect();
        $sourceImages = $sourceMedia->where('type', 'image')->values();
        $sourceVideos = $sourceMedia->where('type', 'video')->values();
        $sourceAudios = $sourceMedia->where('type', 'audio')->values();
    @endphp
    <div class="space-y-6 min-w-0 max-w-full">
        <div class="min-w-0">
            <h1 class="text-2xl font-semibold text-gray-900 break-words">
                Neue Nachricht
            </h1>
            <p class="mt-1 text-sm text-gray-600">
                Erstelle eine neue Meldung für das gewählte Ziel-Portal.
            </p>
        </div>

        @if(isset($sourceNewsItem) && $sourceNewsItem)
            <div class="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3">
                <p class="text-sm font-semibold text-indigo-900">Neues Update als eigener Beitrag</p>
                <p class="mt-1 text-sm text-indigo-800">
                    Bezug zur bestehenden Meldung: NewsID {{ $sourceNewsItem->display_news_id }}.
                    Diese Maske ist absichtlich leer, damit du das Update komplett neu schreiben und mit neuen Medien befüllen kannst.
                </p>
                @if(!empty($prefill['expected_display_news_id']))
                    <p class="mt-1 text-xs text-indigo-700">
                        Diese neue Meldung wird als NewsID {{ $prefill['expected_display_news_id'] }} geführt.
                    </p>
                @endif
            </div>
        @endif

        <div
            class="bg-white/90 backdrop-blur border border-gray-200 rounded-xl shadow px-3 py-3 sm:px-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sticky top-0 z-20 min-w-0"
        >
            <div class="flex flex-wrap items-center gap-2 sm:gap-3 min-w-0">
                <button
                    type="submit"
                    form="news-form"
                    class="inline-flex justify-center items-center min-h-[2.75rem] px-4 py-2 text-sm font-medium rounded-md border border-transparent text-white bg-[#092E48] hover:bg-[#0b3858] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#092E48]"
                >
                    Erstmeldung anlegen
                </button>

                <a
                    href="{{ route('admin.news.index') }}"
                    class="inline-flex justify-center items-center min-h-[2.75rem] px-4 py-2 text-sm font-medium rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50"
                >
                    Abbrechen
                </a>
            </div>

            <div x-data="{ open: false }" class="relative w-full sm:w-auto shrink-0">
                <button
                    type="button"
                    @click="open = ! open"
                    class="inline-flex justify-center items-center w-full sm:w-auto px-3 py-2 text-sm font-medium rounded-md border border-gray-300 text-gray-700 bg-white hover:bg-gray-50"
                >
                    Schnellzugriff
                    <svg class="ml-2 -mr-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd" />
                    </svg>
                </button>

                <div
                    x-cloak
                    x-show="open"
                    @click.away="open = false"
                    class="origin-top-right absolute right-0 left-0 sm:left-auto mt-2 w-full sm:w-52 rounded-md shadow-lg bg-white border border-gray-200 py-1 text-sm z-30"
                >
                    <a href="#section-planned-event" class="block px-3 py-1 hover:bg-gray-50">
                        Geplantes Event
                    </a>
                    @if(\Illuminate\Support\Facades\Schema::hasColumn('news_items', 'brand_id'))
                        <a href="#section-brand" class="block px-3 py-1 hover:bg-gray-50">
                            Marke / Ziel-Portal
                        </a>
                    @endif
                    <a href="#section-message" class="block px-3 py-1 hover:bg-gray-50">
                        Nachricht
                    </a>
                    <a href="#section-publication" class="block px-3 py-1 hover:bg-gray-50">
                        Publikation
                    </a>
                    <a href="#section-media" class="block px-3 py-1 hover:bg-gray-50">
                        Bilder / Medien
                    </a>
                </div>
            </div>
        </div>
        <p class="text-xs text-amber-700">
            Erstversand ist gesperrt, bis Bilder geprüft und als „Versand = Ja“ markiert sind.
        </p>

        <form
            id="news-form"
            method="POST"
            action="{{ route('admin.news.store') }}"
            class="space-y-6"
            enctype="multipart/form-data"
            data-ekn-parse-blocktext-url="{{ route('admin.news.parse-blocktext') }}"
        >
            @csrf
            @if(!empty($forceBrandId))
                <input type="hidden" name="brand_id" value="{{ $forceBrandId }}">
            @endif
            @if(!empty($prefill['source_news_item_id']))
                <input type="hidden" name="source_news_item_id" value="{{ $prefill['source_news_item_id'] }}">
            @endif

            {{-- Audio: File-Input außerhalb der Tab-Panels (x-show), damit die Auswahl erhalten bleibt --}}
            <input
                type="file"
                id="newsCreateAudiosFile"
                name="audios[]"
                accept="audio/*"
                multiple
                class="sr-only"
                tabindex="-1"
                onchange="(function (el) { var h = document.getElementById('newsCreateAudiosFileHint'); if (!h) return; h.textContent = (el.files && el.files.length) ? (el.files.length + ' Datei(en) ausgewählt') : 'Keine Dateien ausgewählt.'; })(this)"
            >

            @php
                $koelnimageBrandIds = ($brands ?? collect())
                    ->filter(fn ($brand) => (string) ($brand->key ?? '') === 'koelnimage')
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();
            @endphp
            <div
                x-data="{
                    activeTab: 'nachricht',
                    selectedBrandId: @js((string) old('brand_id', $newsBrandDefault ?? '')),
                    koelnimageBrandIds: @js($koelnimageBrandIds),
                    get isKoelnimageWorkflow() {
                        return this.koelnimageBrandIds.includes(Number(this.selectedBrandId));
                    }
                }"
                class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden"
            >
                <nav class="flex flex-nowrap gap-0 border-b-2 border-gray-200 bg-gray-50 px-1 sm:px-2 overflow-x-auto [-webkit-overflow-scrolling:touch] min-w-0" aria-label="Tabs">
                    <button type="button" @click="activeTab = 'nachricht'" :class="activeTab === 'nachricht' ? 'border-b-2 border-[#092E48] text-[#092E48] bg-white -mb-0.5' : 'text-gray-600 hover:bg-gray-100'" class="shrink-0 whitespace-nowrap px-3 sm:px-4 py-3 text-sm font-medium border-b-2 border-transparent transition-colors min-h-[2.75rem]">Nachricht</button>
                    <button type="button" data-tab="bilder" @click="activeTab = 'bilder'" :class="activeTab === 'bilder' ? 'border-b-2 border-[#092E48] text-[#092E48] bg-white -mb-0.5' : 'text-gray-600 hover:bg-gray-100'" class="shrink-0 whitespace-nowrap px-3 sm:px-4 py-3 text-sm font-medium border-b-2 border-transparent transition-colors min-h-[2.75rem]">Bilder</button>
                    <button type="button" @click="activeTab = 'videos'" :class="activeTab === 'videos' ? 'border-b-2 border-[#092E48] text-[#092E48] bg-white -mb-0.5' : 'text-gray-600 hover:bg-gray-100'" class="shrink-0 whitespace-nowrap px-3 sm:px-4 py-3 text-sm font-medium border-b-2 border-transparent transition-colors min-h-[2.75rem]">Videos</button>
                    <button type="button" @click="activeTab = 'audios'" :class="activeTab === 'audios' ? 'border-b-2 border-[#092E48] text-[#092E48] bg-white -mb-0.5' : 'text-gray-600 hover:bg-gray-100'" class="shrink-0 whitespace-nowrap px-3 sm:px-4 py-3 text-sm font-medium border-b-2 border-transparent transition-colors min-h-[2.75rem]">Audios</button>
                    <button type="button" @click="activeTab = 'downloads'" :class="activeTab === 'downloads' ? 'border-b-2 border-[#092E48] text-[#092E48] bg-white -mb-0.5' : 'text-gray-600 hover:bg-gray-100'" class="shrink-0 whitespace-nowrap px-3 sm:px-4 py-3 text-sm font-medium border-b-2 border-transparent transition-colors min-h-[2.75rem]">Downloads</button>
                    <button type="button" @click="activeTab = 'witness'" :class="activeTab === 'witness' ? 'border-b-2 border-[#092E48] text-[#092E48] bg-white -mb-0.5' : 'text-gray-600 hover:bg-gray-100'" class="shrink-0 whitespace-nowrap px-3 sm:px-4 py-3 text-sm font-medium border-b-2 border-transparent transition-colors min-h-[2.75rem]">Zeugen</button>
                    <button type="button" @click="activeTab = 'dispatches'" :class="activeTab === 'dispatches' ? 'border-b-2 border-[#092E48] text-[#092E48] bg-white -mb-0.5' : 'text-gray-600 hover:bg-gray-100'" class="shrink-0 whitespace-nowrap px-3 sm:px-4 py-3 text-sm font-medium border-b-2 border-transparent transition-colors min-h-[2.75rem]">Aussendungen</button>
                    <button type="button" @click="activeTab = 'usages'" :class="activeTab === 'usages' ? 'border-b-2 border-[#092E48] text-[#092E48] bg-white -mb-0.5' : 'text-gray-600 hover:bg-gray-100'" class="shrink-0 whitespace-nowrap px-3 sm:px-4 py-3 text-sm font-medium border-b-2 border-transparent transition-colors min-h-[2.75rem]">Verwendungen</button>
                </nav>

                <div x-show="activeTab === 'nachricht'" x-cloak class="space-y-6 p-4 sm:p-6 min-w-0">
            {{-- Abschnitt: Nachricht --}}
            <section
                id="section-message"
                class="bg-gray-50 rounded-lg border border-gray-200 overflow-hidden min-w-0"
            >
                <div class="px-4 sm:px-6 py-3 border-b border-gray-200 bg-[#092E48]/5 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-4 min-w-0">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="inline-block h-6 w-1 shrink-0 rounded-full bg-[#092E48]"></span>
                        <h2 class="text-sm font-semibold tracking-wide text-gray-900 uppercase break-words">
                            Nachricht
                        </h2>
                    </div>
                    <span class="text-xs text-gray-500 sm:text-right sm:max-w-[55%] break-words">
                        Basisinformationen zur Meldung
                    </span>
                </div>

                <div class="px-4 sm:px-6 py-4 sm:py-6 space-y-6 min-w-0">

                    <div class="space-y-2">
                        <label for="title" class="block text-sm font-medium text-gray-700">
                            Titel * <span class="text-xs text-gray-400">(max. 265 Zeichen)</span>
                        </label>
                        <p class="text-xs text-gray-500">Sport-Meldungen z.&nbsp;B. als eine Zeile mit <span class="font-mono"> I </span> zwischen den Teilen (Liga, Paarung, Datum). Auf Kölnimage erscheint darunter die Meta-Zeile aus erstem Schlagwort und Stadt.</p>
                        <input
                            type="text"
                            name="title"
                            id="title"
                            value="{{ old('title', $prefill['title'] ?? '') }}"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                            maxlength="265"
                            required
                        />
                        @error('title')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-2">
                        <label for="teaser" class="block text-sm font-medium text-gray-700">
                            Teaser / Dachzeile <span class="text-xs text-gray-400">(max. 255 Zeichen)</span>
                        </label>
                        <p class="text-xs text-gray-500">Kurze Einordnung für Listen, App-Karten und Social-Vorschau.</p>
                        <input
                            type="text"
                            name="teaser"
                            id="teaser"
                            value="{{ old('teaser', $prefill['teaser'] ?? '') }}"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                            maxlength="255"
                        />
                        @error('teaser')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-2">
                        <label for="subheadline" class="block text-sm font-medium text-gray-700">
                            Subheadline / Unterzeile <span class="text-xs text-gray-400">(max. 512 Zeichen)</span>
                        </label>
                        <p class="text-xs text-gray-500">Ergänzt den Titel für Web, TV-Online und Partner-Feeds.</p>
                        <input
                            type="text"
                            name="subheadline"
                            id="subheadline"
                            value="{{ old('subheadline', $prefill['subheadline'] ?? '') }}"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                            maxlength="512"
                        />
                        @error('subheadline')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-3">
                        <h3 class="text-sm font-medium text-gray-700">
                            Besondere Merkmale
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                            <label class="inline-flex items-center space-x-2 cursor-pointer bg-gray-50 hover:bg-gray-100 px-3 py-2 rounded-lg border border-gray-200">
                                <input
                                    type="checkbox"
                                    name="is_breaking"
                                    value="1"
                                    class="h-4 w-4 rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]"
                                    @checked(old('is_breaking'))
                                >
                                <span class="text-xs font-medium text-gray-700">
                                    Breaking-News
                                </span>
                            </label>

                            <label class="inline-flex items-center space-x-2 cursor-pointer bg-gray-50 hover:bg-gray-100 px-3 py-2 rounded-lg border border-gray-200">
                                <input
                                    type="checkbox"
                                    name="planned_video_upload"
                                    value="1"
                                    class="h-4 w-4 rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]"
                                    @checked(old('planned_video_upload'))
                                >
                                <span class="text-xs font-medium text-gray-700">
                                    Video-Upload geplant
                                </span>
                            </label>

                            <label class="inline-flex items-center space-x-2 cursor-pointer bg-gray-50 hover:bg-gray-100 px-3 py-2 rounded-lg border border-gray-200">
                                <input
                                    type="checkbox"
                                    name="liveu_on_site"
                                    value="1"
                                    class="h-4 w-4 rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]"
                                    @checked(old('liveu_on_site'))
                                >
                                <span class="text-xs font-medium text-gray-700">
                                    LiveU vor Ort
                                </span>
                            </label>

                            <label class="inline-flex items-center space-x-2 cursor-pointer bg-red-50 hover:bg-red-100 px-3 py-2 rounded-lg border border-red-200">
                                <input
                                    type="checkbox"
                                    name="is_confidential"
                                    value="1"
                                    class="h-4 w-4 rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]"
                                    @checked(old('is_confidential'))
                                >
                                <span class="text-xs font-medium text-red-800">
                                    Vertraulich
                                </span>
                            </label>
                        </div>
                        <p class="text-xs text-red-700">
                            Nur für internen Zweck: Informationen zu Einsätzen/Kontrollen dürfen ausschließlich intern genutzt werden. Absolute Vertraulichkeit ist erforderlich, um Einsätze nicht zu gefährden.
                        </p>
                    </div>

                    <div class="space-y-2" x-show="!isKoelnimageWorkflow" x-cloak>
                        <label class="inline-flex items-center space-x-2 cursor-pointer bg-amber-50 hover:bg-amber-100 px-3 py-2 rounded-lg border border-amber-200">
                            <input
                                type="checkbox"
                                name="no_wdr_job"
                                value="1"
                                class="h-4 w-4 rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]"
                                @checked(old('no_wdr_job', true))
                            >
                            <span class="text-sm font-medium text-gray-700">
                                Kein WDR-Job (MoID nur intern, Versand an beliebige Empfänger)
                            </span>
                        </label>
                        <p class="text-xs text-gray-500 ml-1">Standard: keine WDR-Meldung. Eine eingetragene MoID macht daraus einen WDR-Job (Versand nur an WDR). Diese Option verhindert das auch bei MoID.</p>
                    </div>

                    <div class="space-y-2" x-show="!isKoelnimageWorkflow" x-cloak>
                        <label for="moid" class="block text-sm font-medium text-gray-700">
                            MoID <span class="text-xs text-gray-400">(aktiviert WDR-Job)</span>
                        </label>
                        <input
                            type="text"
                            name="moid"
                            id="moid"
                            value="{{ old('moid', $prefill['moid'] ?? '') }}"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                            maxlength="128"
                            placeholder="z.B. MoID_NW9Z_Bonn"
                        />
                        @error('moid')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-2">
                        <label for="body" class="block text-sm font-medium text-gray-700">
                            Basistext
                        </label>
                        <textarea
                            name="body"
                            id="body"
                            rows="8"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                        >{{ old('body', $prefill['body'] ?? '') }}</textarea>
                        <p id="news-blocktext-split-feedback" class="hidden text-sm text-emerald-700 mt-1" role="status"></p>
                        @error('body')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        @include('admin.news.partials.web-text-ai-toolbar', ['webTextAiToolbarLayout' => 'below'])
                    </div>

                    @php
                        $taxonomyGroups = [
                            'Einsatzkategorie' => ['Brand', 'Gefahrgutunfall', 'Verkehrsunfall', 'Hilfeleistung', 'Sucheinsatz', 'Polizeieinsatz', 'Wasserrettung', 'Unwetter', 'Event', 'Luftbild'],
                            'Hilfsorganisationen' => ['Feuerwehr', 'Polizei', 'Rettungsdienst', 'DLRG', 'THW', 'Rettungshundestaffel', 'Rettungshubschrauber', 'Bergwacht', 'SEK'],
                            'Was ist zu sehen?' => ['Flammen', 'Verletzt', 'Tödlich', 'Mord', 'Schusswaffe', 'Drehleiter', 'Streifenwagen', 'Höhenrettung', 'Abschleppdienst', 'Kran', 'Baum', 'Regen', 'Hochwasser', 'Sturm', 'Hagel', 'Wald', 'Glatteis', 'Technischer Defekt', 'Sperrung'],
                            'Straße, Örtlichkeit' => ['Autobahn', 'Landstrasse', 'Bundesstrasse', 'Fluss', 'Flughafen', 'Bahnlinie', 'Gebäude', 'Felswand', 'Feld', 'Graben'],
                        ];
                        $taxonomyInitial = collect(explode(',', (string) old('taxonomy_terms')))
                            ->map(fn ($t) => trim((string) $t))
                            ->filter()
                            ->values()
                            ->all();
                    @endphp
                    <div class="space-y-3" x-data="{ selectedTerms: @js($taxonomyInitial), toggle(term) { this.selectedTerms = this.selectedTerms.includes(term) ? this.selectedTerms.filter(t => t !== term) : [...this.selectedTerms, term]; }, selectedString() { return this.selectedTerms.join(', '); } }">
                        <label class="block text-sm font-medium text-gray-700">
                            Begriffe für Schlagwörter
                        </label>
                        <p class="text-xs text-gray-500">Ausgewählte Begriffe werden automatisch in Schlagwörter übernommen.</p>
                        <input type="hidden" name="taxonomy_terms" :value="selectedString()">
                        @foreach($taxonomyGroups as $groupTitle => $groupTerms)
                            <div class="space-y-2">
                                <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">{{ $groupTitle }}</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($groupTerms as $term)
                                        <button
                                            type="button"
                                            @click="toggle(@js($term))"
                                            :class="selectedTerms.includes(@js($term)) ? 'bg-[#092E48] text-white border-[#092E48]' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                                            class="px-2.5 py-1 rounded-md border text-xs font-medium transition"
                                        >{{ $term }}</button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                        <p class="text-xs text-gray-500">Auswahl: <span class="font-medium text-gray-700" x-text="selectedString() || 'keine'"></span></p>
                        @error('taxonomy_terms')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-2">
                        <label for="keywords" class="block text-sm font-medium text-gray-700">
                            Schlagwörter <span class="text-xs text-gray-400">(kommagetrennt, max. 512 Zeichen)</span>
                        </label>
                        <input
                            type="text"
                            name="keywords"
                            id="keywords"
                            value="{{ old('keywords') }}"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                            maxlength="512"
                            placeholder="z.B. Handball, Bundesliga, Mannheim"
                        />
                        @error('keywords')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label for="event_at" class="block text-sm font-medium text-gray-700">
                                Ereigniszeit
                            </label>
                            <input
                                type="datetime-local"
                                name="event_at"
                                id="event_at"
                                value="{{ old('event_at', $prefill['event_at'] ?? '') }}"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                            />
                            <p class="text-xs text-gray-500">Wann ist das Ereignis tatsächlich passiert?</p>
                            @error('event_at')
                                <p class="text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label for="update_type" class="block text-sm font-medium text-gray-700">
                                Meldungstyp
                            </label>
                            <select
                                name="update_type"
                                id="update_type"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                            >
                                <option value="">Bitte wählen</option>
                                <option value="first_report" @selected(old('update_type', $prefill['update_type'] ?? 'first_report') === 'first_report')>Erstmeldung</option>
                                <option value="update" @selected(old('update_type', $prefill['update_type'] ?? null) === 'update')>Update</option>
                                <option value="correction" @selected(old('update_type', $prefill['update_type'] ?? null) === 'correction')>Korrektur</option>
                                <option value="final" @selected(old('update_type', $prefill['update_type'] ?? null) === 'final')>Abschluss</option>
                            </select>
                            @error('update_type')
                                <p class="text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="space-y-2">
                            <label for="source_type" class="block text-sm font-medium text-gray-700">
                                Quellentyp
                            </label>
                            <select
                                name="source_type"
                                id="source_type"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                            >
                                <option value="">Bitte wählen</option>
                                <option value="official" @selected(old('source_type', $prefill['source_type'] ?? null) === 'official')>Offizielle Stelle</option>
                                <option value="reporter" @selected(old('source_type', $prefill['source_type'] ?? null) === 'reporter')>Reporter vor Ort</option>
                                <option value="agency" @selected(old('source_type', $prefill['source_type'] ?? null) === 'agency')>Agentur</option>
                                <option value="witness" @selected(old('source_type', $prefill['source_type'] ?? null) === 'witness')>Zeuge/Hinweisgeber</option>
                                <option value="other" @selected(old('source_type', $prefill['source_type'] ?? null) === 'other')>Sonstige</option>
                            </select>
                            @error('source_type')
                                <p class="text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <label for="source_name" class="block text-sm font-medium text-gray-700">
                                Quelle (Name/Referenz)
                            </label>
                            <input
                                type="text"
                                name="source_name"
                                id="source_name"
                                value="{{ old('source_name', $prefill['source_name'] ?? '') }}"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                                maxlength="255"
                                placeholder="z. B. Polizei Köln, Pressestelle Stadt Bergheim"
                            />
                            @error('source_name')
                                <p class="text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label for="verification_status" class="block text-sm font-medium text-gray-700">
                            Verifikationsstatus
                        </label>
                        <select
                            name="verification_status"
                            id="verification_status"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                        >
                            <option value="">Bitte wählen</option>
                            <option value="unverified" @selected(old('verification_status', $prefill['verification_status'] ?? null) === 'unverified')>Unbestätigt</option>
                            <option value="verified" @selected(old('verification_status', $prefill['verification_status'] ?? null) === 'verified')>Bestätigt</option>
                            <option value="official" @selected(old('verification_status', $prefill['verification_status'] ?? null) === 'official')>Offiziell bestätigt</option>
                        </select>
                        @error('verification_status')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    @if(!empty($forceBrandId))
                        <div class="space-y-2 rounded-lg border border-blue-200 bg-blue-50 p-3">
                            <p class="text-sm font-medium text-blue-900">Zugeordneter Brand</p>
                            <p class="text-sm text-blue-800">Erftkreis News (fest zugeordnet)</p>
                        </div>
                    @else
                        @include('admin.news.partials.brand-select', [
                            'brands' => $brands ?? collect(),
                            'newsBrandDefault' => $newsBrandDefault ?? null,
                        ])
                    @endif
                </div>
            </section>

            {{-- Abschnitt: Publikation / Einordnung --}}
            <section
                id="section-publication"
                x-data="{
                    country: @js(old('country', $prefill['country'] ?? 'Deutschland')),
                    federal_state: @js(old('federal_state', $prefill['federal_state'] ?? '')),
                    city: @js(old('city', $prefill['city'] ?? '')),
                    street: @js(old('street', $prefill['street'] ?? '')),
                    region: @js(old('region', $prefill['region'] ?? '')),
                    get preview() {
                        const part1 = [this.country, this.federal_state, this.region].filter(Boolean).join(', ');
                        const part2 = [this.city, this.street].filter(Boolean).join(' / ');
                        return [part1, part2].filter(Boolean).join(' / ');
                    }
                }"
                class="bg-white shadow-sm rounded-2xl border border-gray-200 overflow-hidden"
            >
                <div class="px-4 sm:px-6 py-3 border-b border-gray-200 bg-[#092E48]/5 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-4 min-w-0">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="inline-block h-6 w-1 shrink-0 rounded-full bg-[#092E48]"></span>
                        <h2 class="text-sm font-semibold tracking-wide text-gray-900 uppercase break-words">
                            Publikation &amp; Einordnung
                        </h2>
                    </div>
                    <div class="text-[11px] text-gray-500 sm:text-right sm:max-w-[60%] min-w-0 break-words">
                        Ortszeile:&nbsp;
                        <span class="font-semibold text-[#092E48]" x-text="preview || 'Noch keine Angaben'"></span>
                    </div>
                </div>

                <div class="px-4 sm:px-6 py-4 sm:py-6 min-w-0 space-y-8">
                    {{-- Adresse: volle Breite, ohne Karte daneben --}}
                    <div class="space-y-4">
                        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            Adresse
                        </h3>
                        {{-- 3 Spalten × 2 Zeilen: ohne row-span, damit keine Lücken entstehen --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-5">
                            <div class="space-y-2">
                                <label for="country" class="block text-sm font-medium text-gray-700">
                                    Land
                                </label>
                                <input
                                    type="text"
                                    name="country"
                                    id="country"
                                    x-model="country"
                                    value="{{ old('country', $prefill['country'] ?? 'Deutschland') }}"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                                    maxlength="255"
                                />
                                @error('country')
                                    <p class="text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-2">
                                <label for="federal_state" class="block text-sm font-medium text-gray-700">
                                    Bundesland
                                </label>
                                <input
                                    type="text"
                                    name="federal_state"
                                    id="federal_state"
                                    x-model="federal_state"
                                    value="{{ old('federal_state', $prefill['federal_state'] ?? '') }}"
                                    placeholder="z.B. NRW"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                                    maxlength="255"
                                />
                                @error('federal_state')
                                    <p class="text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-2 sm:col-span-2 lg:col-span-1">
                                <label for="street" class="block text-sm font-medium text-gray-700">
                                    Straße
                                </label>
                                <input
                                    type="text"
                                    name="street"
                                    id="street"
                                    x-model="street"
                                    value="{{ old('street', $prefill['street'] ?? '') }}"
                                    placeholder="z.B. Hohe Str."
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                                    maxlength="255"
                                />
                                @error('street')
                                    <p class="text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-2">
                                <label for="region" class="block text-sm font-medium text-gray-700">
                                    Landkreis <span class="text-xs font-normal text-gray-500">(optional)</span>
                                </label>
                                <input
                                    type="text"
                                    name="region"
                                    id="region"
                                    x-model="region"
                                    value="{{ old('region', $prefill['region'] ?? '') }}"
                                    placeholder="z.B. Rhein-Erft-Kreis"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                                    maxlength="255"
                                />
                                @error('region')
                                    <p class="text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-2">
                                <label for="city" class="block text-sm font-medium text-gray-700">
                                    Stadt
                                </label>
                                <input
                                    type="text"
                                    name="city"
                                    id="city"
                                    x-model="city"
                                    value="{{ old('city', $prefill['city'] ?? '') }}"
                                    placeholder="z.B. Bergheim"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                                    maxlength="255"
                                />
                                @error('city')
                                    <p class="text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Publikation (links schmal) + Karte (rechts breit); Credit/KI darunter volle Breite --}}
                    <div class="space-y-6 border-t border-gray-200 pt-8">
                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-start min-w-0">
                            <div class="lg:col-span-4 xl:col-span-3 space-y-4 min-w-0 w-full">
                                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    Publikation
                                </h3>
                                <div class="rounded-xl border border-gray-200 bg-gray-50/80 p-4 space-y-4">
                                    <div class="space-y-2">
                                        <label for="status" class="block text-sm font-medium text-gray-700">
                                            Status
                                        </label>
                                        <select
                                            name="status"
                                            id="status"
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                                        >
                                            @foreach ($statuses as $value => $label)
                                                <option
                                                    value="{{ $value }}"
                                                    @selected(old('status', 'draft') === $value)
                                                >
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('status')
                                            <p class="text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="space-y-2">
                                        <label for="published_at" class="block text-sm font-medium text-gray-700">
                                            Veröffentlichungsdatum
                                        </label>
                                        <input
                                            type="datetime-local"
                                            name="published_at"
                                            id="published_at"
                                            value="{{ old('published_at', now()->format('Y-m-d\TH:i')) }}"
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                                        />
                                        @error('published_at')
                                            <p class="text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="space-y-2">
                                        <label for="embargo_at" class="block text-sm font-medium text-gray-700">
                                            Sperrfrist bis
                                        </label>
                                        <input
                                            type="datetime-local"
                                            name="embargo_at"
                                            id="embargo_at"
                                            value="{{ old('embargo_at') }}"
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                                        />
                                        @error('embargo_at')
                                            <p class="text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="lg:col-span-8 xl:col-span-9 w-full min-w-0 lg:sticky lg:top-4 lg:self-start">
                                @include('admin.news.partials.publication-location-picker', [
                                    'initialLatitude' => old('latitude', $prefill['latitude'] ?? null),
                                    'initialLongitude' => old('longitude', $prefill['longitude'] ?? null),
                                ])
                            </div>
                        </div>

                        <div class="space-y-6 pt-2 border-t border-gray-100">
                            @include('admin.news.partials.author-credit-user-select', [
                                'creditUsers' => $creditUsers,
                                'selectedUserId' => old('author_credit_user_id', auth()->id()),
                                'storedCreditUnmatched' => false,
                                'storedCreditText' => '',
                            ])

                            <div class="space-y-2">
                                <label for="media_ai_context" class="block text-sm font-medium text-gray-700">
                                    Fotograf &amp; KI-Vorarbeit <span class="text-xs font-normal text-gray-500">(optional)</span>
                                </label>
                                <p class="text-xs text-gray-500">
                                    Ergänzung zum gewählten Event (Tab „Nachricht“): Strecke, Kurven, Startnummern – wird bei der <span class="font-medium text-gray-700">Bild-KI</span> zusätzlich mitgeschickt.
                                </p>
                                <textarea
                                    name="media_ai_context"
                                    id="media_ai_context"
                                    rows="5"
                                    maxlength="8000"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] text-sm"
                                    placeholder="z. B. 24h Nürburgring, Qualifying; Nordschleife / Caracciola-Karussell …"
                                >{{ old('media_ai_context', $prefill['media_ai_context'] ?? '') }}</textarea>
                                @error('media_ai_context')
                                    <p class="text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </section>

                </div>

                <div id="section-media" x-show="activeTab === 'bilder'" x-cloak class="p-4 sm:p-6 min-w-0">
                    @include('admin.news.partials.media-tab-bilder-create')
                    @if($sourceImages->isNotEmpty())
                        <div class="mt-4 rounded-lg border border-indigo-200 bg-indigo-50/40 p-4">
                            <h3 class="text-sm font-semibold text-indigo-900">Bilder aus NewsID {{ optional($sourceNewsItem)->display_news_id }} übernehmen</h3>
                            <p class="mt-1 text-xs text-indigo-800">Diese Bilder werden in den neuen Update-Beitrag kopiert und können dann normal mitversendet werden.</p>
                            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-80 overflow-y-auto">
                                @foreach($sourceImages as $media)
                                    <label class="flex items-start gap-3 rounded-md border border-indigo-100 bg-white p-2">
                                        <input
                                            type="checkbox"
                                            name="source_media_ids[]"
                                            value="{{ $media->id }}"
                                            class="mt-1 rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]"
                                            @checked(in_array((int) $media->id, array_map('intval', (array) old('source_media_ids', [])), true))
                                        >
                                        <img src="{{ $media->thumb_url ?: ($media->preview_url ?: $media->url) }}" alt="{{ optional($sourceNewsItem)->title ?: (optional($sourceNewsItem)->subheadline ?: 'Bildmaterial von Erftkreis News Media') }}" class="h-12 w-16 rounded border border-gray-200 object-cover">
                                        <span class="text-xs text-gray-700">
                                            <span class="font-mono text-gray-500 block">ID {{ $media->id }}</span>
                                            {{ $media->display_name ?: ($media->original_name ?: 'Bild') }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
                <div x-show="activeTab === 'videos'" x-cloak class="p-4 sm:p-6 min-w-0">
                    @include('admin.news.partials.media-tab-videos-create')
                    @if($sourceVideos->isNotEmpty())
                        <div class="mt-4 rounded-lg border border-indigo-200 bg-indigo-50/40 p-4">
                            <h3 class="text-sm font-semibold text-indigo-900">Videos aus NewsID {{ optional($sourceNewsItem)->display_news_id }} übernehmen</h3>
                            <p class="mt-1 text-xs text-indigo-800">Ausgewählte Videos werden in den neuen Update-Beitrag kopiert.</p>
                            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-72 overflow-y-auto">
                                @foreach($sourceVideos as $media)
                                    <label class="flex items-start gap-3 rounded-md border border-indigo-100 bg-white p-2">
                                        <input
                                            type="checkbox"
                                            name="source_media_ids[]"
                                            value="{{ $media->id }}"
                                            class="mt-1 rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]"
                                            @checked(in_array((int) $media->id, array_map('intval', (array) old('source_media_ids', [])), true))
                                        >
                                        <span class="text-xs text-gray-700">
                                            <span class="font-mono text-gray-500 block">ID {{ $media->id }}</span>
                                            {{ $media->display_name ?: ($media->original_name ?: 'Video') }}
                                            @if($media->video_metazeile)
                                                <span class="block text-gray-500">{{ $media->video_metazeile }}</span>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
                <div x-show="activeTab === 'audios'" x-cloak class="p-4 sm:p-6 min-w-0">
                    @include('admin.news.partials.media-tab-audios-create')
                    @if($sourceAudios->isNotEmpty())
                        <div class="mt-4 rounded-lg border border-indigo-200 bg-indigo-50/40 p-4">
                            <h3 class="text-sm font-semibold text-indigo-900">Audios aus NewsID {{ optional($sourceNewsItem)->display_news_id }} übernehmen</h3>
                            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-72 overflow-y-auto">
                                @foreach($sourceAudios as $media)
                                    <label class="flex items-start gap-3 rounded-md border border-indigo-100 bg-white p-2">
                                        <input
                                            type="checkbox"
                                            name="source_media_ids[]"
                                            value="{{ $media->id }}"
                                            class="mt-1 rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]"
                                            @checked(in_array((int) $media->id, array_map('intval', (array) old('source_media_ids', [])), true))
                                        >
                                        <span class="text-xs text-gray-700">
                                            <span class="font-mono text-gray-500 block">ID {{ $media->id }}</span>
                                            {{ $media->display_name ?: ($media->original_name ?: 'Audio') }}
                                            @if($media->audio_metazeile)
                                                <span class="block text-gray-500">{{ $media->audio_metazeile }}</span>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
                <div x-show="activeTab === 'downloads'" x-cloak class="p-4 sm:p-6 min-w-0">
                    @include('admin.news.partials.tab-downloads')
                </div>
                <div x-show="activeTab === 'witness'" x-cloak class="p-4 sm:p-6 min-w-0">
                    @include('admin.news.partials.tab-witness')
                </div>
                <div x-show="activeTab === 'dispatches'" x-cloak class="p-4 sm:p-6 min-w-0">
                    @include('admin.news.partials.tab-dispatches')
                </div>
                <div x-show="activeTab === 'usages'" x-cloak class="p-4 sm:p-6 min-w-0">
                    @include('admin.news.partials.tab-usages')
                </div>
            </div>

        </form>
        <script src="{{ asset('js/admin-news-blocktext-split.js') }}?v=8" defer></script>
    </div>
@endsection

