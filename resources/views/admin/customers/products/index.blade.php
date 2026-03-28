@extends('layouts.admin')
@section('content')
<div class="py-6 max-w-7xl mx-auto px-4">
    <h1 class="text-2xl font-semibold text-gray-900">Abteilungen / Redaktionen</h1>
    <p class="text-sm text-gray-600">{{ $customer->name }}</p>
    @if($customer->id)
    <a href="{{ url('/admin/customers/' . (int) $customer->id . '/products/create') }}" class="mt-4 inline-flex px-4 py-2 text-sm font-medium rounded-md text-white bg-[#092E48]">+ Redaktion anlegen</a>
@endif
    @if (session('status'))<div class="mt-4 rounded-md bg-green-50 p-4">{{ session('status') }}</div>@endif
    <div class="mt-6 bg-white rounded-lg border overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Redaktion</th><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kundennummer</th><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Versandziele</th><th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aktionen</th></tr></thead>
            <tbody>
                @forelse($products as $p)
                <tr class="divide-y"><td class="px-4 py-3 font-medium">{{ $p->name }}</td><td class="px-4 py-3 text-sm">{{ $p->buyer_reference ?: '–' }}</td><td class="px-4 py-3 text-sm">{{ $p->delivery_destinations_count }}</td><td class="px-4 py-3 text-right text-sm">@if($customer->id)
                    <a href="{{ url('/admin/products/' . $p->id . '/destinations') }}" class="text-[#092E48] hover:underline">Versandziele</a> <a href="{{ url('/admin/customers/' . (int) $customer->id . '/products/' . $p->id . '/edit') }}" class="text-gray-600 hover:underline">Bearbeiten</a> <form action="{{ url('/admin/customers/' . (int) $customer->id . '/products/' . $p->id) }}" method="POST" class="inline" onsubmit="return confirm('Redaktion löschen?');">@csrf @method('DELETE')<button type="submit" class="text-red-600 hover:underline">Löschen</button></form>
                    @endif</td></tr>
                @empty
                <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">Noch keine Redaktionen.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($customer->id)
    <p class="mt-4"><a href="{{ url('/admin/customers/' . (int) $customer->id) }}" class="text-[#092E48]">Zurück</a></p>
@else
    <p class="mt-4"><a href="{{ url('/admin/customers') }}" class="text-[#092E48]">Zurück zu Medienhäusern</a></p>
@endif
</div>
@endsection
