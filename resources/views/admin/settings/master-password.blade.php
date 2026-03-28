@extends('layouts.admin')

@section('content')
<div class="max-w-md mx-auto">
    <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-amber-200">
        <div class="p-6">
            <h1 class="text-xl font-semibold text-gray-900">Master-Passwort erforderlich</h1>
            <p class="mt-2 text-sm text-gray-600">
                Diese Aktion ist geschützt. Bitte geben Sie das Master-Passwort ein, um fortzufahren.
            </p>

            @if (session('warning'))
            <div class="mt-4 rounded-md bg-amber-50 p-3 border border-amber-200">
                <p class="text-sm text-amber-800">{{ session('warning') }}</p>
            </div>
            @endif
            @if (session('error'))
            <div class="mt-4 rounded-md bg-red-50 p-3 border border-red-200">
                <p class="text-sm text-red-800">{{ session('error') }}</p>
            </div>
            @endif

            <form method="POST" action="{{ route('admin.settings.master-password.verify') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label for="master_password" class="block text-sm font-medium text-gray-700">Master-Passwort</label>
                    <input type="password" name="master_password" id="master_password" autocomplete="off" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] sm:text-sm" />
                    @error('master_password')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex items-center justify-between">
                    <a href="{{ route('admin.settings.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Abbrechen</a>
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]">Bestätigen</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
