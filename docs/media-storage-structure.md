# Medien-Speicherstruktur (ab jetzt für neue Uploads)

## Zielstruktur für neue Medien

Neue Uploads werden unter folgendem Schema gespeichert:

`news-media/YYYY/MM/DD/artikel-slug/{media-id}-{rolle}-{name}.{ext}`

Beispiel:

`news-media/2026/03/23/unfall-a61-lkw-brand/4821-gallery-unfallstelle.jpg`

Abgeleitete Dateien (Preview, Thumb, Poster, Redaction, AI-Preview) liegen unter:

`news-media/YYYY/MM/DD/artikel-slug/derived/{media-id}-{rolle}-{name}.{ext}`

Beispiel:

`news-media/2026/03/23/unfall-a61-lkw-brand/derived/4821-thumb-unfallstelle.webp`

## Zentrale Logik

Die Pfadlogik ist in `app/Services/MediaStorage.php` zentralisiert:

- `generateMediaPath($news, $media, $file, $role)`
- `generateDerivedMediaPath($news, $media, $file, $role)`
- `isStructuredNewsMediaPath($path)` zur Erkennung der neuen Struktur

## Fallbacks und defensive Regeln

- Datum: `published_at` des News-Artikels, sonst `now()`
- Artikel-Slug: `news->slug`, sonst `Str::slug(news->title)`, sonst `news-item`
- Medien-ID: `media->id`, sonst temporär `uniqid('tmp', false)`
- Dateiname: slugifiziert, auf 50 Zeichen gekürzt, Fallback `media`
- Rolle: übergeben oder typbasiert (`gallery`, `video`, `audio`, sonst `media`)
- Extension: aus Datei, validiert auf `[a-z0-9]+`, sonst `bin`

## Kompatibilität mit Bestandsdaten

- Bestehende DB-Pfade werden nicht geändert.
- Alte Dateien bleiben unter bisherigen Pfaden erreichbar.
- Jobs verwenden für bestehende (alte) Pfade weiterhin Legacy-Logik.
- Neue strukturierte Pfade werden nur für neue Uploads erzeugt.
- `MediaStorage::exists()` und `MediaStorage::url()` prüfen weiterhin aktive Disk plus Fallback-Disk.

## Rollenvergabe (aktuell)

- Hauptdateien: `gallery`, `video`, `audio`
- Abgeleitet: `preview`, `thumb`, `poster`, `redacted`, `original`, `preview-ai`

