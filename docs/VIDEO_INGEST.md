# Video-Ingest (MC60 → Sendefassung)

Technische Betriebsdokumentation für das **getrennte** Ingest-Modul: Rohmaterial liegt lokal unter konfigurierbaren Pfaden, wird gescannt und per ffprobe validiert. Erst nach **redaktioneller** Zuordnung zu einer Meldung und bewusstem Start der Sendefassung wird eine MP4 erzeugt und über `MediaStorage` in die produktive `news-media/…`-Struktur übernommen.

Es gibt **keine** automatische Veröffentlichung und **keine** Verknüpfung zur Outbound-FTP-/Delivery-Pipeline (`UploadMediaToDestinationJob` bleibt unangetastet).

## Architektur (Kurz)

1. Dateien im **Inbox**-Ordner → `ingest:scan-inbox` / `ingest:scan-upload` (oder Scheduler) → Verschieben nach **processing**, Datensatz `ingest_files`, Job `ValidateIngestFileJob`.
2. Validierung: ffprobe → Status `validated` oder `rejected`.
3. Admin: Clips einer **News** zuordnen (`assigned`), Reihenfolge optional (`selection_order`).
4. Arbeitsseite **Schnitt / Sendefassung**: Clips auswählen, Reihenfolge setzen → `ProcessIngestRenderJob` (Queue).
5. Render: `IngestRenderService` normalisiert jeden Clip (H.264/AAC, Zielparameter aus `config/ingest.php`), concat, lokale Ausgabe unter **rendered**.
6. Finalisierung: `NewsItemMedia` anlegen, `MediaStorage::putFromLocalFile` + `generateMediaPath(…, 'sendefassung')`, danach `ExtractVideoMetadata` / `GenerateVideoPoster`.
7. Lokale Render-Datei wird nach Erfolg gelöscht; optional Roh-Archiv (`INGEST_ARCHIVE_RAW_AFTER_SUCCESS`).

Siehe auch: `docs/VIDEO_INGEST_ANALYSIS.md` (Bestandsanalyse).

## Tabellen / Modelle

| Tabelle | Zweck |
|--------|--------|
| `ingest_sources` | Quelle (z. B. MC60 Standard) |
| `ingest_batches` | Ein Scan-Lauf |
| `ingest_files` | Ein Roh-Clip inkl. Pfad, Hash, Metadaten, Status, optional `news_item_id`, `selection_order`, `final_news_item_media_id` |
| `ingest_render_jobs` | Ein Render-Lauf: `news_item_id`, `ingest_file_ids` (JSON), Status, `local_output_path`, `final_news_item_media_id` |

Modelle: `App\Models\IngestSource`, `IngestBatch`, `IngestFile`, `IngestRenderJob`.

## Inbox-Pfad (Auflösung)

Der Scan liest **nur** `config('ingest.paths.inbox')`. Die Ermittlung erfolgt zentral in `config/ingest.php` über `App\Services\Ingest\IngestPathResolver`:

| Priorität | Bedingung | Ergebnis (`paths.inbox`) |
|-----------|-----------|-------------------------|
| 1 | `INGEST_INBOX_PATH` ist gesetzt (nicht leer) | genau dieser Pfad |
| 2 | sonst `INGEST_ROOT_PATH` gesetzt | `{INGEST_ROOT_PATH}/upload` (z. B. Projektroot → Ordner `upload`, entspricht `/laravel12/upload` im URL-Pfad) |
| 3 | sonst | `storage/app/ingest/inbox` (wie bisher) |

**Unterordner:** Die Inbox wird nur **nicht rekursiv** gescannt (oberste Ebene). Rekursion ist für spätere Phasen vorgesehen.

## Umgebungsvariablen

