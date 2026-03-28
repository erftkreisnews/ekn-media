@props([
    'title' => null,
    'subtitle' => null,
    'id' => null,
])

<section {{ $attributes->merge([
    'id' => $id,
    'class' => 'bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden',
]) }}>
    @if($title || $subtitle)
        <div class="px-4 sm:px-6 py-3 border-b border-slate-200 bg-slate-50/80 flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <span class="inline-block h-5 w-1.5 rounded-full bg-[#092E48]"></span>
                <h2 class="text-xs sm:text-sm font-semibold tracking-wide text-slate-900 uppercase">
                    {{ $title }}
                </h2>
            </div>
            @if($subtitle)
                <p class="hidden sm:block text-xs text-slate-500">
                    {{ $subtitle }}
                </p>
            @endif
        </div>
    @endif

    <div class="px-4 sm:px-6 py-4 sm:py-6">
        {{ $slot }}
    </div>
</section>

