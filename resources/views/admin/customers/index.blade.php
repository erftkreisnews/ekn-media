@extends('layouts.admin')

@section('content')
    <x-admin.page
        x-data="{
            search: '',
            matches(rowText) {
                if (!this.search) return true;
                return rowText.toLowerCase().includes(this.search.toLowerCase());
            }
        }"
        title="Medienhäuser"
        subtitle="Verwalte Medienhäuser, Redaktionen und Kontakte."
    >
        <x-slot:actions>
            <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                <div class="flex-1 sm:w-64">
                    <input
                        type="search"
                        x-model="search"
                        placeholder="Suche nach Name …"
                        class="block w-full rounded-2xl border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                    >
                </div>
                <a href="{{ route('admin.customers.create') }}" class="inline-flex justify-center items-center px-4 py-2.5 text-sm font-medium rounded-2xl text-white bg-[#092E48] hover:bg-[#0b3858] focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#092E48]">
                    + Neues Medienhaus
                </a>
            </div>
        </x-slot:actions>

        @if (session('status'))
            <div class="rounded-md bg-green-50 p-4 border border-green-200">
                <p class="text-sm text-green-800">{{ session('status') }}</p>
            </div>
        @endif

        <x-admin.card>
            <x-admin.table-cards :items="$organizations">
                <x-slot:table>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Redaktionen</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kontakte</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse($organizations as $org)
                                    <tr
                                        x-show="matches(`{{ Str::lower($org->name) }}`)"
                                        class="align-middle"
                                    >
                                        <td class="px-4 py-3">
                                            <a href="{{ url('/admin/customers/' . $org->id) }}" class="text-[#092E48] font-medium hover:underline">
                                                {{ $org->name }}
                                            </a>
                                        </td>
                                        <td class="px-4 py-3 text-sm">{{ $org->products_count }}</td>
                                        <td class="px-4 py-3 text-sm">{{ $org->contacts_count }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            @if($org->active)
                                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Aktiv</span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">Inaktiv</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm">
                                            <a href="{{ url('/admin/customers/' . $org->id) }}" class="text-[#092E48] hover:underline mr-2">Anzeigen</a>
                                            <a href="{{ url('/admin/customers/' . $org->id . '/edit') }}" class="text-gray-600 hover:underline mr-2">Bearbeiten</a>
                                            <form action="{{ url('/admin/customers/' . $org->id) }}" method="POST" class="inline" onsubmit="return confirm('Organisation löschen?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:underline">Löschen</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">Noch keine Medienhäuser.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-slot:table>

                <x-slot:cards>
                    @forelse($organizations as $org)
                        <article
                            class="rounded-2xl border border-slate-200/80 bg-white shadow-sm px-4 py-3"
                            x-show="matches(`{{ Str::lower($org->name) }}`)"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h2 class="text-sm font-semibold text-slate-900 truncate">
                                        {{ $org->name }}
                                    </h2>
                                    <p class="mt-1 text-xs text-slate-500 flex flex-wrap gap-x-2 gap-y-0.5">
                                        <span>Produkte: {{ $org->products_count }}</span>
                                        <span>·</span>
                                        <span>Kontakte: {{ $org->contacts_count }}</span>
                                    </p>
                                </div>
                                <div class="flex-shrink-0">
                                    @if($org->active)
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Aktiv</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">Inaktiv</span>
                                    @endif
                                </div>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <a href="{{ url('/admin/customers/' . $org->id) }}" class="inline-flex flex-1 sm:flex-none justify-center items-center px-3 py-2 text-xs font-medium rounded-xl border border-[#092E48] text-[#092E48] hover:bg-[#092E48]/5">
                                    Anzeigen
                                </a>
                                <a href="{{ url('/admin/customers/' . $org->id . '/edit') }}" class="inline-flex flex-1 sm:flex-none justify-center items-center px-3 py-2 text-xs font-medium rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-50">
                                    Bearbeiten
                                </a>
                                <form action="{{ url('/admin/customers/' . $org->id) }}" method="POST" class="inline-flex flex-1 sm:flex-none justify-center mt-1 sm:mt-0" onsubmit="return confirm('Organisation löschen?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex justify-center items-center px-3 py-2 w-full text-xs font-medium rounded-xl border border-red-600 text-red-700 hover:bg-red-50">
                                        Löschen
                                    </button>
                                </form>
                            </div>
                        </article>
                    @empty
                        <div class="px-2 py-6 text-center text-sm text-gray-500">
                            Noch keine Medienhäuser.
                        </div>
                    @endforelse
                </x-slot:cards>
            </x-admin.table-cards>

            @if($organizations->hasPages())
                <div class="mt-4">
                    {{ $organizations->links() }}
                </div>
            @endif
        </x-admin.card>
    </x-admin.page>
@endsection

