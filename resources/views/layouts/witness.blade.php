<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', config('app.name'))</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-slate-100 text-slate-900 min-w-0 overflow-x-hidden">
    <header class="bg-[#092E48] text-white shadow">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 py-4 flex items-center gap-3 min-w-0">
            <img src="{{ asset('images/erftkreis-news-logo.png') }}" alt="" aria-hidden="true" class="h-8 w-auto object-contain shrink-0" width="120" height="32">
            <span class="text-sm font-medium text-white/90 truncate">Material einreichen</span>
        </div>
    </header>
    <main class="max-w-3xl mx-auto px-4 sm:px-6 py-8 min-w-0">
        @yield('content')
    </main>
    <footer class="max-w-3xl mx-auto px-4 sm:px-6 pb-10 text-center text-xs text-slate-500">
        <p>Erftkreis News Media · nur mit gültigem Einladungslink</p>
    </footer>
</body>
</html>
