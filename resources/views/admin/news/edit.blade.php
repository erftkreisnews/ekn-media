@extends('layouts.admin')

@section('content')
    @php
        $koelnimageBrandId = ($brands ?? collect())
            ->first(fn ($brand) => (string) ($brand->key ?? '') === 'koelnimage')
            ?->id;
        $isKoelnimageEntry = $koelnimageBrandId !== null
            ? (int) $newsItem->brand_id === (int) $koelnimageBrandId
            : false;
        $entryTypeLabel = $isKoelnimageEntry ? 'Foto-Beitrag' : 'Nachricht';
    @endphp
    <x-admin.page
        title="{{ $isKoelnimageEntry ? 'Foto-Beitrag bearbeiten' : 'Nachricht bearbeiten' }}"
        subtitle="{{ $isKoelnimageEntry ? 'Passe Inhalte und Fotometadaten des Beitrags an.' : 'Passe Inhalt und Metadaten der Nachricht an.' }}"
    >
        <p class="text-xs text-gray-500 -mt-2">
            Erstellt: {{ optional($newsItem->created_at)->format('d.m.Y H:i') }},
            Aktualisiert: {{ optional($newsItem->updated_at)->format('d.m.Y H:i') }}
        </p>
        <div
            class="bg-white/90 backdrop-blur border border-gray-200 rounded-xl shadow px-3 py-3 sm:px-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:sticky sm:top-0 sm:z-20 min-w-0"
        >
            <div class="flex items-center gap-3 min-w-0 flex-1 sm:flex-initial">
                {{-- Auswahl-Button: Speichern / Veröffentlichen / Versenden / Zurück --}}
                <div x-data="{ open: false }" class="relative min-w-0 flex-1 sm:flex-initial sm:max-w-none">
                    <button
                        type="button"
                        @click="open = ! open"
                        class="inline-flex justify-center items-center w-full sm:w-auto min-h-[2.75rem] px-4 py-2 text-sm font-medium rounded-md border border-transparent text-white bg-[#092E48] hover:bg-[#0b3858] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#092E48]"
                    >
                        Aktion
                        <svg class="ml-2 -mr-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <div
                        x-cloak
                        x-show="open"
                        @click.away="open = false"
                        class="origin-top-left absolute left-0 right-0 sm:right-auto mt-2 w-auto sm:w-56 rounded-md shadow-lg bg-white border border-gray-200 py-1 text-sm z-30"
                    >
                        <button type="submit" form="newsEditForm" class="block w-full text-left px-3 py-2 text-[#092E48] bg-[#092E48]/5 hover:bg-[#092E48]/10">
                            Änderungen speichern
                        </button>
                        <button type="submit" form="newsEditForm" name="publish_and_save" value="1" class="block w-full text-left px-3 py-2 text-green-800 bg-green-50 hover:bg-green-100">
                            Speichern & Veröffentlichen
                        </button>
                        <button type="submit" form="newsEditForm" name="save_and_redirect_to_send" value="1" class="block w-full text-left px-3 py-2 text-red-700 bg-red-50 hover:bg-red-100 font-medium">
                            Speichern & Versenden
                        </button>
                        <a href="{{ route('admin.news.edit', ['newsItem' => $newsItem, 'tab' => 'ot']) }}" class="block w-full text-left px-3 py-2 text-indigo-800 bg-indigo-50 hover:bg-indigo-100">
                            Update im laufenden Einsatz erfassen
                        </a>
                        {{-- PATCH: add statements and updates support for news items --}}
                        <a href="{{ route('admin.news.send', $newsItem) }}?context=update" class="block w-full text-left px-3 py-2 text-indigo-800 bg-indigo-50 hover:bg-indigo-100">
                            Update an Redaktion versenden
                        </a>
                        <a href="{{ route('admin.news.index') }}" class="block px-3 py-2 text-gray-600 bg-gray-50 hover:bg-gray-100 border-t border-gray-200 mt-1 pt-1">
                            Zurück zur Übersicht
                        </a>
                    </div>
                </div>
            </div>

            <div x-data="{ open: false }" class="relative w-full sm:w-auto shrink-0">
                <button
                    type="button"
                    @click="open = ! open"
                    class="inline-flex justify-center items-center w-full sm:w-auto min-h-[2.75rem] px-3 py-2 text-sm font-medium rounded-md border border-gray-300 text-gray-700 bg-white hover:bg-gray-50"
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
                    class="origin-top-right absolute left-0 right-0 sm:left-auto sm:right-0 mt-2 w-auto sm:w-52 rounded-md shadow-lg bg-white border border-gray-200 py-1 text-sm z-30"
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
                    @unless($isKoelnimageEntry)
                        <a href="#section-publication" class="block px-3 py-1 hover:bg-gray-50">
                            Publikation
                        </a>
                    @endunless
                    @if (isset($fieldAuditsForEdit) && $fieldAuditsForEdit->isNotEmpty())
                        <a href="#section-news-field-audit" class="block px-3 py-1 hover:bg-gray-50">
                            Änderungshistorie
                        </a>
                    @endif
                    <a href="#section-media" onclick="document.querySelector('[data-tab=bilder]')?.click();" class="block px-3 py-1 hover:bg-gray-50">
                        Bilder / Medien
                    </a>
                </div>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 p-4 border border-green-200">
                <p class="text-sm text-green-800">
                    {{ session('status') }}
                </p>
            </div>
        @endif
        @if(($newsItem->update_type ?? null) === 'first_report' && ! $newsItem->hasPriorDeliveries())
            <div class="rounded-md bg-indigo-50 p-4 border border-indigo-200">
                <p class="text-sm text-indigo-900 font-medium">
                    Workflow aktiv: Erstmeldung wurde angelegt. Ergänze Folgeinfos als Updates im selben Datensatz.
                </p>
                <p class="text-xs text-indigo-700 mt-1">
                    Neue Medien in „Bilder“/„Videos“ hochladen, neue Lage/PM/Daten im Reiter „O-Töne &amp; Updates“ erfassen.
                </p>
            </div>
        @elseif($newsItem->hasPriorDeliveries() && in_array((string) ($newsItem->update_type ?? ''), ['update', 'correction', 'final'], true))
            <div class="rounded-md bg-indigo-50 p-4 border border-indigo-200">
                <p class="text-sm text-indigo-900 font-medium">
                    Bereits versendet – weitere Änderungen als Update an Redaktionen senden.
                </p>
                <p class="text-xs text-indigo-700 mt-1">
                    1) Neuen Stand unter „O-Töne &amp; Updates“ speichern · 2) ggf. Medien mit „Versand = Ja“ · 3) „Update an Redaktion versenden“ – die Mail enthält automatisch den neuen Stand.
                </p>
            </div>
        @endif
        @if(request()->query('mode') === 'pre_send')
            <div class="rounded-md bg-amber-50 p-4 border border-amber-200">
                <p class="text-sm text-amber-900 font-medium">
                    Noch nicht an Redaktionen versendet.
                </p>
                <p class="text-xs text-amber-800 mt-1">
                    Prüfe jetzt die Bilder und setze pro Medium „Versand = Ja“. Danach kannst du direkt hier versenden.
                </p>
                <div class="mt-3">
                    <button
                        type="submit"
                        form="newsEditForm"
                        name="save_and_redirect_to_send"
                        value="1"
                        class="inline-flex justify-center items-center w-full sm:w-auto min-h-[2.75rem] px-4 py-2 text-sm font-semibold rounded-md border border-amber-300 text-amber-900 bg-white hover:bg-amber-100"
                    >
                        Prüfung fertig – jetzt versenden
                    </button>
                </div>
            </div>
        @endif
        @if ($errors->any())
            <div class="rounded-md bg-red-50 p-4 border border-red-200" role="alert">
                <p class="text-sm font-medium text-red-800">Bitte Eingaben prüfen:</p>
                <ul class="mt-1 list-disc list-inside text-sm text-red-700 space-y-0.5">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if (session('error'))
            <div class="rounded-md bg-red-50 p-4 border border-red-200">
                <p class="text-sm text-red-800">
                    {{ session('error') }}
                </p>
            </div>
        @endif

        @php
            $allowedTabs = ['nachricht', 'bilder', 'videos', 'audios', 'downloads', 'witness', 'ot', 'usages'];
            $koelnimageTabs = ['nachricht', 'bilder'];
            $requestedTab = (string) request()->query('tab', '');
            $defaultTab = in_array(($newsItem->update_type ?? null), ['first_report', null, ''], true) ? 'ot' : 'nachricht';
            if ($isKoelnimageEntry) {
                $allowedTabs = $koelnimageTabs;
                $defaultTab = 'nachricht';
            }
            $initialTab = in_array($requestedTab, $allowedTabs, true) ? $requestedTab : $defaultTab;
            $sourceMedia = $sourceMedia ?? collect();
            $sourceImages = $sourceMedia->where('type', 'image')->values();
            $sourceVideos = $sourceMedia->where('type', 'video')->values();
            $sourceAudios = $sourceMedia->where('type', 'audio')->values();
            $koelnimageBrandIds = ($brands ?? collect())
                ->filter(fn ($brand) => (string) ($brand->key ?? '') === 'koelnimage')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        @endphp
            <div
                x-data="{
                    activeTab: @js($initialTab),
                    selectedBrandId: @js((string) old('brand_id', $newsItem->brand_id ?? '')),
                    koelnimageBrandIds: @js($koelnimageBrandIds),
                    get isKoelnimageWorkflow() {
                        return this.koelnimageBrandIds.includes(Number(this.selectedBrandId));
                    },
                    allowedTabsForWorkflow() {
                        return this.isKoelnimageWorkflow
                            ? ['nachricht', 'bilder']
                            : ['nachricht', 'bilder', 'videos', 'audios', 'downloads', 'witness', 'ot', 'usages'];
                    },
                    ensureVisibleTab() {
                        if (! this.allowedTabsForWorkflow().includes(this.activeTab)) {
                            this.activeTab = 'nachricht';
                        }
                    },
                    init() {
                        this.ensureVisibleTab();
                        this.$watch('selectedBrandId', () => this.ensureVisibleTab());
                    }
                }"
                class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden min-w-0 max-w-full"
            >
        <form
            id="newsEditForm"
            method="POST"
            action="{{ route('admin.news.update', $newsItem) }}"
            class="space-y-6 min-w-0"
            enctype="multipart/form-data"
            data-ekn-parse-blocktext-url="{{ route('admin.news.parse-blocktext') }}"
        >
            @csrf
            @method('PATCH')
            @if($isKoelnimageEntry)
                {{-- Kölnimage: kein WDR/MoID; ohne Hidden würde is_wdr_job beim Speichern fälschlich true --}}
                <input type="hidden" name="no_wdr_job" value="1">
            @endif

            {{-- Audio: File-Input außerhalb der Tab-Panels (x-show/display:none), damit Browser die Auswahl zuverlässig behalten --}}
            <input
                type="file"
                id="newsEditAudiosFile"
                name="audios[]"
                form="newsEditForm"
                accept="audio/*"
                multiple
                class="sr-only"
                tabindex="-1"
                onchange="(function (el) { var h = document.getElementById('newsEditAudiosFileHint'); if (!h) return; h.textContent = (el.files && el.files.length) ? (el.files.length + ' Datei(en) ausgewählt') : 'Keine Dateien ausgewählt.'; })(this)"
            >
            @if(isset($sourceNewsItem) && $sourceNewsItem)
                <input type="hidden" name="source_news_item_id" value="{{ $sourceNewsItem->id }}">
            @endif

            <div id="uploadUi" class="hidden rounded-lg border border-gray-200 bg-gray-50 p-4 mb-6">
                <div class="flex items-center gap-3 mb-3">
                    <span id="uplSpin" class="hidden inline-block w-5 h-5 border-2 border-[#092E48] border-t-transparent rounded-full animate-spin" aria-hidden="true"></span>
                    <p id="uplStatus" class="text-sm font-medium text-gray-800">Upload läuft…</p>
                </div>
                <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1 text-sm text-gray-600 mb-2">
                    <span id="uplPct">0 %</span>
                    <span id="uplSpeed" class="hidden"></span>
                    <span id="uplBytes">0 MB / — MB</span>
                </div>
                <div class="w-full h-2.5 bg-gray-200 rounded-full overflow-hidden relative">
                    <div id="uplBar" class="h-full bg-[#092E48] rounded-full transition-[width] duration-300 ease-out" style="width: 0%"></div>
                    <div id="uplIndet" class="hidden absolute inset-0 rounded-full overflow-hidden" aria-hidden="true">
                        <div class="upl-indet-bar h-full bg-[#092E48] rounded-full" style="width: 30%"></div>
                    </div>
                </div>
                <p id="uploadErr" class="mt-2 text-sm text-red-600 hidden"></p>
            </div>
            <style>
                @keyframes uploadMove {
                    0% { transform: translateX(-100%); }
                    100% { transform: translateX(400%); }
                }
                #uplIndet .upl-indet-bar {
                    animation: uploadMove 1.5s ease-in-out infinite;
                }
            </style>

                <nav class="flex flex-nowrap sm:flex-wrap overflow-x-auto overflow-y-hidden touch-pan-x gap-0 border-b-2 border-gray-200 bg-gray-50 px-1 sm:px-2 [scrollbar-width:thin]" aria-label="Tabs">
                    <button type="button" @click="activeTab = 'nachricht'" :class="activeTab === 'nachricht' ? 'border-b-2 border-[#092E48] text-[#092E48] bg-white -mb-0.5' : 'text-gray-600 hover:bg-gray-100'" class="shrink-0 whitespace-nowrap px-3 py-2.5 sm:px-4 sm:py-3 text-sm font-medium border-b-2 border-transparent transition-colors min-h-[2.75rem] sm:min-h-0">{{ $isKoelnimageEntry ? 'Foto' : 'Nachricht' }}</button>
                    <button type="button" data-tab="bilder" @click="activeTab = 'bilder'" :class="activeTab === 'bilder' ? 'border-b-2 border-[#092E48] text-[#092E48] bg-white -mb-0.5' : 'text-gray-600 hover:bg-gray-100'" class="shrink-0 whitespace-nowrap px-3 py-2.5 sm:px-4 sm:py-3 text-sm font-medium border-b-2 border-transparent transition-colors min-h-[2.75rem] sm:min-h-0">Bilder</button>
                    @unless($isKoelnimageEntry)
                        <button type="button" @click="activeTab = 'videos'" :class="activeTab === 'videos' ? 'border-b-2 border-[#092E48] text-[#092E48] bg-white -mb-0.5' : 'text-gray-600 hover:bg-gray-100'" class="shrink-0 whitespace-nowrap px-3 py-2.5 sm:px-4 sm:py-3 text-sm font-medium border-b-2 border-transparent transition-colors min-h-[2.75rem] sm:min-h-0">Videos</button>
                        <button type="button" @click="activeTab = 'audios'" :class="activeTab === 'audios' ? 'border-b-2 border-[#092E48] text-[#092E48] bg-white -mb-0.5' : 'text-gray-600 hover:bg-gray-100'" class="shrink-0 whitespace-nowrap px-3 py-2.5 sm:px-4 sm:py-3 text-sm font-medium border-b-2 border-transparent transition-colors min-h-[2.75rem] sm:min-h-0">Audios</button>
                        <button type="button" @click="activeTab = 'witness'" :class="activeTab === 'witness' ? 'border-b-2 border-[#092E48] text-[#092E48] bg-white -mb-0.5' : 'text-gray-600 hover:bg-gray-100'" class="shrink-0 whitespace-nowrap px-3 py-2.5 sm:px-4 sm:py-3 text-sm font-medium border-b-2 border-transparent transition-colors min-h-[2.75rem] sm:min-h-0">Zeugen</button>
                    @endunless
                    {{-- PATCH: add statements and updates support for news items --}}
                    @unless($isKoelnimageEntry)
                        <button type="button" @click="activeTab = 'ot'" :class="activeTab === 'ot' ? 'border-b-2 border-[#092E48] text-[#092E48] bg-white -mb-0.5' : 'text-gray-600 hover:bg-gray-100'" class="shrink-0 whitespace-nowrap px-3 py-2.5 sm:px-4 sm:py-3 text-sm font-medium border-b-2 border-transparent transition-colors min-h-[2.75rem] sm:min-h-0">O-Töne &amp; Updates</button>
                        <a href="{{ route('admin.deliveries.index', ['news_item_id' => $newsItem->id]) }}" class="shrink-0 whitespace-nowrap inline-flex items-center px-3 py-2.5 sm:px-4 sm:py-3 text-sm font-medium border-b-2 border-transparent text-gray-600 hover:bg-gray-100 transition-colors -mb-0.5 min-h-[2.75rem] sm:min-h-0">Versand</a>
                        <button type="button" @click="activeTab = 'downloads'" :class="activeTab === 'downloads' ? 'border-b-2 border-[#092E48] text-[#092E48] bg-white -mb-0.5' : 'text-gray-600 hover:bg-gray-100'" class="shrink-0 whitespace-nowrap px-3 py-2.5 sm:px-4 sm:py-3 text-sm font-medium border-b-2 border-transparent transition-colors min-h-[2.75rem] sm:min-h-0">Downloads</button>
                        <button type="button" @click="activeTab = 'usages'" :class="activeTab === 'usages' ? 'border-b-2 border-[#092E48] text-[#092E48] bg-white -mb-0.5' : 'text-gray-600 hover:bg-gray-100'" class="shrink-0 whitespace-nowrap px-3 py-2.5 sm:px-4 sm:py-3 text-sm font-medium border-b-2 border-transparent transition-colors min-h-[2.75rem] sm:min-h-0">Verwendungen</button>
                    @endunless
                </nav>

                <div x-show="activeTab === 'nachricht'" x-cloak class="space-y-6 p-4 sm:p-6 min-w-0 max-w-full">
            {{-- Abschnitt: Nachricht --}}
            <section
                id="section-message"
                class="bg-gray-50 rounded-lg border border-gray-200 overflow-hidden min-w-0 max-w-full"
            >
                <div class="px-4 sm:px-6 py-3 border-b border-gray-200 bg-[#092E48]/5 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center space-x-2 min-w-0">
                        <span class="inline-block h-6 w-1 shrink-0 rounded-full bg-[#092E48]"></span>
                        <h2 class="text-sm font-semibold tracking-wide text-gray-900 uppercase">
                            {{ $entryTypeLabel }}
                        </h2>
                    </div>
                    <span class="text-xs text-gray-500 shrink-0">
                        {{ $isKoelnimageEntry ? 'Basisinformationen zum Foto-Beitrag' : 'Basisinformationen zur Meldung' }}
                    </span>
                </div>

                <div class="px-4 sm:px-6 py-4 sm:py-6 space-y-6">
                    @if($isKoelnimageEntry)
                        @include('admin.news.partials.planned-event-select-photo', [
                            'plannedEvents' => $plannedEvents,
                            'newsItem' => $newsItem,
                        ])
                    @endif

                    <div class="space-y-2">
                        <label for="title" class="block text-sm font-medium text-gray-700">
                            Titel * <span class="text-xs text-gray-400">(max. 265 Zeichen)</span>
                        </label>
                        <p class="text-xs text-gray-500">Sport-Meldungen z.&nbsp;B. als eine Zeile mit <span class="font-mono"> I </span> zwischen den Teilen (Liga, Paarung, Datum). Auf Kölnimage erscheint darunter die Meta-Zeile aus erstem Schlagwort und Stadt.</p>
                        <input
                            type="text"
                            name="title"
                            id="title"
                            value="{{ old('title', $newsItem->title) }}"
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
                            value="{{ old('teaser', $newsItem->teaser) }}"
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
                            value="{{ old('subheadline', $newsItem->subheadline) }}"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                            maxlength="512"
                        />
                        @error('subheadline')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-3" x-show="!isKoelnimageWorkflow" x-cloak>
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
                                    @checked(old('is_breaking', $newsItem->is_breaking))
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
                                    @checked(old('planned_video_upload', $newsItem->planned_video_upload))
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
                                    @checked(old('liveu_on_site', $newsItem->liveu_on_site))
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
                                    @checked(old('is_confidential', $newsItem->is_confidential))
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

                    <div
                        class="space-y-2"
                        x-data="{
                            mobileBodyExpanded: false,
                            mobileEditorOpen: false,
                            syncBodyInput() {
                                this.$nextTick(() => {
                                    const bodyEl = document.getElementById('body');
                                    if (bodyEl) {
                                        bodyEl.dispatchEvent(new Event('input', { bubbles: true }));
                                    }
                                });
                            },
                            openMobileEditor() {
                                this.mobileEditorOpen = true;
                                document.body.classList.add('overflow-hidden');
                                this.$nextTick(() => {
                                    if (this.$refs.mobileBodyTextarea && this.$refs.inlineBodyTextarea) {
                                        this.$refs.mobileBodyTextarea.value = this.$refs.inlineBodyTextarea.value;
                                    }
                                    this.$refs.mobileBodyTextarea?.focus();
                                });
                            },
                            closeMobileEditor() {
                                this.mobileEditorOpen = false;
                                document.body.classList.remove('overflow-hidden');
                                this.$nextTick(() => {
                                    this.$refs.inlineBodyTextarea?.focus();
                                });
                            }
                        }"
                        @keydown.escape.window="if (mobileEditorOpen) closeMobileEditor()"
                    >
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <label for="body" class="block text-sm font-medium text-gray-700">
                                Basistext
                            </label>
                            <div class="flex flex-col sm:flex-row flex-wrap gap-2 w-full sm:w-auto sm:justify-end">
                                <button
                                    type="button"
                                    @click="openMobileEditor()"
                                    class="sm:hidden inline-flex items-center justify-center rounded-md border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 bg-white hover:bg-gray-50"
                                >
                                    Vollbild schreiben
                                </button>
                                @include('admin.news.partials.web-text-ai-toolbar')
                            </div>
                        </div>
                        <textarea
                            name="body"
                            id="body"
                            rows="8"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] text-base sm:text-sm leading-6 min-h-[42svh] sm:min-h-[16rem] resize-y"
                            :class="mobileBodyExpanded ? 'min-h-[75svh]' : 'min-h-[42svh]'"
                            x-ref="inlineBodyTextarea"
                            @focus="mobileBodyExpanded = true"
                            @blur="mobileBodyExpanded = false"
                            autocapitalize="sentences"
                            autocomplete="off"
                            spellcheck="true"
                        >{{ old('body', $newsItem->body) }}</textarea>
                        <p class="text-xs text-gray-500 sm:hidden">
                            Tipp: „Vollbild schreiben“ blendet störende Bereiche aus.
                        </p>
                        <div
                            x-show="mobileEditorOpen"
                            x-cloak
                            class="sm:hidden fixed inset-0 z-[90] bg-white flex flex-col"
                        >
                            <div class="flex items-center justify-between gap-3 px-3 py-2 border-b border-gray-200 bg-white">
                                <p class="text-sm font-semibold text-gray-800">Basistext bearbeiten</p>
                                <button
                                    type="button"
                                    @click="closeMobileEditor()"
                                    class="inline-flex items-center justify-center rounded-md border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 bg-white hover:bg-gray-50"
                                >
                                    Fertig
                                </button>
                            </div>
                            <div class="flex-1 p-3">
                                <textarea
                                    x-ref="mobileBodyTextarea"
                                    @input="$refs.inlineBodyTextarea.value = $event.target.value; syncBodyInput()"
                                    class="block h-full w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] text-base leading-7 resize-none"
                                    autocapitalize="sentences"
                                    autocomplete="off"
                                    spellcheck="true"
                                >{{ old('body', $newsItem->body) }}</textarea>
                            </div>
                        </div>
                        <p id="news-blocktext-split-feedback" class="hidden text-sm text-emerald-700 mt-1" role="status"></p>
                        @error('body')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    @php
                        $taxonomyGroups = [
                            'Einsatzkategorie' => ['Brand', 'Gefahrgutunfall', 'Verkehrsunfall', 'Hilfeleistung', 'Sucheinsatz', 'Polizeieinsatz', 'Wasserrettung', 'Unwetter', 'Event', 'Luftbild'],
                            'Hilfsorganisationen' => ['Feuerwehr', 'Polizei', 'Rettungsdienst', 'DLRG', 'THW', 'Rettungshundestaffel', 'Rettungshubschrauber', 'Bergwacht', 'SEK'],
                            'Was ist zu sehen?' => ['Flammen', 'Verletzt', 'Tödlich', 'Mord', 'Schusswaffe', 'Drehleiter', 'Streifenwagen', 'Höhenrettung', 'Abschleppdienst', 'Kran', 'Baum', 'Regen', 'Hochwasser', 'Sturm', 'Hagel', 'Wald', 'Glatteis', 'Technischer Defekt', 'Sperrung'],
                            'Straße, Örtlichkeit' => ['Autobahn', 'Landstrasse', 'Bundesstrasse', 'Fluss', 'Flughafen', 'Bahnlinie', 'Gebäude', 'Felswand', 'Feld', 'Graben'],
                        ];
                        $taxonomyInitialRaw = trim((string) old('taxonomy_terms')) !== ''
                            ? (string) old('taxonomy_terms')
                            : (string) ($newsItem->keywords ?? '');
                        $taxonomyAllowed = collect($taxonomyGroups)->flatten()->values();
                        $taxonomyInitial = collect(explode(',', $taxonomyInitialRaw))
                            ->map(fn ($t) => trim((string) $t))
                            ->filter(fn ($t) => $t !== '' && $taxonomyAllowed->contains($t))
                            ->values()
                            ->all();
                    @endphp
                    <div class="space-y-3" x-show="!isKoelnimageWorkflow" x-cloak x-data="{ selectedTerms: @js($taxonomyInitial), toggle(term) { this.selectedTerms = this.selectedTerms.includes(term) ? this.selectedTerms.filter(t => t !== term) : [...this.selectedTerms, term]; }, selectedString() { return this.selectedTerms.join(', '); } }">
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
                            value="{{ old('keywords', $newsItem->keywords) }}"
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
                                value="{{ old('event_at', optional($newsItem->event_at)->format('Y-m-d\\TH:i')) }}"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                            />
                            <p class="text-xs text-gray-500">Wann ist das Ereignis tatsächlich passiert?</p>
                            @error('event_at')
                                <p class="text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label for="news_item_update_type" class="block text-sm font-medium text-gray-700">
                                Meldungstyp
                            </label>
                            <select
                                name="update_type"
                                id="news_item_update_type"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                            >
                                <option value="">Bitte wählen</option>
                                <option value="first_report" @selected(old('update_type', $newsItem->update_type) === 'first_report') @disabled($newsItem->hasPriorDeliveries())>Erstmeldung</option>
                                <option value="update" @selected(old('update_type', $newsItem->update_type) === 'update')>Update</option>
                                <option value="correction" @selected(old('update_type', $newsItem->update_type) === 'correction')>Korrektur</option>
                                <option value="final" @selected(old('update_type', $newsItem->update_type) === 'final')>Abschluss</option>
                            </select>
                            @error('update_type')
                                <p class="text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            @if($newsItem->hasPriorDeliveries())
                                <p class="text-xs text-gray-500">„Erstmeldung“ ist nach dem ersten Versand nicht mehr wählbar.</p>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4" x-show="!isKoelnimageWorkflow" x-cloak>
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
                                <option value="official" @selected(old('source_type', $newsItem->source_type) === 'official')>Offizielle Stelle</option>
                                <option value="reporter" @selected(old('source_type', $newsItem->source_type) === 'reporter')>Reporter vor Ort</option>
                                <option value="agency" @selected(old('source_type', $newsItem->source_type) === 'agency')>Agentur</option>
                                <option value="witness" @selected(old('source_type', $newsItem->source_type) === 'witness')>Zeuge/Hinweisgeber</option>
                                <option value="other" @selected(old('source_type', $newsItem->source_type) === 'other')>Sonstige</option>
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
                                value="{{ old('source_name', $newsItem->source_name) }}"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                                maxlength="255"
                                placeholder="z. B. Polizei Köln, Pressestelle Stadt Bergheim"
                            />
                            @error('source_name')
                                <p class="text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="space-y-2" x-show="!isKoelnimageWorkflow" x-cloak>
                        <label for="verification_status" class="block text-sm font-medium text-gray-700">
                            Verifikationsstatus
                        </label>
                        <select
                            name="verification_status"
                            id="verification_status"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                        >
                            <option value="">Bitte wählen</option>
                            <option value="unverified" @selected(old('verification_status', $newsItem->verification_status) === 'unverified')>Unbestätigt</option>
                            <option value="verified" @selected(old('verification_status', $newsItem->verification_status) === 'verified')>Bestätigt</option>
                            <option value="official" @selected(old('verification_status', $newsItem->verification_status) === 'official')>Offiziell bestätigt</option>
                        </select>
                        @error('verification_status')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- (O‑Ton/Updates wurde in eigenen Reiter verschoben) --}}

                    <div class="space-y-2" x-show="!isKoelnimageWorkflow" x-cloak>
                        <label class="inline-flex items-center space-x-2 cursor-pointer bg-amber-50 hover:bg-amber-100 px-3 py-2 rounded-lg border border-amber-200">
                            <input
                                type="checkbox"
                                name="no_wdr_job"
                                value="1"
                                class="h-4 w-4 rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]"
                                @checked(old('no_wdr_job', ! ($newsItem->is_wdr_job ?? false)))
                            >
                            <span class="text-sm font-medium text-gray-700">
                                Kein WDR-Job (MoID nur intern, Versand an beliebige Empfänger)
                            </span>
                        </label>
                        <p class="text-xs text-gray-500 ml-1">Standard: keine WDR-Meldung. Mit eingetragener MoID wird automatisch ein WDR-Job angenommen (Versand nur an WDR). Diese Option setzt das außer Kraft, auch wenn eine MoID steht.</p>
                    </div>

                    <div class="space-y-2" x-show="!isKoelnimageWorkflow" x-cloak>
                        <label for="moid" class="block text-sm font-medium text-gray-700">
                            MoID <span class="text-xs text-gray-400">(aktiviert WDR-Job; dann nur Versand an WDR)</span>
                        </label>
                        <input
                            type="text"
                            name="moid"
                            id="moid"
                            value="{{ old('moid', $newsItem->moid) }}"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                            maxlength="128"
                            placeholder="z.B. MoID_NW9Z_Bonn"
                        />
                        @error('moid')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    @include('admin.news.partials.brand-select', [
                        'brands' => $brands ?? collect(),
                        'newsItem' => $newsItem,
                    ])
                </div>
            </section>

            {{-- Abschnitt: Publikation / Einordnung --}}
            <section
                id="section-publication"
                x-data="{
                    country: @js(old('country', $newsItem->country ?? 'Deutschland')),
                    federal_state: @js(old('federal_state', $newsItem->federal_state)),
                    city: @js(old('city', $newsItem->city)),
                    street: @js(old('street', $newsItem->street)),
                    region: @js(old('region', $newsItem->region)),
                    get preview() {
                        const part1 = [this.country, this.federal_state, this.region].filter(Boolean).join(', ');
                        const part2 = [this.city, this.street].filter(Boolean).join(' / ');
                        return [part1, part2].filter(Boolean).join(' / ');
                    }
                }"
                x-show="!isKoelnimageWorkflow"
                x-cloak
                class="bg-white shadow-sm rounded-2xl border border-gray-200 overflow-hidden min-w-0 max-w-full"
            >
                <div class="px-4 sm:px-6 py-3 border-b border-gray-200 bg-[#092E48]/5 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex items-center space-x-2 min-w-0">
                        <span class="inline-block h-6 w-1 shrink-0 rounded-full bg-[#092E48]"></span>
                        <h2 class="text-sm font-semibold tracking-wide text-gray-900 uppercase">
                            Publikation &amp; Einordnung
                        </h2>
                    </div>
                    <div class="text-[11px] text-gray-500 min-w-0 break-words">
                        Ortszeile:&nbsp;
                        <span class="font-semibold text-[#092E48]" x-text="preview || 'Noch keine Angaben'"></span>
                    </div>
                </div>

                <div class="px-4 sm:px-6 py-4 sm:py-6 min-w-0 space-y-8">
                    <div class="space-y-4">
                        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            Adresse
                        </h3>
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
                                    value="{{ old('country', $newsItem->country ?? 'Deutschland') }}"
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
                                    value="{{ old('federal_state', $newsItem->federal_state) }}"
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
                                    value="{{ old('street', $newsItem->street) }}"
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
                                    value="{{ old('region', $newsItem->region) }}"
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
                                    value="{{ old('city', $newsItem->city) }}"
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
                                                    @selected(old('status', $newsItem->status) === $value)
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
                                            value="{{ old('published_at', optional($newsItem->published_at)->format('Y-m-d\TH:i')) }}"
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
                                            value="{{ old('embargo_at', optional($newsItem->embargo_at)->format('Y-m-d\TH:i')) }}"
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
                                    'initialLatitude' => $newsItem->latitude,
                                    'initialLongitude' => $newsItem->longitude,
                                ])
                            </div>
                        </div>

                        @php
                            $storedCredit = trim((string) ($newsItem->author_credit ?? ''));
                            $matchedCreditUserId = $creditUsers->first(function ($u) use ($storedCredit) {
                                return trim((string) $u->name) === $storedCredit;
                            })?->id;
                            $storedCreditUnmatched = $storedCredit !== '' && $matchedCreditUserId === null;
                            $selectedCreditUserId = old('author_credit_user_id', $matchedCreditUserId ?? auth()->id());
                        @endphp
                        <div class="space-y-6 pt-2 border-t border-gray-100">
                            @include('admin.news.partials.author-credit-user-select', [
                                'creditUsers' => $creditUsers,
                                'selectedUserId' => $selectedCreditUserId,
                                'storedCreditUnmatched' => $storedCreditUnmatched,
                                'storedCreditText' => $storedCredit,
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
                                    placeholder="z. B. 24h Nürburgring, Qualifying; Nordschleife / Caracciola-Karussell; Fokus GT3 …"
                                >{{ old('media_ai_context', $newsItem->media_ai_context) }}</textarea>
                                @error('media_ai_context')
                                    <p class="text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        @include('admin.news.partials.field-audit-history', [
                            'fieldAuditsForEdit' => $fieldAuditsForEdit ?? collect(),
                        ])
                    </div>
                </div>
            </section>

                </div>

                <div id="section-media" x-show="activeTab === 'bilder'" x-cloak class="p-4 sm:p-6 min-w-0 max-w-full">
                    @include('admin.news.partials.media-tab-bilder', [
                        'newsItem' => $newsItem,
                        'bulkPlannedEventImageAiEnabled' => $bulkPlannedEventImageAiEnabled ?? false,
                        'bulkEventMotivAssignEnabled' => $bulkEventMotivAssignEnabled ?? false,
                        'bulkEventMotivPicks' => $bulkEventMotivPicks ?? [],
                        'bulkEventMotivLabel' => $bulkEventMotivLabel ?? null,
                    ])
                    @if(isset($sourceNewsItem) && $sourceImages->isNotEmpty())
                        <div class="mt-4 rounded-lg border border-indigo-200 bg-indigo-50/40 p-4">
                            <h3 class="text-sm font-semibold text-indigo-900">Bilder aus NewsID {{ $sourceNewsItem->display_news_id }} übernehmen</h3>
                            <p class="mt-1 text-xs text-indigo-800">Ausgewählte Bilder werden in diese Meldung kopiert, sobald du „Änderungen speichern“ klickst.</p>
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
                <div x-show="activeTab === 'videos' && !isKoelnimageWorkflow" x-cloak class="p-4 sm:p-6 min-w-0 max-w-full">
                    @include('admin.news.partials.media-tab-videos', ['newsItem' => $newsItem])
                    @if(isset($sourceNewsItem) && $sourceVideos->isNotEmpty())
                        <div class="mt-4 rounded-lg border border-indigo-200 bg-indigo-50/40 p-4">
                            <h3 class="text-sm font-semibold text-indigo-900">Videos aus NewsID {{ $sourceNewsItem->display_news_id }} übernehmen</h3>
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
                <div x-show="activeTab === 'audios' && !isKoelnimageWorkflow" x-cloak class="p-4 sm:p-6 min-w-0 max-w-full">
                    @include('admin.news.partials.media-tab-audios', ['newsItem' => $newsItem])
                    @if(isset($sourceNewsItem) && $sourceAudios->isNotEmpty())
                        <div class="mt-4 rounded-lg border border-indigo-200 bg-indigo-50/40 p-4">
                            <h3 class="text-sm font-semibold text-indigo-900">Audios aus NewsID {{ $sourceNewsItem->display_news_id }} übernehmen</h3>
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
                <div x-show="activeTab === 'downloads' && !isKoelnimageWorkflow" x-cloak class="p-4 sm:p-6 min-w-0 max-w-full">
                    @include('admin.news.partials.tab-downloads')
                </div>
                <div x-show="activeTab === 'witness' && !isKoelnimageWorkflow" x-cloak class="p-4 sm:p-6 min-w-0 max-w-full">
                    @include('admin.news.partials.tab-witness', ['newsItem' => $newsItem, 'witnessSubmissions' => $witnessSubmissions ?? collect()])
                </div>
                <div x-show="activeTab === 'usages' && !isKoelnimageWorkflow" x-cloak class="p-4 sm:p-6 min-w-0 max-w-full">
                    @include('admin.news.partials.tab-usages')
                </div>

        </form>
        <script src="{{ asset('js/admin-news-blocktext-split.js') }}?v=8" defer></script>
        @include('admin.news.partials.media-satellite-forms', ['newsItem' => $newsItem])
        @include('admin.news.partials.tab-ot-updates', ['newsItem' => $newsItem])
            </div>
        <form id="statementCreateForm" method="POST" action="{{ route('admin.news.statements.store', $newsItem) }}" class="hidden">
            @csrf
        </form>
        <form id="updateCreateForm" method="POST" action="{{ route('admin.news.updates.store', $newsItem) }}" class="hidden">
            @csrf
        </form>
    </x-admin.page>

    <script>
