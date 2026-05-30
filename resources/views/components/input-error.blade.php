@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'text-sm text-red-600 dark:text-red-400 space-y-1 break-words']) }}>
        @foreach ((array) $messages as $message)
            <li class="break-words">{{ $message }}</li>
        @endforeach
    </ul>
@endif
