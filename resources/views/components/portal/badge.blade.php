@props(['variant' => 'neutral'])

@php
$classes = match ($variant) {
    'info' => 'bg-ekn-900/10 text-ekn-900 border-ekn-900/20',
    'success' => 'bg-green-100 text-green-800 border-green-200',
    default => 'bg-gray-100 text-gray-700 border-gray-200',
};
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border {$classes}"]) }}>{{ $slot }}</span>
