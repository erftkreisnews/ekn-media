@extends('layouts.frontend')

@section('title', config('app.name', 'Erftkreis News') . ' – Startseite')

@section('content')
    <div class="bg-white rounded-lg shadow-sm px-4 py-5 sm:p-6 sm:p-8">
        <h1 class="text-lg sm:text-xl font-semibold text-[#092E48] mb-3 leading-tight">Willkommen bei Erftkreis News Media</h1>
        <p class="text-gray-600 mb-6 leading-relaxed text-[15px] sm:text-base">
            Ihre Anlaufstelle für Nachrichten und Medien aus der Region Köln-Bonn. Melden Sie sich an, um auf Inhalte und den Kundenbereich zuzugreifen.
        </p>
        @guest
            <div class="flex flex-col sm:flex-row flex-wrap gap-3">
                @if (Route::has('login'))
                <a href="{{ route('login') }}" class="inline-flex items-center justify-center min-h-[44px] px-4 py-3 sm:py-2 bg-[#092E48] text-white text-sm font-medium rounded-md hover:opacity-90 focus:outline-none transition active:opacity-95 w-full sm:w-auto">Anmelden</a>
        @endif
                        @if (Route::has('register'))
                <a href="{{ route('register') }}" class="inline-flex items-center justify-center min-h-[44px] px-4 py-3 sm:py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-50 focus:outline-none transition active:bg-gray-100 w-full sm:w-auto">Konto erstellen</a>
            @endif
                </div>
        @else
            <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center min-h-[44px] px-4 py-3 sm:py-2 bg-[#092E48] text-white text-sm font-medium rounded-md hover:opacity-90 focus:outline-none transition active:opacity-95 w-full sm:w-auto">Zur Übersicht</a>
        @endguest
                </div>
@endsection
