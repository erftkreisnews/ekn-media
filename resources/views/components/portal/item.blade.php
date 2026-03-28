@props(['item'])

<article class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <header class="px-4 pt-4">
        <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500 mb-2">
            <span>{{ $item->published_at?->format('d.m.Y, H:i') }} Uhr</span>
            <span>·</span>
            <span>NEWSID: {{ $item->id }}</span>
            @if($item->location_label)
                <span class="w-full mt-0.5 text-right">{{ $item->location_label }}</span>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2 mb-2">
            @if($item->images->count() > 0)
                <x-portal.badge variant="info">Foto</x-portal.badge>
            @endif
            @if($item->videos->count() > 0)
                <x-portal.badge variant="info">Video</x-portal.badge>
            @endif
            @if($item->audios->count() > 0)
                <x-portal.badge variant="info">Audio</x-portal.badge>
            @endif
        </div>
        <div class="bg-ekn-900 -mx-4 px-4 py-3">
            <h2 class="text-lg font-semibold">
                <a href="{{ route('news.show', $item->slug) }}" class="text-white no-underline hover:underline">{{ $item->title }}</a>
            </h2>
            @if($item->subheadline)
                <p class="text-white/95 text-sm mt-1">{{ $item->subheadline }}</p>
            @endif
        </div>
    </header>
    <div class="p-4 flex flex-col sm:flex-row gap-4">
        @if($item->teaser_image)
            <div class="shrink-0 w-[180px] sm:w-[200px]">
                <a href="{{ route('news.show', $item->slug) }}" class="block relative overflow-hidden rounded-lg border border-gray-200 aspect-[3/2]">
                    @php
                        $teaserImageUrl = $item->teaser_image->thumb_url ?: $item->teaser_image->preview_url;
                    @endphp
                    @if($teaserImageUrl)
                    <img
                        src="{{ $teaserImageUrl }}"
                        alt="{{ $item->teaser_image->display_name }}"
                        class="w-full h-full object-cover"
                        loading="eager"
                        decoding="sync"
                    >
                    <span class="absolute inset-0 flex items-center justify-center pointer-events-none" aria-hidden="true">
                        <img src="{{ asset('images/erftkreis-news-logo.png') }}?v=2" alt="" class="max-w-[14%] max-h-[14%] w-auto h-auto object-contain opacity-[0.35]">
                    </span>
                    @else
                    <div class="w-full h-full flex items-center justify-center text-slate-400 text-xs bg-slate-100">Bild wird vorbereitet</div>
                    @endif
                </a>
            </div>
        @endif
        <div class="min-w-0 flex-1">
            <p class="text-gray-700 text-sm leading-relaxed">
                {!! Str::limit(strip_tags($item->teaser ?: $item->body), 280) !!}
                <a href="{{ route('news.show', $item->slug) }}" class="text-ekn-900 font-medium hover:underline">(weiterlesen)</a>
            </p>
        </div>
    </div>
</article>
