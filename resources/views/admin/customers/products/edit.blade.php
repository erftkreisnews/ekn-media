@extends('layouts.admin')
@section('content')
<div class="py-6">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-semibold text-gray-900">Abteilung / Redaktion bearbeiten</h1>
        <p class="mt-1 text-sm text-gray-600">{{ $customer->name }} · {{ $product->name }}</p>
        @if (session('status'))<div class="mt-4 rounded-md bg-green-50 p-4"><p class="text-sm text-green-800">{{ session('status') }}</p></div>@endif
        @if (session('error'))<div class="mt-4 rounded-md bg-red-50 p-4"><p class="text-sm text-red-800">{{ session('error') }}</p></div>@endif
        @if(config('lexware.api_key'))
            <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4 flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-gray-900">Lexware-Stand uebernehmen</p>
                    <p class="text-xs text-gray-600">
                        Laedt Kundennummer, Rechnungsadresse und E-Mail-Adressen aus dem aktuell verknuepften Lexware-Kontakt in diese Redaktion.
                    </p>
                </div>
                <form method="POST" action="{{ route('admin.customers.products.import-lexware', [$customer, $product]) }}">
                    @csrf
                    <button type="submit" @if(!$product->lexware_contact_id) disabled @endif class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md {{ $product->lexware_contact_id ? 'text-white bg-[#092E48] hover:bg-[#0b3858]' : 'text-gray-400 bg-gray-200 cursor-not-allowed' }}">
                        Daten aus Lexware uebernehmen
                    </button>
                </form>
            </div>
        @endif
        <form method="POST" action="{{ route('admin.customers.products.update', [$customer, $product]) }}" class="mt-6 bg-white rounded-lg border border-gray-200 shadow-sm p-6 space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Name *</label>
                <input type="text" name="name" id="name" value="{{ old('name', $product->name) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="buyer_reference" class="block text-sm font-medium text-gray-700">Kundennummer / Buyer Reference</label>
                <input type="text" name="buyer_reference" id="buyer_reference" value="{{ old('buyer_reference', $product->buyer_reference) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                @error('buyer_reference')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="border-t border-gray-200 pt-4 mt-4">
                <h2 class="text-base font-semibold text-gray-900 mb-1">Rechnungsadresse</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="sm:col-span-2">
                        <label for="billing_name" class="block text-sm font-medium text-gray-700">Adressname</label>
                        <input type="text" name="billing_name" id="billing_name" value="{{ old('billing_name', $product->billing_name) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="billing_company" class="block text-sm font-medium text-gray-700">Firma / Medienhaus</label>
                        <input type="text" name="billing_company" id="billing_company" value="{{ old('billing_company', $product->billing_company ?: $customer->name) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="billing_street" class="block text-sm font-medium text-gray-700">Straße / Hausnummer</label>
                        <input type="text" name="billing_street" id="billing_street" value="{{ old('billing_street', $product->billing_street) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                    </div>
                    <div>
                        <label for="billing_postal_code" class="block text-sm font-medium text-gray-700">PLZ</label>
                        <input type="text" name="billing_postal_code" id="billing_postal_code" value="{{ old('billing_postal_code', $product->billing_postal_code) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                    </div>
                    <div>
                        <label for="billing_city" class="block text-sm font-medium text-gray-700">Ort</label>
                        <input type="text" name="billing_city" id="billing_city" value="{{ old('billing_city', $product->billing_city) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="billing_country" class="block text-sm font-medium text-gray-700">Land</label>
                        <input type="text" name="billing_country" id="billing_country" value="{{ old('billing_country', $product->billing_country ?: 'Deutschland') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-200 pt-4 mt-4">
                <h2 class="text-base font-semibold text-gray-900 mb-1">Rechnungs-E-Mails</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="billing_email_primary" class="block text-sm font-medium text-gray-700">Primäre E-Mail</label>
                        <input type="email" name="billing_email_primary" id="billing_email_primary" value="{{ old('billing_email_primary', $product->billing_email_primary) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                    </div>
                    <div>
                        <label for="billing_email_secondary" class="block text-sm font-medium text-gray-700">Weitere E-Mail</label>
                        <input type="email" name="billing_email_secondary" id="billing_email_secondary" value="{{ old('billing_email_secondary', $product->billing_email_secondary) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                    </div>
                </div>
                <div class="mt-3">
                    <label for="billing_notes" class="block text-sm font-medium text-gray-700">Notizen</label>
                    <textarea name="billing_notes" id="billing_notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">{{ old('billing_notes', $product->billing_notes) }}</textarea>
                </div>
                @if(config('lexware.api_key'))
                    <p class="mt-3 text-xs text-gray-500">Lexware Kontakt-ID: {{ $product->lexware_contact_id ?: 'noch nicht zugeordnet' }}</p>
                @endif
            </div>
            <div>
                <label class="inline-flex items-center">
                    <input type="checkbox" name="active" value="1" {{ old('active', $product->active) ? 'checked' : '' }} class="rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]">
                    <span class="ml-2 text-sm text-gray-700">Aktiv</span>
                </label>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]">Speichern</button>
                <a href="{{ route('admin.destinations.index', $product) }}" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-gray-300 text-gray-700 bg-white hover:bg-gray-50">Versandziele</a>
                <a href="{{ route('admin.customers.products.index', $customer) }}" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-gray-300 text-gray-700 bg-white hover:bg-gray-50">Zurück</a>
            </div>
        </form>
    </div>
</div>
@endsection
