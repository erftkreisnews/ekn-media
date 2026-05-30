{{-- Erwartet $f (\App\Models\IngestFile) — kompakte Vorschau für Tabellen- und Kartenzeilen --}}
<a href="{{ route('admin.ingest.show', $f) }}"
    aria-label="Ingest-Eintrag #{{ $f->id }} öffnen"
    class="group block shrink-0 overflow-hidden rounded border border-gray-200 bg-gray-950 w-14 h-10 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-[#092E48]"
    title="Vorschau – Detailansicht">
    <span class="sr-only">Ingest-Eintrag #{{ $f->id }} öffnen</span>
    @if ($f->isIngestImageFile())
        <img
            src="{{ route('admin.ingest.thumbnail', $f) }}"
            alt=""
            aria-hidden="true"
            class="h-full w-full object-cover"
            loading="lazy"
            decoding="async"
        >
    @elseif ($f->isIngestVideoFile())
        @php
            $ingestThumbVideoSrc = $f->hasIngestPreviewVideo()
                ? route('admin.ingest.preview-playback', $f)
                : ($f->isBrowserPlayableOriginal() ? route('admin.ingest.playback', $f) : null);
        @endphp
        @if ($ingestThumbVideoSrc)
            <video
                class="h-full w-full object-cover pointer-events-none"
                muted
                playsinline
                preload="metadata"
                src="{{ $ingestThumbVideoSrc }}#t=0.001"
            ></video>
        @else
            <span class="relative block h-full w-full">
                <img
                    src="{{ route('admin.ingest.thumbnail', $f) }}"
                    alt=""
                    aria-hidden="true"
                    class="h-full w-full object-cover"
                    loading="lazy"
                    decoding="async"
                >
                <span class="pointer-events-none absolute inset-0 flex items-center justify-center bg-black/30 text-[8px] font-bold text-white">▶</span>
            </span>
        @endif
    @else
        <span class="flex h-full w-full items-center justify-center bg-gray-100 text-[9px] font-medium text-gray-500">—</span>
    @endif
</a>
