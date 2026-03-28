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

        <title>@yield('title', 'Erftkreis News Media – Bild- und Videomaterial für Redaktionen')</title>
        @php
            $pageTitle = trim($__env->yieldContent('title') ?: 'Erftkreis News Media – Bild- und Videomaterial für Redaktionen');
            $metaDescription = trim($__env->yieldContent('meta_description') ?: 'Erftkreis News Media – Pressefotos und Video-Footage für Redaktionen. Bild- und Videomaterial aus Köln, Bonn und dem Rhein-Erft-Kreis.');
            $canonicalCandidate = trim((string) $__env->yieldContent('canonical', ''));

            // Globaler Canonical-Fix:
            // - immer HTTPS
            // - ohne Query-Parameter
            // - einheitlich ohne doppelte / trailing slashes
            $canonicalNormalize = function (string $url): string {
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

                // Query/Fragment ignorieren (parse_url hat diese nicht als Teil von $path).
                $canonical = 'https://' . $host . $port . $path;

                // Kein trailing Slash außer Root
                $canonical = rtrim($canonical, '/');
                if ($canonical === '') {
                    return 'https://' . $host . $port . '/';
                }

                return $canonical;
            };

            if ($canonicalCandidate !== '') {
                $canonicalUrl = $canonicalNormalize($canonicalCandidate);
            } else {
                $path = trim((string) request()->path(), '/');
                $canonicalUrl = $canonicalNormalize(
                    rtrim((string) config('app.url'), '/') . ($path === '' ? '/' : '/' . $path)
                );
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
    <body class="font-sans antialiased bg-gray-100">
        <div class="min-h-screen flex flex-col">
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
                class="sticky top-0 z-50 bg-ekn-900 text-white shadow-sm"
            >
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="h-16 flex items-center justify-between">

                        <!-- Logo -->
                        <a href="{{ url('/') }}" class="flex items-center gap-3" title="Erftkreis News – Startseite">
                            <img
                                src="{{ asset('images/erftkreis-news-logo.png') }}"
                                class="h-9 w-auto"
                                alt="Erftkreis News – Aktuelle Nachrichten Köln Bonn Rhein-Erft-Kreis"
                                title="Erftkreis News"
                            >
                        </a>

                        <!-- Desktop Navigation -->
                        <nav class="hidden lg:flex items-center gap-6 text-sm font-medium text-white/95">
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
                            class="lg:hidden inline-flex items-center justify-center rounded-xl p-2 ring-1 ring-white/20 hover:ring-white/35 transition"
                            @click="toggle()"
                            :aria-expanded="open.toString()"
                            aria-label="Menü öffnen"
                        >
                            <svg x-show="!open" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                            <svg x-show="open" x-cloak class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Overlay -->
                <div
                    x-show="open"
                    x-cloak
                    x-transition.opacity
                    class="fixed inset-0 bg-black/50 lg:hidden"
                    @click="close()"
                    aria-hidden="true"
                ></div>

                <!-- Mobile Panel (Drawer) -->
                <div
                    x-show="open"
                    x-cloak
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 -translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 -translate-y-2"
                    class="lg:hidden fixed left-0 right-0 top-16 bg-white text-ekn-900 shadow-xl ring-1 ring-slate-200"
                    @click.outside="close()"
                >
                    <nav class="max-w-7xl mx-auto px-4 sm:px-6 py-4 flex flex-col gap-2 text-sm font-semibold">
                        <a href="{{ route('home') }}" @click="close()"
                           class="rounded-xl px-3 py-2 hover:bg-ekn-50">News</a>

                        @auth
                            <a href="{{ url('/admin') }}" @click="close()"
                               class="rounded-xl px-3 py-2 hover:bg-ekn-50">Admin</a>
                        @endauth

                        @guest
                            <a href="{{ route('login') }}" @click="close()"
                               class="rounded-xl px-3 py-2 hover:bg-ekn-50">Login</a>
                        @endguest
                    </nav>
                </div>
            </header>

            <main class="flex-1 min-w-0 bg-gray-100 overflow-x-hidden pb-[env(safe-area-inset-bottom)]">
                <div class="max-w-7xl mx-auto py-4 px-4 sm:py-6 sm:px-6 lg:px-8 min-w-0">
                    @yield('content')
                </div>
            </main>

            <footer class="bg-white border-t border-gray-200 py-4 mt-auto">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-center sm:justify-between gap-3 text-sm text-gray-600">
                    <span>© {{ date('Y') }} Erftkreis News</span>
                    <nav class="flex items-center gap-4 flex-wrap justify-center sm:justify-end" aria-label="Navigation und Rechtliches">
                        <a href="https://www.erftkreis-news.de" target="_blank" rel="noopener noreferrer" class="hover:text-[#092E48] focus:outline-none focus:underline">Aktuelle Nachrichten</a>
                        <a href="{{ route('home') }}" class="hover:text-[#092E48] focus:outline-none focus:underline">Medienangebote</a>
                        <a href="https://www.erftkreis-news.de/impressum" target="_blank" rel="noopener noreferrer" class="hover:text-[#092E48] focus:outline-none focus:underline">Impressum</a>
                        <a href="https://www.erftkreis-news.de/datenschutzerklaerung" target="_blank" rel="noopener noreferrer" class="hover:text-[#092E48] focus:outline-none focus:underline">Datenschutz</a>
                        <button type="button" onclick="localStorage.removeItem('erftkreis_media_cookie_info'); window.location.reload();" class="hover:text-[#092E48] focus:outline-none focus:underline text-inherit bg-transparent border-0 p-0 cursor-pointer text-sm">Cookie-Hinweis erneut anzeigen</button>
                    </nav>
                </div>
            </footer>

            <x-cookie-notice />
        </div>
        @stack('scripts')
    </body>
</html>
