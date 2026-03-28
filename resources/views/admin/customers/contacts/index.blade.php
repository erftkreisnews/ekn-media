@extends('layouts.admin')
@section('content')
<div class="py-6 max-w-7xl mx-auto px-4">
    <h1 class="text-2xl font-semibold text-gray-900">Kontakte</h1>
    <p class="text-sm text-gray-600">{{ $customer->name }}</p>
    @if($customer->id)<a href="{{ url('/admin/customers/' . (int) $customer->id . '/contacts/create') }}" class="mt-4 inline-flex px-4 py-2 text-sm font-medium rounded-md text-white bg-[#092E48]">Neuer Kontakt</a>@endif
    @if (session('status'))<div class="mt-4 rounded-md bg-green-50 p-4">{{ session('status') }}</div>@endif
    <div class="mt-6 bg-white rounded-lg border overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">E-Mail</th><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Redaktion</th><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rechnung</th><th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aktionen</th></tr></thead>
            <tbody>
                @forelse($contacts as $c)
                <tr><td class="px-4 py-3 font-medium">{{ $c->name }}</td><td class="px-4 py-3 text-sm">{{ $c->email }}</td><td class="px-4 py-3 text-sm">{{ $c->product?->name ?: 'Medienhaus-weit' }}</td><td class="px-4 py-3 text-sm">@if($c->use_for_invoice)<span class="inline-flex items-center rounded-md bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Rechnungsadresse</span>@if($c->billing_department || $c->billing_type)<span class="ml-1 text-gray-500">{{ $c->getBillingDepartmentLabel() }}{{ $c->billing_department && $c->billing_type ? ' · ' : '' }}{{ $c->getBillingTypeLabel() }}</span>@endif @else<span class="text-gray-400">—</span>@endif</td><td class="px-4 py-3 text-right">@if($customer->id)<a href="{{ url('/admin/customers/' . (int) $customer->id . '/contacts/' . $c->id . '/edit') }}" class="text-[#092E48] hover:underline">Bearbeiten</a> <form action="{{ url('/admin/customers/' . (int) $customer->id . '/contacts/' . $c->id) }}" method="POST" class="inline" onsubmit="return confirm('Kontakt löschen?');">@csrf @method('DELETE')<button type="submit" class="text-red-600 hover:underline">Löschen</button></form>@endif</td></tr>
                @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">Noch keine Kontakte.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="mt-4"><a href="{{ url('/admin/customers/' . (int) $customer->id) }}" class="text-[#092E48]">Zurück</a></p>
</div>
@endsection
