@extends('layouts.admin')

@section('content')
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">
                Neue Nachricht
            </h1>
            <p class="mt-1 text-sm text-gray-600">
                Erstelle eine neue Meldung für Erftkreis News.
            </p>
        </div>

        <div
            class="bg-white/90 backdrop-blur border border-gray-200 rounded-xl shadow px-4 py-3 flex items-center justify-between sticky top-0 z-20"
        >
            <div class="flex items-center space-x-3">
                <button
                    type="submit"
                    form="news-form"
                    class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-transparent text-white bg-[#092E48] hover:bg-[#0b3858] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#092E48]"
                >
                    Speichern
                </button>

                <a
                    href="{{ route('admin.news.index') }}"
                    class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50"
                >
                    Abbrechen
                </a>
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
                    <a href="#section-media" class="block px-3 py-1 hover:bg-gray-50">
                        Bilder / Medien
                    </a>
                    <a href="#section-access" class="block px-3 py-1 hover:bg-gray-50">
                        Zugang (später)
                    </a>
                </div>
            </div>
        </div>

        <form
            id="news-form"
            method="POST"
            action="{{ route('admin.news.store') }}"
            class="space-y-6"
            enctype="multipart/form-data"
        >
            @csrf

            <div x-data="{ activeTab: 'nachricht' }" class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                <nav class="flex flex-wrap gap-0 border-b-2 border-gray-200 bg-gray-50 px-2" aria-label="Tabs">
                    <button type="button" @click="activeTab = 'nachricht'" :class="activeTab === 'nachricht' ? 'border-b-2 border-[#092E48] text-[#092E48] bg-white -mb-0.5' : 'text-gray-600 hover:bg-gray-100'" class="px-4 py-3 text-sm font-medium border-b-2 border-transparent transition-colors">Nachricht</button>
                    <button type="button" data-tab="bilder" @click="activeTab = 'bilder'" :class="activeTab === 'bilder' ? 'border-b-2 border-[#092E48] text-[#092E48] bg-white -mb-0.5' : 'text-gray-600 hover:bg-gray-100'" class="px-4 py-3 text-sm font-medium border-b-2 border-transparent transition-colors">Bilder</button>
                    <button type="button" @click="activeTab = 'videos'" :class="activeTab === 'videos' ? 'border-b-2 border-[#092E48] text-[#092E48] bg-white -mb-0.5' : 'text-gray-600 hover:bg-gray-100'" class="px-4 py-3 text-sm font-medium border-b-2 border-transparent transition-colors">Videos</button>
                    <button type="button" @click="activeTab = 'audios'" :class="activeTab === 'audios' ? 'border-b-2 border-[#092E48] text-[#092E48] bg-white -mb-0.5' : 'text-gray-600 hover:bg-gray-100'" class="px-4 py-3 text-sm font-medium border-b-2 border-transparent transition-colors">Audios</button>
                    <button type="button" @click="activeTab = 'downloads'" :class="activeTab === 'downloads' ? 'border-b-2 border-[#092E48] text-[#092E48] bg-white -mb-0.5' : 'text-gray-600 hover:bg-gray-100'" class="px-4 py-3 text-sm font-medium border-b-2 border-transparent transition-colors">Downloads</button>
                    <button type="button" @click="activeTab = 'witness'" :class="activeTab === 'witness' ? 'border-b-2 border-[#092E48] text-[#092E48] bg-white -mb-0.5' : 'text-gray-600 hover:bg-gray-100'" class="px-4 py-3 text-sm font-medium border-b-2 border-transparent transition-colors">Zeugen</button>
                    <button type="button" @click="activeTab = 'dispatches'" :class="activeTab === 'dispatches' ? 'border-b-2 border-[#092E48] text-[#092E48] bg-white -mb-0.5' : 'text-gray-600 hover:bg-gray-100'" class="px-4 py-3 text-sm font-medium border-b-2 border-transparent transition-colors">Aussendungen</button>
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
                            value="{{ old('title') }}"
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
                            value="{{ old('teaser') }}"
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
                            value="{{ old('subheadline') }}"
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
                        >{{ old('body') }}</textarea>
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
                            value="{{ old('keywords') }}"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                            maxlength="512"
                            placeholder="z.B. Köln, Unfall, Polizei"
                        />
                        @error('keywords')
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
                        </div>
                    </div>
                </div>
            </section>

            {{-- Abschnitt: Publikation / Einordnung --}}
            <section
                id="section-publication"
                x-data="{
                    country: @js(old('country', 'Deutschland')),
                    federal_state: @js(old('federal_state')),
                    city: @js(old('city')),
                    street: @js(old('street')),
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
                            value="{{ old('country', 'Deutschland') }}"
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
                            value="{{ old('federal_state') }}"
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
                            value="{{ old('street') }}"
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
                            value="{{ old('city') }}"
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
                            Embargo bis
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

                    <div class="space-y-2 md:col-span-2">
                        <label for="author_credit" class="block text-sm font-medium text-gray-700">
                            Credit des Autors <span class="text-xs text-gray-400">(Byline)</span>
                        </label>
                        <input
                            type="text"
                            name="author_credit"
                            id="author_credit"
                            value="{{ old('author_credit') }}"
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
                    @include('admin.news.partials.media-tab-bilder-create')
                </div>
                <div x-show="activeTab === 'videos'" x-cloak class="p-6">
                    @include('admin.news.partials.media-tab-videos-create')
                </div>
                <div x-show="activeTab === 'audios'" x-cloak class="p-6">
                    @include('admin.news.partials.media-tab-audios-create')
                </div>
                <div x-show="activeTab === 'downloads'" x-cloak class="p-6">
                    @include('admin.news.partials.tab-downloads')
                </div>
                <div x-show="activeTab === 'witness'" x-cloak class="p-6">
                    @include('admin.news.partials.tab-witness')
                </div>
                <div x-show="activeTab === 'dispatches'" x-cloak class="p-6">
                    @include('admin.news.partials.tab-dispatches')
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

                <div class="px-6 py-6">
                    <p class="text-sm text-gray-600">
                        Zugangssteuerung und kundenspezifische Einstellungen folgen später. Aktuell werden alle veröffentlichten Nachrichten öffentlich im System geführt.
                    </p>
                </div>
            </section>
        </form>
    </div>
@endsection

