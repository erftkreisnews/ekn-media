{{-- Tab „Videos“: Drop-Zone (Drag & Drop + Klick) + Liste (wie Bilder) --}}
@php
    $newsItemVideos = $newsItem->videos;
    $existingVideoNames = $newsItemVideos->pluck('original_name')->filter()->values()->toArray();
@endphp
    <div class="bg-white shadow-sm rounded-2xl border border-gray-200 overflow-hidden min-w-0 max-w-full" x-data="videoUploadDropzone({{ json_encode($existingVideoNames) }})">
    <div class="px-4 sm:px-6 py-4 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <h2 class="text-base font-semibold text-gray-900">Videos</h2>
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
            <button type="submit" form="newsEditForm" data-upload="videos" class="inline-flex justify-center items-center px-4 py-2 text-sm font-medium rounded-2xl text-white bg-[#092E48] hover:bg-[#0b3858] min-h-[2.75rem]" @click="syncFileInput()">
                Hochladen
            </button>
        </div>
    </div>

    <div class="px-4 sm:px-6 py-4 border-b border-gray-200 space-y-4">
        @include('admin.news.partials.video-metadata-guidelines')
    </div>

    {{-- Drop-Zone: Dateien ablegen oder klicken zum Auswählen (keine Doppelten) --}}
    <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
        <input x-ref="fileInput" type="file" name="videos[]" form="newsEditForm" accept="video/*" multiple
            class="sr-only"
            @change="addFiles($event.target.files)">
        <div
            @click="$refs.fileInput.value = ''; $refs.fileInput.click()"
            @dragover.prevent="dragOver = true"
            @dragleave.prevent="dragOver = false"
            @drop.prevent="addFilesFromDrop($event.dataTransfer.files); dragOver = false"
            :class="dragOver ? 'border-[#092E48] bg-[#092E48]/5' : 'border-gray-300'"
            class="border-2 border-dashed rounded-2xl p-4 sm:p-8 text-center cursor-pointer transition-colors hover:border-[#092E48] hover:bg-gray-50/80">
            <p class="text-sm font-medium text-gray-700">Dateien hier ablegen oder klicken zum Auswählen</p>
            <p class="mt-1 text-xs text-gray-500">Video-Dateien. Doppelte Dateinamen werden ignoriert.</p>
            <p x-show="pendingCount > 0" x-cloak class="mt-2 text-sm text-[#092E48]" x-text="pendingCount + ' Datei(en) zum Hochladen ausgewählt'"></p>
            <p x-show="skippedCount > 0" x-cloak class="mt-1 text-xs text-amber-600" x-text="skippedCount + ' Doppelte übersprungen'"></p>
        </div>
    </div>
    @error('videos.*')
        <p class="px-6 py-2 text-sm text-red-600">{{ $message }}</p>
    @enderror

    <div class="px-4 sm:px-6 py-4 space-y-4">
        @if ($newsItemVideos->isEmpty())
            <p class="text-sm text-gray-500">Noch keine Videos für diese Meldung hochgeladen.</p>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-2">
                @foreach ($newsItemVideos as $m)
                    <div class="border border-gray-200 rounded-xl overflow-hidden bg-white flex flex-col min-w-0">
                        @php
                            $routePlayback = route('admin.news.media.playback', [$newsItem, $m->id]);
                            $rel = $m->resolveDeliveryDownloadRelativePath();
                            $presigned = $rel && config('media_storage.prefer_presigned_streaming', true)
                                ? app(\App\Services\MediaStorage::class)->temporaryPlaybackUrlForPath($rel, now()->addMinutes(120))
                                : null;
                            $playbackUrl = $presigned ?? $routePlayback;
                            $downloadPlaybackUrl = $routePlayback . (str_contains($routePlayback, '?') ? '&' : '?') . 'download=1';
                        @endphp
                        <div class="relative aspect-video bg-gray-900 max-w-full min-w-0 max-h-40">
                            {{-- preload=none: kein paralleles Laden vieler großer Videos (sonst Spinner). Abspielen startet beim Play. --}}
                            <video
                                src="{{ $playbackUrl }}"
                                controls
                                playsinline
                                class="w-full h-full max-w-full max-h-40 object-contain"
                                preload="none"
                                @if($m->preview_url) poster="{{ $m->preview_url }}" @endif
                            >
                                Ihr Browser unterstützt das Video-Tag nicht.
                                <a href="{{ $downloadPlaybackUrl }}">Video herunterladen</a>
                            </video>
                        </div>
                        <div class="p-2 space-y-1.5">
                            <p class="text-sm font-medium text-gray-900 truncate" title="{{ $m->original_name ?? $m->display_name }}">{{ $m->original_name ?? $m->display_name }}</p>
                            @php
                                $durationLabel = null;
                                if ($m->duration_s) {
                                    $totalSeconds = (int) round((float) $m->duration_s);
                                    $minutes = intdiv($totalSeconds, 60);
                                    $seconds = $totalSeconds % 60;
                                    $durationLabel = sprintf('%02d:%02d min', $minutes, $seconds);
                                }
                                $sizeLabel = $m->file_size_kb ? number_format($m->file_size_kb / 1024, 1, ',', '.') . ' MB' : null;
                                $overviewParts = [];
                                if ($m->video_metazeile) {
                                    $overviewParts[] = $m->video_metazeile;
                                }
                                if ($durationLabel) {
                                    $overviewParts[] = $durationLabel;
                                }
                                if ($sizeLabel) {
                                    $overviewParts[] = $sizeLabel;
                                }
                                $overviewLine = $overviewParts ? implode(' · ', $overviewParts) : null;
                            @endphp
                            <p class="text-xs text-gray-500">
                                @if($overviewLine)
                                    {{ $overviewLine }}
                                @else
                                    Analysiere…
                                @endif
                            </p>
                            <p class="text-[11px] text-gray-400">#{{ $m->id }}</p>

                            <div class="pt-2 border-t border-gray-100 space-y-2">
                                <p class="text-xs font-semibold text-gray-800">Metadaten bearbeiten</p>
                                <label class="block">
                                    <span class="text-[11px] font-medium text-gray-600">Caption</span>
                                    <textarea name="media[{{ $m->id }}][caption]" form="newsEditForm" rows="2" maxlength="1800"
                                        class="mt-0.5 w-full rounded-lg border-gray-300 text-xs text-gray-900 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                                        placeholder="Bild-/Videotext">{{ old('media.'.$m->id.'.caption', $m->caption) }}</textarea>
                                </label>
                                <label class="block">
                                    <span class="text-[11px] font-medium text-gray-600">Ort</span>
                                    <input type="text" name="media[{{ $m->id }}][metadata_location]" form="newsEditForm" value="{{ old('media.'.$m->id.'.metadata_location', $m->metadata_location) }}"
                                        maxlength="512"
                                        class="mt-0.5 w-full rounded-lg border-gray-300 text-xs text-gray-900 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                                        placeholder="z. B. Stadt, Straße">
                                </label>
                                <label class="block">
                                    <span class="text-[11px] font-medium text-gray-600">Datum (Aufnahme / Ereignis)</span>
                                    <input type="date" name="media[{{ $m->id }}][metadata_recorded_at]" form="newsEditForm"
                                        value="{{ old('media.'.$m->id.'.metadata_recorded_at', $m->metadata_recorded_at ? $m->metadata_recorded_at->format('Y-m-d') : '') }}"
                                        class="mt-0.5 w-full rounded-lg border-gray-300 text-xs text-gray-900 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                                </label>
                                <label class="block">
                                    <span class="text-[11px] font-medium text-gray-600">Keywords</span>
                                    <input type="text" name="media[{{ $m->id }}][media_keywords]" form="newsEditForm" value="{{ old('media.'.$m->id.'.media_keywords', $m->media_keywords) }}"
                                        maxlength="512"
                                        class="mt-0.5 w-full rounded-lg border-gray-300 text-xs text-gray-900 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                                        placeholder="durch Komma getrennt">
                                </label>
                                <label class="block">
                                    <span class="text-[11px] font-medium text-gray-600">Beschreibung <span class="font-normal text-gray-400">(optional)</span></span>
                                    <textarea name="media[{{ $m->id }}][description]" form="newsEditForm" rows="2" maxlength="8000"
                                        class="mt-0.5 w-full rounded-lg border-gray-300 text-xs text-gray-900 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                                        placeholder="Zusätzlicher Fließtext">{{ old('media.'.$m->id.'.description', $m->description) }}</textarea>
                                </label>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 pt-2 pb-1 border-t border-gray-100">
                                <label class="inline-flex items-center gap-2 cursor-pointer px-3 py-1.5 text-xs font-medium rounded-full border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition-colors
                                    {{ !$m->is_visible ? 'ring-1 ring-gray-400 bg-gray-100' : '' }}">
                                    <input type="hidden" name="media[{{ $m->id }}][is_visible]" value="1" form="newsEditForm">
                                    <input type="checkbox" name="media[{{ $m->id }}][is_visible]" value="0" form="newsEditForm" class="h-4 w-4 rounded border-gray-300 text-[#092E48] focus:ring-[#092E48] focus:ring-offset-0"
                                        @checked(!$m->is_visible)>
                                    <span>Nicht sichtbar</span>
                                </label>
                                <label class="inline-flex items-center gap-2 cursor-pointer px-3 py-1.5 text-xs font-medium rounded-full border transition-colors
                                    {{ $m->versand ? 'border-[#092E48] bg-[#092E48]/10 text-[#092E48]' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 hover:border-[#092E48]' }}">
                                    <input type="hidden" name="media[{{ $m->id }}][versand]" value="0" form="newsEditForm">
                                    <input type="checkbox" name="media[{{ $m->id }}][versand]" value="1" form="newsEditForm" class="h-4 w-4 rounded border-gray-300 text-[#092E48] focus:ring-[#092E48] focus:ring-offset-0"
                                        @checked($m->versand)>
                                    <span>Versand</span>
                                </label>
                            </div>
                            @include('admin.news.partials.media-delivery-org-checkboxes', ['newsItem' => $newsItem, 'm' => $m, 'organizationsForMediaDelivery' => $organizationsForMediaDelivery ?? collect()])
                            <div class="flex flex-wrap items-center gap-2 pt-2">
                                <a href="{{ $downloadPlaybackUrl }}" class="inline-flex items-center gap-1 text-xs text-gray-600 hover:text-[#092E48]">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    Download
                                </a>
                                <button type="submit" form="run-stills-media-{{ $m->id }}" class="inline-flex items-center gap-1 text-xs text-[#092E48] hover:text-[#0b3858]">
                                    Bilder erzeugen
                                </button>
                                <button type="submit" form="unlink-media-{{ $m->id }}" class="inline-flex items-center gap-1 text-xs text-amber-600 hover:text-amber-700">Verkn. löschen</button>
                                <button type="submit" form="destroy-media-{{ $m->id }}" class="inline-flex items-center gap-1 text-xs text-red-600 hover:text-red-700">Löschen</button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
