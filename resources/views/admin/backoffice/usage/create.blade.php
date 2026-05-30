@extends('layouts.admin')

@section('content')
    <div class="space-y-6">
        <div>
            <a href="{{ route('admin.backoffice.usage.index') }}" class="text-sm text-gray-600 hover:text-[#092E48] mb-2 inline-block">← Nutzung &amp; Auswertungen</a>
            <h1 class="text-2xl font-semibold text-gray-900">Nachverfolgung erfassen</h1>
            <p class="mt-1 text-sm text-gray-600">
                Neuen Nutzungs-Eintrag anlegen (News, Medienhaus, Format, Mengen). Der Eintrag erscheint später auf der Rechnung.
            </p>
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

        <form method="POST" action="{{ route('admin.backoffice.usage.store') }}" class="bg-white rounded-lg border border-gray-200 shadow-sm p-6 space-y-6" id="usage-form-create">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="news_item_id" class="block text-sm font-medium text-gray-700">Nachricht (NewsID, optional)</label>
                    <select id="news_item_id" name="news_item_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        <option value="" @selected(old('news_item_id') === null || old('news_item_id') === '')>Freies Angebot</option>
                        @foreach($newsItems as $item)
                            <option value="{{ $item->id }}" @selected(old('news_item_id') == $item->id)>
                                #{{ $item->id }} – {{ \Illuminate\Support\Str::limit($item->title, 80) }}
                                @if($item->published_at)
                                    ({{ $item->published_at->format('d.m.Y H:i') }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">„Freies Angebot“, wenn die Nutzung keiner EKN-Meldung (NewsID) zugeordnet ist.</p>
                </div>

                <div>
                    <label for="used_at" class="block text-sm font-medium text-gray-700">Nutzungsdatum</label>
                    <input type="date" id="used_at" name="used_at" value="{{ old('used_at', now()->format('Y-m-d')) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm" required>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="organization_id" class="block text-sm font-medium text-gray-700">Medienhaus</label>
                    <select id="organization_id" name="organization_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm" required>
                        <option value="">Bitte auswählen …</option>
                        @foreach($organizations as $org)
                            <option value="{{ $org->id }}" @selected(old('organization_id') == $org->id)>
                                {{ $org->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="product_id" class="block text-sm font-medium text-gray-700">Abteilung / Rechnungseinheit</label>
                    <select id="product_id" name="product_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm" required>
                        <option value="">Bitte auswählen …</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>
                                {{ $product->organization?->name ? $product->organization->name.' – ' : '' }}{{ $product->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="usage_format" class="block text-sm font-medium text-gray-700">Format / Sendung / Plattform</label>
                    <input type="text" id="usage_format" name="usage_format" value="{{ old('usage_format') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm" placeholder="z. B. Online/App oder WDR Aktuell 12:45, 16:00, AKS 18:45" required>
                </div>

                <div>
                    <label for="reference_code" class="block text-sm font-medium text-gray-700">PVNr. / Referenz / Aktenzeichen</label>
                    <input type="text" id="reference_code" name="reference_code" value="{{ old('reference_code') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm" placeholder="z. B. 00890687">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="billing_department" class="block text-sm font-medium text-gray-700">Abrechnungs‑Einheit (optional / Legacy)</label>
                    <select id="billing_department" name="billing_department" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        <option value="">Keine besondere Einheit / nicht WDR</option>
                        <option value="newsroom" @selected(old('billing_department') === 'newsroom')>WDR Newsroom</option>
                        <option value="studio_koeln" @selected(old('billing_department') === 'studio_koeln')>WDR Studio Köln</option>
                        <option value="studio_bonn" @selected(old('billing_department') === 'studio_bonn')>WDR Studio Bonn</option>
                    </select>
                    @if(auth()->user()?->hasRole('admin'))
                        <p class="mt-1 text-xs text-gray-500">Bei WDR Newsroom gelten automatisch Netto-Tarife: Video {{ number_format((float) ($wdrNewsroomTariff['video_per_minute'] ?? 448.60), 2, ',', '.') }} €/Min; Audio {{ number_format((float) ($wdrNewsroomTariff['audio_per_minute'] ?? 0), 2, ',', '.') }} €/Min; Bilder gestaffelt (1. Bild {{ number_format((float) ($wdrNewsroomTariff['image_first'] ?? 42.37), 2, ',', '.') }} €, Bild 2-4 {{ number_format((float) ($wdrNewsroomTariff['image_additional'] ?? 28.25), 2, ',', '.') }} €, ab Bild 5 {{ number_format((float) ($wdrNewsroomTariff['image_from_five'] ?? 28.25), 2, ',', '.') }} €).</p>
                    @endif
                </div>

                <div>
                    <label for="billing_type" class="block text-sm font-medium text-gray-700">Abrechnungsart (WDR: Foto+Video = Lizenz, Audio = Honorar)</label>
                    <select id="billing_type" name="billing_type" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        <option value="">Automatisch (Standard: Lizenz)</option>
                        <option value="lizenz" @selected(old('billing_type') === 'lizenz')>Lizenz</option>
                        <option value="honorar" @selected(old('billing_type') === 'honorar')>Honorar (Audio)</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Mengen</label>
                    <div class="mt-1 grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label for="images_count" class="block text-xs font-medium text-gray-600">Anzahl genutzter Bilder</label>
                            <input type="number" min="0" step="1" id="images_count" name="images_count" value="{{ old('images_count', 0) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        </div>
                        <div>
                            <label for="video_minutes" class="block text-xs font-medium text-gray-600">Sendeminuten Video (TV)</label>
                            <input type="number" min="0" step="0.1" id="video_minutes" name="video_minutes" value="{{ old('video_minutes', 0) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        </div>
                        <div>
                            <label for="radio_minutes" class="block text-xs font-medium text-gray-600">Sendeminuten Radio</label>
                            <input type="number" min="0" step="0.1" id="radio_minutes" name="radio_minutes" value="{{ old('radio_minutes', 0) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        </div>
                        <div>
                            <label for="print_copies" class="block text-xs font-medium text-gray-600">Print-Ausgabe / Auflage</label>
                            <input type="number" min="0" step="1" id="print_copies" name="print_copies" value="{{ old('print_copies', 0) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        </div>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Video und Radio nicht in einem Eintrag kombinieren. Honorar (Audio): Abrechnungsart „Honorar“ und Radio-Sendeminuten; Minutenpreis unten eintragen.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Preise (netto)</label>
                    <div class="mt-1 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="price_per_image" class="block text-xs font-medium text-gray-600">Preis pro Bild (netto, €)</label>
                            <input type="number" min="0" step="0.01" id="price_per_image" name="price_per_image" value="{{ old('price_per_image', 0) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        </div>
                        <div>
                            <label for="price_per_minute" class="block text-xs font-medium text-gray-600">Preis pro Minute Video/Radio (netto, €)</label>
                            <input type="number" min="0" step="0.01" id="price_per_minute" name="price_per_minute" value="{{ old('price_per_minute', 0) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="usage_rights" class="block text-sm font-medium text-gray-700">Nutzungsrecht</label>
                    <input type="text" id="usage_rights" name="usage_rights" value="{{ old('usage_rights') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm" placeholder="z. B. uneingeschränkte Weltrechte">
                </div>

                <div>
                    <label for="line_item_note" class="block text-sm font-medium text-gray-700">Positionshinweis</label>
                    <input type="text" id="line_item_note" name="line_item_note" value="{{ old('line_item_note') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm" placeholder="z. B. Schnittbilder mit O-Ton">
                </div>
            </div>

            <div>
                <label for="article_url" class="block text-sm font-medium text-gray-700">Beitrags‑URL (Nachweis)</label>
                <input type="url" id="article_url" name="article_url" value="{{ old('article_url') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm" placeholder="https://…">
                <p class="mt-1 text-xs text-gray-500">Nur bei Online-Nutzung mit Bildern erforderlich.</p>
            </div>

            <div class="space-y-3">
                <label class="inline-flex items-start gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="text_taken_over" value="1" class="mt-0.5 rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]" @checked(old('text_taken_over'))>
                    <span>Text wurde übernommen (wichtig für RAG/Training)</span>
                </label>
                <div>
                    <label for="text_taken_over_excerpt" class="block text-sm font-medium text-gray-700">Übernommener Text (optional)</label>
                    <textarea id="text_taken_over_excerpt" name="text_taken_over_excerpt" rows="4" class="mt-1 block w-full rounded-md border-gray-300 text-sm" placeholder="Optionaler Auszug oder Hinweis, welcher Text übernommen wurde …">{{ old('text_taken_over_excerpt') }}</textarea>
                </div>
            </div>

            <div class="pt-4 border-t border-gray-200 flex items-center justify-end gap-3">
                <a href="{{ route('admin.backoffice.usage.index') }}" class="px-4 py-2 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                    Abbrechen
                </a>
                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]">
                    Eintrag speichern
                </button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('usage-form-create');
    if (!form) return;

    const billingDepartment = form.querySelector('#billing_department');
    const billingType = form.querySelector('#billing_type');
    const pricePerImage = form.querySelector('#price_per_image');
    const pricePerMinute = form.querySelector('#price_per_minute');
    const productSelect = form.querySelector('#product_id');
    const videoMinutes = form.querySelector('#video_minutes');
    const radioMinutes = form.querySelector('#radio_minutes');

    if (!billingDepartment || !pricePerImage || !pricePerMinute || !productSelect) return;

    const isNewsroom = () => {
        if (billingDepartment.value === 'newsroom') return true;
        const selectedText = (productSelect.options[productSelect.selectedIndex]?.text || '').toLowerCase();
        return selectedText.includes('newsroom');
    };

    const applyNewsroomTariff = () => {
        if (!isNewsroom()) return;
        if (billingType && billingType.value === 'honorar') {
            return;
        }
        const v = videoMinutes ? Number(videoMinutes.value) : 0;
        const r = radioMinutes ? Number(radioMinutes.value) : 0;
        if (r > 0 && v <= 0) {
            pricePerMinute.value = @json($wdrNewsroomTariffJs['audio_per_minute'] ?? '0.00');
            return;
        }
        pricePerMinute.value = @json($wdrNewsroomTariffJs['video_per_minute'] ?? '448.60');
        if (!pricePerImage.value || Number(pricePerImage.value) === 0) {
            pricePerImage.value = @json($wdrNewsroomTariffJs['image_first'] ?? '42.37');
        }
    };

    billingDepartment.addEventListener('change', applyNewsroomTariff);
    if (billingType) billingType.addEventListener('change', applyNewsroomTariff);
    productSelect.addEventListener('change', applyNewsroomTariff);
    if (videoMinutes) videoMinutes.addEventListener('input', applyNewsroomTariff);
    if (radioMinutes) radioMinutes.addEventListener('input', applyNewsroomTariff);
    applyNewsroomTariff();
})();
</script>
@endpush
