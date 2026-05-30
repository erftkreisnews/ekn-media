@extends('layouts.admin')

@section('title', 'Bild-Editor')
@section('fullscreen_content', true)
@section('hide_footer', true)

@section('content')
    @php
        $prevUrl = $prevMedia ? route('admin.news.media.image-editor', [$newsItem, $prevMedia->id]) : null;
        $nextUrl = $nextMedia ? route('admin.news.media.image-editor', [$newsItem, $nextMedia->id]) : null;
        $metaEditUrl = route('admin.news.media.edit', [$newsItem, $medium->id]);
    @endphp

    <div
        class="admin-image-editor-page flex min-h-0 flex-1 flex-col overflow-hidden"
        x-data="adminImageEditor({
            mediaId: @js($medium->id),
            editorSourceUrl: @js($editorSource['proxy']),
            editorDirectSourceUrl: @js($editorSource['direct']),
            editorSourceCacheKey: @js($editorSource['cache_key']),
            prefetchEditorSources: @js($prefetchEditorSources),
            editorSaveUrl: @js(route('admin.images.editor-save', $medium->id)),
            originalName: @js($medium->original_name ?: 'bild.jpg'),
            redirectUrlTemplate: @js(route('admin.news.media.image-editor', [$newsItem, '__ID__'])),
        })"
    >
        <header class="admin-image-editor-header shrink-0">
            <div class="admin-image-editor-header-left min-w-0">
                <a href="{{ $metaEditUrl }}" class="mb-1 inline-flex items-center gap-1 text-xs text-white/60 hover:text-white">
                    ← Metadaten &amp; IPTC
                </a>
                <span class="admin-image-editor-title block">Bild-Editor</span>
                <span class="admin-image-editor-filename block truncate">{{ $medium->original_name ?: 'Bild #'.$medium->id }}</span>
                <p class="mt-0.5 truncate text-xs text-white/50">{{ $newsItem->title }}</p>
            </div>
            <div class="admin-image-editor-header-actions">
                @if ($prevUrl)
                    <a href="{{ $prevUrl }}" class="admin-image-editor-btn admin-image-editor-btn--ghost hidden sm:inline-flex">‹ Vorheriges</a>
                @endif
                @if ($nextUrl)
                    <a href="{{ $nextUrl }}" class="admin-image-editor-btn admin-image-editor-btn--ghost hidden sm:inline-flex">Nächstes ›</a>
                @endif
                <button type="button" class="admin-image-editor-btn admin-image-editor-btn--ghost" @click="resetEditor()">Zurücksetzen</button>
                <a href="{{ $metaEditUrl }}" class="admin-image-editor-icon-btn" aria-label="Schließen" title="Zur Metadaten-Seite">✕</a>
            </div>
        </header>

        <p
            x-show="editorMessage"
            class="shrink-0 px-4 py-2 text-center text-sm"
            :class="editorError ? 'bg-red-500/20 text-red-200' : 'bg-emerald-500/20 text-emerald-200'"
            x-text="editorMessage"
            x-cloak
        ></p>

        <div class="admin-image-editor-body min-h-0 flex-1">
            <aside class="admin-image-editor-rail" aria-label="Export-Vorschau">
                <p class="admin-image-editor-rail-title">Vorschau</p>
                <div class="admin-image-editor-rail-thumb">
                    <canvas
                        x-ref="editorRailPreview"
                        class="admin-image-editor-rail-canvas"
                        width="400"
                        height="520"
                        aria-label="Live-Vorschau des Ausschnitts"
                    ></canvas>
                </div>
                <p class="admin-image-editor-rail-filename">{{ $medium->original_name ?: 'Bild #'.$medium->id }}</p>
                <a href="{{ $metaEditUrl }}" class="admin-image-editor-rail-link">Metadaten</a>
            </aside>

            <div class="admin-image-editor-stage min-h-0 flex flex-1 flex-col">
                <div
                    class="admin-image-editor-stage-view flex min-h-0 flex-1 items-center justify-center overflow-hidden p-2 sm:p-3"
                    x-ref="editorStageView"
                >
                    <div
                        class="admin-image-editor-canvas-wrap relative shrink-0"
                        x-ref="editorCanvasWrap"
                        x-show="editorDisplayReady"
                        x-cloak
                    >
                        <canvas x-ref="editorCanvas" class="admin-image-editor-canvas block"></canvas>
                        <div
                            class="admin-image-editor-crop-overlay absolute inset-0 touch-none"
                            :class="editorCropDragging ? 'is-dragging' : ''"
                        >
                            <div
                                class="admin-image-editor-crop-box"
                                :style="cropBoxScreenStyle()"
                                @pointerdown.prevent="startCropDrag($event, 'move')"
                            >
                                <div class="admin-image-editor-crop-grid" aria-hidden="true"></div>
                                <template x-for="handle in cropHandles" :key="handle">
                                    <div
                                        class="admin-image-editor-crop-handle"
                                        :class="'is-' + handle"
                                        @pointerdown.prevent.stop="startCropDrag($event, handle)"
                                    ></div>
                                </template>
                            </div>
                        </div>
                    </div>
                    <p x-show="!editorDisplayReady && !editorError" class="text-sm text-white/50">Bild wird geladen…</p>
                </div>

                <nav class="admin-image-editor-filmstrip" aria-label="Bilder dieser Meldung">
                    @foreach ($filmstripImages as $item)
                        <a
                            href="{{ $item['url'] }}"
                            class="admin-image-editor-filmstrip-item {{ (int) $item['id'] === (int) $medium->id ? 'is-active' : '' }}"
                            title="{{ $item['label'] }}"
                            @if ((int) $item['id'] === (int) $medium->id) aria-current="true" @endif
                        >
                            @if ($item['thumb'])
                                <img src="{{ $item['thumb'] }}" alt="" class="admin-image-editor-filmstrip-thumb" loading="lazy" decoding="async">
                            @else
                                <span class="admin-image-editor-filmstrip-fallback">#{{ $item['id'] }}</span>
                            @endif
                        </a>
                    @endforeach
                </nav>
            </div>

            <aside class="admin-image-editor-tools min-h-0 overflow-y-auto overflow-x-hidden">
                @include('admin.partials.image-editor-tools')
            </aside>
        </div>
    </div>
@endsection
