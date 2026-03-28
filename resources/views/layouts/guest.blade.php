<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', config('app.name', 'EKN - Media'))</title>

        {{-- Favicon --}}
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-100">
        <div class="min-h-screen flex flex-col">
            <header class="bg-[#092E48] text-white shadow pt-[env(safe-area-inset-top)]">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center h-16 gap-4">
                        <a href="{{ url('/') }}" class="flex items-center shrink-0 py-2" aria-label="Startseite">
                            <img
                                src="{{ asset('images/erftkreis-news-logo.png') }}"
                                alt="ERFTKREIS NEWS – Immer Aktuell"
                                class="h-9 w-auto object-contain object-left"
                            >
                        </a>
                        <nav class="flex items-center gap-2 shrink-0">
                            @if (Route::has('login'))
                            <a href="{{ route('login') }}" class="px-4 py-2 rounded-md text-sm font-medium text-white/90 hover:bg-white/10 hover:text-white">Anmelden</a>
                            @endif
                            @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-white border border-white/30 hover:bg-white/10 focus:outline-none transition whitespace-nowrap">Registrieren</a>
                            @endif
                        </nav>
                    </div>
                </div>
            </header>

            <main class="flex-1 flex items-center justify-center py-8 px-4 sm:px-6 lg:px-8 pb-[env(safe-area-inset-bottom)]">
                <div class="w-full sm:max-w-md">
                    <div class="bg-white rounded-lg shadow-sm p-6 sm:p-8">
                        {{ $slot }}
                    </div>
                </div>
            </main>

            <footer class="bg-white border-t border-gray-200 py-4 mt-auto">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-center sm:justify-between gap-3 text-sm text-gray-600">
                    <span>© {{ date('Y') }} Erftkreis News</span>
                    <nav class="flex items-center gap-4 flex-wrap justify-center sm:justify-end" aria-label="Rechtliches">
                        <a href="https://www.erftkreis-news.de/impressum" target="_blank" rel="noopener noreferrer" class="hover:text-[#092E48] focus:outline-none focus:underline">Impressum</a>
                        <a href="https://www.erftkreis-news.de/datenschutzerklaerung" target="_blank" rel="noopener noreferrer" class="hover:text-[#092E48] focus:outline-none focus:underline">Datenschutz</a>
                        <button type="button" onclick="localStorage.removeItem('erftkreis_media_cookie_info'); window.location.reload();" class="hover:text-[#092E48] focus:outline-none focus:underline text-inherit bg-transparent border-0 p-0 cursor-pointer text-sm">Cookie-Hinweis erneut anzeigen</button>
                    </nav>
                </div>
            </footer>

            <x-cookie-notice />
        </div>
    </body>
</html>
