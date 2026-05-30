{{-- Eine gemeinsame Liste für alle Zuordnungsfelder (massiv weniger DOM als pro-Zeile-<select>). --}}
<datalist id="ingest-news-assign-datalist">
    @foreach ($ingestNewsChoices as $n)
        <option value="{{ $n->id }}">#{{ $n->id }}@if ($n->brand) · {{ Str::limit($n->brand->name, 14) }}@endif · {{ Str::limit($n->title, 56) }}</option>
    @endforeach
</datalist>
