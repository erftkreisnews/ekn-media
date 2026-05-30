@props([
    'title' => null,
    'subtitle' => null,
    'id' => null,
    'collapsible' => false,
    'expanded' => true,
])

@php
    $collapsible = filter_var($collapsible, FILTER_VALIDATE_BOOLEAN);
    $expanded = filter_var($expanded, FILTER_VALIDATE_BOOLEAN);
    $panelId = $id ? $id.'-panel' : 'admin-section-'.substr(sha1(($title ?? '').($subtitle ?? '').uniqid('', true)), 0, 12);
@endphp

<section {{ $attributes->merge([
    'id' => $id,
    'class' => 'bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden',
]) }} @if ($collapsible) x-data="{ open: @js($expanded) }" @endif>
    @if ($title || $subtitle)
        @if ($collapsible)
            <button
                type="button"
                @if ($id) id="{{ $id }}-toggle" @endif
                class="w-full px-4 sm:px-6 py-3 border-b border-slate-200 bg-slate-50/80 flex flex-wrap items-center justify-between gap-3 text-left hover:bg-slate-100/80 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#092E48]"
                @click="open = ! open"
                :aria-expanded="open"
                aria-controls="{{ $panelId }}"
                aria-label="{{ ($title ?? 'Bereich').' ein- oder ausklappen' }}"
            >
                <div class="flex items-center gap-2 min-w-0">
                    <span class="inline-block h-5 w-1.5 shrink-0 rounded-full bg-[#092E48]" aria-hidden="true"></span>
                    <h2 class="text-xs sm:text-sm font-semibold tracking-wide text-slate-900 uppercase">
                        {{ $title }}
                    </h2>
                    <svg
                        class="shrink-0 h-5 w-5 text-slate-500 transition-transform duration-200"
                        :class="open ? 'rotate-180' : ''"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                    </svg>
                </div>
                @if ($subtitle)
                    <p class="w-full sm:w-auto sm:max-w-[55%] sm:text-right text-xs text-slate-500 order-last sm:order-none">
                        {{ $subtitle }}
                    </p>
                @endif
            </button>
        @else
            <div class="px-4 sm:px-6 py-3 border-b border-slate-200 bg-slate-50/80 flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="inline-block h-5 w-1.5 rounded-full bg-[#092E48]"></span>
                    <h2 class="text-xs sm:text-sm font-semibold tracking-wide text-slate-900 uppercase">
                        {{ $title }}
                    </h2>
                </div>
                @if ($subtitle)
                    <p class="hidden sm:block text-xs text-slate-500">
                        {{ $subtitle }}
                    </p>
                @endif
            </div>
        @endif
    @endif

    @if ($collapsible)
        <div
            id="{{ $panelId }}"
            x-show="open"
            @if (! $expanded) x-cloak @endif
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-1"
        >
            <div class="px-4 sm:px-6 py-4 sm:py-6">
                {{ $slot }}
            </div>
        </div>
    @else
        <div class="px-4 sm:px-6 py-4 sm:py-6">
            {{ $slot }}
        </div>
    @endif
</section>
