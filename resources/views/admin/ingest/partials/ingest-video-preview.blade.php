{{-- Erwartet $f (\App\Models\IngestFile), optional $controls (bool), $preload (string), $linkClass, $mediaClass --}}
@php
    $ingestVideoControls = $controls ?? true;
    $ingestVideoPreload = $preload ?? 'metadata';
    $ingestVideoLinkClass = $linkClass ?? 'relative block h-full w-full focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-[#092E48]';
    $ingestVideoMediaClass = $mediaClass ?? 'h-full w-full object-contain max-h-32';
    $ingestVideoSrc = null;
    if ($f->isIngestVideoFile()) {
        if ($f->hasIngestPreviewVideo()) {
            $ingestVideoSrc = route('admin.ingest.preview-playback', $f);
        } elseif ($f->isBrowserPlayableOriginal()) {
            $ingestVideoSrc = route('admin.ingest.playback', $f);
        }
    }
    $ingestVideoGenerating = $f->status === \App\Models\IngestFile::STATUS_PREVIEW_GENERATING;
@endphp

@if ($ingestVideoSrc)
    <video
        class="{{ $ingestVideoMediaClass }}"
        @if ($ingestVideoControls) controls @endif
        playsinline
        preload="{{ $ingestVideoPreload }}"
        src="{{ $ingestVideoSrc }}"
        title="{{ $f->hasIngestPreviewVideo() ? 'Kurzvorschau' : $f->original_name }}"
    ></video>
@elseif ($f->isIngestVideoFile())
    <a href="{{ route('admin.ingest.show', $f) }}" class="{{ $ingestVideoLinkClass }}" title="Clip öffnen und abspielen">
        <img
            src="{{ route('admin.ingest.thumbnail', $f) }}"
            alt=""
            aria-hidden="true"
            class="{{ $ingestVideoMediaClass }} bg-gray-900 object-cover"
            loading="lazy"
            decoding="async"
        >
        <span class="pointer-events-none absolute inset-0 flex items-center justify-center bg-black/25">
            @if ($ingestVideoGenerating)
                <span class="rounded bg-black/70 px-2 py-1 text-[10px] font-medium text-white">Vorschau lädt…</span>
            @else
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-black/60 text-sm text-white" aria-hidden="true">▶</span>
            @endif
        </span>
    </a>
@endif
