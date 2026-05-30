@extends('layouts.admin')

@section('title', 'Bild direkt versenden')

@section('content')
    <div class="max-w-4xl mx-auto space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Bild direkt versenden</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Unabhängiger Sofortversand aus der Bild-Mediathek (ohne News-Route).
                </p>
            </div>
            <a href="{{ route('admin.images.index') }}" class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                Zur Bild-Mediathek
            </a>
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
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    @if ($medium->thumb_url || $medium->preview_url || $medium->url)
                        <img src="{{ $medium->thumb_url ?: ($medium->preview_url ?: $medium->url) }}" alt="{{ $medium->newsItem?->title ?: ($medium->newsItem?->subheadline ?: 'Bildmaterial von Erftkreis News Media') }}" class="w-full h-auto">
                    @else
                        <div class="aspect-[4/3] bg-gray-100 flex items-center justify-center text-sm text-gray-500">Keine Vorschau</div>
                    @endif
                    <div class="p-4 space-y-1 text-sm">
                        <p><span class="font-medium text-gray-700">Media-ID:</span> {{ $medium->id }}</p>
                        <p><span class="font-medium text-gray-700">Datei:</span> {{ $medium->original_name ?: ('medium-'.$medium->id) }}</p>
                        <p><span class="font-medium text-gray-700">News-ID:</span> {{ $medium->news_item_id ?: '—' }}</p>
                        <p><span class="font-medium text-gray-700">Versand:</span> {{ $medium->versand ? 'Ja' : 'Nein (wird bei Versand aktiviert)' }}</p>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl border border-emerald-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-3 border-b border-emerald-200 bg-emerald-50">
                        <h2 class="text-sm font-semibold tracking-wide text-emerald-900 uppercase">Sofortversand</h2>
                    </div>
                    <form method="post" action="{{ route('admin.images.quick-send.post', $medium->id) }}" class="p-6 space-y-4">
                        @csrf
                        <p class="text-sm text-gray-600">
                            Versandziel wählen oder manuelle E-Mail-Adresse eintragen. Dieser Versand ist unabhängig vom News-Bearbeitungsweg.
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
                                placeholder="z. B. EIL: Einsatzbilder – 2 Fotos"
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
                                placeholder="Kurze Einsatz-Info, Hinweise zur Nutzung ..."
                            >{{ old('editor_note') }}</textarea>
                        </div>

                        <div class="pt-1">
                            <button
                                type="submit"
                                class="inline-flex w-full items-center justify-center px-4 py-3 text-base font-bold rounded-xl border-2 border-[#092E48] bg-[#092E48] text-white hover:bg-[#0b3858] shadow-sm min-h-[3rem] tracking-wide focus:outline-none focus:ring-2 focus:ring-[#092E48]/40"
                                style="background-color:#092E48;color:#ffffff;"
                            >
                                Sofortversand per Mail starten
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
