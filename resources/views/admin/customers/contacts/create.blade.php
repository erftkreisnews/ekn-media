@extends('layouts.admin')
@section('content')
<div class="py-6 max-w-2xl mx-auto px-4">
    <h1 class="text-2xl font-semibold text-gray-900">Neuer Rechnungsempfänger / Kontakt</h1>
    <p class="text-sm text-gray-600">{{ $customer->name }}</p>
    <form method="POST" action="{{ url('/admin/customers/' . (int) $customer->id . '/contacts') }}" class="mt-6 bg-white rounded-lg border border-gray-200 p-6 space-y-4">
        @csrf
        <div><label for="name" class="block text-sm font-medium text-gray-700">Name (optional)</label><input type="text" name="name" id="name" value="{{ old('name') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="z. B. Newsroom Honorar oder leer lassen">@error('name')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
        <div><label for="email" class="block text-sm font-medium text-gray-700">E-Mail *</label><input type="email" name="email" id="email" value="{{ old('email') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">@error('email')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
        <div><label for="phone" class="block text-sm font-medium text-gray-700">Telefon</label><input type="text" name="phone" id="phone" value="{{ old('phone') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></div>
        <div><label for="role" class="block text-sm font-medium text-gray-700">Rolle</label><input type="text" name="role" id="role" value="{{ old('role') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></div>
        <div><label for="product_id" class="block text-sm font-medium text-gray-700">Abteilung / Redaktion</label><select name="product_id" id="product_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"><option value="">— Medienhaus-weit —</option>@foreach($products as $pr)<option value="{{ $pr->id }}">{{ $pr->name }}</option>@endforeach</select></div>
        <div class="border-t border-gray-200 pt-4 mt-4">
            <p class="text-sm font-medium text-gray-700 mb-2">Rechnungsversand</p>
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="use_for_invoice" value="1" {{ old('use_for_invoice') ? 'checked' : '' }} class="rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]"> Als Rechnungsempfänger verwenden (Person oder Funktionsadresse, bei Rechnungserstellung auswählbar)</label>
            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div><label for="billing_department" class="block text-xs font-medium text-gray-600">Abrechnungs-Redaktion (optional / Legacy)</label><select name="billing_department" id="billing_department" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"><option value="">— Keine —</option><option value="newsroom" {{ old('billing_department') === 'newsroom' ? 'selected' : '' }}>Newsroom</option><option value="studio_koeln" {{ old('billing_department') === 'studio_koeln' ? 'selected' : '' }}>Studio Köln</option><option value="studio_bonn" {{ old('billing_department') === 'studio_bonn' ? 'selected' : '' }}>Studio Bonn</option></select></div>
                <div><label for="billing_type" class="block text-xs font-medium text-gray-600">Lizenz / Honorar</label><select name="billing_type" id="billing_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"><option value="">— Keine —</option><option value="lizenz" {{ old('billing_type') === 'lizenz' ? 'selected' : '' }}>Lizenz</option><option value="honorar" {{ old('billing_type') === 'honorar' ? 'selected' : '' }}>Honorar</option></select></div>
            </div>
            <p class="mt-2 text-xs text-gray-500">Funktionsadressen (z. B. newsroom-honorare@...) sind ausdrücklich erlaubt. Wenn der Name leer bleibt, wird automatisch die E-Mail als Bezeichnung verwendet.</p>
        </div>
        <div class="flex gap-3"><button type="submit" class="px-4 py-2 text-sm font-medium rounded-md text-white bg-[#092E48]">Anlegen</button><a href="{{ url('/admin/customers/' . (int) $customer->id . '/contacts') }}" class="px-4 py-2 text-sm font-medium rounded-md border border-gray-300 bg-white">Abbrechen</a></div>
    </form>
</div>
@endsection
