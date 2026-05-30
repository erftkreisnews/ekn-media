@php
    /** @var \App\Models\NewsItem $item */
    $thumb = $item->teaser_image_for_public;
    $thumbUrl = $thumb ? ($thumb->thumb_url ?: $thumb->preview_url) : null;
    $item->loadMissing('plannedEvent');
    $locationLabel = filled($item->location_chip_label) ? $item->location_chip_label : null;
    if ($locationLabel === null && filled($item->keywords)) {
        $keywordParts = array_values(array_filter(array_map(
            static fn (string $part): string => trim($part),
            explode(',', (string) $item->keywords)
        ), static fn (string $part): bool => $part !== '' && ! preg_match('/^20\d{2}$/u', $part)));
        $locationLabel = $keywordParts[0] ?? null;
    }
    $badgeText = $locationLabel !== null ? mb_strtoupper($locationLabel, 'UTF-8') : null;
    $metaYear = $item->event_at?->format('Y') ?? $item->published_at?->format('Y');
    $newsUrl = route('koelnimage.gallery.photos', $item->slug);
    $cardTitle = \Illuminate\Support\Str::limit(strip_tags((string) $item->title), 120);
@endphp
<article class="flex min-h-0 flex-col overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm ring-1 ring-black/[0.04] transition hover:shadow-md">
    <a href="{{ $newsUrl }}" title="{{ $cardTitle }}" aria-label="Beitrag öffnen: {{ $cardTitle }}" class="relative block aspect-[16/10] w-full shrink-0 overflow-hidden bg-gray-100">
        <span class="sr-only">Beitrag öffnen: {{ $cardTitle }}</span>
        @if($thumbUrl)
            <img src="{{ $thumbUrl }}" alt="{{ $cardTitle }}" title="{{ $cardTitle }}" class="h-full w-full object-cover" loading="lazy" width="400" height="250">
        @else
            <div class="flex h-full min-h-[120px] items-center justify-center px-2 text-center text-[11px] leading-snug text-gray-400">Kein Bild</div>
        @endif
        @if($badgeText)
            <span class="absolute right-2 top-2 z-10 max-w-[calc(100%-1rem)] truncate rounded bg-red-700 px-1.5 py-0.5 text-[9px] font-bold uppercase leading-tight tracking-wide text-white shadow-sm ring-1 ring-white/15 sm:text-[10px]">{{ $badgeText }}</span>
        @endif
    </a>
    <div class="flex min-h-0 flex-1 flex-col p-2.5 sm:p-3">
        <h3 class="text-[13px] font-bold leading-snug text-gray-900 sm:text-sm">
            <a href="{{ $newsUrl }}" title="{{ $cardTitle }}" class="koelnimage-card-headline block [overflow-wrap:anywhere] hover:text-red-800">{{ $item->title }}</a>
        </h3>
        <p class="mt-auto pt-2 text-[11px] leading-snug text-gray-500 sm:text-xs">
            <span class="koelnimage-card-meta inline [overflow-wrap:anywhere]">
                @if($metaYear && $locationLabel)
                    {{ $metaYear }}<span aria-hidden="true"> · </span>{{ $locationLabel }}
                @elseif($locationLabel)
                    {{ $locationLabel }}
                @elseif($metaYear)
                    {{ $metaYear }}
                @else
                    <span class="text-gray-400">—</span>
                @endif
            </span>
        </p>
    </div>
</article>
