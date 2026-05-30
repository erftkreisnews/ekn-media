@extends('layouts.admin')

@section('content')
<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">{{ $customer->name }}</h1>
                <p class="mt-1 text-sm text-gray-600">Medienhaus · @if($customer->active)<span class="text-green-600">Aktiv</span>@else<span class="text-gray-500">Inaktiv</span>@endif</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ url('/admin/customers/' . $customer->id . '/edit') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-gray-300 text-gray-700 bg-white hover:bg-gray-50">Bearbeiten</a>
                <a href="{{ route('admin.customers.index') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-gray-300 text-gray-700 bg-white hover:bg-gray-50">Zurück</a>
            </div>
        </div>

        @if (session('status'))
            <div class="mt-4 rounded-md bg-green-50 p-4 border border-green-200"><p class="text-sm text-green-800">{{ session('status') }}</p></div>
        @endif

        @if($customer->notes)
            <div class="mt-4 p-4 bg-gray-50 rounded-lg border border-gray-200 text-sm text-gray-700">{{ $customer->notes }}</div>
        @endif

        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4 flex items-start justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Medienhaus</p>
                    @can('admin.customers.billing_sensitive')
                        <p class="mt-2 text-sm text-gray-700">Lexware- und Rechnungsdaten werden auf den untergeordneten Abteilungen/Redaktionen gepflegt. Versandkennungen bleiben auf Medienhaus-Ebene.</p>
                    @else
                        <p class="mt-2 text-sm text-gray-700">Stammdaten und Versand pro Redaktion; PV-, Kunden- und Autorenkennzeichen sieht nur die Buchhaltung.</p>
                    @endcan
                </div>
            </div>
        </div>

        <div x-data="{ tab: 'products' }" class="mt-6">
            <nav class="flex gap-1 border-b border-gray-200">
                <button type="button" @click="tab = 'products'" :class="tab === 'products' ? 'border-b-2 border-[#092E48] text-[#092E48]' : 'text-gray-600 hover:text-gray-900'" class="px-4 py-3 text-sm font-medium">Abteilungen / Redaktionen</button>
                <button type="button" @click="tab = 'contacts'" :class="tab === 'contacts' ? 'border-b-2 border-[#092E48] text-[#092E48]' : 'text-gray-600 hover:text-gray-900'" class="px-4 py-3 text-sm font-medium">Kontakte</button>
                <button type="button" @click="tab = 'destinations'" :class="tab === 'destinations' ? 'border-b-2 border-[#092E48] text-[#092E48]' : 'text-gray-600 hover:text-gray-900'" class="px-4 py-3 text-sm font-medium">Versandziele (Org)</button>
            </nav>

            <div x-show="tab === 'products'" class="bg-white rounded-b-lg border border-t-0 border-gray-200 shadow-sm p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">Abteilungen / Redaktionen</h2>
                    @if($customer->id)
                    <a href="{{ url('/admin/customers/' . (int) $customer->id . '/products/create') }}" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]">+ Redaktion anlegen</a>
                    @endif
                </div>
                @if($customer->products->isEmpty())
                    <p class="text-gray-500">Noch keine Redaktionen. @if($customer->id)<a href="{{ url('/admin/customers/' . (int) $customer->id . '/products/create') }}" class="text-[#092E48] hover:underline">Erste anlegen</a>@endif</p>
                @else
                    <ul class="divide-y divide-gray-200">
                        @foreach($customer->products as $p)
                        <li class="py-3 flex items-center justify-between gap-6">
                            <div class="min-w-0">
                                <span class="font-medium text-gray-900">{{ $p->name }}</span>
                                <span class="text-sm text-gray-500 ml-2">{{ $p->delivery_destinations_count ?? 0 }} Versandziele</span>
                                @can('admin.customers.billing_sensitive')
                                <div class="mt-1 text-sm text-gray-600 space-y-0.5">
                                    <p>Buyer Reference: {{ $p->resolvedBuyerReference() ?: '–' }}</p>
                                    <p>Lexware Kontakt-ID: {{ $p->lexware_contact_id ?: '–' }}</p>
                                    <p>Rechnungsadresse: {{ $p->billing_address_single_line ?: '–' }}</p>
                                    @if($p->billing_email_primary || $p->billing_email_secondary)
                                        <p>E-Mail: {{ $p->billing_email_primary ?: '–' }}@if($p->billing_email_secondary), {{ $p->billing_email_secondary }}@endif</p>
                                    @endif
                                </div>
                                @endcan
                            </div>
                            <div class="flex gap-2">
                                <a href="{{ route('admin.destinations.index', $p) }}" class="text-sm text-[#092E48] hover:underline">Versandziele</a>
                                <a href="{{ url('/admin/customers/' . $customer->id . '/products/' . $p->id . '/edit') }}" class="text-sm text-gray-600 hover:underline">Bearbeiten</a>
                                @can('admin.customers.delete')
                                    <form action="{{ url('/admin/customers/' . $customer->id . '/products/' . $p->id) }}" method="POST" class="inline" onsubmit="return window.adminConfirmDelete(this)" data-delete-prompt="Redaktion löschen? Geben Sie zur Bestätigung „ja“ ein.">@csrf @method('DELETE')<button type="submit" class="text-sm text-red-600 hover:underline">Löschen</button></form>
                                @endcan
                            </div>
                        </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div x-show="tab === 'contacts'" x-cloak class="bg-white rounded-b-lg border border-t-0 border-gray-200 shadow-sm p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">Kontakte</h2>
                    @if($customer->id)
                    <a href="{{ url('/admin/customers/' . (int) $customer->id . '/contacts/create') }}" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]">+ Kontakt anlegen</a>
                    @endif
                </div>
                @if($customer->contacts->isEmpty())
                    <p class="text-gray-500">Noch keine Kontakte. @if($customer->id)<a href="{{ url('/admin/customers/' . (int) $customer->id . '/contacts/create') }}" class="text-[#092E48] hover:underline">Ersten anlegen</a>@endif</p>
                @else
                    <ul class="divide-y divide-gray-200">
                        @foreach($customer->contacts as $c)
                        <li class="py-3 flex items-center justify-between">
                            <div>
                                <span class="font-medium text-gray-900">{{ $c->name }}</span>
                                <span class="text-sm text-gray-600">{{ $c->email }}</span>
                                @if($c->product)<span class="text-xs text-gray-500 ml-2">({{ $c->product->name }})</span>@endif
                                @if($c->use_for_invoice)<span class="text-xs text-emerald-700 ml-2">Rechnung</span>@endif
                            </div>
                            @if($customer->id)<a href="{{ url('/admin/customers/' . (int) $customer->id . '/contacts/' . $c->id . '/edit') }}" class="text-sm text-[#092E48] hover:underline">Bearbeiten</a>@endif
                        </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div x-show="tab === 'destinations'" x-cloak class="bg-white rounded-b-lg border border-t-0 border-gray-200 shadow-sm p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">Versandziele</h2>
                    <a href="{{ url('/admin/customers/' . $customer->id . '/products') }}" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]">Redaktionen → Versandziele</a>
                </div>
                @if($customer->deliveryDestinations->isEmpty())
                    <p class="text-gray-500">Noch keine Versandziele (weder auf Medienhaus- noch auf Redaktionen-Ebene). <a href="{{ url('/admin/customers/' . $customer->id . '/products') }}" class="text-[#092E48] hover:underline">Redaktionen öffnen, dort „Versandziele“ wählen</a></p>
                @else
                    <p class="text-sm text-gray-500 mb-3">Alle Versandziele dieser Organisation (Medienhaus und Redaktionen).</p>
                    <ul class="divide-y divide-gray-200">
                        @foreach($customer->deliveryDestinations as $d)
                        <li class="py-3 flex items-center justify-between">
                            <div>
                                <span class="font-medium text-gray-900">{{ $d->label }}</span>
                                <span class="text-sm text-gray-500 ml-2">{{ $d->type }}</span>
                                @if($d->product)<span class="text-sm text-gray-500 ml-2">· {{ $d->product->name }}</span>@endif
                                @if(!$d->active)<span class="text-sm text-gray-400 ml-1">(inaktiv)</span>@endif
                            </div>
                            <div class="flex gap-2">
                                <a href="{{ url('/admin/destinations/' . $d->id . '/edit') }}" class="text-sm text-[#092E48] hover:underline">Bearbeiten</a>
                                @can('admin.customers.delete')
                                    <form action="{{ url('/admin/destinations/' . $d->id) }}" method="POST" class="inline" onsubmit="return window.adminConfirmDelete(this)" data-delete-prompt="Versandziel löschen? Geben Sie zur Bestätigung „ja“ ein.">@csrf @method('DELETE')<button type="submit" class="text-sm text-red-600 hover:underline">Löschen</button></form>
                                @endcan
                            </div>
                        </li>
                        @endforeach
                    </ul>
                    <p class="mt-3"><a href="{{ url('/admin/customers/' . $customer->id) }}#destinations" class="text-sm text-[#092E48] hover:underline">Alle Versandziele verwalten</a></p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
