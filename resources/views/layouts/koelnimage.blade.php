<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $robotsFromSection = trim((string) $__env->yieldContent('robots', ''));
        $robotsBase = $robotsFromSection !== '' ? $robotsFromSection : ($robotsMeta ?? 'index, follow');
        $robotsMetaFinal = $robotsBase === 'index, follow'
            ? 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1'
            : $robotsBase;
        $pageTitle = trim($__env->yieldContent('title') ?: 'Kölnimage');
        $metaDescription = trim($__env->yieldContent('meta_description')
            ?: 'Pressefotos Köln & Rheinland: Events, Karneval, Sport. Für Redaktionen — Redaktionsdesk 02236 4809 488.');
        $req = request();
        $canonicalCandidate = trim((string) $__env->yieldContent('canonical', ''));
        if ($canonicalCandidate !== '') {
            $canonicalUrl = $canonicalCandidate;
        } else {
            $trimPath = trim((string) $req->path(), '/');
            $canonicalUrl = rtrim($req->getSchemeAndHttpHost(), '/').($trimPath === '' ? '/' : '/'.$trimPath);
            if ($req->routeIs('koelnimage.galleries.index') && count($req->query()) === 1 && $req->has('page') && (int) $req->query('page', 1) >= 2) {
                $canonicalUrl .= '?page='.(int) $req->query('page');
            }
        }
        $ogType = trim($__env->yieldContent('og_type') ?: 'website');
        $ogImageRaw = trim($__env->yieldContent('og_image') ?: '');
        if ($ogImageRaw === '') {
            $ogImageRaw = rtrim($req->getSchemeAndHttpHost(), '/').'/images/koelnimage/koeln-skyline.png';
        } elseif (! \Illuminate\Support\Str::startsWith($ogImageRaw, ['http://', 'https://'])) {
            $ogImageRaw = rtrim($req->getSchemeAndHttpHost(), '/').'/'.ltrim($ogImageRaw, '/');
        }
        $ogImage = $ogImageRaw;
        $twitterSite = trim((string) config('brands.koelnimage_twitter_site', ''));
    @endphp
    <meta name="robots" content="{{ e($robotsMetaFinal) }}">
    <meta name="description" content="{{ e($metaDescription) }}">
    <title>{{ e($pageTitle) }}</title>
    <link rel="canonical" href="{{ e($canonicalUrl) }}">
    <meta property="og:type" content="{{ e($ogType) }}">
    <meta property="og:site_name" content="Kölnimage">
    <meta property="og:title" content="{{ e($pageTitle) }}">
    <meta property="og:description" content="{{ e($metaDescription) }}">
    <meta property="og:url" content="{{ e($canonicalUrl) }}">
    <meta property="og:image" content="{{ e($ogImage) }}">
    <meta property="og:locale" content="{{ str_replace('_', '-', app()->getLocale()) }}">
    <meta name="twitter:card" content="summary_large_image">
    @if($twitterSite !== '')
        <meta name="twitter:site" content="{{ e($twitterSite) }}">
    @endif
    <meta name="twitter:title" content="{{ e($pageTitle) }}">
    <meta name="twitter:description" content="{{ e($metaDescription) }}">
    <meta name="twitter:image" content="{{ e($ogImage) }}">
    @stack('head')
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white text-gray-900 min-h-screen">
    <header class="sticky top-0 z-50 border-b border-gray-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex h-14 sm:h-16 w-full max-w-7xl items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
            <a href="{{ route('koelnimage.home') }}" title="Kölnimage Startseite" class="flex min-w-0 items-center gap-2 sm:gap-3">
                <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-red-700 text-white sm:h-9 sm:w-9" aria-hidden="true">
                    <img
                        src="{{ asset('images/koelnimage-dom-silhouette.svg') }}"
                        alt=""
                        width="28"
                        height="14"
                        class="h-3.5 w-7 object-contain brightness-0 invert"
                        decoding="async"
                    >
                </span>
                <span class="truncate text-base font-semibold tracking-tight text-gray-900 sm:text-lg">Kölnimage</span>
            </a>
            <nav class="hidden md:flex flex-1 items-center justify-center gap-5 text-sm font-medium text-gray-700">
                <a href="{{ route('koelnimage.home') }}" title="Startseite" class="hover:text-red-700">Startseite</a>
                <a href="{{ route('koelnimage.events.index') }}" title="Events" class="hover:text-red-700">Events</a>
                <a href="{{ route('koelnimage.motorsport.index') }}" title="Motorsport" class="hover:text-red-700">Motorsport</a>
                <a href="{{ route('koelnimage.galleries.index') }}" title="Galerien" class="hover:text-red-700">Galerien</a>
                <a href="{{ route('koelnimage.customer.downloads') }}" title="Kundendownloads" class="hover:text-red-700">Kundendownloads</a>
            </nav>
            <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                <a href="{{ route('login') }}" title="Anmelden" class="inline-flex rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-800 shadow-sm hover:bg-gray-50 sm:px-3 sm:text-sm">Anmelden</a>
            </div>
        </div>
        <nav class="flex gap-4 overflow-x-auto border-t border-gray-100 px-4 py-2 text-xs font-medium text-gray-700 md:hidden [-webkit-overflow-scrolling:touch]" aria-label="Mobil">
            <a href="{{ route('koelnimage.home') }}" title="Start" class="shrink-0 hover:text-red-700">Start</a>
            <a href="{{ route('koelnimage.events.index') }}" title="Events" class="shrink-0 hover:text-red-700">Events</a>
            <a href="{{ route('koelnimage.motorsport.index') }}" title="Motorsport" class="shrink-0 hover:text-red-700">Motorsport</a>
            <a href="{{ route('koelnimage.galleries.index') }}" title="Galerien" class="shrink-0 hover:text-red-700">Galerien</a>
            <a href="{{ route('koelnimage.customer.downloads') }}" title="Downloads" class="shrink-0 hover:text-red-700">Downloads</a>
        </nav>
    </header>

    <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
        @yield('content')
    </main>

    <footer class="border-t border-zinc-200 bg-white">
        <div class="mx-auto grid w-full max-w-7xl gap-8 px-4 py-10 sm:px-6 lg:grid-cols-3 lg:px-8">
            <section>
                <h3 class="text-lg font-semibold text-zinc-900">Material anfragen</h3>
                <p class="mt-2 text-2xl font-bold text-red-700">02236 4809 488</p>
                <p class="mt-2 text-sm text-zinc-600">Direkter Kontakt für Redaktionen</p>
            </section>
            <section>
                <h3 class="text-lg font-semibold text-zinc-900">Redaktionsdesk</h3>
                <p class="mt-2 text-sm text-zinc-600">24h-Hotline: 02236 4809 488</p>
                <p class="mt-4"><a href="{{ route('koelnimage.neukunde') }}" title="Neukunde werden" class="text-sm font-medium text-red-700 hover:text-red-800">Neukunde werden</a></p>
            </section>
            <section>
                <h3 class="text-lg font-semibold text-zinc-900">Weitere Seiten</h3>
                <ul class="mt-3 space-y-2 text-sm">
                    <li><a href="{{ route('koelnimage.home') }}" title="Startseite" class="text-zinc-700 hover:text-red-700">Startseite</a></li>
                    <li><a href="{{ route('koelnimage.galleries.index') }}" title="Galerien" class="text-zinc-700 hover:text-red-700">Galerien</a></li>
                </ul>
            </section>
        </div>
        <div class="border-t border-zinc-200">
            <div class="mx-auto flex w-full max-w-7xl flex-col gap-3 px-4 py-4 text-xs text-zinc-600 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
                <p>© {{ date('Y') }} Kölnimage</p>
            </div>
        </div>
    </footer>
</body>
</html>
