@extends('layouts.admin')

@section('content')
    <div class="space-y-6 max-w-4xl mx-auto">
        <div>
            <a href="{{ route('admin.backoffice.billing.index') }}" class="text-sm text-gray-600 hover:text-[#092E48] mb-2 inline-block">← Abrechnung</a>
            <h1 class="text-2xl font-semibold text-gray-900">Rechnung anlegen</h1>
            <p class="mt-1 text-sm text-gray-600">
                Erzeuge einen Rechnungsentwurf für
                <span class="font-medium">{{ $customer->name }}</span>
                – Redaktion
                <span class="font-medium">{{ $product->name }}</span>.
            </p>
        </div>

        @if (session('error'))
            <div class="rounded-md bg-red-50 p-4 border border-red-200">
                <p class="text-sm text-red-800">{{ session('error') }}</p>
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Offene Videominuten</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">
                    {{ number_format((float) $openVideo, 1, ',', '.') }}
                </p>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Offene Bilder</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">
                    {{ (int) $openPhotos }}
                </p>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Offener Netto-Betrag</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">
                    {{ number_format((float) $openNet, 2, ',', '.') }} €
                </p>
                <p class="mt-1 text-xs text-gray-500">
                    {{ $openRecordsCount }} Nachverfolgungs-Einträge ohne Rechnung.
                </p>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
            <h2 class="text-base font-medium text-gray-900 mb-4">Rechnungsempfänger wählen</h2>

            @if ($billingContacts->isEmpty())
                <p class="text-sm text-gray-600">
                    Für dieses Medienhaus wurden noch keine Kontakte als Rechnungsempfänger markiert.
                    Bitte lege im Kundenbereich unter „Kontakte“ einen Kontakt mit aktivierter Option
                    „Als Ansprechpartner für Rechnungen verwenden“ an.
                </p>
            @else
                <form method="POST" action="{{ route('admin.backoffice.billing.store') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">

                    <div>
                        <label for="contact_id" class="block text-sm font-medium text-gray-700">Rechnungsempfänger (Kontakt)</label>
                        <select id="contact_id" name="contact_id" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                            <option value="">Bitte auswählen …</option>
                            @foreach($billingContacts as $contact)
                                <option value="{{ $contact->id }}" @selected($suggestedContactId === $contact->id)>
                                    {{ $contact->name }}
                                    @if($contact->email)
                                        – {{ $contact->email }}
                                    @endif
                                    @if($contact->product)
                                        (Redaktion: {{ $contact->product->name }})
                                    @endif
                                    @if($contact->billing_department || $contact->billing_type)
                                        – {{ $contact->getBillingDepartmentLabel() }}{{ $contact->billing_department && $contact->billing_type ? ' · ' : '' }}{{ $contact->getBillingTypeLabel() }}
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        @error('contact_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <p class="text-xs text-gray-500">
                        Der gewählte Kontakt wird als Rechnungsempfänger verwendet. Alle aktuell offenen Nachverfolgungs-Einträge
                        für diese Redaktion werden dem neuen Rechnungsentwurf zugeordnet.
                    </p>

                    <div class="pt-4 border-t border-gray-200 flex items-center justify-end gap-3">
                        <a href="{{ route('admin.backoffice.billing.index') }}" class="px-4 py-2 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                            Abbrechen
                        </a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]">
                            Rechnungsentwurf anlegen
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endsection

