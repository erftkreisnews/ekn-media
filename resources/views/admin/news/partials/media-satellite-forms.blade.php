{{-- Außerhalb von #newsEditForm: verschachtelte <form>-Tags würden die Zuordnung der Medienfelder zum Hauptformular zerstellen. --}}
@php
    $newsItemMedia = $newsItem->media()->orderBy('id')->get();
@endphp

@if ($newsItem->images->isNotEmpty() && trim((string) ($newsItem->author_credit ?? '')) !== '')
    <form id="applyAuthorCreditForm" method="POST" action="{{ route('admin.news.apply-author-credit', $newsItem) }}" class="hidden"
        onsubmit="return confirm('Credit des Autors wirklich auf alle {{ $newsItem->images->count() }} Bilder dieses Beitrags übernehmen?');">
        @csrf
    </form>
@endif

@foreach ($newsItemMedia as $m)
    @php
        $unlinkMsg = $m->type === 'image'
            ? 'Verknüpfung wirklich löschen? Bild bleibt gespeichert.'
            : 'Verknüpfung wirklich löschen?';
        $destroyPrompt = match ($m->type) {
            'image' => 'Bild endgültig löschen? Geben Sie zur Bestätigung „ja“ ein.',
            'video' => 'Video endgültig löschen? Geben Sie zur Bestätigung „ja“ ein.',
            'audio' => 'Audio endgültig löschen? Geben Sie zur Bestätigung „ja“ ein.',
            default => 'Medium endgültig löschen? Geben Sie zur Bestätigung „ja“ ein.',
        };
    @endphp
    <form id="unlink-media-{{ $m->id }}" method="POST" action="{{ route('admin.news.media.unlink', [$newsItem, $m->id]) }}" class="hidden"
        onsubmit="return confirm({{ json_encode($unlinkMsg) }});">
        @csrf
    </form>
    <form id="destroy-media-{{ $m->id }}" method="POST" action="{{ route('admin.news.media.destroy', [$newsItem, $m->id]) }}" class="hidden"
        onsubmit="return window.adminConfirmDelete(this)"
        data-delete-prompt="{{ $destroyPrompt }}">
        @csrf
        @method('DELETE')
    </form>
    @if ($m->type === 'video')
        <form id="run-stills-media-{{ $m->id }}" method="POST" action="{{ route('admin.news.media.stills.run', [$newsItem, $m->id]) }}" class="hidden"
            onsubmit="return confirm('Jetzt Bilder aus diesem Video nachträglich erzeugen?');">
            @csrf
        </form>
    @endif
    @if ($m->type === 'image' && $m->is_unkentlich)
        <form id="unkentlich-media-{{ $m->id }}" method="POST" action="{{ route('admin.news.media.unkentlich.aufheben', [$newsItem, $m->id]) }}" class="hidden"
            onsubmit="return confirm('Markierung „Unkenntlich“ aufheben? Die pixelierten Bereiche bleiben verändert.');">
            @csrf
        </form>
    @endif
@endforeach
