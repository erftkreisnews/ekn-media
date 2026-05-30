{{-- Erwartet $stats (array vom VideoIngestController::ingestIndexStats) --}}
<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-7 gap-3 min-w-0">
    <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm">
        <p class="text-xs font-medium text-gray-500">Gesamt</p>
        <p class="mt-1 text-lg font-semibold tabular-nums text-gray-900">{{ number_format($stats['total'], 0, ',', '.') }}</p>
    </div>

    <div class="rounded-lg border border-sky-200 bg-sky-50/80 p-3 shadow-sm">
        <p class="text-xs font-medium text-sky-800">Heute</p>
        <p class="mt-1 text-lg font-semibold tabular-nums text-sky-950">{{ number_format($stats['today'], 0, ',', '.') }}</p>
    </div>

    <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm">
        <p class="text-xs font-medium text-gray-500">Validiert+</p>
        <p class="mt-1 text-lg font-semibold tabular-nums text-gray-900">{{ number_format($stats['validated'], 0, ',', '.') }}</p>
        <p class="mt-0.5 text-[10px] leading-tight text-gray-400">ab Status „validiert“</p>
    </div>

    <div class="rounded-lg border border-amber-200 bg-amber-50/60 p-3 shadow-sm">
        <p class="text-xs font-medium text-amber-900">Ohne Preview</p>
        <p class="mt-1 text-lg font-semibold tabular-nums text-amber-950">{{ number_format($stats['without_preview'], 0, ',', '.') }}</p>
    </div>

    <div class="rounded-lg border border-red-200 bg-red-50/60 p-3 shadow-sm">
        <p class="text-xs font-medium text-red-900">Abgelehnt / Fehler</p>
        <p class="mt-1 text-lg font-semibold tabular-nums text-red-950">{{ number_format($stats['rejected_or_failed'], 0, ',', '.') }}</p>
    </div>

    <div class="rounded-lg border border-indigo-200 bg-indigo-50/60 p-3 shadow-sm">
        <p class="text-xs font-medium text-indigo-900">Ausgewählt</p>
        <p class="mt-1 text-lg font-semibold tabular-nums text-indigo-950">{{ number_format($stats['is_selected'], 0, ',', '.') }}</p>
    </div>

    <div class="rounded-lg border border-emerald-200 bg-emerald-50/60 p-3 shadow-sm">
        <p class="text-xs font-medium text-emerald-900">Mit News</p>
        <p class="mt-1 text-lg font-semibold tabular-nums text-emerald-950">{{ number_format($stats['with_news'], 0, ',', '.') }}</p>
    </div>
</div>
