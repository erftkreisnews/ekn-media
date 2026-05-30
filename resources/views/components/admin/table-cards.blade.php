@props([
    // Optional: Sammlung für Referenz, aber nicht zwingend nötig
    'items' => collect(),
])

{{-- lg: Tabelle; <lg: Karten — Klassen in app.css @apply, damit Breakpoints im Build sicher sind --}}
<div class="admin-table-cards-desktop">
    {{ $table ?? $slot }}
</div>

<div class="admin-table-cards-mobile">
    {{ $cards ?? '' }}
</div>
