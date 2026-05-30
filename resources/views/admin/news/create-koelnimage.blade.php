@extends('layouts.admin')

@section('content')
    <div class="space-y-6 min-w-0 max-w-full">
        <div class="min-w-0">
            <h1 class="text-2xl font-semibold text-gray-900 break-words">
                Neue Foto-Eingabe
            </h1>
            <p class="mt-1 text-sm text-gray-600">
                Foto-Maske für Kölnimage mit Fokus auf kurze Beschreibung und Bild-Upload.
            </p>
        </div>

        <div class="bg-white/90 backdrop-blur border border-gray-200 rounded-xl shadow px-3 py-3 sm:px-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sticky top-0 z-20 min-w-0">
            <div class="flex flex-wrap items-center gap-2 sm:gap-3 min-w-0">
                <button
                    type="submit"
                    form="news-koelnimage-form"
                    class="inline-flex justify-center items-center min-h-[2.75rem] px-4 py-2 text-sm font-medium rounded-md border border-transparent text-white bg-[#092E48] hover:bg-[#0b3858] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#092E48]"
                >
                    Speichern
                </button>

                <a
                    href="{{ route('admin.news.index') }}"
                    class="inline-flex justify-center items-center min-h-[2.75rem] px-4 py-2 text-sm font-medium rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50"
                >
                    Abbrechen
                </a>
            </div>
        </div>

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

        <form
            id="news-koelnimage-form"
            method="POST"
            action="{{ route('admin.news.store') }}"
            class="space-y-6"
            enctype="multipart/form-data"
            data-ekn-parse-blocktext-url="{{ route('admin.news.parse-blocktext') }}"
        >
            @csrf
            <input type="hidden" name="news_entry_flow" value="koelnimage_photo">
            <input type="hidden" name="status" value="{{ old('status', 'draft') }}">
            <input type="hidden" name="author_credit_user_id" value="{{ old('author_credit_user_id', auth()->id()) }}">
            {{-- Kölnimage: kein WDR-Job; Checkbox fehlt sonst → is_wdr_job würde fälschlich true werden --}}
            <input type="hidden" name="no_wdr_job" value="1">
            @if(!empty($forceBrandId))
                <input type="hidden" name="brand_id" value="{{ $forceBrandId }}">
            @endif

            <section class="bg-gray-50 rounded-lg border border-gray-200 overflow-hidden min-w-0">
                <div class="px-4 sm:px-6 py-3 border-b border-gray-200 bg-[#092E48]/5">
                    <h2 class="text-sm font-semibold tracking-wide text-gray-900 uppercase break-words">
                        Meldungsdaten
                    </h2>
                </div>

                <div class="px-4 sm:px-6 py-4 sm:py-6 space-y-6 min-w-0">
                    @include('admin.news.partials.planned-event-select-photo', [
                        'plannedEvents' => $plannedEvents,
                        'newsItem' => null,
                    ])

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
                            Kurze Beschreibung
                        </label>
                        <input
                            type="text"
                            name="teaser"
                            id="teaser"
                            value="{{ old('teaser') }}"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                            maxlength="255"
                            placeholder="z. B. Scherer PHX gewinnt beim Sonntagsrennen"
                        />
                        @error('teaser')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-2">
                        <label for="body" class="block text-sm font-medium text-gray-700">
                            Text (optional)
                        </label>
                        <textarea
                            name="body"
                            id="body"
                            rows="6"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                        >{{ old('body') }}</textarea>
                        <p id="news-blocktext-split-feedback" class="hidden text-sm text-emerald-700 mt-1" role="status"></p>
                        @error('body')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        @include('admin.news.partials.web-text-ai-toolbar', ['webTextAiToolbarLayout' => 'below'])
                    </div>

                    <div class="space-y-2">
                        <label for="keywords" class="block text-sm font-medium text-gray-700">
                            Schlagwörter
                        </label>
                        <input
                            type="text"
                            name="keywords"
                            id="keywords"
                            value="{{ old('keywords') }}"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                            maxlength="512"
                            placeholder="z. B. Motorsport, Nürburgring, GT3"
                        />
                        @error('keywords')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-2 rounded-lg border border-blue-200 bg-blue-50 p-3">
                        <p class="text-sm font-medium text-blue-900">Zugeordneter Brand</p>
                        <p class="text-sm text-blue-800">Kölnimage (fest zugeordnet)</p>
                    </div>
                </div>
            </section>

            @include('admin.news.partials.media-tab-bilder-create-photo')
        </form>
        <script src="{{ asset('js/admin-news-blocktext-split.js') }}?v=8" defer></script>
    </div>
@endsection
