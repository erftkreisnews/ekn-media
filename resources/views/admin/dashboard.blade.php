@extends('layouts.admin')

@section('content')
    <div class="space-y-8">
        @if (session('status'))
            <div class="rounded-xl bg-green-50 p-4 border border-green-200">
                <p class="text-sm text-green-800">{{ session('status') }}</p>
            </div>
        @endif
        @if (session('error'))
            <div class="rounded-xl bg-red-50 p-4 border border-red-200">
                <p class="text-sm text-red-800">{{ session('error') }}</p>
            </div>
        @endif

        <div class="rounded-2xl bg-ekn-900 text-white px-6 py-8 sm:px-8 sm:py-10">
            <p class="text-sm font-medium text-white/70 uppercase tracking-wider">Admin-Bereich</p>
            <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight">
                Willkommen, {{ auth()->user()->name }}
            </h1>
            <p class="mt-3 text-sm sm:text-base text-white/80 max-w-2xl">
                Wähle einen Bereich – alles Wichtige auf einen Blick.
                @if(filled($brandFilterLabel ?? null))
                    Aktiver Marken-Filter: <span class="font-medium text-white">{{ $brandFilterLabel }}</span>.
                @endif
            </p>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @can('admin.news')
                <a href="{{ route('admin.news.index') }}" class="group block rounded-2xl border border-gray-200 bg-white p-6 shadow-sm hover:border-ekn-900/20 hover:shadow-md transition">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Redaktion</p>
                            <h2 class="mt-2 text-lg font-semibold text-gray-900 group-hover:text-ekn-900">Nachrichten</h2>
                            <p class="mt-2 text-3xl font-bold text-ekn-900">{{ $newsCount }}</p>
                            <p class="mt-1 text-sm text-gray-600">
                                @if($isAdmin ?? false)
                                    {{ filled($brandFilterLabel ?? null) ? 'Nachrichten (gefiltert)' : 'Nachrichten im System' }}
                                @else
                                    {{ filled($brandFilterLabel ?? null) ? 'Eigene Nachrichten (gefiltert)' : 'Eigene Nachrichten' }}
                                @endif
                            </p>
                        </div>
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-ekn-900/10 text-ekn-900">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V9a2 2 0 012-2h2a2 2 0 012 2v9a2 2 0 01-2 2h-2z"/></svg>
                        </span>
                    </div>
                    <p class="mt-4 text-sm font-medium text-ekn-900 group-hover:underline">Zur Übersicht →</p>
                </a>
            @endcan

            @can('admin.media')
                <a href="{{ route('admin.video.index') }}" class="group block rounded-2xl border border-gray-200 bg-white p-6 shadow-sm hover:border-ekn-900/20 hover:shadow-md transition">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Medien</p>
                            <h2 class="mt-2 text-lg font-semibold text-gray-900 group-hover:text-ekn-900">Video &amp; Bilder</h2>
                            <p class="mt-2 text-sm text-gray-600">Bibliotheken, Uploads und Metadaten verwalten.</p>
                        </div>
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-ekn-900/10 text-ekn-900">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        </span>
                    </div>
                    <p class="mt-4 text-sm font-medium text-ekn-900 group-hover:underline">Medien öffnen →</p>
                </a>
            @endcan

            @can('admin.deliveries')
                <a href="{{ route('admin.deliveries.index') }}" class="group block rounded-2xl border border-gray-200 bg-white p-6 shadow-sm hover:border-ekn-900/20 hover:shadow-md transition">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Auslieferung</p>
                            <h2 class="mt-2 text-lg font-semibold text-gray-900 group-hover:text-ekn-900">Versand</h2>
                            <p class="mt-2 text-sm text-gray-600">Lieferungen, Downloads und Versandläufe.</p>
                        </div>
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-ekn-900/10 text-ekn-900">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        </span>
                    </div>
                    <p class="mt-4 text-sm font-medium text-ekn-900 group-hover:underline">Zum Versand →</p>
                </a>
            @endcan

            @if(auth()->user()?->hasRole('admin') && auth()->user()?->can('admin.customers'))
                <a href="{{ route('admin.customers.index') }}" class="group block rounded-2xl border border-gray-200 bg-white p-6 shadow-sm hover:border-ekn-900/20 hover:shadow-md transition">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Vertrieb</p>
                            <h2 class="mt-2 text-lg font-semibold text-gray-900 group-hover:text-ekn-900">Kunden</h2>
                            <p class="mt-2 text-sm text-gray-600">Organisationen, Redaktionen, Kontakte, Versandziele.</p>
                        </div>
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-ekn-900/10 text-ekn-900">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </span>
                    </div>
                    <p class="mt-4 text-sm font-medium text-ekn-900 group-hover:underline">Kundenverwaltung →</p>
                </a>
            @endif

            @can('admin.settings')
                <a href="{{ route('admin.event-planning.index') }}" class="group block rounded-2xl border border-gray-200 bg-white p-6 shadow-sm hover:border-ekn-900/20 hover:shadow-md transition">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Redaktion &amp; KI</p>
                            <h2 class="mt-2 text-lg font-semibold text-gray-900 group-hover:text-ekn-900">Eventplanung</h2>
                            <p class="mt-2 text-sm text-gray-600">Geplante Events, Vorschläge, Haus-Kader und KI-Kontext — eigener Bereich.</p>
                        </div>
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-ekn-900/10 text-ekn-900">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </span>
                    </div>
                    <p class="mt-4 text-sm font-medium text-ekn-900 group-hover:underline">Zur Eventplanung →</p>
                </a>
                <a href="{{ route('admin.settings.index') }}" class="group block rounded-2xl border border-gray-200 bg-white p-6 shadow-sm hover:border-ekn-900/20 hover:shadow-md transition">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">System</p>
                            <h2 class="mt-2 text-lg font-semibold text-gray-900 group-hover:text-ekn-900">Einstellungen</h2>
                            <p class="mt-2 text-sm text-gray-600">Backup, KI, Jobs und weitere Optionen.</p>
                        </div>
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-ekn-900/10 text-ekn-900">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </span>
                    </div>
                    <p class="mt-4 text-sm font-medium text-ekn-900 group-hover:underline">Einstellungen öffnen →</p>
                </a>
            @endcan

            @if(auth()->user()->can('admin.backoffice') || auth()->user()->can('admin.users'))
                <a href="{{ route('admin.backoffice.index') }}" class="group block rounded-2xl border border-gray-200 bg-white p-6 shadow-sm hover:border-ekn-900/20 hover:shadow-md transition">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Intern</p>
                            <h2 class="mt-2 text-lg font-semibold text-gray-900 group-hover:text-ekn-900">Backoffice</h2>
                            <p class="mt-2 text-sm text-gray-600">Abrechnung, Nutzung, Benutzer.</p>
                        </div>
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-ekn-900/10 text-ekn-900">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        </span>
                    </div>
                    <p class="mt-4 text-sm font-medium text-ekn-900 group-hover:underline">Backoffice öffnen →</p>
                </a>
            @endif
        </div>
    </div>
@endsection
