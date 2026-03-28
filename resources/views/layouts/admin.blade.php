<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Admin') – {{ config('app.name', 'EKN - Media') }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="font-sans antialiased bg-gray-100">
    <div class="min-h-screen flex flex-col">
        <header class="sticky top-0 z-50 bg-[#092E48] text-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="h-16 flex items-center justify-between">
                    <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3">
                        <img src="{{ asset('images/erftkreis-news-logo.png') }}" class="h-9 w-auto" alt="EKN Admin">
                    </a>
                    <nav class="flex items-center gap-4 text-sm font-medium">
                        <a href="{{ route('admin.dashboard') }}" class="hover:text-white/90">Dashboard</a>
                        <a href="{{ route('admin.news.index') }}" class="hover:text-white/90">Nachrichten</a>
                        <a href="{{ route('admin.deliveries.index') }}" class="hover:text-white/90">Versand</a>
                        <a href="{{ route('admin.ingest.index') }}" class="hover:text-white/90">Ingest</a>
                        <a href="{{ route('admin.backoffice.index') }}" class="hover:text-white/90">Backoffice</a>
                        <a href="{{ route('admin.settings.index') }}" class="hover:text-white/90">Einstellungen</a>
                        <a href="{{ url('/') }}" class="hover:text-white/90">Zur Website</a>
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="hover:text-white/90">Abmelden</button>
                        </form>
                    </nav>
                </div>
            </div>
        </header>

        <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
            @yield('content')
        </main>
    </div>
    @stack('scripts')
</body>
</html>
