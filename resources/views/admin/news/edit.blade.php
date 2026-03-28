@extends('layouts.admin')

@section('content')
    <x-admin.page
        title="Nachricht bearbeiten"
        subtitle="Passe Inhalt und Metadaten der Nachricht an."
    >
        <div
            class="bg-white/90 backdrop-blur border border-gray-200 rounded-xl shadow px-4 py-3 flex items-center justify-between sticky top-0 z-20"
        >
            <div class="flex items-center space-x-3">
                {{-- Auswahl-Button: Speichern / Veröffentlichen / Versenden / Zurück --}}
                <div x-data="{ open: false }" class="relative">
                    <button
                        type="button"
                        @click="open = ! open"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-transparent text-white bg-[#092E48] hover:bg-[#0b3858] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#092E48]"
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
                        class="origin-top-left absolute left-0 mt-2 w-56 rounded-md shadow-lg bg-white border border-gray-200 py-1 text-sm z-30"
                    >
                        <button type="submit" form="newsEditForm" class="block w-full text-left px-3 py-2 text-[#092E48] bg-[#092E48]/5 hover:bg-[#092E48]/10">
                            Änderungen speichern
                        </button>
                        <button type="submit" form="newsEditForm" name="publish_and_save" value="1" class="block w-full text-left px-3 py-2 text-green-800 bg-green-50 hover:bg-green-100">
                            Speichern & Veröffentlichen
                        </button>
                        <a href="{{ route('admin.news.send', $newsItem) }}" class="block w-full text-left px-3 py-2 text-red-700 bg-red-50 hover:bg-red-100 font-medium">
                            Speichern & Versenden
                        </a>
                        <a href="{{ route('admin.news.index') }}" class="block px-3 py-2 text-gray-600 bg-gray-50 hover:bg-gray-100 border-t border-gray-200 mt-1 pt-1">
                            Zurück zur Übersicht
                        </a>
                    </div>
                </div>
            </div>

            <div x-data="{ open: false }" class="relative">
                <button
                    type="button"
                    @click="open = ! open"
                    class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md border border-gray-300 text-gray-700 bg-white hover:bg-gray-50"
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
                    class="origin-top-right absolute right-0 mt-2 w-52 rounded-md shadow-lg bg-white border border-gray-200 py-1 text-sm"
                >
                    <a href="#section-message" class="block px-3 py-1 hover:bg-gray-50">
                        Nachricht
                    </a>
                    <a href="#section-publication" class="block px-3 py-1 hover:bg-gray-50">
                        Publikation
                    </a>
                    <a href="#section-media" onclick="document.querySelector('[data-tab=bilder]')?.click();" class="block px-3 py-1 hover:bg-gray-50">
                        Bilder / Medien
                    </a>
                    <a href="#section-access" class="block px-3 py-1 hover:bg-gray-50">
                        Zugang (später)
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
        @if (session('error'))
            <div class="rounded-md bg-red-50 p-4 border border-red-200">
                <p class="text-sm text-red-800">
                    {{ session('error') }}
                </p>
            </div>
        @endif

        <form
            id="newsEditForm"
            method="POST"
            action="{{ route('admin.news.update', $newsItem) }}"
            class="space-y-6"
            enctype="multipart/form-data"
        >
            @csrf
            @method('PATCH')

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

            @php
                $allowedTabs = ['nachricht', 'bilder', 'videos', 'audios', 'downloads', 'witness', 'usages'];
                $requestedTab = (string) request()->query('tab', '');
                $initialTab = in_array($requestedTab, $allowedTabs, true) ? $requestedTab : 'nachricht';
            @endphp
            <div x-data="{ activeTab: @js($initialTab) }" class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                <nav class="flex flex-wrap gap-0 border-b-2 border-gray-200 bg-gray-50 px-2" aria-label="Tabs">
                    <button type="button" @click="activeTab = 'nachricht'" :class="activeTab === 'nachricht' ? 'border-b-2 border-[#092E48] text-[#092E48] bg-white -mb-0.5' : 'text-gray-600 hover:bg-gray-100'" class="px-4 py-3 text-sm font-medium border-b-2 border-transparent transition-colors">Nachricht</button>
                    <button type="button" data-tab="bilder" @click="activeTab = 'bilder'" :class="activeTab === 'bilder' ? 'border-b-2 border-[#092E48] text-[#092E48] bg-white -mb-0.5' : 'text-gray-600 hover:bg-gray-100'" class="px-4 py-3 text-sm font-medium border-b-2 border-transparent transition-colors">Bilder</button>
                    <button type="button" @click="activeTab = 'videos'" :class="activeTab === 'videos' ? 'border-b-2 border-[#092E48] text-[#092E48] bg-white -mb-0.5' : 'text-gray-600 hover:bg-gray-100'" class="px-4 py-3 text-sm font-medium border-b-2 border-transparent transition-colors">Videos</button>
                    <button type="button" @click="activeTab = 'audios'" :class="activeTab === 'audios' ? 'border-b-2 border-[#092E48] text-[#092E48] bg-white -mb-0.5' : 'text-gray-600 hover:bg-gray-100'" class="px-4 py-3 text-sm font-medium border-b-2 border-transparent transition-colors">Audios</button>
                    <button type="button" @click="activeTab = 'downloads'" :class="activeTab === 'downloads' ? 'border-b-2 border-[#092E48] text-[#092E48] bg-white -mb-0.5' : 'text-gray-600 hover:bg-gray-100'" class="px-4 py-3 text-sm font-medium border-b-2 border-transparent transition-colors">Downloads</button>
                    <button type="button" @click="activeTab = 'witness'" :class="activeTab === 'witness' ? 'border-b-2 border-[#092E48] text-[#092E48] bg-white -mb-0.5' : 'text-gray-600 hover:bg-gray-100'" class="px-4 py-3 text-sm font-medium border-b-2 border-transparent transition-colors">Zeugen</button>
                    <a href="{{ route('admin.deliveries.index') }}" class="px-4 py-3 text-sm font-medium border-b-2 border-transparent text-gray-600 hover:bg-gray-100 transition-colors -mb-0.5 inline-block">Versand</a>
                    <button type="button" @click="activeTab = 'usages'" :class="activeTab === 'usages' ? 'border-b-2 border-[#092E48] text-[#092E48] bg-white -mb-0.5' : 'text-gray-600 hover:bg-gray-100'" class="px-4 py-3 text-sm font-medium border-b-2 border-transparent transition-colors">Verwendungen</button>
                </nav>

                <div x-show="activeTab === 'nachricht'" x-cloak class="space-y-6 p-6">
            {{-- Abschnitt: Nachricht --}}
            <section
                id="section-message"
                class="bg-gray-50 rounded-lg border border-gray-200 overflow-hidden"
            >
                <div class="px-6 py-3 border-b border-gray-200 bg-[#092E48]/5 flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <span class="inline-block h-6 w-1 rounded-full bg-[#092E48]"></span>
                        <h2 class="text-sm font-semibold tracking-wide text-gray-900 uppercase">
                            Nachricht
                        </h2>
                    </div>
                    <span class="text-xs text-gray-500">
                        Basisinformationen zur Meldung
                    </span>
                </div>

                <div class="px-6 py-6 space-y-6">
                    <div class="space-y-2">
                        <label for="title" class="block text-sm font-medium text-gray-700">
                            Titel * <span class="text-xs text-gray-400">(max. 265 Zeichen)</span>
                        </label>
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
                            Dachzeile <span class="text-xs text-gray-400">(max. 255 Zeichen)</span>
                        </label>
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
                            Unterzeile <span class="text-xs text-gray-400">(max. 512 Zeichen)</span>
                        </label>
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

                    <div class="space-y-2">
                        <label for="body" class="block text-sm font-medium text-gray-700">
                            Web-Text
                        </label>
                        <textarea
                            name="body"
                            id="body"
                            rows="8"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                        >{{ old('body', $newsItem->body) }}</textarea>
                        @error('body')
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
                            placeholder="z.B. Köln, Unfall, Polizei"
                        />
                        @error('keywords')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-2">
                        <label class="inline-flex items-center space-x-2 cursor-pointer bg-amber-50 hover:bg-amber-100 px-3 py-2 rounded-lg border border-amber-200">
                            <input
                                type="checkbox"
                                name="no_wdr_job"
                                value="1"
                                class="h-4 w-4 rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]"
                                @checked(old('no_wdr_job', !($newsItem->is_wdr_job ?? true)))
                            >
                            <span class="text-sm font-medium text-gray-700">
                                Kein WDR-Job (MoID ignorieren, Versand an beliebige Empfänger)
                            </span>
                        </label>
                        <p class="text-xs text-gray-500 ml-1">Anklicken = keine WDR-Meldung, MoID wird ignoriert. Nicht anklicken = WDR-Job: MoID Pflicht, Versand nur an Westdeutscher Rundfunk (WDR).</p>
                    </div>

                    <div class="space-y-2">
                        <label for="moid" class="block text-sm font-medium text-gray-700">
                            MoID <span class="text-xs text-gray-400">(nur bei WDR-Job; wenn gesetzt, nur Versand an WDR)</span>
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

                    <div class="space-y-3">
                        <h3 class="text-sm font-medium text-gray-700">
                            Besondere Merkmale
                        </h3>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
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
                        </div>
                    </div>
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
                    get preview() {
                        const part1 = [this.country, this.federal_state].filter(Boolean).join(', ');
                        const part2 = [this.city, this.street].filter(Boolean).join(' / ');
                        return [part1, part2].filter(Boolean).join(' / ');
                    }
                }"
                class="bg-white shadow-sm rounded-2xl border border-gray-200 overflow-hidden"
            >
                <div class="px-6 py-3 border-b border-gray-200 bg-[#092E48]/5 flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <span class="inline-block h-6 w-1 rounded-full bg-[#092E48]"></span>
                        <h2 class="text-sm font-semibold tracking-wide text-gray-900 uppercase">
                            Publikation &amp; Einordnung
                        </h2>
                    </div>
                    <div class="text-[11px] text-gray-500">
                        Ortszeile:&nbsp;
                        <span class="font-semibold text-[#092E48]" x-text="preview || 'Noch keine Angaben'"></span>
                    </div>
                </div>

                <div class="px-6 py-6 grid grid-cols-1 md:grid-cols-4 gap-6">
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

                    <div class="space-y-2 md:col-span-2">
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
                        <label for="city" class="block text-sm font-medium text-gray-700">
                            Stadt
                        </label>
                        <input
                            type="text"
                            name="city"
                            id="city"
                            x-model="city"
                            value="{{ old('city', $newsItem->city) }}"
                            placeholder="z.B. Köln"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                            maxlength="255"
                        />
                        @error('city')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

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
                            Embargo bis
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

                    <div class="space-y-2 md:col-span-2">
                        <label for="author_credit" class="block text-sm font-medium text-gray-700">
                            Credit des Autors <span class="text-xs text-gray-400">(Byline)</span>
                        </label>
                        <input
                            type="text"
                            name="author_credit"
                            id="author_credit"
                            value="{{ old('author_credit', $newsItem->author_credit) }}"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                            maxlength="255"
                            placeholder="z.B. Max Mustermann"
                        />
                        @error('author_credit')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </section>

                </div>

                <div id="section-media" x-show="activeTab === 'bilder'" x-cloak class="p-6">
                    @include('admin.news.partials.media-tab-bilder', ['newsItem' => $newsItem])
                </div>
                <div x-show="activeTab === 'videos'" x-cloak class="p-6">
                    @include('admin.news.partials.media-tab-videos', ['newsItem' => $newsItem])
                </div>
                <div x-show="activeTab === 'audios'" x-cloak class="p-6">
                    @include('admin.news.partials.media-tab-audios', ['newsItem' => $newsItem])
                </div>
                <div x-show="activeTab === 'downloads'" x-cloak class="p-6">
                    @include('admin.news.partials.tab-downloads')
                </div>
                <div x-show="activeTab === 'witness'" x-cloak class="p-6">
                    @include('admin.news.partials.tab-witness')
                </div>
                <div x-show="activeTab === 'usages'" x-cloak class="p-6">
                    @include('admin.news.partials.tab-usages')
                </div>
            </div>

            {{-- Abschnitt: Zugang – Platzhalter --}}
            <section
                id="section-access"
                class="bg-white shadow-sm rounded-lg border border-gray-200"
            >
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">
                        Zugang &amp; Kunden
                    </h2>
                </div>

                <div class="px-6 py-6 space-y-3 text-sm text-gray-600">
                    <p>
                        Zugangssteuerung und kundenspezifische Einstellungen folgen später. Aktuell werden alle veröffentlichten Nachrichten öffentlich im System geführt.
                    </p>
                    <p class="text-xs text-gray-500">
                        Erstellt: {{ optional($newsItem->created_at)->format('d.m.Y H:i') }},
                        Aktualisiert: {{ optional($newsItem->updated_at)->format('d.m.Y H:i') }}
                    </p>
                </div>
            </section>
        </form>

        {{-- Mobile: Sticky Bottom Actionbar --}}
        <div class="sm:hidden fixed inset-x-0 bottom-0 z-30">
            <div class="bg-white/95 border-t border-slate-200/80 px-4 pt-2 pb-2.5 shadow-lg backdrop-blur-md pb-[max(0.5rem,env(safe-area-inset-bottom))]">
                <div class="flex flex-col gap-2">
                    <button
                        type="submit"
                        form="newsEditForm"
                        class="inline-flex justify-center items-center w-full min-h-[2.75rem] px-4 py-2 text-sm font-medium rounded-2xl text-white bg-[#092E48] hover:bg-[#0b3858] focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#092E48]"
                    >
                        Änderungen speichern
                    </button>
                    <div class="flex flex-col xs:flex-row gap-2">
                        <button
                            type="submit"
                            form="newsEditForm"
                            name="publish_and_save"
                            value="1"
                            class="inline-flex justify-center items-center flex-1 min-h-[2.75rem] px-4 py-2 text-sm font-medium rounded-2xl text-emerald-800 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200"
                        >
                            Speichern &amp; Veröffentlichen
                        </button>
                        <a
                            href="{{ route('admin.news.send', $newsItem) }}"
                            class="inline-flex justify-center items-center flex-1 min-h-[2.75rem] px-4 py-2 text-sm font-medium rounded-2xl text-red-700 bg-red-50 hover:bg-red-100 border border-red-200"
                        >
                            Speichern &amp; Versenden
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </x-admin.page>

    <script>
(function () {
    var form = document.getElementById('newsEditForm');
    if (!form) return;

    var uploadUi = document.getElementById('uploadUi');
    var uplBar = document.getElementById('uplBar');
    var uplPct = document.getElementById('uplPct');
    var uplSpeed = document.getElementById('uplSpeed');
    var uplBytes = document.getElementById('uplBytes');
    var uplStatus = document.getElementById('uplStatus');
    var uplSpin = document.getElementById('uplSpin');
    var uplIndet = document.getElementById('uplIndet');
    var uploadErr = document.getElementById('uploadErr');

    function hasVideoFiles() {
        var inputs = form.querySelectorAll('input[type="file"][name^="videos"]');
        for (var i = 0; i < inputs.length; i++) {
            if (inputs[i].files && inputs[i].files.length > 0) return true;
        }
        return false;
    }

    function hasImageFiles() {
        var inputs = form.querySelectorAll('input[type="file"][name^="images"]');
        for (var i = 0; i < inputs.length; i++) {
            if (inputs[i].files && inputs[i].files.length > 0) return true;
        }
        return false;
    }

    function formatMb(bytes) {
        return (bytes / (1024 * 1024)).toFixed(2);
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
        var submitter = e.submitter || null;
        var uploadType = submitter && submitter.dataset ? submitter.dataset.upload : null;
        if (!uploadType || (uploadType !== 'videos' && uploadType !== 'images')) {
            return;
        }

        if (uploadType === 'videos' && !hasVideoFiles()) return;
        if (uploadType === 'images' && !hasImageFiles()) return;

        e.preventDefault();

        uploadUi.classList.remove('hidden');
        uploadErr.classList.add('hidden');
        uploadErr.textContent = '';
        if (uplStatus) uplStatus.textContent = 'Upload läuft…';
        if (uplSpin) uplSpin.classList.remove('hidden');
        uplSpeed.classList.add('hidden');
        setProgress(0, 0, 0, true);

        var lastLoaded = 0;
        var lastTime = Date.now();

        var xhr = new XMLHttpRequest();
        var fd = new FormData(form);

        xhr.upload.addEventListener('progress', function (ev) {
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
        });

        xhr.addEventListener('load', function () {
            if (xhr.status >= 200 && xhr.status < 300) {
                if (uplStatus) uplStatus.textContent = 'Verarbeite…';
                uplIndet.classList.add('hidden');
                uplBar.classList.remove('hidden');
                uplBar.style.width = '100%';
                uplPct.textContent = '100 %';
                uplSpeed.classList.add('hidden');
                if (uplStatus) uplStatus.textContent = 'Fertig ✓';

                // Nach dem Upload im passenden Tab landen (Server-Reload).
                try {
                    var url = new URL(window.location.href);
                    url.searchParams.set('tab', uploadType === 'images' ? 'bilder' : 'videos');
                    window.location.href = url.toString();
                } catch (err) {
                    // Fallback, falls URL-Konstrukt nicht verfügbar ist.
                    window.location.reload();
                }
            } else {
                if (uplSpin) uplSpin.classList.add('hidden');
                uplIndet.classList.add('hidden');
                uplBar.classList.remove('hidden');
                uploadErr.classList.remove('hidden');
                uploadErr.textContent = 'Fehler beim Hochladen (Status ' + xhr.status + '). ' + (xhr.responseText || '');
            }
        });

        xhr.addEventListener('error', function () {
            if (uplSpin) uplSpin.classList.add('hidden');
            uplIndet.classList.add('hidden');
            uplBar.classList.remove('hidden');
            uploadErr.classList.remove('hidden');
            uploadErr.textContent = 'Netzwerkfehler beim Hochladen.';
        });

        xhr.open('POST', form.action);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('Accept', 'application/json');
        var token = form.querySelector('input[name="_token"]');
        if (token) xhr.setRequestHeader('X-CSRF-TOKEN', token.value);

        var methodInput = form.querySelector('input[name="_method"]');
        if (methodInput) fd.set('_method', methodInput.value);

        xhr.send(fd);
    });
})();
    </script>
@endsection

