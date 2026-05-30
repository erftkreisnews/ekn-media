<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Admin') – {{ config('app.name', 'EKN - Media') }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-ekn-900 min-h-screen flex flex-col" style="background-color: #092E48;">
    <main class="flex-1 flex items-center justify-center px-4 py-8 sm:py-12">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <img
                    src="{{ asset('images/erftkreis-news-logo.png') }}"
                    alt="Erftkreis News"
                    class="h-10 w-auto mx-auto mb-6 brightness-0 invert opacity-95"
                >
                <h1 class="text-2xl font-semibold text-white tracking-tight">
                    @yield('heading', 'EKN Media')
                </h1>
                <p class="mt-2 text-sm text-white/70">
                    @yield('subheading', 'Melde dich im Admin-Bereich an')
                </p>
            </div>

            <div class="bg-white rounded-xl shadow-xl p-6 sm:p-8">
                {{ $slot }}
            </div>

            <p class="mt-6 text-center text-xs text-white/50">
                <a href="{{ url('/') }}" class="hover:text-white/80 underline underline-offset-2 transition">
                    Zur öffentlichen Website
                </a>
            </p>
        </div>
    </main>
</body>
</html>
