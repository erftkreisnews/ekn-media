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
    <body class="font-sans antialiased bg-gray-100 overflow-x-hidden">
        <div class="min-h-screen flex flex-col min-w-0 max-w-full w-full">
            <header class="bg-[#092E48] text-white shadow pt-[env(safe-area-inset-top,0px)]">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 min-w-0">
                    <div class="flex justify-between items-center min-h-[4rem] gap-2 sm:gap-4">
                        <a href="{{ url('/') }}" class="flex items-center shrink-0 min-w-0 py-2 max-w-[min(100%,14rem)] sm:max-w-none" aria-label="Startseite">
                            <img
                                src="{{ asset('images/erftkreis-news-logo.png') }}"
                                alt="ERFTKREIS NEWS – Immer Aktuell"
                                class="h-9 w-auto max-h-9 max-w-full object-contain object-left"
                            >
                            <span class="sr-only">Startseite</span>
                        </a>
                        <nav class="flex flex-wrap items-center justify-end gap-1 sm:gap-2 shrink-0">
                            @if (Route::has('login'))
                            <a href="{{ route('login') }}" class="inline-flex items-center justify-center min-h-[44px] px-3 sm:px-4 rounded-md text-sm font-medium text-white/90 hover:bg-white/10 hover:text-white">Anmelden</a>
                            @endif
                            @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="inline-flex items-center justify-center min-h-[44px] px-3 sm:px-4 text-sm font-medium rounded-md text-white border border-white/30 hover:bg-white/10 focus:outline-none transition">Registrieren</a>
                            @endif
                        </nav>
                    </div>
                </div>
            </header>

            <main class="flex-1 flex items-center justify-center py-6 sm:py-8 px-4 sm:px-6 lg:px-8 pb-[max(1.5rem,env(safe-area-inset-bottom,0px))] min-w-0 w-full">
                <div class="w-full max-w-full sm:max-w-md min-w-0">
                    <div class="bg-white rounded-lg shadow-sm p-5 sm:p-8 min-w-0 max-w-full">
                        {{ $slot }}
                    </div>
                </div>
            </main>

            <footer class="bg-white border-t border-gray-200 py-4 mt-auto min-w-0">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-center sm:justify-between gap-4 text-sm text-gray-600">
                    <span class="text-center sm:text-left">© {{ date('Y') }} Erftkreis News</span>
                    <nav class="flex flex-col sm:flex-row sm:flex-wrap items-center gap-x-4 gap-y-2 justify-center sm:justify-end text-center sm:text-right" aria-label="Rechtliches">
                        <a href="https://www.erftkreis-news.de/impressum" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-[44px] items-center hover:text-[#092E48] focus:outline-none focus:underline">Impressum</a>
                        <a href="https://www.erftkreis-news.de/datenschutzerklaerung" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-[44px] items-center hover:text-[#092E48] focus:outline-none focus:underline">Datenschutz</a>
                        <button type="button" onclick="localStorage.removeItem('erftkreis_media_cookie_info'); window.location.reload();" class="inline-flex min-h-[44px] items-center hover:text-[#092E48] focus:outline-none focus:underline text-inherit bg-transparent border-0 cursor-pointer text-sm px-1">Cookie-Hinweis erneut anzeigen</button>
                    </nav>
                </div>
            </footer>

            <x-cookie-notice />
        </div>
    </body>
</html>
