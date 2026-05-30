@extends('layouts.admin')

@section('content')
    <div class="space-y-4">
        <div>
            <a href="{{ route('admin.backoffice.index') }}" class="text-sm text-gray-600 hover:text-[#092E48] mb-2 inline-block">← Backoffice</a>
            <h1 class="text-2xl font-semibold text-gray-900">Benutzer &amp; Rollen</h1>
            <p class="mt-1 text-sm text-gray-600">
                Übersicht aller Benutzer mit ihren Rollen. Rollen werden über Spatie Permission verwaltet.
            </p>
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 p-4 border border-green-200">
                <p class="text-sm text-green-800">{{ session('status') }}</p>
            </div>
        @endif

        <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between gap-3">
                <h2 class="text-sm font-semibold text-gray-900">Benutzer</h2>
                <a
                    href="{{ route('admin.backoffice.users.create') }}"
                    class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858] shrink-0"
                >
                    Neuer Benutzer
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">E‑Mail</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rollen</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($users as $user)
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-900 whitespace-nowrap">
                                    {{ $user->name }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">
                                    {{ $user->email }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    @if($user->roles->isEmpty())
                                        <span class="text-xs text-gray-400">Keine Rolle</span>
                                    @else
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($user->roles as $role)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800">
                                                    {{ config('admin_roles.role_labels.'.$role->name, $role->name) }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5">
                                        <a href="{{ route('admin.backoffice.users.edit', $user) }}" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md border border-[#092E48] text-[#092E48] hover:bg-[#092E48]/5">
                                            Bearbeiten
                                        </a>
                                        <form method="POST" action="{{ route('admin.backoffice.users.welcome-mail', $user) }}">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md border-2 border-emerald-700 text-emerald-800 hover:bg-emerald-100">
                                                Willkommens-Mail
                                            </button>
                                        </form>
                                        @if(auth()->id() !== $user->id)
                                            <form method="POST" action="{{ route('admin.backoffice.users.destroy', $user) }}" onsubmit="return window.adminConfirmDelete(this)" data-delete-prompt="Benutzer „{{ $user->name }}“ wirklich löschen? Dies kann nicht rückgängig gemacht werden. Geben Sie zur Bestätigung „ja“ ein.">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md border border-red-600 text-red-700 hover:bg-red-50">
                                                    Löschen
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-sm text-center text-gray-500">
                                    Es sind noch keine Benutzer vorhanden.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

