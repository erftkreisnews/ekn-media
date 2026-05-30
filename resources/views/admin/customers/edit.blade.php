@extends('layouts.admin')

@section('content')
    <div class="py-6">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-2xl font-semibold text-gray-900">Medienhaus bearbeiten</h1>
            <p class="mt-1 text-sm text-gray-600">{{ $customer->name }}</p>

            @if (session('status'))
                <div class="mt-4 rounded-md bg-green-50 p-4 border border-green-200">
                    <p class="text-sm text-green-800">{{ session('status') }}</p>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.customers.update', $customer) }}" class="mt-6 bg-white rounded-lg border border-gray-200 shadow-sm p-6 space-y-4">
                @csrf
                
                
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">Name *</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $customer->name) }}" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                    @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700">Notizen</label>
                    <textarea name="notes" id="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">{{ old('notes', $customer->notes) }}</textarea>
                </div>
                <div>
                    <label for="publication_domains" class="block text-sm font-medium text-gray-700">Web-Domains (Lizenz-Fundstellen)</label>
                    <textarea name="publication_domains" id="publication_domains" rows="4" placeholder="wdr.de&#10;www1.wdr.de&#10;koeln.de"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] font-mono text-sm">{{ old('publication_domains', $customer->publication_domains) }}</textarea>
                    <p class="mt-1 text-xs text-gray-500">Eine Domain pro Zeile (oder kommagetrennt). Treffer-URLs mit passender Domain werden als „lizenziert“ vorgeschlagen.</p>
                </div>
                <div>
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="active" value="1" {{ old('active', $customer->active) ? 'checked' : '' }} class="rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]">
                        <span class="ml-2 text-sm text-gray-700">Aktiv</span>
                    </label>
                </div>

                @can('admin.customers.billing_sensitive')
                <div class="border-t border-gray-200 pt-4 mt-4">
                    <h2 class="text-base font-semibold text-gray-900 mb-1">Externe Identifikatoren</h2>
                    <p class="text-sm text-gray-600 mb-3">Einmal pro Medienhaus – gelten für alle Versandziele dieser Organisation.</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="sm:col-span-2">
                            <label for="external_reference_label" class="block text-sm font-medium text-gray-700">Bezeichnung (Label)</label>
                            <input type="text" name="external_reference_label" id="external_reference_label" value="{{ old('external_reference_label', $customer->external_reference_label) }}" placeholder="z.B. dpa Autorennummer"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                        </div>
                        <div>
                            <label for="external_author_id" class="block text-sm font-medium text-gray-700">Autorennummer</label>
                            <input type="text" name="external_author_id" id="external_author_id" value="{{ old('external_author_id', $customer->external_author_id) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                        </div>
                        <div>
                            <label for="external_supplier_id" class="block text-sm font-medium text-gray-700">Lieferanten-ID</label>
                            <input type="text" name="external_supplier_id" id="external_supplier_id" value="{{ old('external_supplier_id', $customer->external_supplier_id) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                        </div>
                        <div>
                            <label for="external_vendor_code" class="block text-sm font-medium text-gray-700">Vendor-Code</label>
                            <input type="text" name="external_vendor_code" id="external_vendor_code" value="{{ old('external_vendor_code', $customer->external_vendor_code) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                        </div>
                    </div>
                </div>
                @endcan
                <div class="flex gap-3 pt-2">
                    <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]">Speichern</button>
                    <a href="{{ route('admin.customers.show', $customer) }}" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-gray-300 text-gray-700 bg-white hover:bg-gray-50">Zurück</a>
                </div>
            </form>
        </div>
    </div>
@endsection