(function () {
    var form = document.getElementById('newsEditForm');
    if (!form) return;
    var sendAfterSaveUrl = @json(route('admin.news.send', $newsItem));
    var uploadBatchSize = {{ (int) config('media.news_upload_batch_size', 18) }};

    var uploadUi = document.getElementById('uploadUi');
    var uplBar = document.getElementById('uplBar');
    var uplPct = document.getElementById('uplPct');
    var uplSpeed = document.getElementById('uplSpeed');
    var uplBytes = document.getElementById('uplBytes');
    var uplStatus = document.getElementById('uplStatus');
    var uplSpin = document.getElementById('uplSpin');
    var uplIndet = document.getElementById('uplIndet');
    var uploadErr = document.getElementById('uploadErr');

    /** Zuverlässiger als querySelector: alle dem Formular zugeordneten Controls (inkl. form="…"). */
    function formFileInputs(namePrefix) {
        var out = [];
        var els = form.elements;
        for (var i = 0; i < els.length; i++) {
            var el = els[i];
            if (el.type !== 'file' || !el.name) continue;
            if (el.name.indexOf(namePrefix) !== 0) continue;
            out.push(el);
        }
        return out;
    }

    function hasVideoFiles() {
        var inputs = formFileInputs('videos');
        for (var i = 0; i < inputs.length; i++) {
            if (inputs[i].files && inputs[i].files.length > 0) return true;
        }
        return false;
    }

    function hasImageFiles() {
        var inputs = formFileInputs('images');
        for (var i = 0; i < inputs.length; i++) {
            if (inputs[i].files && inputs[i].files.length > 0) return true;
        }
        return false;
    }

    function hasAudioFiles() {
        var byId = document.getElementById('newsEditAudiosFile');
        if (byId && byId.files && byId.files.length > 0) {
            return true;
        }
        var inputs = formFileInputs('audios');
        for (var i = 0; i < inputs.length; i++) {
            if (inputs[i].files && inputs[i].files.length > 0) return true;
        }
        return false;
    }

    function formatMb(bytes) {
        return (bytes / (1024 * 1024)).toFixed(2);
    }

    /** CSRF: Meta-Tag bevorzugen (gleicher Token wie Layout), Formularfeld mitziehen. */
    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.getAttribute('content')) {
            return meta.getAttribute('content');
        }
        var input = form.querySelector('input[name="_token"]');
        return input ? input.value : '';
    }

    function syncCsrfTokenDom(token) {
        if (!token) return;
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) meta.setAttribute('content', token);
        var input = form.querySelector('input[name="_token"]');
        if (input) input.value = token;
    }

    function applyCsrfToXhr(xhr, fd) {
        var token = getCsrfToken();
        if (!token) return;
        if (fd) {
            fd.delete('_token');
            fd.set('_token', token);
        }
        xhr.setRequestHeader('X-CSRF-TOKEN', token);
        syncCsrfTokenDom(token);
    }

    function appendFilesFromInputs(fd, namePrefix) {
        collectFileEntries(namePrefix).forEach(function (entry) {
            fd.append(entry.name, entry.file);
        });
    }

    function collectFileEntries(namePrefix) {
        var entries = [];
        formFileInputs(namePrefix).forEach(function (inp) {
            if (!inp.files || inp.files.length === 0) return;
            for (var i = 0; i < inp.files.length; i++) {
                entries.push({ name: inp.name, file: inp.files[i] });
            }
        });
        return entries;
    }

    function buildBatchFormDataFromEntries(explicit, entries, submitter) {
        var fd = new FormData();
        var methodInput = form.querySelector('input[name="_method"]');
        if (methodInput) fd.append('_method', methodInput.value);
        appendRequiredNewsFields(fd);
        fd.append('ekn_media_only', '1');
        entries.forEach(function (entry) {
            fd.append(entry.name, entry.file);
        });
        if (submitter && submitter.getAttribute('name')) {
            fd.set(submitter.getAttribute('name'), submitter.value || '');
        }
        return fd;
    }

    function fileEntriesForExplicit(explicit) {
        if (explicit === 'images') return collectFileEntries('images');
        if (explicit === 'videos') return collectFileEntries('videos');
        if (explicit === 'audios') return collectFileEntries('audios');
        return [];
    }

    function handleUploadSuccess(xhr, tabAfter, submitter) {
        if (uplStatus) uplStatus.textContent = 'Verarbeite…';
        uplIndet.classList.add('hidden');
        uplBar.classList.remove('hidden');
        uplBar.style.width = '100%';
        uplPct.textContent = '100 %';
        uplSpeed.classList.add('hidden');
        if (uplStatus) uplStatus.textContent = 'Fertig ✓';

        var redirectUrl = null;
        try {
            var payload = JSON.parse(xhr.responseText);
            if (payload && payload.redirect) redirectUrl = payload.redirect;
        } catch (parseErr) {}
        if (redirectUrl) {
            window.location.href = redirectUrl;
            return;
        }
        if (submitter && submitter.getAttribute('name') === 'save_and_redirect_to_send') {
            window.location.href = sendAfterSaveUrl;
            return;
        }
        try {
            var url = new URL(window.location.href);
            url.searchParams.set('tab', tabAfter);
            window.location.href = url.toString();
        } catch (err) {
            window.location.reload();
        }
    }

    function handleUploadError(err) {
        if (uplSpin) uplSpin.classList.add('hidden');
        uplIndet.classList.add('hidden');
        uplBar.classList.remove('hidden');
        uploadErr.classList.remove('hidden');
        var status = err && err.status ? err.status : 0;
        var detail = '';
        if (status === 422) {
            try {
                var j = JSON.parse(err.responseText || '');
                if (j.errors) {
                    detail = Object.keys(j.errors).map(function (k) {
                        return j.errors[k].join(' ');
                    }).join(' ');
                } else if (j.message) {
                    detail = j.message;
                }
            } catch (err2) {
                detail = err.responseText ? String(err.responseText).slice(0, 500) : '';
            }
        } else if (status === 413) {
            detail = 'Datei zu groß für den Server (HTTP 413). Bitte PHP upload_max_filesize/post_max_size und ggf. nginx client_max_body_size erhöhen.';
        } else if (status === 419) {
            detail = 'Sitzung oder Sicherheitstoken abgelaufen (Seite war zu lange offen). Bitte Seite neu laden (F5), ggf. erneut anmelden, dann Upload wiederholen.';
        } else if (status > 0) {
            detail = err.responseText ? String(err.responseText).slice(0, 800) : '';
        }
        uploadErr.textContent = status > 0
            ? ('Fehler beim Hochladen (HTTP ' + status + '). ' + (detail || ''))
            : 'Netzwerkfehler beim Hochladen.';
    }

    function uploadFormDataOnce(fd, onProgress) {
        return new Promise(function (resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.upload.addEventListener('progress', function (ev) {
                if (typeof onProgress === 'function') {
                    onProgress(ev);
                }
            });
            xhr.addEventListener('load', function () {
                if (xhr.status >= 200 && xhr.status < 300) {
                    resolve(xhr);
                } else {
                    reject({ status: xhr.status, responseText: xhr.responseText });
                }
            });
            xhr.addEventListener('error', function () {
                reject({ status: 0, responseText: '' });
            });
            xhr.open('POST', form.action);
            xhr.withCredentials = true;
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('Accept', 'application/json');
            applyCsrfToXhr(xhr, fd);
            xhr.send(fd);
        });
    }

    function runMediaUpload(explicit, submitter, tabAfter) {
        var mediaOnly = explicit === 'images' || explicit === 'videos' || explicit === 'audios';
        var entries = mediaOnly ? fileEntriesForExplicit(explicit) : [];

        if (mediaOnly && entries.length > uploadBatchSize) {
            var batches = [];
            for (var i = 0; i < entries.length; i += uploadBatchSize) {
                batches.push(entries.slice(i, i + uploadBatchSize));
            }
            var totalSize = entries.reduce(function (sum, entry) {
                return sum + entry.file.size;
            }, 0);
            var doneSize = 0;
            var chain = Promise.resolve();
            batches.forEach(function (batch, batchIndex) {
                chain = chain.then(function () {
                    if (uplStatus) {
                        uplStatus.textContent = 'Upload läuft… Teil ' + (batchIndex + 1) + ' von ' + batches.length;
                    }
                    var fd = buildBatchFormDataFromEntries(explicit, batch, submitter);
                    fd.set('ekn_redirect_tab', tabAfter);
                    var batchSize = batch.reduce(function (sum, entry) {
                        return sum + entry.file.size;
                    }, 0);
                    var batchStart = doneSize;
                    return uploadFormDataOnce(fd, function (ev) {
                        if (!ev.lengthComputable) {
                            setProgress(0, 0, 0, true);
                            return;
                        }
                        var overallLoaded = batchStart + ev.loaded;
                        var pct = totalSize > 0 ? Math.round((overallLoaded / totalSize) * 100) : 0;
                        setProgress(pct, overallLoaded, totalSize, false);
                    }).then(function (xhr) {
                        doneSize += batchSize;
                        return xhr;
                    });
                });
            });
            return chain.then(function (lastXhr) {
                handleUploadSuccess(lastXhr, tabAfter, submitter);
            }).catch(handleUploadError);
        }

        var fd = buildUploadFormData(explicit, submitter);
        fd.set('ekn_redirect_tab', tabAfter);
        var lastLoaded = 0;
        var lastTime = Date.now();
        return uploadFormDataOnce(fd, function (ev) {
            if (ev.lengthComputable) {
                var pct = Math.round((ev.loaded / ev.total) * 100);
                var now = Date.now();
                var dt = (now - lastTime) / 1000;
                if (dt > 0.2 && ev.loaded > lastLoaded) {
                    var dBytes = ev.loaded - lastLoaded;
                    var mbPerSec = (dBytes / (1024 * 1024)) / dt;
                    uplSpeed.textContent = mbPerSec.toFixed(2) + ' MB/s';
                    uplSpeed.classList.remove('hidden');
                    lastLoaded = ev.loaded;
                    lastTime = now;
                }
                setProgress(pct, ev.loaded, ev.total, false);
                if (uplStatus && pct >= 90) uplStatus.textContent = 'Fast fertig…';
            } else {
                setProgress(0, 0, 0, true);
            }
        }).then(function (xhr) {
            handleUploadSuccess(xhr, tabAfter, submitter);
        }).catch(handleUploadError);
    }

    /** Pflichtfelder für PATCH-Validierung, ohne hunderte media[…]-Felder. */
    function appendRequiredNewsFields(fd) {
        ['title', 'status', 'author_credit_user_id'].forEach(function (fieldName) {
            var els = form.querySelectorAll('[name="' + fieldName + '"]');
            for (var i = 0; i < els.length; i++) {
                var el = els[i];
                if (el.disabled) continue;
                if ((el.type === 'radio' || el.type === 'checkbox') && !el.checked) continue;
                fd.set(fieldName, el.value);
                return;
            }
        });
    }

    /**
     * Nur-Medien-Upload (Button „Hochladen“): schlankes FormData verhindert CSRF-/max_input_vars-Probleme
     * bei Meldungen mit vielen Bildern. Speichern mit Medien + Formularänderungen: volles FormData.
     */
    function buildUploadFormData(explicit, submitter) {
        var mediaOnlyUpload = explicit === 'images' || explicit === 'videos' || explicit === 'audios';
        var fd;
        if (mediaOnlyUpload) {
            fd = new FormData();
            var methodInput = form.querySelector('input[name="_method"]');
            if (methodInput) fd.append('_method', methodInput.value);
            appendRequiredNewsFields(fd);
            fd.append('ekn_media_only', '1');
            if (explicit === 'images') appendFilesFromInputs(fd, 'images');
            else if (explicit === 'videos') appendFilesFromInputs(fd, 'videos');
            else if (explicit === 'audios') appendFilesFromInputs(fd, 'audios');
        } else {
            fd = new FormData(form);
            if (submitter && submitter.getAttribute('name')) {
                fd.set(submitter.getAttribute('name'), submitter.value || '');
            }
            var methodInput = form.querySelector('input[name="_method"]');
            if (methodInput) fd.set('_method', methodInput.value);
        }
        return fd;
    }

    /** Schreibt Alpine pendingFiles (Dropzone) ins native File-Input – nötig vor jedem Submit. */
    function syncAlpineDropzones() {
        if (!window.Alpine || typeof window.Alpine.$data !== 'function') return;
        var seen = new Set();
        form.querySelectorAll('input[type="file"][name^="videos"], input[type="file"][name^="images"]').forEach(function (inp) {
            var root = inp.closest('[x-data]');
            while (root) {
                if (seen.has(root)) break;
                seen.add(root);
                try {
                    var data = window.Alpine.$data(root);
                    if (data && typeof data.syncFileInput === 'function') {
                        data.syncFileInput();
                        break;
                    }
                } catch (err) {}
                root = root.parentElement ? root.parentElement.closest('[x-data]') : null;
            }
        });
    }

    /** Hilfe für Fehlermeldung: Alpine-Zustand der Video-Dropzone (ohne natives File-Input). */
    function videoDropzoneAlpineState() {
        var inp = form.querySelector('input[type="file"][name^="videos"]');
        if (!inp || !window.Alpine || typeof window.Alpine.$data !== 'function') return null;
        var root = inp.closest('[x-data]');
        if (!root) return null;
        try {
            var d = window.Alpine.$data(root);
            if (!d || typeof d.syncFileInput !== 'function') return null;
            return {
                pending: Number(d.pendingCount) || 0,
                skipped: Number(d.skippedCount) || 0,
            };
        } catch (e) {
            return null;
        }
    }

    function setProgress(pct, loaded, total, useIndeterminate) {
        if (useIndeterminate) {
            uplBar.style.width = '0%';
            uplBar.classList.add('hidden');
            uplIndet.classList.remove('hidden');
            uplPct.textContent = '—';
            uplSpeed.classList.add('hidden');
            uplBytes.textContent = '— MB / — MB';
        } else {
            uplIndet.classList.add('hidden');
            uplBar.classList.remove('hidden');
            uplBar.style.width = pct + '%';
            uplPct.textContent = pct + ' %';
            uplBytes.textContent = formatMb(loaded) + ' MB / ' + formatMb(total) + ' MB';
        }
    }

    form.addEventListener('submit', function (e) {
        syncAlpineDropzones();

        var submitter = e.submitter || null;
        var explicit = submitter && submitter.dataset ? submitter.dataset.upload : null;
        var hasV = hasVideoFiles();
        var hasI = hasImageFiles();
        var hasA = hasAudioFiles();

        if (explicit === 'videos' && !hasV) {
            e.preventDefault();
            uploadUi.classList.remove('hidden');
            uploadErr.classList.remove('hidden');
            var vState = videoDropzoneAlpineState();
            if (vState && vState.pending > 0) {
                uploadErr.textContent = 'Die ausgewählten Videos konnten nicht für den Upload übernommen werden (Browser/API). Seite neu laden und erneut versuchen oder anderen Browser testen.';
            } else if (vState && vState.skipped > 0) {
                uploadErr.textContent = 'Keine neue Datei zum Hochladen: Alle ausgewählten Videos hatten denselben Dateinamen wie bereits hochgeladene (oder wie eine andere ausgewählte Datei) und wurden übersprungen. Bitte andere Dateien wählen oder Dateinamen anpassen.';
            } else {
                uploadErr.textContent = 'Bitte wählen Sie zuerst eine oder mehrere Dateien aus (Klick in die Fläche oder Drag & Drop). Hinweis: Bereits vorhandene Dateinamen werden nicht erneut hochgeladen.';
            }
            return;
        }
        if (explicit === 'images' && !hasI) {
            e.preventDefault();
            uploadUi.classList.remove('hidden');
            uploadErr.classList.remove('hidden');
            uploadErr.textContent = 'Bitte wählen Sie zuerst eine oder mehrere Bilddateien aus. Hinweis: Doppelte Dateinamen innerhalb der aktuellen Auswahl werden übersprungen.';
            return;
        }
        if (explicit === 'audios' && !hasA) {
            e.preventDefault();
            uploadUi.classList.remove('hidden');
            uploadErr.classList.remove('hidden');
            uploadErr.textContent = 'Bitte wählen Sie zuerst eine oder mehrere Audiodateien aus.';
            return;
        }

        // XHR mit Fortschritt: expliziter „Hochladen“-Button ODER Speichern/Veröffentlichen mit angehängten Medien
        var useMediaXhr = explicit === 'videos' || explicit === 'images' || explicit === 'audios' || hasV || hasI || hasA;
        if (!useMediaXhr) {
            return;
        }

        e.preventDefault();

        var tabAfter = 'videos';
        if (hasV) tabAfter = 'videos';
        else if (hasI) tabAfter = 'bilder';
        else if (hasA) tabAfter = 'audios';
        else if (explicit === 'images') tabAfter = 'bilder';
        else if (explicit === 'audios') tabAfter = 'audios';
        else tabAfter = 'videos';

        uploadUi.classList.remove('hidden');
        uploadErr.classList.add('hidden');
        uploadErr.textContent = '';
        if (uplStatus) uplStatus.textContent = 'Upload läuft…';
        if (uplSpin) uplSpin.classList.remove('hidden');
        uplSpeed.classList.add('hidden');
        setProgress(0, 0, 0, true);

        runMediaUpload(explicit, submitter, tabAfter);
    });
})();
    </script>

    <script>
    (function () {
        var btn = document.getElementById('btnPresseportalLoadUpdate');
        if (!btn || btn.disabled) return;
        var csrf = document.querySelector('meta[name="csrf-token"]');
        if (!csrf) return;
        btn.addEventListener('click', function () {
            var urlInput = document.getElementById('update_presseportal_url');
            var url = urlInput && urlInput.value ? String(urlInput.value).trim() : '';
            if (!url) {
                window.alert('Bitte eine Presseportal-URL einfügen.');
                return;
            }
            btn.disabled = true;
            fetch('{{ route('admin.news.presseportal.fetch-story', $newsItem) }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf.content,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ url: url }),
            })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (!res.ok || !res.j.success) {
                        window.alert((res.j && res.j.message) ? res.j.message : 'Fehler beim Laden.');
                        return;
                    }
                    var d = res.j.data;
                    var bodyEl = document.getElementById('update_body');
                    var titleEl = document.getElementById('update_title');
                    var typeEl = document.getElementById('update_type');
                    var srcType = document.getElementById('update_source_type');
                    var srcLabel = document.getElementById('update_source_label');
                    var ha = document.getElementById('update_happened_at');
                    var sid = document.getElementById('update_presseportal_story_id');
                    var oid = document.getElementById('update_presseportal_office_id');
                    if (bodyEl) bodyEl.value = d.body || '';
                    if (titleEl) titleEl.value = d.title || '';
                    if (typeEl) typeEl.value = 'press_release';
                    if (srcType && d.source_type) srcType.value = d.source_type;
                    if (srcLabel) srcLabel.value = d.source_label || '';
                    if (ha && d.happened_at) ha.value = d.happened_at;
                    if (sid) sid.value = d.presseportal_story_id != null ? String(d.presseportal_story_id) : '';
                    if (oid) oid.value = d.presseportal_office_id != null ? String(d.presseportal_office_id) : '';
                })
                .catch(function (e) {
                    window.alert(e.message || 'Netzwerkfehler');
                })
                .then(function () {
                    btn.disabled = false;
                });
        });
    })();
    </script>

@endsection