| Variable | Bedeutung |
|----------|-----------|
| `INGEST_ENABLED` | `true`, damit Scan-Commands importieren (Standard: `false`) |
| `INGEST_ROOT_PATH` | Optional: Basisverzeichnis; Inbox wird `{ROOT}/upload` (Vorrang nach `INGEST_INBOX_PATH`) |
| `INGEST_INBOX_PATH` | Optional: Absoluter Pfad Inbox (höchste Priorität, rückwärtskompatibel) |
| `INGEST_PROCESSING_PATH` | Nach Import |
| `INGEST_RENDERED_PATH` | Finale lokale MP4 vor S3 |
| `INGEST_ARCHIVE_PATH` | Optionales Archiv Rohmaterial |
| `INGEST_FAILED_PATH` | Für künftige Fehlerablage (Struktur vorgesehen) |
| `INGEST_TMP_PATH` | Temp-Segmente / concat-Listen |
| `INGEST_STABLE_INTERVAL` / `INGEST_STABLE_CHECKS` | Stabilitätscheck vor Import (Größe/Mtime) |
| `INGEST_OUTPUT_*` | Bitrate, FPS, Auflösung (siehe `config/ingest.php`) |
| `INGEST_ARCHIVE_RAW_AFTER_SUCCESS` | Nach erfolgreicher News-Anbindung Rohclips ins Archiv verschieben |

Pfade sind **nicht** im Code fix verdrahtet, sondern über `config/ingest.php` und ENV.

## Artisan-Befehle

```bash
php artisan ingest:scan-inbox
php artisan ingest:scan-upload
php artisan ingest:cleanup [--tmp-max-age-hours=48] [--remove-completed-local-renders]
```

- **scan-inbox** / **scan-upload:** identische Logik; `scan-upload` ist ein Alias mit sprechendem Namen. Nur sinnvoll mit `INGEST_ENABLED=true` und existierender Migration + Quelle `mc60-default` (Seed in Migration).
- **cleanup:** alte Dateien im Tmp-Ordner; optional Reste abgeschlossener Render-Pfade bereinigen.

Empfehlung: `ingest:scan-upload` (oder `ingest:scan-inbox`) per Cron (z. B. alle 2–5 Minuten) auf dem Server ausführen, wo die Inbox liegt.

## Admin-Oberfläche

| Route | Funktion |
|-------|----------|
| `GET /admin/ingest` | Liste, Filter Status/Quelle/Datum |
| `GET /admin/ingest/files/{id}` | Detail, Vorschau (`/playback`), Zuordnung Meldung |
| `GET /admin/ingest/news/{newsItem}` | Auswahl + Reihenfolge, Button „Sendefähige MP4 erzeugen“ |
| `GET /admin/ingest/files/{id}/playback` | Stream der Rohdatei (nur Pfade unter `ingest.paths`) |

Berechtigung: wie übriges Admin – `auth` + `permission:access_admin`.

## Jobs

- `ValidateIngestFileJob` – ffprobe, Metadaten in `ingest_files`.
- `ProcessIngestRenderJob` – Render, S3-Upload, `NewsItemMedia`, Metadaten-/Poster-Jobs.

Queue: wie konfiguriert (`QUEUE_CONNECTION`); Render kann lange laufen (`timeout` im Job erhöht).

## Manuelle Smoke-Tests

1. Migration ausführen: `php artisan migrate`.
2. `INGEST_ENABLED=true`, z. B. `INGEST_ROOT_PATH=/pfad/zu/laravel12` (Inbox = …/upload) oder `INGEST_INBOX_PATH` setzen (oder Standard unter `storage/app/ingest/…`).
3. Kleine Test-MP4 in Inbox legen, `php artisan ingest:scan-upload`.
4. In Admin Clip öffnen, Meldung zuordnen, Schnittseite, Sendefassung starten.
5. In der Meldung prüfen: neues Video, Pfad unter `news-media/…`, Poster nach Jobs.

Ohne ffmpeg/ffprobe auf dem Server schlagen Validierung und Render fehl.

## Risiken / offene Punkte

- **ffmpeg-Abhängigkeit:** Server muss `ffmpeg`/`ffprobe` bereitstellen (`config/media.php` Pfade).
- **Stabilitätscheck:** Import wartet bewusst (Schlaf zwischen Prüfungen) – bei vielen Dateien Cron-Intervall abstimmen.
- **Starke Codec-Unterschiede:** Pipeline normalisiert pro Clip; extrem pathologische Quellen können trotzdem manuelle Nachbearbeitung brauchen.
- **Outbound-Delivery:** nicht Teil dieses Moduls; Versand bleibt über bestehende News-/FTP-Workflows.
