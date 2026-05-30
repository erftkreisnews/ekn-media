@extends('layouts.admin')

@section('content')
    <div class="space-y-6 max-w-3xl">
        @if (session('status'))
            <div class="rounded-md bg-green-50 p-4 border border-green-200">
                <p class="text-sm text-green-800">{{ session('status') }}</p>
            </div>
        @endif

        <div>
            <a href="{{ route('admin.settings.index') }}" class="text-sm text-gray-600 hover:text-[#092E48] mb-2 inline-block">← Einstellungen</a>
            @php
                $invoiceVatPercent = (float) config('invoice.payment.vat_rate', 7);
            @endphp
            <h1 class="text-2xl font-semibold text-gray-900">WDR-Tarife (Nutzung)</h1>
            <p class="mt-1 text-sm text-gray-600">
                Diese Werte steuern die automatische Preisberechnung im Bereich Nutzung &amp; Auswertungen.
            </p>
            <div class="mt-4 rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800">
                <p class="font-medium text-slate-900">Nettopreise &amp; MwSt.</p>
                <p class="mt-1 text-slate-700">
                    Alle Tarife unten sind <strong>Nettobeträge</strong> (ohne Umsatzsteuer).
                    Auf Rechnungen wird auf den Nettobetrag die Umsatzsteuer von <strong>{{ number_format($invoiceVatPercent, fmod($invoiceVatPercent, 1.0) === 0.0 ? 0 : 1, ',', '.') }}&nbsp;%</strong> aufgeschlagen
                    (aktueller Wert aus der Rechnungskonfiguration, Standard 7&nbsp;%).
                </p>
            </div>
        </div>

        @if ($errors->any())
            <div class="rounded-md bg-red-50 p-4 border border-red-200">
                <p class="text-sm font-medium text-red-800 mb-2">Bitte Eingaben prüfen:</p>
                <ul class="mt-1 text-sm text-red-700 list-disc list-inside space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.settings.usage-tariffs.save') }}" class="bg-white rounded-lg border border-gray-200 shadow-sm p-6 space-y-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="wdr_newsroom_video_price_per_minute" class="block text-sm font-medium text-gray-700">WDR Newsroom Video (EUR/Minute, netto)</label>
                    <input
                        type="number"
                        min="0"
                        step="0.01"
                        id="wdr_newsroom_video_price_per_minute"
                        name="wdr_newsroom_video_price_per_minute"
                        value="{{ old('wdr_newsroom_video_price_per_minute', number_format((float) $tariffs['wdr_newsroom_video_price_per_minute'], 2, '.', '')) }}"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm"
                        required
                    >
                </div>

                <div>
                    <label for="wdr_newsroom_image_first_price" class="block text-sm font-medium text-gray-700">WDR Newsroom Bild 1 (EUR, netto)</label>
                    <input
                        type="number"
                        min="0"
                        step="0.01"
                        id="wdr_newsroom_image_first_price"
                        name="wdr_newsroom_image_first_price"
                        value="{{ old('wdr_newsroom_image_first_price', number_format((float) $tariffs['wdr_newsroom_image_first_price'], 2, '.', '')) }}"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm"
                        required
                    >
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="wdr_newsroom_image_additional_price" class="block text-sm font-medium text-gray-700">WDR Newsroom Bild 2-4 (EUR je Bild, netto)</label>
                    <input
                        type="number"
                        min="0"
                        step="0.01"
                        id="wdr_newsroom_image_additional_price"
                        name="wdr_newsroom_image_additional_price"
                        value="{{ old('wdr_newsroom_image_additional_price', number_format((float) $tariffs['wdr_newsroom_image_additional_price'], 2, '.', '')) }}"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm"
                        required
                    >
                </div>
                <div>
                    <label for="wdr_newsroom_image_from_five_price" class="block text-sm font-medium text-gray-700">WDR Newsroom ab Bild 5 (EUR je Bild, netto)</label>
                    <input
                        type="number"
                        min="0"
                        step="0.01"
                        id="wdr_newsroom_image_from_five_price"
                        name="wdr_newsroom_image_from_five_price"
                        value="{{ old('wdr_newsroom_image_from_five_price', number_format((float) $tariffs['wdr_newsroom_image_from_five_price'], 2, '.', '')) }}"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm"
                        required
                    >
                </div>
            </div>

            <div>
                <label for="wdr_newsroom_audio_price_per_minute" class="block text-sm font-medium text-gray-700">WDR Newsroom Audio (EUR/Minute, netto)</label>
                <input
                    type="number"
                    min="0"
                    step="0.01"
                    id="wdr_newsroom_audio_price_per_minute"
                    name="wdr_newsroom_audio_price_per_minute"
                    value="{{ old('wdr_newsroom_audio_price_per_minute', number_format((float) $tariffs['wdr_newsroom_audio_price_per_minute'], 2, '.', '')) }}"
                    class="mt-1 block w-full rounded-md border-gray-300 text-sm"
                    required
                >
            </div>

            <div class="pt-4 border-t border-gray-200 flex items-center justify-end gap-3">
                <a href="{{ route('admin.settings.index') }}" class="px-4 py-2 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                    Abbrechen
                </a>
                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]">
                    Speichern
                </button>
            </div>
        </form>
    </div>
@endsection
