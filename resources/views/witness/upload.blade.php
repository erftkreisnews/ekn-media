@extends('layouts.witness')

@section('title', $pageTitle.' · '.config('app.name'))

@section('content')
    <article class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-8 min-w-0 max-w-full">
        <h1 class="text-xl sm:text-2xl font-semibold text-slate-900 mb-2 break-words">{{ $pageTitle }}</h1>
        @if ($newsItem && filled($newsItem->title))
            <p class="text-sm text-slate-600 mb-6 break-words">
                @lang('witness.news_hint', ['title' => $newsItem->title])
            </p>
        @endif

        @if (session('status'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900 mb-6 break-words" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if (! $canUpload)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 mb-6">
                Dieser Link ist abgelaufen, wurde zurückgezogen oder hat die maximale Anzahl an Uploads erreicht. Bitte wenden Sie sich an die Redaktion.
            </div>
        @else
            @if ($errors->has('form'))
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 mb-6">{{ $errors->first('form') }}</div>
            @endif

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 mb-6">
                <h2 class="text-sm font-semibold text-slate-900 mb-2">{{ __('witness.legal_box_title') }}</h2>
                @php
                    $legalParagraphs = preg_split("/\R{2,}/u", trim($legalText)) ?: [];
                @endphp
                <div class="text-xs sm:text-sm text-slate-700 leading-relaxed max-h-64 overflow-y-auto pr-1">
                    @foreach ($legalParagraphs as $paragraph)
                        <p class="{{ $loop->last ? '' : 'mb-3' }}">{{ $paragraph }}</p>
                    @endforeach
                </div>
                <p class="mt-3 text-[11px] text-slate-500">Version {{ $legalVersion }} · Prüfsumme {{ Str::limit($legalChecksum, 16, '') }}…</p>
            </div>

            <form method="post" action="{{ url()->current() }}" enctype="multipart/form-data" class="space-y-5" autocomplete="off">
                @csrf

                <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="absolute -left-[9999px] w-px h-px opacity-0 pointer-events-none" aria-hidden="true">

                <div>
                    <label for="submitter_name" class="block text-sm font-medium text-slate-700 mb-1">Ihr Name <span class="text-red-600">*</span></label>
                    <input type="text" name="submitter_name" id="submitter_name" required maxlength="191" value="{{ old('submitter_name') }}"
                        class="w-full min-h-[44px] rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                    @error('submitter_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="submitter_email" class="block text-sm font-medium text-slate-700 mb-1">E-Mail <span class="text-red-600">*</span></label>
                    <input type="email" name="submitter_email" id="submitter_email" required maxlength="191" value="{{ old('submitter_email') }}"
                        class="w-full min-h-[44px] rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                    @error('submitter_email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="submitter_phone" class="block text-sm font-medium text-slate-700 mb-1">Telefon (optional)</label>
                    <input type="text" name="submitter_phone" id="submitter_phone" maxlength="64" value="{{ old('submitter_phone') }}"
                        class="w-full min-h-[44px] rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                    @error('submitter_phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="media" class="block text-sm font-medium text-slate-700 mb-1">Dateien (Fotos, Videos oder Audio) <span class="text-red-600">*</span></label>
                    <p class="text-xs text-slate-500 mb-2">Mehrere Dateien möglich (Strg/Cmd + Klick oder nacheinander wählen). Pro Datei max. {{ number_format((int) config('witness.max_file_kb', 153600) / 1024, 0, ',', '.') }} MB · bis zu {{ (int) config('witness.max_files_per_submit', 20) }} Dateien pro Absenden, sofern der Link noch genug Uploads erlaubt.</p>
                    <input type="file" name="media[]" id="media" required multiple accept="image/*,video/*,audio/*,.jpg,.jpeg,.png,.webp,.mp4,.mov,.mp3,.m4a,.wav,.webm"
                        class="block w-full text-sm text-slate-700 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-[#092E48] file:text-white">
                    @error('media')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    @error('media.*')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="witness_suggested_title" class="block text-sm font-medium text-slate-700 mb-1">{{ __('witness.witness_suggested_title_label') }}</label>
                    <p class="text-xs text-slate-500 mb-1">{{ __('witness.witness_suggested_title_help') }}</p>
                    <input type="text" name="witness_suggested_title" id="witness_suggested_title" maxlength="255" value="{{ old('witness_suggested_title') }}"
                        class="w-full min-h-[44px] rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                    @error('witness_suggested_title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="space-y-3 rounded-lg border border-slate-200 bg-slate-50/80 p-4">
                    <label class="flex gap-3 items-start cursor-pointer">
                        <input type="hidden" name="consent_terms" value="0">
                        <input type="checkbox" name="consent_terms" value="1" class="mt-1 rounded border-slate-300 text-[#092E48]" @checked(old('consent_terms')) required>
                        <span class="text-sm text-slate-800">{{ __('witness.checkbox_terms') }}</span>
                    </label>
                    <label class="flex gap-3 items-start cursor-pointer">
                        <input type="hidden" name="consent_rights" value="0">
                        <input type="checkbox" name="consent_rights" value="1" class="mt-1 rounded border-slate-300 text-[#092E48]" @checked(old('consent_rights')) required>
                        <span class="text-sm text-slate-800">{{ __('witness.checkbox_rights') }}</span>
                    </label>
                    <label class="flex gap-3 items-start cursor-pointer">
                        <input type="hidden" name="credit_anonymous" value="0">
                        <input type="checkbox" name="credit_anonymous" value="1" class="mt-1 rounded border-slate-300 text-[#092E48]" @checked(old('credit_anonymous'))>
                        <span class="text-sm text-slate-800">{{ __('witness.credit_anonymous_label') }}</span>
                    </label>
                </div>

                <button type="submit" class="w-full sm:w-auto inline-flex justify-center items-center min-h-[48px] px-6 py-2.5 rounded-lg bg-[#092E48] text-white text-sm font-medium hover:bg-[#0b3858]">
                    Absenden
                </button>
            </form>
        @endif
    </article>
@endsection
