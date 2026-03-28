<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'EKN - Media') }} – @yield('title', 'Erftkreis News Media')</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none!important}</style>
    @stack('styles')
</head>
<body class="font-sans antialiased bg-gray-100">
    <div class="min-h-screen flex flex-col">
        @auth
        <header class="bg-[#092E48] text-white shadow">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-16">
                    <a href="{{ url('/') }}" class="flex items-center gap-2 shrink-0">
                        <span class="w-[120px] h-9 bg-white/20 rounded inline-block"></span>
                        <span class="text-white font-medium">{{ config('app.name', 'EKN - Media') }}</span>
                    </a>
                    <nav class="hidden lg:flex items-center gap-1">
                        <a href="{{ route('dashboard') }}" class="px-4 py-2 rounded-md text-sm font-medium {{ request()->routeIs('dashboard') ? 'bg-white/20' : 'text-white/90 hover:bg-white/10' }}">Übersicht</a>
                        @can('access_admin')
                        <a href="{{ route('admin.dashboard') }}" class="px-4 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.*') ? 'bg-white/20' : 'text-white/90 hover:bg-white/10' }}">Nachrichten (Admin)</a>
                        @endcan
                        @can('view_customer_area')
                        <a href="{{ route('kunden.dashboard') }}" class="px-4 py-2 rounded-md text-sm font-medium {{ request()->routeIs('kunden.*') ? 'bg-white/20' : 'text-white/90 hover:bg-white/10' }}">Kundenbereich</a>
                        @endcan
                    </nav>
                    <div class="flex items-center gap-2">
                        <x-dropdown align="right" width="48">
                            <x-slot name="trigger">
                                <button class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-md text-white hover:bg-white/10">
                                    <span>{{ auth()->user()?->name ?? auth()->user()?->email }}</span>
                                    <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                                </button>
                            </x-slot>
                            <x-slot name="content">
                                <form action="{{ route('logout') }}" method="post">@csrf<button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Abmelden</button></form>
                            </x-slot>
                        </x-dropdown>
                    </div>
                </div>
            </div>
        </header>
        @endauth

        <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <x-alert-banner />
            @yield('content')
        </main>

        <footer class="border-t border-gray-200 bg-white py-4 mt-auto">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-center sm:justify-between gap-3 text-sm text-gray-600">
                <span>© {{ date('Y') }} Erftkreis News</span>
                <nav class="flex items-center gap-4" aria-label="Rechtliches">
                    <a href="https://www.erftkreis-news.de/impressum" target="_blank" rel="noopener noreferrer" class="hover:text-[#092E48]">Impressum</a>
                    <a href="https://www.erftkreis-news.de/datenschutzerklaerung" target="_blank" rel="noopener noreferrer" class="hover:text-[#092E48]">Datenschutz</a>
                </nav>
            </div>
        </footer>
    </div>
    @stack('scripts')
</body>
</html>
