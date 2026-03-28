{{-- Tab „Audios“: Upload + Liste mit Player, Analyse-Zeile und Aktionen --}}
<div class="bg-white shadow-sm rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-4 sm:px-6 py-4 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <h2 class="text-base font-semibold text-gray-900">Audios</h2>
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
            <label class="inline-flex items-center justify-between sm:justify-start gap-2 text-sm text-gray-600 w-full sm:w-auto">
                <input type="file" name="audios[]" accept="audio/*" multiple
                    class="block w-full sm:w-auto text-sm text-gray-600 file:mr-2 file:py-2 file:px-3 file:rounded-2xl file:border-0 file:text-sm file:font-medium file:bg-[#092E48] file:text-white hover:file:bg-[#0b3858]">
                <span class="hidden sm:inline">Keine Dateien ausgewählt.</span>
            </label>
            <button type="submit" form="newsEditForm" class="inline-flex justify-center items-center px-4 py-2 text-sm font-medium rounded-2xl text-white bg-[#092E48] hover:bg-[#0b3858] min-h-[2.75rem]">
                Hochladen
            </button>
        </div>
    </div>
    @error('audios.*')
        <p class="px-6 py-2 text-sm text-red-600">{{ $message }}</p>
    @enderror

    <div class="px-4 sm:px-6 py-4 space-y-4">
        @php $newsItemAudios = $newsItem->audios; @endphp
        @if ($newsItemAudios->isEmpty())
            <p class="text-sm text-gray-500">Noch keine Audios für diese Meldung hochgeladen.</p>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($newsItemAudios as $m)
                    @php
                        $routePlayback = route('admin.news.media.playback', [$newsItem, $m->id]);
                        $rel = $m->resolveDeliveryDownloadRelativePath();
                        $presigned = $rel && config('media_storage.prefer_presigned_streaming', true)
                            ? app(\App\Services\MediaStorage::class)->temporaryPlaybackUrlForPath($rel, now()->addMinutes(120))
                            : null;
                        $playbackUrl = $presigned ?? $routePlayback;
                        $downloadPlaybackUrl = $routePlayback . (str_contains($routePlayback, '?') ? '&' : '?') . 'download=1';
                    @endphp
                    <div class="border border-gray-200 rounded-2xl overflow-hidden bg-white flex flex-col border-l-4
                        @if ($m->quality_status === 'ok') border-l-green-500
                        @elseif ($m->quality_status === 'fail') border-l-red-500
                        @endif
                        @if ($m->quality_status === 'warning') style="border-left-color: #d97706 !important;"
                        @endif">
                        <div class="p-3 bg-gray-50 border-b border-gray-200">
                            @if ($m->quality_status)
                                <span class="inline-block mb-2 px-2 py-1 text-xs font-medium rounded shadow-sm"
                                    @if ($m->quality_status === 'ok') style="background-color: #16a34a; color: #fff;"
                                    @elseif ($m->quality_status === 'warning') style="background-color: #d97706; color: #fff;"
                                    @else style="background-color: #dc2626; color: #fff;"
                                    @endif
                                    title="{{ $m->quality_notes }}">
                                    @if ($m->quality_status === 'ok') ÖRR-OK
                                    @elseif ($m->quality_status === 'warning') Warnung
                                    @else Fehler
                                    @endif
                                </span>
                            @endif
                            <audio src="{{ $playbackUrl }}" controls class="w-full h-10" preload="metadata">
                                Ihr Browser unterstützt das Audio-Tag nicht.
                                <a href="{{ $downloadPlaybackUrl }}">Audio herunterladen</a>
                            </audio>
                        </div>
                        <div class="p-3 space-y-2">
                            <p class="text-sm font-medium text-gray-900 truncate" title="{{ $m->display_name }}">{{ $m->display_name }}</p>
                            <p class="text-xs text-gray-500">
                                @if ($m->audio_metazeile)
                                    {{ $m->audio_metazeile }}
                                @else
                                    Analysiere…
                                @endif
                            </p>
                            <p class="text-xs text-gray-400">{{ number_format($m->file_size_kb) }} KB · #{{ $m->id }}</p>
                            <div class="flex flex-wrap items-center gap-2 pt-2 pb-1 border-t border-gray-100">
                                <label class="inline-flex items-center gap-2 cursor-pointer px-3 py-1.5 text-xs font-medium rounded-full border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition-colors
                                    {{ !$m->is_visible ? 'ring-1 ring-gray-400 bg-gray-100' : '' }}">
                                    <input type="hidden" name="media[{{ $m->id }}][is_visible]" value="1">
                                    <input type="checkbox" name="media[{{ $m->id }}][is_visible]" value="0" class="h-4 w-4 rounded border-gray-300 text-[#092E48] focus:ring-[#092E48] focus:ring-offset-0"
                                        @checked(!$m->is_visible)>
                                    <span>Nicht sichtbar</span>
                                </label>
                                <label class="inline-flex items-center gap-2 cursor-pointer px-3 py-1.5 text-xs font-medium rounded-full border transition-colors
                                    {{ $m->versand ? 'border-[#092E48] bg-[#092E48]/10 text-[#092E48]' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 hover:border-[#092E48]' }}">
                                    <input type="hidden" name="media[{{ $m->id }}][versand]" value="0">
                                    <input type="checkbox" name="media[{{ $m->id }}][versand]" value="1" class="h-4 w-4 rounded border-gray-300 text-[#092E48] focus:ring-[#092E48] focus:ring-offset-0"
                                        @checked($m->versand)>
                                    <span>Versand</span>
                                </label>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 pt-2">
                                <a href="{{ $downloadPlaybackUrl }}" class="inline-flex items-center gap-1 text-xs text-gray-600 hover:text-[#092E48]">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    Download
                                </a>
                                <form action="{{ route('admin.news.media.unlink', [$newsItem, $m->id]) }}" method="POST" class="inline" onsubmit="return confirm('Verknüpfung wirklich löschen?');">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1 text-xs text-amber-600 hover:text-amber-700">Verkn. löschen</button>
                                </form>
                                <form action="{{ route('admin.news.media.destroy', [$newsItem, $m->id]) }}" method="POST" class="inline" onsubmit="return confirm('Audio endgültig löschen?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center gap-1 text-xs text-red-600 hover:text-red-700">Löschen</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
