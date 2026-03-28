@extends('layouts.admin')

@section('content')
    <div class="max-w-md mx-auto">
        <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
            <div class="p-6">
                <h1 class="text-xl font-semibold text-gray-900">Aktion ausführen</h1>
                <p class="mt-2 text-sm text-gray-600">
                    Sie haben das Master-Passwort korrekt eingegeben. Klicken Sie den Button unten, um die geplante Aktion auszuführen.
                </p>
                <p class="mt-2 text-xs text-gray-500 break-all">
                    Aktion: {{ $intendedMethod }} – {{ $intendedUrl }}
                </p>

                <form method="POST" action="{{ $intendedUrl }}" class="mt-6 block">
                    @csrf
                    @if ($intendedMethod !== 'GET' && $intendedMethod !== 'POST')
                        @method($intendedMethod)
                    @endif
                    <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3 text-base font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#092E48] shadow-sm">
                        Aktion jetzt ausführen
                    </button>
                </form>

                <p class="mt-6">
                    <a href="{{ route('admin.settings.index') }}" class="text-sm text-gray-600 hover:text-gray-900 underline">Zurück zu Einstellungen</a>
                </p>
            </div>
        </div>
    </div>
@endsection
