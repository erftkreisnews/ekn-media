@extends('layouts.admin')

@section('content')
    <div class="space-y-6">
        <div>
            <a href="{{ route('admin.backoffice.usage.index') }}" class="text-sm text-gray-600 hover:text-[#092E48] mb-2 inline-block">← Nutzung &amp; Auswertungen</a>
            <h1 class="text-2xl font-semibold text-gray-900">Nachverfolgung bearbeiten</h1>
            <p class="mt-1 text-sm text-gray-600">
                Aktualisiere den Nutzungs-Eintrag so, dass Format, Nachweis und journalistische Nutzung auf der Rechnung korrekt erscheinen.
            </p>
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

        <form method="POST" action="{{ route('admin.backoffice.usage.update', $record) }}" class="bg-white rounded-lg border border-gray-200 shadow-sm p-6 space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="news_item_id" class="block text-sm font-medium text-gray-700">Nachricht (NewsID)</label>
                    <select id="news_item_id" name="news_item_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm" required>
                        <option value="">Bitte auswählen …</option>
                        @foreach($newsItems as $item)
                            <option value="{{ $item->id }}" @selected(old('news_item_id', $record->news_item_id) == $item->id)>
                                #{{ $item->id }} – {{ \Illuminate\Support\Str::limit($item->title, 80) }}
                                @if($item->published_at)
                                    ({{ $item->published_at->format('d.m.Y H:i') }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="used_at" class="block text-sm font-medium text-gray-700">Nutzungsdatum</label>
                    <input type="date" id="used_at" name="used_at" value="{{ old('used_at', optional($record->used_at)->format('Y-m-d')) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm" required>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="organization_id" class="block text-sm font-medium text-gray-700">Medienhaus</label>
                    <select id="organization_id" name="organization_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm" required>
                        <option value="">Bitte auswählen …</option>
                        @foreach($organizations as $org)
                            <option value="{{ $org->id }}" @selected(old('organization_id', $record->organization_id) == $org->id)>
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
                            <option value="{{ $product->id }}" @selected(old('product_id', $record->product_id) == $product->id)>
                                {{ $product->organization?->name ? $product->organization->name.' – ' : '' }}{{ $product->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="usage_format" class="block text-sm font-medium text-gray-700">Format / Sendung / Plattform</label>
                    <input type="text" id="usage_format" name="usage_format" value="{{ old('usage_format', $record->usage_format) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm" placeholder="z. B. Online/App oder WDR Aktuell 12:45, 16:00, AKS 18:45" required>
                </div>

                <div>
                    <label for="reference_code" class="block text-sm font-medium text-gray-700">PVNr. / Referenz / Aktenzeichen</label>
                    <input type="text" id="reference_code" name="reference_code" value="{{ old('reference_code', $record->reference_code) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm" placeholder="z. B. 00890687">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="billing_department" class="block text-sm font-medium text-gray-700">Abrechnungs‑Einheit (optional / Legacy)</label>
                    <select id="billing_department" name="billing_department" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        <option value="">Keine besondere Einheit / nicht WDR</option>
                        <option value="newsroom" @selected(old('billing_department', $record->billing_department) === 'newsroom')>WDR Newsroom</option>
                        <option value="studio_koeln" @selected(old('billing_department', $record->billing_department) === 'studio_koeln')>WDR Studio Köln</option>
                        <option value="studio_bonn" @selected(old('billing_department', $record->billing_department) === 'studio_bonn')>WDR Studio Bonn</option>
                    </select>
                </div>

                <div>
                    <label for="billing_type" class="block text-sm font-medium text-gray-700">Abrechnungsart (Lizenz / Honorar)</label>
                    <select id="billing_type" name="billing_type" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        <option value="">Keine Auswahl / nicht relevant</option>
                        <option value="lizenz" @selected(old('billing_type', $record->billing_type) === 'lizenz')>Lizenz</option>
                        <option value="honorar" @selected(old('billing_type', $record->billing_type) === 'honorar')>Honorar</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Mengen</label>
                    <div class="mt-1 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="images_count" class="block text-xs font-medium text-gray-600">Anzahl genutzter Bilder</label>
                            <input type="number" min="0" step="1" id="images_count" name="images_count" value="{{ old('images_count', $record->images_count) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        </div>
                        <div>
                            <label for="video_minutes" class="block text-xs font-medium text-gray-600">Sendeminuten Video</label>
                            <input type="number" min="0" step="0.1" id="video_minutes" name="video_minutes" value="{{ old('video_minutes', (float) $record->video_minutes) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Preise (netto)</label>
                    <div class="mt-1 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="price_per_image" class="block text-xs font-medium text-gray-600">Preis pro Bild (netto, €)</label>
                            <input type="number" min="0" step="0.01" id="price_per_image" name="price_per_image" value="{{ old('price_per_image', (float) $record->price_per_image) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        </div>
                        <div>
                            <label for="price_per_minute" class="block text-xs font-medium text-gray-600">Preis pro Videominute (netto, €)</label>
                            <input type="number" min="0" step="0.01" id="price_per_minute" name="price_per_minute" value="{{ old('price_per_minute', (float) $record->price_per_minute) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="usage_rights" class="block text-sm font-medium text-gray-700">Nutzungsrecht</label>
                    <input type="text" id="usage_rights" name="usage_rights" value="{{ old('usage_rights', $record->usage_rights) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm" placeholder="z. B. uneingeschränkte Weltrechte">
                </div>

                <div>
                    <label for="line_item_note" class="block text-sm font-medium text-gray-700">Positionshinweis</label>
                    <input type="text" id="line_item_note" name="line_item_note" value="{{ old('line_item_note', $record->line_item_note) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm" placeholder="z. B. Schnittbilder mit O-Ton">
                </div>
            </div>

            <div>
                <label for="article_url" class="block text-sm font-medium text-gray-700">Beitrags‑URL (Nachweis)</label>
                <input type="url" id="article_url" name="article_url" value="{{ old('article_url', $record->article_url) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm" placeholder="https://…">
            </div>

            <div class="pt-4 border-t border-gray-200 flex items-center justify-end gap-3">
                <a href="{{ route('admin.backoffice.usage.index') }}" class="px-4 py-2 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                    Abbrechen
                </a>
                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]">
                    Änderungen speichern
                </button>
            </div>
        </form>

        <div class="pt-2">
            <form
                method="POST"
                action="{{ route('admin.backoffice.usage.destroy', $record) }}"
                onsubmit="return confirm('Diesen Nutzungs-Eintrag wirklich löschen?');"
            >
                @csrf
                @method('DELETE')
                <button
                    type="submit"
                    class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700"
                >
                    Nutzung löschen
                </button>
            </form>
        </div>
    </div>
@endsection
