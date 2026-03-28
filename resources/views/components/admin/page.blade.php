@props([
    'title' => null,
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'space-y-6']) }}>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            @if($title)
                <h1 class="text-2xl font-semibold text-gray-900">
                    {{ $title }}
                </h1>
            @endif

            @if($subtitle)
                <p class="mt-1 text-sm text-gray-600">
                    {{ $subtitle }}
                </p>
            @endif
        </div>

        @isset($actions)
            <div class="w-full sm:w-72">
                {{ $actions }}
            </div>
        @endisset
    </div>

    {{ $slot }}
</div>

