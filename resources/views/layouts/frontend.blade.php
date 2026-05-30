<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @php
            $robotsFromSection = trim((string) $__env->yieldContent('robots', ''));
            $robotsMetaFinal = $robotsFromSection !== '' ? $robotsFromSection : ($robotsMeta ?? 'index, follow');
        @endphp
        <meta name="robots" content="{{ e($robotsMetaFinal) }}">

        <title>@yield('title', 'Erftkreis News Media – Bildmaterial und Videomaterial für Redaktionen aus Köln, Bonn und dem Rhein-Erft-Kreis')</title>
        @php
            $pageTitle = trim($__env->yieldContent('title') ?: 'Erftkreis News Media – Bildmaterial und Videomaterial für Redaktionen aus Köln, Bonn und dem Rhein-Erft-Kreis');
            $metaDescription = trim($__env->yieldContent('meta_description') ?: 'B2B-Medienportal für Redaktionen: Pressefotos und Videomaterial zu Einsatzlagen und aktuellen Ereignissen in Köln, Bonn und dem Rhein-Erft-Kreis. Ereignisbezogenes Material mit Ortsbezug für die redaktionelle Nutzung.');
            $canonicalCandidate = trim((string) $__env->yieldContent('canonical', ''));

            // Globaler Canonical-Fix:
            // - immer HTTPS
            // - einheitlich ohne doppelte / trailing slashes
            // - Query-Parameter nur, wenn explizit erlaubt (SEO: Pagination)
            $canonicalNormalize = function (string $url, array $allowedQueryKeys = []): string {
                $url = trim($url);
                if ($url === '') {
                    return '';
                }

                $baseUrl = (string) config('app.url');
                $baseParts = parse_url($baseUrl);
                $defaultHost = $baseParts['host'] ?? '';
                $defaultPort = isset($baseParts['port']) ? ':' . $baseParts['port'] : '';

                // Wenn die View nur einen Pfad liefert, auf Basis-URL aufbauen.
                if (!preg_match('#^https?://#i', $url)) {
                    $url = rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
                }

                $parts = parse_url($url);
                $host = $parts['host'] ?? $defaultHost;
                $port = isset($parts['port']) ? ':' . $parts['port'] : $defaultPort;
                $path = $parts['path'] ?? '/';

                $canonical = 'https://' . $host . $port . $path;

                $queryOut = [];
                if (!empty($allowedQueryKeys)) {
                    $queryRaw = (string) ($parts['query'] ?? '');
                    parse_str($queryRaw, $queryIn);
                    foreach ($allowedQueryKeys as $k) {
                        if (array_key_exists($k, $queryIn)) {
                            $queryOut[$k] = $queryIn[$k];
                        }
                    }
                }
                if (!empty($queryOut)) {
                    $canonical .= '?' . http_build_query($queryOut);
                }

                // Kein trailing Slash außer Root
                $canonical = rtrim($canonical, '/');
                if ($canonical === '') {
                    return 'https://' . $host . $port . '/';
                }

                return $canonical;
            };

            // Für Pagination darf ?page=N im Canonical bleiben (aber nicht page=1).
            $requestHasQuery = count(request()->query()) > 0;
            $isPurePagination = $requestHasQuery && count(request()->query()) === 1 && request()->has('page');
            $pageParam = (int) request()->query('page', 1);
            $allowedQueryKeys = ($isPurePagination && $pageParam > 1) ? ['page'] : [];

            if ($canonicalCandidate !== '') {
                $canonicalUrl = $canonicalNormalize($canonicalCandidate, $allowedQueryKeys);
            } else {
                $path = trim((string) request()->path(), '/');
                $canonicalUrl = $canonicalNormalize(
                    request()->fullUrl(),
                    $allowedQueryKeys
                );
            }

            // Facetten-/Suche-URLs (beliebige Query-Parameter außer reiner Pagination) nicht indexieren.
            if ($requestHasQuery && ! $isPurePagination) {
                $robotsMetaFinal = 'noindex, follow';
            }
            $ogImage = trim((string) $__env->yieldContent('og_image')) ?: asset('images/erftkreis-news-logo.png');
            $ogType = trim($__env->yieldContent('og_type') ?: 'website');
            if ($ogImage && !\Illuminate\Support\Str::startsWith($ogImage, ['http://', 'https://'])) {
                $ogImage = config('app.url') . (\Illuminate\Support\Str::startsWith($ogImage, '/') ? '' : '/') . ltrim($ogImage, '/');
            }
        @endphp
        <meta name="description" content="{{ e($metaDescription) }}">

        <link rel="canonical" href="{{ e($canonicalUrl) }}">

        <meta property="og:type" content="{{ e($ogType) }}">
        <meta property="og:site_name" content="{{ e(config('app.name', 'Erftkreis News')) }}">
        <meta property="og:title" content="{{ e($pageTitle) }}">
        <meta property="og:description" content="{{ e($metaDescription) }}">
        <meta property="og:url" content="{{ e($canonicalUrl) }}">
        <meta property="og:image" content="{{ e($ogImage) }}">
        <meta property="og:locale" content="{{ str_replace('_', '-', app()->getLocale()) }}">

        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:site" content="@erftkreisnews">
        <meta name="twitter:title" content="{{ e($pageTitle) }}">
        <meta name="twitter:description" content="{{ e($metaDescription) }}">
        <meta name="twitter:image" content="{{ e($ogImage) }}">

        @stack('head_jsonld')

        {{-- Favicon --}}
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('styles')
    </head>
    <body class="font-sans antialiased bg-gray-100 overflow-x-hidden">
        <div class="min-h-[100dvh] min-h-screen flex flex-col w-full max-w-full min-w-0">
            <header
                x-data="{
                    open:false,
                    toggle() {
                        this.open = !this.open;
                        document.documentElement.classList.toggle('overflow-hidden', this.open);
                    },
                    close() {
                        this.open = false;
                        document.documentElement.classList.remove('overflow-hidden');
                    }
                }"
                @keydown.escape.window="close()"
                class="sticky top-0 z-50"
            >
                <div class="relative z-[60] bg-ekn-900 text-white shadow-sm pt-[env(safe-area-inset-top,0px)]">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 min-w-0">
                        <div class="h-16 flex items-center justify-between gap-3 min-w-0">

                        <!-- Logo -->
                        <a href="{{ url('/') }}" aria-label="Startseite" class="flex min-w-0 shrink-0 items-center gap-2 sm:gap-3" title="Erftkreis News – Startseite">
                            <img
                                src="{{ asset('images/erftkreis-news-logo.png') }}"
                                class="h-9 w-auto max-w-[min(100%,12rem)] sm:max-w-none object-contain object-left"
                                alt="Erftkreis News – Aktuelle Nachrichten Köln Bonn Rhein-Erft-Kreis"
                                title="Erftkreis News"
                            >
                            <span class="sr-only">Startseite</span>
                        </a>

                        <!-- Desktop Navigation -->
                        <nav class="hidden lg:flex items-center gap-6 text-sm font-medium text-white/95 shrink-0">
                            <a href="{{ route('home') }}" class="hover:text-white hover:underline underline-offset-4">News</a>

                            @auth
                                <a href="{{ url('/admin') }}" class="hover:text-white hover:underline underline-offset-4">Admin</a>
                            @endauth

                            @guest
                                <a href="{{ route('login') }}" class="hover:text-white hover:underline underline-offset-4">Login</a>
                            @endguest
                        </nav>

                        <!-- Mobile Hamburger -->
                        <button
                            type="button"
                            id="frontend-nav-toggle"
                            class="lg:hidden inline-flex items-center justify-center min-h-[44px] min-w-[44px] rounded-xl p-2 ring-1 ring-white/20 hover:ring-white/35 transition shrink-0"
                            @click="toggle()"
                            :aria-expanded="open.toString()"
                            aria-controls="frontend-mobile-nav"
                            :aria-label="open ? 'Menü schließen' : 'Menü öffnen'"
                        >
                            <svg x-show="!open" class="h-6 w-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                            <svg x-show="open" x-cloak class="h-6 w-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                        </div>
                    </div>
                </div>

                <!-- Overlay: nur unterhalb der Header-Leiste, damit Logo/Hamburger bedienbar bleiben -->
                <div
                    x-show="open"
                    x-cloak
                    x-transition.opacity
                    class="fixed inset-x-0 top-16 bottom-0 z-40 bg-black/50 lg:hidden"
                    @click="close()"
                    aria-hidden="true"
                ></div>

                <!-- Mobile Panel (Drawer) -->
                <div
                    id="frontend-mobile-nav"
                    x-show="open"
                    x-cloak
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 -translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 -translate-y-2"
                    class="lg:hidden fixed left-0 right-0 top-16 z-50 max-h-[calc(100dvh-4rem)] overflow-y-auto overscroll-contain bg-white text-ekn-900 shadow-xl ring-1 ring-slate-200 pb-[env(safe-area-inset-bottom,0px)]"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="frontend-nav-toggle"
                >
                    <nav class="max-w-7xl mx-auto px-4 sm:px-6 py-3 sm:py-4 flex flex-col gap-1 text-sm font-semibold min-w-0" aria-label="Hauptnavigation mobil">
                        <a href="{{ route('home') }}" @click="close()"
                           class="rounded-xl px-3 py-3 min-h-[48px] flex items-center hover:bg-ekn-50 active:bg-ekn-100">News</a>

                        @auth
                            <a href="{{ url('/admin') }}" @click="close()"
                               class="rounded-xl px-3 py-3 min-h-[48px] flex items-center hover:bg-ekn-50 active:bg-ekn-100">Admin</a>
                        @endauth

                        @guest
                            <a href="{{ route('login') }}" @click="close()"
                               class="rounded-xl px-3 py-3 min-h-[48px] flex items-center hover:bg-ekn-50 active:bg-ekn-100">Login</a>
                            <a href="{{ route('neukunden') }}" @click="close()"
                               class="rounded-xl px-3 py-3 min-h-[48px] flex items-center hover:bg-ekn-50 active:bg-ekn-100">Neukunden</a>
                        @endguest
                    </nav>
                </div>
            </header>

            <main class="flex-1 min-w-0 max-w-full bg-gray-100 overflow-x-hidden pb-[env(safe-area-inset-bottom)]">
                <div class="w-full max-w-7xl mx-auto py-4 px-4 sm:py-6 sm:px-6 lg:px-8 min-w-0">
                    @yield('content')
                </div>
            </main>

            <footer class="bg-white border-t border-gray-200 py-4 mt-auto min-w-0 max-w-full">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-center sm:justify-between gap-4 text-sm text-gray-600 min-w-0">
                    <span class="text-center sm:text-left shrink-0">© {{ date('Y') }} Erftkreis News</span>
                    <nav class="flex flex-col sm:flex-row sm:flex-wrap items-stretch sm:items-center gap-x-4 gap-y-2 justify-center sm:justify-end w-full sm:w-auto min-w-0" aria-label="Navigation und Rechtliches">
                        <a href="https://www.erftkreis-news.de" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-[44px] items-center justify-center sm:justify-start hover:text-[#092E48] focus:outline-none focus:underline text-center sm:text-left break-words">Aktuelle Nachrichten</a>
                        <a href="{{ route('home') }}" class="inline-flex min-h-[44px] items-center justify-center sm:justify-start hover:text-[#092E48] focus:outline-none focus:underline">Medienangebote</a>
                        <a href="{{ route('neukunden') }}" class="inline-flex min-h-[44px] items-center justify-center sm:justify-start hover:text-[#092E48] focus:outline-none focus:underline">Kunde werden</a>
                        <a href="{{ route('agb') }}" class="inline-flex min-h-[44px] items-center justify-center sm:justify-start hover:text-[#092E48] focus:outline-none focus:underline">AGB</a>
                        <a href="https://www.erftkreis-news.de/impressum" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-[44px] items-center justify-center sm:justify-start hover:text-[#092E48] focus:outline-none focus:underline break-words">Impressum</a>
                        <a href="https://www.erftkreis-news.de/datenschutzerklaerung" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-[44px] items-center justify-center sm:justify-start hover:text-[#092E48] focus:outline-none focus:underline break-words">Datenschutz</a>
                        <button type="button" onclick="localStorage.removeItem('erftkreis_media_cookie_info'); window.location.reload();" class="inline-flex min-h-[44px] items-center justify-center sm:justify-start hover:text-[#092E48] focus:outline-none focus:underline text-inherit bg-transparent border-0 cursor-pointer text-sm text-center sm:text-left px-1 py-1 -mx-1 rounded-md hover:bg-gray-50">Cookie-Hinweis erneut anzeigen</button>
                    </nav>
                </div>
            </footer>

            <x-cookie-notice />
        </div>
        @stack('scripts')
    </body>
</html>
