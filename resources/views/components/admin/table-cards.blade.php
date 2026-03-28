@props([
    // Optional: Sammlung für Referenz, aber nicht zwingend nötig
    'items' => collect(),
])

{{-- Desktop: klassische Tabelle --}}
<div class="hidden md:block px-4 py-3 sm:px-6 sm:py-4">
    {{ $table ?? $slot }}
</div>

{{-- Mobile: Karten-Ansicht --}}
<div class="md:hidden space-y-3 px-3 py-3 sm:px-4 sm:py-4">
    {{ $cards ?? '' }}
</div>


