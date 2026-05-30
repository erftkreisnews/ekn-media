@extends('layouts.admin')
@section('content')
<div class="py-6 max-w-7xl mx-auto px-4">
    <h1 class="text-2xl font-semibold text-gray-900">Versandziele</h1>
    <p class="text-sm text-gray-600">{{ $indexLabel }}</p>
    <a href="{{ route($createRoute, $createParams) }}" class="mt-4 inline-flex px-4 py-2 text-sm font-medium rounded-md text-white bg-[#092E48]">+ Versandziel anlegen</a>
    @if (session('status'))<div class="mt-4 rounded-md bg-green-50 p-4 border border-green-200">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="mt-4 rounded-md bg-red-50 p-4 border border-red-200">{{ session('error') }}</div>@endif
    <div class="mt-6 bg-white rounded-lg border overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Typ</th><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bezeichnung</th><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Externe ID</th><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th><th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aktionen</th></tr></thead>
            <tbody>
                @forelse($destinations as $d)
                <tr>
                    <td class="px-4 py-3 text-sm">{{ $d->type }}</td>
                    <td class="px-4 py-3 font-medium">{{ $d->label }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                        @php $ids = $d->getExternalIdentifiers(); @endphp
                        @if(count($ids) > 0)
                            {{ $ids[0]['label'] }}: {{ \Illuminate\Support\Str::limit($ids[0]['value'], 20) }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm">@if($d->active)<span class="text-green-600">Aktiv</span>@else<span class="text-gray-500">Inaktiv</span>@endif</td>
                    <td class="px-4 py-3 text-right text-sm">
                        <a href="{{ route($editRoute, array_merge($itemRouteParams, [$d])) }}" class="text-[#092E48] hover:underline">Bearbeiten</a>
                        @if($d->type !== 'email')
                            <form action="{{ route('admin.destinations.test', $d) }}" method="POST" class="inline">@csrf<button type="submit" class="text-amber-600 hover:underline ml-1">Testen</button></form>
                        @endif
                        @can('admin.customers.delete')
                            <form action="{{ route($destroyRoute, array_merge($itemRouteParams, [$d])) }}" method="POST" class="inline" onsubmit="return window.adminConfirmDelete(this)" data-delete-prompt="Versandziel löschen? Geben Sie zur Bestätigung „ja“ ein.">@csrf @method('DELETE')<button type="submit" class="text-red-600 hover:underline ml-1">Löschen</button></form>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">Noch keine Versandziele.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="mt-4"><a href="{{ route($backRoute, $backParams) }}" class="text-[#092E48]">Zurück</a></p>
</div>
@endsection
