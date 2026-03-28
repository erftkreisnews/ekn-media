@extends('layouts.admin')

@section('content')
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 p-4 border border-green-200">
                <p class="text-sm text-green-800">{{ session('status') }}</p>
            </div>
        @endif
        @if (session('error'))
            <div class="rounded-md bg-red-50 p-4 border border-red-200">
                <p class="text-sm text-red-800">{{ session('error') }}</p>
            </div>
        @endif

        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Admin-Dashboard</h1>
            <p class="mt-2 text-sm text-gray-600">
                Willkommen im Admin-Bereich von Erftkreis News Media. Hier haben Sie die System-Übersicht.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
                <div class="p-4">
                    <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Nachrichten</h2>
                    <p class="mt-2 text-gray-900 font-semibold">
                        {{ $newsCount }} Nachrichten im System
                    </p>
                    <p class="mt-1 text-sm text-gray-600">
                        Alle anlegen, bearbeiten und verwalten.
                    </p>
                    <div class="mt-4">
                        <a
                            href="{{ route('admin.news.index') }}"
                            class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]"
                        >
                            Zur Nachrichten-Übersicht
                        </a>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
                <div class="p-4">
                    <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Kunden</h2>
                    <p class="mt-2 text-gray-900 font-semibold">
                        Kundenverwaltung
                    </p>
                    <p class="mt-1 text-sm text-gray-600">
                        Organisationen, Produkte, Kontakte, Versandziele (E-Mail/FTP).
                    </p>
                    <div class="mt-4">
                        <a href="{{ route('admin.customers.index') }}" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]">
                            Zur Kundenverwaltung
                        </a>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
                <div class="p-4">
                    <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Einstellungen</h2>
                    <p class="mt-2 text-gray-900 font-semibold">
                        System- und Benutzer-Einstellungen
                    </p>
                    <p class="mt-1 text-sm text-gray-600">
                        Backup, weitere Optionen später.
                    </p>
                    <div class="mt-4">
                        <a
                            href="{{ route('admin.settings.index') }}"
                            class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]"
                        >
                            Zu den Einstellungen
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
