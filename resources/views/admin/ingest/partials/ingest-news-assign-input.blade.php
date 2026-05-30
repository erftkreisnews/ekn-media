{{-- Erwartet: $f (\App\Models\IngestFile), $suffix (string, eindeutig pro Kontext), $inputClass (Tailwind-Klassen) --}}
@php
    $__ingestNewsAssignFieldId = 'ingest-news-assign-'.$f->id.'-'.$suffix;
@endphp
<input
    type="number"
    name="news_item_id"
    id="{{ $__ingestNewsAssignFieldId }}"
    class="{{ trim((string) $inputClass) }}"
    list="ingest-news-assign-datalist"
    min="1"
    step="1"
    inputmode="numeric"
    autocomplete="off"
    placeholder="Meldungs-ID …"
    aria-label="Meldung für Ingest-Eintrag {{ $f->id }}"
    value="{{ $f->news_item_id !== null ? (int) $f->news_item_id : '' }}"
>
