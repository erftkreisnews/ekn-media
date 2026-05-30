@extends('layouts.admin')

@section('content')
    <div class="space-y-6">
        <div>
            <a href="{{ route('admin.settings.index') }}" class="text-sm text-gray-600 hover:text-[#092E48] mb-2 inline-block">← Einstellungen</a>
            <h1 class="text-2xl font-semibold text-gray-900">Presseportal</h1>
            <p class="mt-1 text-sm text-gray-600">
                API-Anbindung an <a href="http://api.presseportal.de/v2/docs/" class="text-[#092E48] hover:underline" target="_blank" rel="noopener noreferrer">Presseportal API V2</a>
                (news aktuell). Der API-Key wird in der <code class="text-xs bg-gray-100 px-1 rounded">.env</code> als <code class="text-xs bg-gray-100 px-1 rounded">PRESSEPORTAL_API_KEY</code> gesetzt.
            </p>
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 p-4 border border-green-200">
                <p class="text-sm text-green-800">{{ session('status') }}</p>
            </div>
        @endif
        @if (session('presseportal_test_ok'))
            <div class="rounded-md bg-green-50 p-4 border border-green-200">
                <p class="text-sm text-green-800">{{ session('presseportal_test_ok') }}</p>
            </div>
        @endif
        @if (session('presseportal_test_error'))
            <div class="rounded-md bg-red-50 p-4 border border-red-200">
                <p class="text-sm text-red-800">{{ session('presseportal_test_error') }}</p>
            </div>
        @endif

        <div class="bg-white rounded-2xl ring-1 ring-slate-200 overflow-hidden shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-sm font-semibold tracking-wide text-gray-900 uppercase">API-Key</h2>
            </div>
            <div class="px-6 py-4 space-y-3 text-sm">
                <p class="text-gray-700">
                    @if($keySet)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 text-emerald-800 px-2.5 py-0.5 text-xs font-medium">Key gesetzt</span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-red-100 text-red-800 px-2.5 py-0.5 text-xs font-medium">Key fehlt</span>
                    @endif
                </p>
                <form method="post" action="{{ route('admin.settings.presseportal.test') }}" class="inline">
                    @csrf
                    <button type="submit" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-800 hover:bg-gray-50">
                        Verbindung testen
                    </button>
                </form>
            </div>
        </div>

        <div class="bg-white rounded-2xl ring-1 ring-slate-200 overflow-hidden shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-sm font-semibold tracking-wide text-gray-900 uppercase">Dienststellen (Whitelist)</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Tragen Sie Presseportal-Dienststellen-IDs ein (Zahl in der URL nach <code class="text-xs bg-gray-100 px-1 rounded">/pm/</code>, z. B. <strong>7304</strong> bei
                    <span class="whitespace-nowrap">…/pm/7304/6245919</span>). Solange <strong>keine</strong> Dienststelle eingetragen ist, sind alle URLs erlaubt. Sobald mindestens eine aktive Dienststelle existiert, dürfen nur noch Meldungen dieser Stellen importiert werden.
                </p>
            </div>
            <div class="px-6 py-4 overflow-x-auto">
                @if($offices->isEmpty())
                    <p class="text-sm text-gray-500 mb-4">Noch keine Einträge – alle Presseportal-URLs sind zulässig (sofern API-Key gesetzt).</p>
                @else
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Dienststellen-ID</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Bearbeiten</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-600">Aktion</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($offices as $o)
                                <tr>
                                    <td class="px-3 py-2 font-mono align-top">{{ $o->office_id }}</td>
                                    <td class="px-3 py-2">
                                        <form method="post" action="{{ route('admin.settings.presseportal.offices.update', $o) }}" class="space-y-2">
                                            @csrf
                                            @method('PATCH')
                                            <div class="flex flex-wrap items-center gap-2">
                                                <input type="text" name="name" value="{{ $o->name }}" class="rounded-md border-gray-300 text-sm min-w-[12rem] flex-1">
                                                <input type="number" name="sort_order" value="{{ $o->sort_order }}" class="w-20 rounded-md border-gray-300 text-sm" title="Sortierung">
                                                <label class="inline-flex items-center gap-1 text-xs text-gray-600">
                                                    <input type="hidden" name="is_active" value="0">
                                                    <input type="checkbox" name="is_active" value="1" @checked($o->is_active)> aktiv
                                                </label>
                                                <button type="submit" class="inline-flex px-2 py-1 rounded border border-gray-300 text-xs hover:bg-gray-50">Speichern</button>
                                            </div>
                                            <input type="text" name="notes" value="{{ $o->notes }}" class="block w-full rounded-md border-gray-300 text-xs" placeholder="Notiz (optional)">
                                        </form>
                                    </td>
                                    <td class="px-3 py-2 text-right align-top">
                                        <form method="post" action="{{ route('admin.settings.presseportal.offices.destroy', $o) }}" class="inline" onsubmit="return window.adminConfirmDelete(this)" data-delete-prompt="Dienststelle aus der Liste entfernen? Geben Sie zur Bestätigung „ja“ ein.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:underline text-sm">Entfernen</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                <div class="mt-6 pt-4 border-t border-gray-100">
                    <h3 class="text-sm font-medium text-gray-900 mb-3">Neue Dienststelle</h3>
                    <form method="post" action="{{ route('admin.settings.presseportal.offices.store') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Dienststellen-ID (Presseportal)</label>
                            <input type="number" name="office_id" required min="1" class="block w-full rounded-md border-gray-300 text-sm" placeholder="z. B. 7304">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Anzeigename</label>
                            <input type="text" name="name" required maxlength="255" class="block w-full rounded-md border-gray-300 text-sm" placeholder="z. B. Polizei Bonn">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Sortierung</label>
                            <input type="number" name="sort_order" value="0" min="0" class="block w-full rounded-md border-gray-300 text-sm">
                        </div>
                        <div class="lg:col-span-4">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Notiz (optional)</label>
                            <input type="text" name="notes" maxlength="2000" class="block w-full rounded-md border-gray-300 text-sm" placeholder="interne Notiz">
                        </div>
                        <div class="lg:col-span-4">
                            <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]">Hinzufügen</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
