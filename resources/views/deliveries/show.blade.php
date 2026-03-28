@extends('layouts.frontend')

@section('title', 'Medienpaket | ' . $newsItem->title . ' | ' . config('app.name'))

@section('content')
<div class="bg-ekn-50 -mx-4 sm:-mx-6 lg:-mx-8 py-8 sm:py-10">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Block 1: Überschrift im gleichen Stil wie News-Detail --}}
        <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 px-4 py-4 sm:px-6 sm:py-5 mb-4">
            @php
                $titleParts = explode(':', $newsItem->title, 2);
            @endphp
            <h1 class="text-xl sm:text-2xl font-semibold text-ekn-900 leading-snug">
                @if(count($titleParts) === 2)
                    <span>{{ e($titleParts[0]) }}:</span>
                    <span class="sm:block"> {{ e(trim($titleParts[1])) }}</span>
                @else
                    {{ $newsItem->title }}
                @endif
            </h1>
        </div>

        {{-- Block 2: Beschreibung & Downloads --}}
        <article class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 overflow-hidden">
            <div class="p-4 sm:p-6">
                <div class="flex flex-wrap items-baseline justify-between gap-4 text-sm text-slate-600 mb-4">
                    <div>
                        Datum: {{ $newsItem->published_at?->format('d.m.Y, H:i') }} Uhr
                        · NEWSID: {{ $newsItem->id }}
                        @if($newsItem->location_label)
                            · {{ $newsItem->location_label }}
                        @endif
                    </div>
                    <div class="text-ekn-900 font-medium">
                        Link gültig bis {{ $delivery->expires_at->format('d.m.Y H:i') }} Uhr
                    </div>
                </div>

                @if($newsItem->subheadline)
                    <div class="font-semibold text-ekn-900 mb-4">{{ $newsItem->subheadline }}</div>
                @endif

                <div class="prose prose-gray max-w-none text-gray-700 mb-6 space-y-4">
                    {!! $newsItem->body !!}
                </div>

                @if(session('error'))
                    <div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800 mb-4">
                        {{ session('error') }}
                    </div>
                @endif
                @if(session('status'))
                    <div class="rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-800 mb-4">
                        {{ session('status') }}
                    </div>
                @endif

                {{-- Medienliste: Kacheln anklickbar → Detailansicht (Lightbox) mit Download --}}
                <section class="border-t border-gray-200 pt-6 mt-6" x-data="{ overlayImage: null, overlayTitle: '', overlayDownloadUrl: '' }" x-effect="document.body.classList.toggle('overflow-hidden', !!overlayImage)" @keydown.escape.window="overlayImage = null">
                        {{-- Lightbox: Bild in Großansicht, Schließen + Download --}}
                        <div x-show="overlayImage" x-cloak
                            x-transition:enter="ease-out duration-200"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            x-transition:leave="ease-in duration-150"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60"
                            @click.self="overlayImage = null">
                            <div class="relative inline-block rounded-xl shadow-2xl overflow-hidden bg-white max-w-[90vw] max-h-[90vh] flex flex-col" @click.stop>
                                <img :src="overlayImage" :alt="overlayTitle" class="block w-auto h-auto object-contain" style="max-height: 70vh;">
                                <div class="flex items-center justify-between gap-4 p-3 border-t border-gray-200 bg-gray-50">
                                    <p class="text-sm font-medium text-gray-900 truncate min-w-0" x-text="overlayTitle"></p>
                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        {{-- Kein HTML-Attribut „download“: sonst doppeltes Verhalten (Speichern + Bild im Tab). Großansicht nur über „Ansehen“. --}}
                                        <a :href="overlayDownloadUrl"
                                           class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                            Download
                                        </a>
                                        <button type="button" @click="overlayImage = null"
                                            class="px-3 py-2 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                                            Schließen
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @php
                            $allowedImages = $allowedMedia->where('type', 'image')->values();
                            $allowedVideos = $allowedMedia->where('type', 'video')->values();
                            $allowedAudios = $allowedMedia->where('type', 'audio')->values();
                        @endphp

                        @if($allowedMedia->isEmpty())
                            <p class="text-gray-500">Keine Medien für den Versand freigegeben.</p>
                        @else
                            {{-- Medien-Bereich: einheitlicher Block mit Abstand zum Artikel --}}
                            <div class="mt-10 pt-8 border-t border-gray-200">
                                <h2 class="text-lg font-semibold text-ekn-900 mb-1">Medien zum Download</h2>
                                <p class="text-sm text-gray-500 mb-6">
                                    Bilder anklicken für Detailansicht. Download-Links sind 10 Minuten gültig.
                                    Seite neu laden erzeugt neue Links. Für mehrere Downloads bitte „Auswahl herunterladen“ nutzen (kein ZIP).
                                </p>

                                {{-- Multi-Select Download (ohne ZIP) --}}
                                <div class="flex flex-wrap items-center gap-3 mb-6">
                                    <button
                                        type="button"
                                        id="downloadSelectedBtn"
                                        class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-[#092E48] text-[#092E48] bg-white hover:bg-[#092E48]/5 transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                                    >
                                        Auswahl herunterladen (nacheinander)
                                    </button>
                                    <span id="downloadSelectedStatus" class="text-sm text-gray-500"></span>
                                </div>

                                <iframe id="download-queue-iframe" style="display:none" aria-hidden="true"></iframe>

                            {{-- Bilder --}}
                            @if($allowedImages->isNotEmpty())
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                    @foreach($allowedImages as $media)
                                        @php
                                            $hasPublic = (bool) $media->public_url;
                                            $previewUrl = $media->public_url ?? $media->preview_url;
                                            $displayName = $media->display_name ?: $media->original_name ?: 'Medium #' . $media->id;
                                            $hasPreviewImage = $previewUrl !== null && $previewUrl !== '';
                                            $offerDownload = $hasPublic
                                                || (
                                                    $media->redaction_status === \App\Models\NewsItemMedia::REDACTION_FAILED
                                                    && ($media->preview_path || $media->path)
                                                );
                                            $downloadUrl = $downloadUrls[$media->id] ?? '#';
                                        @endphp
                                        <div class="border border-gray-200 rounded-lg overflow-hidden bg-white shadow-sm">
                                            <div class="aspect-video bg-gray-100 relative flex border-l-4 border-l-[#092E48] {{ $hasPreviewImage ? 'cursor-zoom-in' : '' }}"
                                                @if($hasPreviewImage) @click="overlayImage = '{{ $previewUrl }}'; overlayTitle = @js($displayName); overlayDownloadUrl = '{{ $offerDownload ? $downloadUrl : '#' }}'" @endif>
                                                @if($hasPreviewImage)
                                                <img src="{{ $previewUrl }}" alt="{{ $displayName }}" class="w-full h-full object-cover pointer-events-none">
                                                @else
                                                <div class="w-full h-full flex items-center justify-center text-slate-500 text-sm px-2">Bild wird vorbereitet</div>
                                                @endif
                                            </div>
                                            <div class="p-3 space-y-2">
                                                <p class="text-xs font-mono text-gray-500">{{ sprintf('%06d', $media->id) }}</p>
                                                <p class="text-sm font-medium text-gray-900 truncate min-w-0" title="{{ $media->display_name ?: $media->original_name }}">{{ $media->display_name ?: $media->original_name ?: 'Medium #' . $media->id }}</p>
                                                @if($media->width || $media->height)
                                                    <p class="text-xs text-gray-500">{{ $media->width }}×{{ $media->height }}</p>
                                                @endif
                                                @if($media->photographer)
                                                    <p class="text-xs text-gray-500">Fotograf: {{ $media->photographer }}</p>
                                                @endif
                                                @if($media->caption)
                                                    <p class="text-xs text-gray-500 leading-snug">
                                                        {{ \Illuminate\Support\Str::limit($media->caption, 200) }}
                                                    </p>
                                                @endif
                                                <div class="pt-2 border-t border-gray-100 flex flex-wrap gap-2 items-start">
                                                    @if($offerDownload)
                                                    <button type="button"
                                                        @click="overlayImage = '{{ $previewUrl }}'; overlayTitle = @js($displayName); overlayDownloadUrl = '{{ $downloadUrl }}'"
                                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 hover:border-[#092E48] hover:text-[#092E48] transition-colors">
                                                        Ansehen
                                                    </button>
                                                    <a href="{{ $downloadUrl }}"
                                                       class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 hover:border-[#092E48] hover:text-[#092E48] transition-colors">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                        Download
                                                    </a>
                                                    <label class="inline-flex items-center gap-2 text-xs text-gray-600 cursor-pointer">
                                                        <input
                                                            type="checkbox"
                                                            class="dl-select rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]"
                                                            data-download-url="{{ $downloadUrl }}"
                                                            value="{{ $media->id }}"
                                                        >
                                                        Auswählen
                                                    </label>
                                                    @else
                                                        @if($media->redaction_status === \App\Models\NewsItemMedia::REDACTION_PENDING)
                                                            <p class="text-xs text-slate-500">Für dieses Bild ist der Download in Kürze möglich. Bitte die Seite später erneut laden.</p>
                                                        @else
                                                            <p class="text-xs text-slate-500">Download in Kürze verfügbar. Bitte die Seite später erneut laden.</p>
                                                        @endif
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Eine Zeile Abstand → Trennstrich → eine Zeile Abstand → Video zum Download --}}
                            @if($allowedVideos->isNotEmpty())
                                <div class="mt-6 pt-6 border-t-2 border-gray-300">
                                    <h3 class="text-base font-semibold text-ekn-900 mb-1">Video zum Download</h3>
                                    <p class="text-sm text-gray-500 mb-4">Videos hier abspielen oder über „Download“ speichern.</p>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                        @foreach($allowedVideos as $media)
                                            <div class="border border-gray-200 rounded-lg overflow-hidden bg-white shadow-sm">
                                                <div class="aspect-video bg-gray-900 border-l-4 border-l-[#092E48] flex items-center justify-center overflow-hidden">
                                                    {{-- Same-Origin-Stream: direkte S3-URL im <video> scheitert oft (CORS/Range) --}}
                                                    <video
                                                        src="{{ $streamUrls[$media->id] ?? '#' }}"
                                                        controls
                                                        preload="metadata"
                                                        playsinline
                                                        class="w-full h-full object-contain"
                                                        aria-label="Video: {{ $media->display_name ?: $media->original_name }}"
                                                    >
                                                        Ihr Browser unterstützt die Wiedergabe nicht.
                                                        <a href="{{ $downloadUrls[$media->id] ?? '#' }}">Video herunterladen</a>
                                                    </video>
                                                </div>
                                                <div class="p-3 space-y-2">
                                                    <p class="text-xs font-mono text-gray-500">{{ sprintf('%06d', $media->id) }}</p>
                                                    <p class="text-sm font-medium text-gray-900 truncate min-w-0" title="{{ $media->display_name ?: $media->original_name }}">{{ $media->display_name ?: $media->original_name ?: 'Medium #' . $media->id }}</p>
                                                    @if($media->video_metazeile)
                                                        <p class="text-xs text-gray-500">{{ $media->video_metazeile }}</p>
                                                    @endif
                                                    <div class="pt-2 border-t border-gray-100">
                                                        <a href="{{ $downloadUrls[$media->id] ?? '#' }}"
                                                           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 hover:border-[#092E48] hover:text-[#092E48] transition-colors">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                            Download
                                                        </a>
                                                        <div class="mt-2">
                                                            <label class="inline-flex items-center gap-2 text-xs text-gray-600 cursor-pointer">
                                                                <input
                                                                    type="checkbox"
                                                                    class="dl-select rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]"
                                                                    data-download-url="{{ $downloadUrls[$media->id] ?? '#' }}"
                                                                    value="{{ $media->id }}"
                                                                >
                                                                Auswählen
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Optional: Audios --}}
                            @if($allowedAudios->isNotEmpty())
                                <div class="mt-8 pt-6 border-t border-gray-200">
                                    <h3 class="text-base font-semibold text-ekn-900 mb-1">Audios</h3>
                                    <p class="text-sm text-gray-500 mb-4">Audio-Dateien zum Download.</p>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                        @foreach($allowedAudios as $media)
                                            <div class="border border-gray-200 rounded-lg overflow-hidden bg-white">
                                                <div class="px-3 pt-3 pb-1 bg-gray-50 border-b border-gray-100 border-l-4 border-l-gray-200">
                                                    @if(!empty($streamUrls[$media->id]))
                                                        <audio src="{{ $streamUrls[$media->id] }}" controls preload="metadata" class="w-full h-10">
                                                            <a href="{{ $downloadUrls[$media->id] ?? '#' }}">Audio herunterladen</a>
                                                        </audio>
                                                    @else
                                                        <div class="aspect-video bg-gray-100 flex items-center justify-center">
                                                            <span class="text-gray-400" aria-hidden="true">
                                                                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/></svg>
                                                            </span>
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="p-3 space-y-2">
                                                    <p class="text-xs font-mono text-gray-500">{{ sprintf('%06d', $media->id) }}</p>
                                                    <p class="text-sm font-medium text-gray-900 truncate min-w-0">{{ $media->display_name ?: $media->original_name ?: 'Medium #' . $media->id }}</p>
                                                    @if($media->duration_s || $media->bitrate_bps)
                                                        <p class="text-xs text-gray-500">
                                                            @if($media->duration_s) {{ number_format($media->duration_s, 1) }} s @endif
                                                            @if($media->bitrate_bps) {{ round($media->bitrate_bps / 1000) }} kbps @endif
                                                        </p>
                                                    @endif
                                                    <div class="pt-2 border-t border-gray-100">
                                                        <a href="{{ $downloadUrls[$media->id] ?? '#' }}"
                                                           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 hover:border-[#092E48] hover:text-[#092E48] transition-colors">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                            Download
                                                        </a>
                                                        <div class="mt-2">
                                                            <label class="inline-flex items-center gap-2 text-xs text-gray-600 cursor-pointer">
                                                                <input
                                                                    type="checkbox"
                                                                    class="dl-select rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]"
                                                                    data-download-url="{{ $downloadUrls[$media->id] ?? '#' }}"
                                                                    value="{{ $media->id }}"
                                                                >
                                                                Auswählen
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            </div>
                        @endif
                    </section>

                @if($delivery->confirmed_at === null)
                    {{-- Optional: Redaktion & Produkt angeben (keine Pflicht mehr vor dem Download). --}}
                    <section class="border border-ekn-200 rounded-xl bg-ekn-50/50 p-6 mt-8">
                        <h2 class="text-lg font-semibold text-ekn-900 mb-3">Redaktion &amp; Produkt (optional)</h2>
                        @if($delivery->allowed_organization_id)
                            <p class="text-sm text-gray-600 mb-4">
                                Diese Angaben helfen uns bei der Dokumentation der Nutzung.
                                Sie können die Medien auch ohne Angabe direkt herunterladen.
                            </p>
                            <form method="POST" action="{{ $confirmUrl }}" x-data="{
                                orgId: @js(old('organization_id')),
                                productId: @js(old('product_id')),
                                productsByOrg: @js($productsByOrg->map(fn($items) => $items->map(fn($p) => ['id' => $p->id, 'name' => $p->name])->values())->toArray())
                            }">
                                @csrf
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label for="organization_id" class="block text-sm font-medium text-gray-700 mb-1">Medienhaus (Redaktion)</label>
                                        <select name="organization_id" id="organization_id" required
                                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                                                x-model="orgId"
                                                x-on:change="productId = ''">
                                            <option value="">— Bitte wählen —</option>
                                            @foreach($organizations as $org)
                                                <option value="{{ $org->id }}" @selected(old('organization_id') == $org->id)>{{ $org->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('organization_id')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label for="product_id" class="block text-sm font-medium text-gray-700 mb-1">Format (Produkt)</label>
                                        <select name="product_id" id="product_id" required
                                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                                                x-model="productId">
                                            <option value="">— Zuerst Medienhaus wählen —</option>
                                            <template x-for="p in (productsByOrg[orgId] || [])" :key="p.id">
                                                <option :value="p.id" x-text="p.name"></option>
                                            </template>
                                        </select>
                                        @error('product_id')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                                <label class="inline-flex items-center gap-2 cursor-pointer mb-4 block">
                                    <input type="checkbox" name="save_as_recipient" value="1" {{ old('save_as_recipient') ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]">
                                    <span class="text-sm text-gray-700">Mich als Empfänger im System anlegen (für künftige Versände)</span>
                                </label>
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-[#092E48] text-white text-sm font-medium rounded-md hover:bg-[#0b3858]">
                                    Angaben speichern
                                </button>
                            </form>
                        @else
                            <p class="text-sm text-gray-600 mb-4">
                                Wenn Sie möchten, können Sie hier Ihr Medienhaus (Redaktion) und das Format (Produkt) angeben.
                                Diese Information ist freiwillig und dient ausschließlich unserer internen Dokumentation.
                            </p>
                            <form method="POST" action="{{ $confirmUrl }}">
                                @csrf
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label for="self_reported_organization_name" class="block text-sm font-medium text-gray-700 mb-1">Medienhaus (Redaktion)</label>
                                        <input type="text" name="self_reported_organization_name" id="self_reported_organization_name"
                                               value="{{ old('self_reported_organization_name') }}"
                                               maxlength="255" placeholder="z. B. Westdeutscher Rundfunk"
                                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                                        @error('self_reported_organization_name')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label for="self_reported_product_name" class="block text-sm font-medium text-gray-700 mb-1">Format (Produkt)</label>
                                        <input type="text" name="self_reported_product_name" id="self_reported_product_name"
                                               value="{{ old('self_reported_product_name') }}"
                                               maxlength="255" placeholder="z. B. Studio Köln"
                                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                                        @error('self_reported_product_name')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                                <label class="inline-flex items-center gap-2 cursor-pointer mb-4 block">
                                    <input type="checkbox" name="save_as_recipient" value="1" {{ old('save_as_recipient') ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]">
                                    <span class="text-sm text-gray-700">Mich als Empfänger im System anlegen (für künftige Versände)</span>
                                </label>
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-[#092E48] text-white text-sm font-medium rounded-md hover:bg-[#0b3858]">
                                    Angaben speichern
                                </button>
                            </form>
                        @endif
                        <p class="mt-3 text-xs text-gray-500">
                            Mit dem Herunterladen der Dateien erklären Sie sich damit einverstanden, dass wir die von Ihnen
                            freiwillig angegebenen Daten (z. B. Redaktion, Format) zum Zweck der Versand-Dokumentation verarbeiten.
                        </p>
                    </section>
                @endif
            </div>
        </article>
    </div>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const btn = document.getElementById('downloadSelectedBtn');
            const statusEl = document.getElementById('downloadSelectedStatus');
            const iframe = document.getElementById('download-queue-iframe');

            if (!btn || !statusEl || !iframe) return;

            const delayMs = 1400; // Browser nicht mit gleichzeitigen Downloads überlasten.

            btn.addEventListener('click', () => {
                const selected = Array.from(document.querySelectorAll('input.dl-select:checked'))
                    .map((el) => el.dataset.downloadUrl)
                    .filter((u) => typeof u === 'string' && u.length > 0 && u !== '#');

                if (selected.length === 0) {
                    statusEl.textContent = 'Bitte zuerst Dateien auswählen.';
                    return;
                }

                btn.disabled = true;
                statusEl.textContent = `Starte Download von ${selected.length} Datei(en)...`;

                let i = 0;
                const next = () => {
                    if (i >= selected.length) {
                        btn.disabled = false;
                        statusEl.textContent = 'Downloads gestartet.';
                        return;
                    }

                    iframe.src = selected[i++];
                    setTimeout(next, delayMs);
                };

                next();
            });
        });
    </script>
@endpush

@endsection
