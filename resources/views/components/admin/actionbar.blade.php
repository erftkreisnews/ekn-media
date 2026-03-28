@props([
    'form' => null,
])

<div
    {{ $attributes->merge([
        'class' => 'flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3',
    ]) }}
>
    <div class="flex flex-wrap items-center gap-2">
        {{ $slot }}
    </div>

    @if($form)
        <div class="hidden sm:flex flex-wrap items-center gap-2">
            {{ $form }}
        </div>
    @endif
</div>

@push('styles')
    <style>
        @media (max-width: 639px) {
            .ekn-actionbar-sticky {
                position: sticky;
                bottom: 0;
                z-index: 30;
                backdrop-filter: blur(12px);
            }
        }
    </style>
@endpush

