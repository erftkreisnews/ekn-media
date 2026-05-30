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
<body @class([
    'font-sans antialiased bg-gray-100',
    'admin-body--fullscreen overflow-hidden' => View::hasSection('fullscreen_content'),
])>
    @php
        $adminUser = auth()->user();
        $showBackoffice = $adminUser && ($adminUser->can('admin.backoffice') || $adminUser->can('admin.users'));
        $isAdminRole = $adminUser?->hasRole('admin') ?? false;
        $brandOptions = collect();
        $selectedBrandContext = (string) session('admin.brand_filter', 'all');
        $websiteUrl = url('/');
        if (\Illuminate\Support\Facades\Schema::hasTable('brands')) {
            $brandOptions = \App\Models\Brand::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'primary_host']);
        }
        if ($selectedBrandContext !== 'all' && ctype_digit($selectedBrandContext)) {
            $selectedBrand = $brandOptions->firstWhere('id', (int) $selectedBrandContext);
            $selectedBrandHost = trim((string) ($selectedBrand?->primary_host ?? ''));
            if ($selectedBrandHost !== '') {
                $websiteUrl = 'https://'.$selectedBrandHost.'/';
            }
        }
        $eventPlanningActive = request()->routeIs([
            'admin.event-planning.*',
            'admin.settings.planned-events.*',
            'admin.settings.event-suggestions.*',
            'admin.settings.house-squad.*',
        ]);
    @endphp
    <div
        @class([
            'flex flex-col',
            'h-dvh max-h-dvh min-h-0 overflow-hidden' => View::hasSection('fullscreen_content'),
            'min-h-screen' => ! View::hasSection('fullscreen_content'),
        ])
        x-data="{
            mobileOpen: false,
            toggleMobile() {
                this.mobileOpen = ! this.mobileOpen;
                document.documentElement.classList.toggle('overflow-hidden', this.mobileOpen);
            },
            closeMobile() {
                this.mobileOpen = false;
                document.documentElement.classList.remove('overflow-hidden');
            },
        }"
        @keydown.escape.window="closeMobile()"
    >
        <header class="sticky top-0 z-50 bg-[#092E48] text-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="h-16 flex items-center justify-between gap-3">
                    <a href="{{ route('admin.dashboard') }}" aria-label="Admin-Startseite" class="flex items-center gap-3 shrink-0 min-w-0">
                        <img src="{{ asset('images/erftkreis-news-logo.png') }}" class="h-9 w-auto" alt="EKN Admin">
                        <span class="sr-only">Admin-Startseite</span>
                    </a>

                    {{-- Desktop / Laptop: eine Zeile ab lg (Nav ist unter lg ohnehin ausgeblendet) --}}
                    <nav class="hidden lg:flex lg:flex-nowrap items-center gap-3 xl:gap-4 text-sm font-medium justify-end min-w-0">
                        <a href="{{ route('admin.dashboard') }}" class="hover:text-white/90">Dashboard</a>
                        @can('admin.news')
                            <a href="{{ route('admin.news.index') }}" class="hover:text-white/90">Nachrichten</a>
                            <a href="{{ route('admin.witness.inbox') }}" class="hover:text-white/90 {{ request()->routeIs('admin.witness.*') ? 'font-semibold underline underline-offset-2' : '' }}">Zeugen-Eingang</a>
                        @endcan
                        @can('admin.media')
                            <a href="{{ route('admin.video.index') }}" class="hover:text-white/90 {{ request()->routeIs('admin.video.*') ? 'font-semibold underline underline-offset-2' : '' }}">Video</a>
                            <a href="{{ route('admin.images.index') }}" class="hover:text-white/90 {{ request()->routeIs('admin.images.*') ? 'font-semibold underline underline-offset-2' : '' }}">Bilder</a>
                        @endcan
                        @if($isAdminRole)
                            @can('admin.ingest')
                            <div class="relative" x-data="{ ingestOpen: false }" @keydown.escape.window="ingestOpen = false">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-md px-1 py-0.5 hover:bg-white/10 hover:text-white"
                                    :class="ingestOpen ? 'bg-white/10' : ''"
                                    @click="ingestOpen = ! ingestOpen"
                                    :aria-expanded="ingestOpen.toString()"
                                    aria-haspopup="true"
                                >
                                    Ingest
                                    <svg class="h-4 w-4 opacity-80" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
                                </button>
                                <div
                                    x-show="ingestOpen"
                                    x-transition
                                    @click.outside="ingestOpen = false"
                                    class="absolute left-0 mt-1 min-w-[11rem] rounded-lg border border-white/10 bg-[#0a3552] py-1 shadow-lg z-50"
                                    style="display: none;"
                                >
                                    <a
                                        href="{{ route('admin.ingest.index') }}"
                                        class="block px-3 py-2 text-sm hover:bg-white/10 {{ request()->routeIs('admin.ingest.index') && ! request()->routeIs('admin.ingest.entries') && ! request()->routeIs('admin.ingest.statistics') ? 'bg-white/10 font-semibold' : '' }}"
                                    >Sichtung</a>
                                    <a
                                        href="{{ route('admin.ingest.entries') }}"
                                        class="block px-3 py-2 text-sm hover:bg-white/10 {{ request()->routeIs('admin.ingest.entries') ? 'bg-white/10 font-semibold' : '' }}"
                                    >Alle Einträge</a>
                                    <a
                                        href="{{ route('admin.ingest.statistics') }}"
                                        class="block px-3 py-2 text-sm hover:bg-white/10 {{ request()->routeIs('admin.ingest.statistics') ? 'bg-white/10 font-semibold' : '' }}"
                                    >Statistik</a>
                                    <a
                                        href="{{ route('admin.ingest.render-jobs') }}"
                                        class="block px-3 py-2 text-sm hover:bg-white/10 {{ request()->routeIs('admin.ingest.render-jobs') ? 'bg-white/10 font-semibold' : '' }}"
                                    >Render-Jobs</a>
                                </div>
                            </div>
                            @endcan
                        @endif
                        @can('admin.deliveries')
                            <a href="{{ route('admin.deliveries.index') }}" class="hover:text-white/90">Versand</a>
                        @endcan
                        @can('admin.settings')
                            <div class="relative" x-data="{ eventPlanOpen: false }" @keydown.escape.window="eventPlanOpen = false">
                                <button
                                    type="button"
                                    @class([
                                        'inline-flex items-center gap-1 rounded-md px-1 py-0.5 hover:bg-white/10 hover:text-white',
                                        'bg-white/10 font-semibold underline underline-offset-2' => $eventPlanningActive,
                                    ])
                                    :class="eventPlanOpen ? 'ring-1 ring-white/20' : ''"
                                    @click="eventPlanOpen = ! eventPlanOpen"
                                    :aria-expanded="eventPlanOpen.toString()"
                                    aria-haspopup="true"
                                >
                                    Eventplanung
                                    <svg class="h-4 w-4 opacity-80" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
                                </button>
                                <div
                                    x-show="eventPlanOpen"
                                    x-transition
                                    @click.outside="eventPlanOpen = false"
                                    class="absolute left-0 mt-1 min-w-[14rem] rounded-lg border border-white/10 bg-[#0a3552] py-1 shadow-lg z-50"
                                    style="display: none;"
                                >
                                    <a
                                        href="{{ route('admin.event-planning.index') }}"
                                        class="block px-3 py-2 text-sm hover:bg-white/10 {{ request()->routeIs('admin.event-planning.index') ? 'bg-white/10 font-semibold' : '' }}"
                                    >Übersicht</a>
                                    <a
                                        href="{{ route('admin.settings.planned-events.index') }}"
                                        class="block px-3 py-2 text-sm hover:bg-white/10 {{ request()->routeIs('admin.settings.planned-events.index') ? 'bg-white/10 font-semibold' : '' }}"
                                    >Geplante Veranstaltungen</a>
                                    <a
                                        href="{{ route('admin.settings.event-suggestions.index') }}"
                                        class="block px-3 py-2 text-sm hover:bg-white/10 {{ request()->routeIs('admin.settings.event-suggestions.*') ? 'bg-white/10 font-semibold' : '' }}"
                                    >Event-Vorschläge</a>
                                    <a
                                        href="{{ route('admin.settings.planned-events.global') }}"
                                        class="block px-3 py-2 text-sm hover:bg-white/10 {{ request()->routeIs('admin.settings.planned-events.global') ? 'bg-white/10 font-semibold' : '' }}"
                                    >Zusatz-Vorgaben</a>
                                    <a
                                        href="{{ route('admin.settings.house-squad.edit') }}"
                                        class="block px-3 py-2 text-sm hover:bg-white/10 {{ request()->routeIs('admin.settings.house-squad.*') ? 'bg-white/10 font-semibold' : '' }}"
                                    >Haus-Kader</a>
                                </div>
                            </div>
                        @endcan
                        @if($brandOptions->isNotEmpty())
                            <form method="POST" action="{{ route('admin.brand-context.update') }}" class="inline-flex items-center gap-2 rounded-md bg-white/10 px-2 py-1">
                                @csrf
                                <label for="brand_context_top" class="text-xs uppercase tracking-wide text-white/80">Brand</label>
                                <select
                                    id="brand_context_top"
                                    name="brand_context"
                                    class="rounded border-white/20 bg-[#0a3552] px-2 py-1 text-xs text-white"
                                    onchange="this.form.submit()"
                                >
                                    <option value="all" {{ $selectedBrandContext === 'all' ? 'selected' : '' }}>Alle</option>
                                    @foreach($brandOptions as $brandOption)
                                        <option value="{{ $brandOption->id }}" {{ $selectedBrandContext === (string) $brandOption->id ? 'selected' : '' }}>
                                            {{ $brandOption->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="hover:text-white/90">Abmelden</button>
                        </form>
                    </nav>

                    {{-- Mobil / kleine Tablets: Hamburger --}}
                    <button
                        type="button"
                        class="lg:hidden inline-flex items-center justify-center rounded-lg p-2.5 min-h-[44px] min-w-[44px] ring-1 ring-white/25 hover:bg-white/10 active:bg-white/15 shrink-0"
                        @click="toggleMobile()"
                        :aria-expanded="mobileOpen.toString()"
                        aria-controls="admin-mobile-nav"
                        aria-label="Hauptmenü"
                    >
                        <svg x-show="!mobileOpen" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                        <svg x-show="mobileOpen" x-cloak class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
        </header>

        {{-- Mobile: Backdrop (unterhalb der Sticky-Bar, Inhalt scrollt nicht mit) --}}
        <div
            x-show="mobileOpen"
            x-cloak
            x-transition
            class="lg:hidden fixed inset-0 z-40 bg-black/50"
            @click="closeMobile()"
            aria-hidden="true"
        ></div>

        {{-- Mobile: Panel mit scrollbarem Inhalt --}}
        <div
            id="admin-mobile-nav"
            x-show="mobileOpen"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-1"
            class="lg:hidden fixed left-0 right-0 top-16 z-50 max-h-[min(100dvh-4rem,100vh-4rem)] overflow-y-auto overscroll-contain touch-pan-y border-b border-white/10 bg-[#0a3552] shadow-xl"
            role="navigation"
            aria-label="Admin-Hauptmenü"
        >
            <nav class="flex flex-col py-2 pb-[max(0.5rem,env(safe-area-inset-bottom))] text-sm font-medium">
                <a href="{{ route('admin.dashboard') }}" class="px-4 py-3 text-white hover:bg-white/10 active:bg-white/15" @click="closeMobile()">Dashboard</a>
                @can('admin.news')
                    <a href="{{ route('admin.news.index') }}" class="px-4 py-3 text-white hover:bg-white/10 active:bg-white/15" @click="closeMobile()">Nachrichten</a>
                    <a href="{{ route('admin.witness.inbox') }}" class="px-4 py-3 text-white hover:bg-white/10 active:bg-white/15 {{ request()->routeIs('admin.witness.*') ? 'bg-white/10 font-semibold' : '' }}" @click="closeMobile()">Zeugen-Eingang</a>
                @endcan
                @can('admin.media')
                    <a href="{{ route('admin.video.index') }}" class="px-4 py-3 text-white hover:bg-white/10 active:bg-white/15 {{ request()->routeIs('admin.video.*') ? 'bg-white/10 font-semibold' : '' }}" @click="closeMobile()">Video</a>
                    <a href="{{ route('admin.images.index') }}" class="px-4 py-3 text-white hover:bg-white/10 active:bg-white/15 {{ request()->routeIs('admin.images.*') ? 'bg-white/10 font-semibold' : '' }}" @click="closeMobile()">Bilder</a>
                @endcan
                @can('admin.deliveries')
                    <a href="{{ route('admin.deliveries.index') }}" class="px-4 py-3 text-white hover:bg-white/10 active:bg-white/15" @click="closeMobile()">Versand</a>
                @endcan
                @can('admin.settings')
                    <div class="border-t border-white/10 my-1"></div>
                    <p class="px-4 pt-2 pb-1 text-xs font-semibold uppercase tracking-wide text-white/60">Eventplanung</p>
                    <a href="{{ route('admin.event-planning.index') }}" class="px-4 py-3 pl-6 text-white hover:bg-white/10 active:bg-white/15 {{ request()->routeIs('admin.event-planning.index') ? 'bg-white/10 font-semibold' : '' }}" @click="closeMobile()">Übersicht</a>
                    <a href="{{ route('admin.settings.planned-events.index') }}" class="px-4 py-3 pl-6 text-white hover:bg-white/10 active:bg-white/15 {{ request()->routeIs('admin.settings.planned-events.index') ? 'bg-white/10 font-semibold' : '' }}" @click="closeMobile()">Geplante Veranstaltungen</a>
                    <a href="{{ route('admin.settings.event-suggestions.index') }}" class="px-4 py-3 pl-6 text-white hover:bg-white/10 active:bg-white/15 {{ request()->routeIs('admin.settings.event-suggestions.*') ? 'bg-white/10 font-semibold' : '' }}" @click="closeMobile()">Event-Vorschläge</a>
                    <a href="{{ route('admin.settings.planned-events.global') }}" class="px-4 py-3 pl-6 text-white hover:bg-white/10 active:bg-white/15 {{ request()->routeIs('admin.settings.planned-events.global') ? 'bg-white/10 font-semibold' : '' }}" @click="closeMobile()">Zusatz-Vorgaben</a>
                    <a href="{{ route('admin.settings.house-squad.edit') }}" class="px-4 py-3 pl-6 text-white hover:bg-white/10 active:bg-white/15 {{ request()->routeIs('admin.settings.house-squad.*') ? 'bg-white/10 font-semibold' : '' }}" @click="closeMobile()">Haus-Kader</a>
                @endcan
                @if($brandOptions->isNotEmpty())
                    <div class="px-4 py-2 border-t border-white/10">
                        <form method="POST" action="{{ route('admin.brand-context.update') }}" class="flex flex-col gap-2">
                            @csrf
                            <label for="brand_context_mobile" class="text-xs uppercase tracking-wide text-white/70">Brand-Filter</label>
                            <select id="brand_context_mobile" name="brand_context" class="rounded-md border border-white/20 bg-[#0a3552] px-3 py-2 text-white" onchange="this.form.submit()">
                                <option value="all" {{ $selectedBrandContext === 'all' ? 'selected' : '' }}>Alle Marken</option>
                                @foreach($brandOptions as $brandOption)
                                    <option value="{{ $brandOption->id }}" {{ $selectedBrandContext === (string) $brandOption->id ? 'selected' : '' }}>
                                        {{ $brandOption->name }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    </div>
                @endif
                @if($isAdminRole)
                    @can('admin.ingest')
                    <div class="border-t border-white/10 my-1"></div>
                    <p class="px-4 pt-2 pb-1 text-xs font-semibold uppercase tracking-wide text-white/60">Ingest</p>
                    <a href="{{ route('admin.ingest.index') }}" class="px-4 py-3 pl-6 text-white hover:bg-white/10 active:bg-white/15 {{ request()->routeIs('admin.ingest.index') && ! request()->routeIs('admin.ingest.entries') && ! request()->routeIs('admin.ingest.statistics') ? 'bg-white/10 font-semibold' : '' }}" @click="closeMobile()">Sichtung</a>
                    <a href="{{ route('admin.ingest.entries') }}" class="px-4 py-3 pl-6 text-white hover:bg-white/10 active:bg-white/15 {{ request()->routeIs('admin.ingest.entries') ? 'bg-white/10 font-semibold' : '' }}" @click="closeMobile()">Alle Einträge</a>
                    <a href="{{ route('admin.ingest.statistics') }}" class="px-4 py-3 pl-6 text-white hover:bg-white/10 active:bg-white/15 {{ request()->routeIs('admin.ingest.statistics') ? 'bg-white/10 font-semibold' : '' }}" @click="closeMobile()">Statistik</a>
                    <a href="{{ route('admin.ingest.render-jobs') }}" class="px-4 py-3 pl-6 text-white hover:bg-white/10 active:bg-white/15 {{ request()->routeIs('admin.ingest.render-jobs') ? 'bg-white/10 font-semibold' : '' }}" @click="closeMobile()">Render-Jobs</a>
                    @endcan
                    <div class="border-t border-white/10 my-1"></div>
                    @if($showBackoffice)
                        <a href="{{ route('admin.backoffice.index') }}" class="px-4 py-3 text-white hover:bg-white/10 active:bg-white/15" @click="closeMobile()">Backoffice</a>
                    @endif
                    @can('admin.settings')
                        <a href="{{ route('admin.settings.index') }}" class="px-4 py-3 text-white hover:bg-white/10 active:bg-white/15" @click="closeMobile()">Einstellungen</a>
                    @endcan
                @else
                    <div class="border-t border-white/10 my-1"></div>
                    @if($adminUser && $adminUser->can('admin.backoffice'))
                        <a href="{{ route('admin.backoffice.usage.create') }}" class="px-4 py-3 text-white hover:bg-white/10 active:bg-white/15" @click="closeMobile()">Nachverfolgung erfassen</a>
                    @endif
                    <a href="{{ route('profile.edit') }}" class="px-4 py-3 text-white hover:bg-white/10 active:bg-white/15" @click="closeMobile()">Einstellungen</a>
                @endif
                <a href="{{ $websiteUrl }}" class="px-4 py-3 text-white hover:bg-white/10 active:bg-white/15" @click="closeMobile()">Zur Website</a>
                <div class="border-t border-white/15 mt-2 pt-2">
                    <form method="POST" action="{{ route('logout') }}" class="px-4 pb-2">
                        @csrf
                        <button type="submit" class="w-full text-left rounded-lg px-3 py-3 text-white hover:bg-white/10 active:bg-white/15 font-medium">
                            Abmelden
                        </button>
                    </form>
                </div>
            </nav>
        </div>

        <main @class([
            'flex-1 w-full min-w-0',
            'flex flex-col min-h-0 max-w-none p-0 overflow-hidden' => View::hasSection('fullscreen_content'),
            'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8' => ! View::hasSection('fullscreen_content'),
        ])>
            @if ($errors->has('confirmation'))
                <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                    {{ $errors->first('confirmation') }}
                </div>
            @endif
            @yield('content')
        </main>

        @unless (View::hasSection('hide_footer'))
        <footer class="border-t border-gray-200 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex flex-wrap items-center justify-center sm:justify-end gap-x-5 gap-y-2 text-sm text-gray-600">
                    <a href="https://www.erftkreis-news.de/impressum" target="_blank" rel="noopener noreferrer" class="hover:text-gray-900">Impressum</a>
                    <a href="https://www.erftkreis-news.de/datenschutzerklaerung" target="_blank" rel="noopener noreferrer" class="hover:text-gray-900">Datenschutz</a>
                    <button type="button" onclick="localStorage.removeItem('erftkreis_media_cookie_info'); window.location.reload();" class="hover:text-gray-900 text-inherit bg-transparent border-0 cursor-pointer p-0">
                        Cookie-Hinweis erneut anzeigen
                    </button>
                    @if($isAdminRole)
                        @if($showBackoffice)
                            <a href="{{ route('admin.backoffice.index') }}" class="hover:text-gray-900">Backoffice</a>
                        @endif
                        @can('admin.settings')
                            <a href="{{ route('admin.event-planning.index') }}" class="hover:text-gray-900">Eventplanung</a>
                            <a href="{{ route('admin.settings.index') }}" class="hover:text-gray-900">Einstellungen</a>
                        @endcan
                    @else
                        @can('admin.settings')
                            <a href="{{ route('admin.event-planning.index') }}" class="hover:text-gray-900">Eventplanung</a>
                        @endcan
                        @if($adminUser && $adminUser->can('admin.backoffice'))
                            <a href="{{ route('admin.backoffice.usage.create') }}" class="hover:text-gray-900">Nachverfolgung erfassen</a>
                        @endif
                        <a href="{{ route('profile.edit') }}" class="hover:text-gray-900">Einstellungen</a>
                    @endif
                    <a href="{{ $websiteUrl }}" class="hover:text-gray-900">Zur Website</a>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="hover:text-gray-900">Abmelden</button>
                    </form>
                </div>
            </div>
        </footer>
        @endunless
    </div>
    {{-- Fallback, falls @vite/app.js nicht lädt: sonst schlägt „typeof adminConfirmDelete && …“ fehl und Löschen tut nichts --}}
    <script>
        window.adminConfirmDelete = window.adminConfirmDelete || function (form) {
            if (!form || !(form instanceof HTMLFormElement)) {
                return false;
            }
            var msg = String(form.getAttribute('data-delete-prompt') || 'Wirklich endgültig löschen?')
                .replace(/\s*Geben Sie zur Bestätigung[^.]*\.?/gi, '')
                .replace(/\s+/g, ' ')
                .trim();
            if (!window.confirm(msg || 'Wirklich endgültig löschen?')) {
                return false;
            }
            var hidden = form.querySelector('input[name="confirmation"][data-delete-confirmation="1"]');
            if (!hidden) {
                hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'confirmation';
                hidden.setAttribute('data-delete-confirmation', '1');
                form.appendChild(hidden);
            }
            hidden.value = 'ja';
            return true;
        };
    </script>
    @stack('scripts')
</body>
</html>
