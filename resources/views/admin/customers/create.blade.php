@extends('layouts.admin')

@section('content')
<div class="py-6">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-semibold text-gray-900">Neues Medienhaus</h1>
        <p class="mt-1 text-sm text-gray-600">Übergeordnete Organisation anlegen, z. B. WDR oder RTL.</p>

        <form method="POST" action="{{ route('admin.customers.store') }}" class="mt-6 bg-white rounded-lg border border-gray-200 shadow-sm p-6 space-y-4">
            @csrf
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Name *</label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="notes" class="block text-sm font-medium text-gray-700">Notizen</label>
                <textarea name="notes" id="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">{{ old('notes') }}</textarea>
            </div>
            <div>
                <label class="inline-flex items-center">
                    <input type="checkbox" name="active" value="1" {{ old('active', true) ? 'checked' : '' }} class="rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]">
                    <span class="ml-2 text-sm text-gray-700">Aktiv</span>
                </label>
            </div>
            <div class="border-t border-gray-200 pt-4 mt-4">
                <h2 class="text-base font-semibold text-gray-900 mb-1">Externe Identifikatoren (optional)</h2>
                <p class="text-sm text-gray-600 mb-3">Einmal pro Medienhaus – gelten für alle Versandziele dieser Organisation.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="sm:col-span-2">
                        <label for="external_reference_label" class="block text-sm font-medium text-gray-700">Bezeichnung (Label)</label>
                        <input type="text" name="external_reference_label" id="external_reference_label" value="{{ old('external_reference_label') }}" placeholder="z.B. dpa Autorennummer" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                    </div>
                    <div>
                        <label for="external_author_id" class="block text-sm font-medium text-gray-700">Autorennummer</label>
                        <input type="text" name="external_author_id" id="external_author_id" value="{{ old('external_author_id') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                    </div>
                    <div>
                        <label for="external_supplier_id" class="block text-sm font-medium text-gray-700">Lieferanten-ID</label>
                        <input type="text" name="external_supplier_id" id="external_supplier_id" value="{{ old('external_supplier_id') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                    </div>
                    <div>
                        <label for="external_vendor_code" class="block text-sm font-medium text-gray-700">Vendor-Code</label>
                        <input type="text" name="external_vendor_code" id="external_vendor_code" value="{{ old('external_vendor_code') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                    </div>
                </div>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]">Anlegen</button>
                <a href="{{ route('admin.customers.index') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-gray-300 text-gray-700 bg-white hover:bg-gray-50">Abbrechen</a>
            </div>
        </form>
    </div>
</div>
@endsection
