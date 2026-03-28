@extends('layouts.admin')

@section('content')
    <div class="space-y-4 max-w-3xl">
        <div>
            <a href="{{ route('admin.backoffice.users.index') }}" class="text-sm text-gray-600 hover:text-[#092E48] mb-2 inline-block">← Benutzer &amp; Rollen</a>
            <h1 class="text-2xl font-semibold text-gray-900">Benutzer bearbeiten</h1>
            <p class="mt-1 text-sm text-gray-600">
                Stammdaten und Rollen für diesen Benutzer anpassen.
            </p>
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 p-4 border border-green-200">
                <p class="text-sm text-green-800">{{ session('status') }}</p>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.backoffice.users.update', $user) }}" class="bg-white rounded-lg border border-gray-200 shadow-sm p-6 space-y-6">
            @csrf
            @method('PUT')

            <div class="space-y-2">
                <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    value="{{ old('name', $user->name) }}"
                    required
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                >
                @error('name')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="space-y-2">
                <label for="email" class="block text-sm font-medium text-gray-700">E‑Mail</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="{{ old('email', $user->email) }}"
                    required
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                >
                @error('email')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="space-y-2">
                <h2 class="text-sm font-medium text-gray-700">Rollen</h2>
                <p class="text-xs text-gray-500">
                    Wähle aus, welche Rollen dieser Benutzer haben soll. Rollen steuern z.&nbsp;B. den Zugriff auf den Admin‑Bereich.
                </p>
                <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach($roles as $role)
                        <label class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 bg-gray-50 hover:bg-gray-100 cursor-pointer">
                            <input
                                type="checkbox"
                                name="roles[]"
                                value="{{ $role->name }}"
                                @checked(in_array($role->name, old('roles', $user->roles->pluck('name')->all())))
                                class="h-4 w-4 rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]"
                            >
                            <span class="text-sm text-gray-800">{{ $role->name }}</span>
                        </label>
                    @endforeach
                </div>
                @error('roles')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-end gap-3 pt-4">
                <a href="{{ route('admin.backoffice.users.index') }}" class="px-4 py-2 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                    Abbrechen
                </a>
                <button type="submit" class="px-4 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]">
                    Speichern
                </button>
            </div>
        </form>
    </div>
@endsection

