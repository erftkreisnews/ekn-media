@extends('layouts.admin')

@section('content')
    <div class="space-y-6 min-w-0 max-w-full">
        <div class="min-w-0">
            <h1 class="text-2xl font-semibold text-gray-900 break-words">
                Neue Bilder
            </h1>
            <p class="mt-1 text-sm text-gray-600">
                Bild-Upload mit schlanker Eingabemaske. Es wird automatisch eine Nachricht im Status Entwurf erstellt.
            </p>
        </div>

        <div class="bg-white/90 backdrop-blur border border-gray-200 rounded-xl shadow px-3 py-3 sm:px-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sticky top-0 z-20 min-w-0">
            <div class="flex flex-wrap items-center gap-2 sm:gap-3 min-w-0">
                <button
                    type="submit"
                    form="news-images-form"
                    class="inline-flex justify-center items-center min-h-[2.75rem] px-4 py-2 text-sm font-medium rounded-md border border-transparent text-white bg-[#092E48] hover:bg-[#0b3858] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#092E48]"
                >
                    Bilder speichern
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

        @php
            $koelnimageBrandIds = ($brands ?? collect())
                ->filter(fn ($brand) => (string) ($brand->key ?? '') === 'koelnimage')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        @endphp

        <form
            id="news-images-form"
            method="POST"
            action="{{ route('admin.news.store') }}"
            class="space-y-6"
            enctype="multipart/form-data"
            x-data="{
                selectedBrandId: @js((string) old('brand_id', $newsBrandDefault ?? '')),
                koelnimageBrandIds: @js($koelnimageBrandIds),
                get isKoelnimageWorkflow() {
                    return this.koelnimageBrandIds.includes(Number(this.selectedBrandId));
                }
            }"
        >
            @csrf
            <input type="hidden" name="status" value="draft">
            <input type="hidden" name="author_credit_user_id" value="{{ old('author_credit_user_id', $currentUserId) }}">

            <section class="bg-gray-50 rounded-lg border border-gray-200 overflow-hidden min-w-0">
                <div class="px-4 sm:px-6 py-3 border-b border-gray-200 bg-[#092E48]/5">
                    <h2 class="text-sm font-semibold tracking-wide text-gray-900 uppercase break-words">
                        Basisdaten
                    </h2>
                </div>

                <div class="px-4 sm:px-6 py-4 sm:py-6 space-y-6 min-w-0">
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
                            placeholder="z. B. 24h Qualifiers Sonntag, Rennen 2"
                        />
                        @error('teaser')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
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

                    @include('admin.news.partials.brand-select', [
                        'brands' => $brands ?? collect(),
                        'newsBrandDefault' => $newsBrandDefault ?? null,
                    ])
                </div>
            </section>

            @include('admin.news.partials.media-tab-bilder-create')
        </form>
    </div>
@endsection
