# Phase 1 – Codeanalyse: Video-Ingest und bestehendes Medien-/News-System

**Stand:** Analyse der ursprünglichen Codebasis **plus** Abgleich mit dem **bereits implementierten** separaten Ingest-Modul (Migrationen, Services, Admin, Jobs). Vollständige Betriebsdokumentation der Pipeline: `docs/VIDEO_INGEST.md`.

---

## 1. Modelle und Tabellen (News und Medien)

| Komponente | Datei / Tabelle | Rolle |
|------------|-----------------|--------|
| `NewsItem` | `app/Models/NewsItem.php`, `news_items` | Meldung (`slug`, `published_at`, Status, Lösch-Hooks inkl. `MediaStorage`-Bereinigung) |
| `NewsItemMedia` | `app/Models/NewsItemMedia.php`, `news_item_media` | Medienzeile: `type` (`image`/`video`/`audio`), `path` (relativer Storage-Pfad), `preview_path`, Video-Metadaten, Redaction, Versand-Flags |
| `MediaAsset` | `app/Models/MediaAsset.php` | Eloquent-Alias auf `news_item_media` für API-Uploads |

**Zuordnung:** `news_item_media.news_item_id` → `news_items.id` (`hasMany` auf `NewsItem`).

**Ingest (eigenes Schema, getrennt vom produktiven Medienpfad bis zum Final-Upload):**

| Tabelle | Modell | Zweck |
|---------|--------|--------|
| `ingest_sources` | `IngestSource` | Quelle (Seed: `mc60-default`) |
| `ingest_batches` | `IngestBatch` | Ein Scan-Lauf |
| `ingest_files` | `IngestFile` | Roh-Clip: Pfade, Hash, MIME, Metadaten, Status, optional News, Sortierung, `final_news_item_media_id` |
| `ingest_render_jobs` | `IngestRenderJob` | Ein Render-Lauf mit JSON-Liste der Clip-IDs |

Migration: `database/migrations/2026_03_25_120000_create_ingest_tables.php`.

---

## 2. Wie Medien einer News zugeordnet werden

| Weg | Einstieg | Ablauf |
|-----|----------|--------|
| **Admin-Upload** | `NewsItemController::processMediaUploads` | `NewsItemMedia::create`, `MediaStorage::storeUploadedFileAs` + `generateMediaPath`; bei Video: `ExtractVideoMetadata::dispatch`, `GenerateVideoPoster::dispatch` |
| **API** | `MediaUploadController::store` | Pending-Pfad, dann `ProcessMediaPipeline` für Qualität/Preview/Redaction-Pipeline |

Publikation erfolgt **nicht** automatisch durch Medien-Upload; sie hängt an `NewsItem`-Status und `published_at`.

**Ingest-Final:** `ProcessIngestRenderJob` legt nach erfolgreichem Render ein `NewsItemMedia` an, lädt mit `MediaStorage::putFromLocalFile` hoch, setzt `path`, stößt dieselben Metadaten-/Poster-Jobs an – analog zu einem normalen Video-Upload.

---

## 3. MediaStorage / S3-Abstraktion

Zentrale Klasse: `app/Services/MediaStorage.php`.

- **Disk:** `config('media_storage.disk')` ← `MEDIA_DISK` / `FILESYSTEM_DISK`; Fallback-Disk für Altbestände.
- **Neue Pfadstruktur:** `generateMediaPath` / `generateDerivedMediaPath`, Präfix `news-media/YYYY/MM/DD/{slug}/…`; abgeleitet unter `…/derived/…` (siehe `docs/media-storage-structure.md`).
- **Hilfen:** `exists`, `url`, `temporaryPlaybackUrlForPath`, `resolveReadableLocalPath`, `putFromLocalFile`, `delete`, `isStructuredNewsMediaPath`.

**Ingest:** Rohdateien liegen **nur** unter den konfigurierten **lokalen absoluten Pfaden** (`config/ingest.php`); erst die **Sendefassung** wird über `generateMediaPath($news, $media, $originalName, 'sendefassung')` in die produktive Struktur geschrieben.

**Hinweis zur Spezifikation:** Eine Laravel-Filesystem-**Disk** wie `INGEST_DISK=local` ist **nicht** vorgesehen; stattdessen explizite Verzeichnis-ENV-Variablen (`INGEST_INBOX_PATH` usw.). Das reduziert Kopplung an `filesystems.php` und passt zu einem dedizierten MC60-Dateisystem.

---

## 4. Bestehende ffmpeg-/Preview-/Metadata-Jobs

| Job | Datei | Zweck |
|-----|--------|--------|
| `ExtractVideoMetadata` | `app/Jobs/ExtractVideoMetadata.php` | ffprobe → `NewsItemMedia` (Breite, Höhe, fps, Codec, Dauer) |
| `GenerateVideoPoster` | `app/Jobs/GenerateVideoPoster.php` | ffmpeg Frame → Poster (WebP) auf Storage |
| `GenerateNewsMediaPreview` | `app/Jobs/GenerateNewsMediaPreview.php` | Bild-Preview/Thumb |
| `ProcessMediaPipeline` | `app/Jobs/ProcessMediaPipeline.php` | Orchestrierung nach API-Upload |
| `ExtractAudioMetadata` | `app/Jobs/ExtractAudioMetadata.php` | Audio-Metadaten |

Konfiguration: `config/media.php` – `ffmpeg_path`, `ffprobe_path`.

**Ingest-spezifisch (eigenständig, nutzt dieselben Binär-Pfade):**

| Komponente | Zweck |
|------------|--------|
| `IngestFfprobeService` | Validierung + Metadaten für `ingest_files` (Video-Stream, Dauer > 0; Audio wird erkannt, aber nicht zwingend als Pflicht abgelehnt) |
| `ValidateIngestFileJob` | Queue-Validierung nach Import |
| `IngestRenderService` | Normalisierung pro Clip + Concat → MP4 (H.264, AAC, yuv420p, faststart, konfigurierbare Bitrate/FPS/Auflösung) |
| `ProcessIngestRenderJob` | Render → S3 → DB → Poster/Metadaten-Jobs für **finales** `NewsItemMedia` |

---

## 5. Outbound FTP/SFTP (nicht für Ingest verwenden)

| Komponente | Rolle |
|------------|--------|
| `UploadMediaToDestinationJob` | **Outbound:** lädt **bereits produktive** `NewsItemMedia` zu `DeliveryDestination` (FTP/FTPS/SFTP, u. a. phpseclib) |
| `DeliveryDestination`, `DeliveryRun`, `DeliveryRunItem` | Zieldefinitionen und Versand-Tracking |
| `DeliveryDestinationController` | Admin: CRUD/Test/Upload für Destinations (`routes/web.php` unter `/products/.../destinations`, `/destinations/...`) |
| `NewsDeliveryController` | Meldungsbezogener Versand (z. B. `send-ftp`) |

Das Ingest-Modul **importiert nicht** über diese Klassen und **ändert** die Outbound-Pipeline **nicht**. Eingehendes Rohmaterial läuft ausschließlich über Dateisystem-Inbox + `ingest:scan-inbox`.

---

## 6. Admin-Bereiche und sinnvolle Erweiterung

- **Bestehend:** News-Bearbeitung inkl. Medien-Tabs (`NewsItemController`), Video/Audio-Übersichten, Settings, Deliveries.
- **Ingest:** eigener Block **ohne** Vermischung mit Delivery:
  - Navigation: `resources/views/layouts/admin.blade.php` → Link „Ingest“
  - Routen (Präfix `admin`, Middleware wie übriges Admin): siehe `routes/web.php` – `VideoIngestController`
  - Views: `resources/views/admin/ingest/{index,show,news-workspace}.blade.php`

---

## Wiederverwendbare Bausteine (bestätigt)

- `MediaStorage::generateMediaPath` / `putFromLocalFile` für finales Video.
- `ExtractVideoMetadata` und `GenerateVideoPoster` nach erfolgreicher Finalisierung.
- ffprobe/ffmpeg-Pfade aus `config/media.php`.
- Keine Anbindung von Rohmaterial an `news_item_media.path` vor dem erfolgreichen Render- und Upload-Schritt.

---

## Riskante Kopplungen (weiterhin vermeiden)

- Outbound-`DeliveryDestination` / `UploadMediaToDestinationJob` nicht als Ingest-Transport missbrauchen.
- Rohclips nicht unter `news-media/…` ablegen, bevor die Sendefassung steht.
- `ProcessMediaPipeline` nicht für Roh-Ingest erzwingen (eigene Validierungs- und Renderpfade halten die Pipeline beherrschbar).

---

## Statusmodell: Spezifikation vs. Implementierung

Die Spezifikation nannte viele feingranulare Zustände. Im Code ist ein **kompaktes** Modell umgesetzt (Datei- und Job-Status getrennt):

**`IngestFile`:** `imported` → `validating` → `validated` | `rejected`; nach Redaktion `assigned`; nach erfolgreichem Schnitt `used_in_render`; `failed` bei Bedarf.

**`IngestRenderJob`:** `queued` → `rendering` → `uploading` → `completed` | `failed`.

Das deckt den Ablauf ab, ersetzt aber nicht jedes einzelne Spez-Label (z. B. kein separates `uploaded_to_s3` auf Dateiebene – der Schritt steckt im Render-Job-Status und im gesetzten `NewsItemMedia`).

---

## Empfohlene / umgesetzte neue Komponenten (Minimal-invasiv)

| Komponente | Pfad / Signatur |
|------------|-----------------|
| Konfiguration | `config/ingest.php`, ENV-Präfix `INGEST_*` |
| Scan | `app/Console/Commands/IngestScanInboxCommand` → `ingest:scan-inbox` |
| Cleanup | `app/Console/Commands/IngestCleanupCommand` → `ingest:cleanup` |
| Services | `IngestScanService`, `IngestDirectoryService`, `IngestFfprobeService`, `IngestRenderService` |
| Jobs | `ValidateIngestFileJob`, `ProcessIngestRenderJob` |
| Admin | `VideoIngestController` |
| Tests | `tests/Unit/IngestDirectoryServiceTest.php`, `tests/Feature/Admin/VideoIngestAdminTest.php` |

**Optionaler Ausbau (nicht zwingend):** feinere Status-Enum auf `IngestFile`, Pflicht-Audio in der Validierung, zusätzliche Feature-Tests für Scan/Render (ohne echtes ffmpeg in CI: Mocks), explizite Ablage rejected Dateien unter `INGEST_FAILED_PATH` mit `rename`.

---

## Kurzreferenz Suchbegriffe → Code

| Begriff | Wo |
|---------|-----|
| `MediaStorage` | `app/Services/MediaStorage.php` |
| `NewsItem` / `NewsItemMedia` | `app/Models/` |
| `UploadMediaToDestinationJob` | `app/Jobs/UploadMediaToDestinationJob.php` |
| `GenerateVideoPoster` / `ExtractVideoMetadata` | `app/Jobs/` |
| `ProcessMediaPipeline` | `app/Jobs/ProcessMediaPipeline.php` |
| `DeliveryDestination` | `app/Models/`, `DeliveryDestinationController` |
| Ingest | `app/Services/Ingest/*`, `app/Models/Ingest*.php`, `app/Jobs/ValidateIngestFileJob.php`, `ProcessIngestRenderJob.php` |

---

*Letzte inhaltliche Ergänzung: Abgleich Codebasis + Ingest-Implementierung und Status-Mapping.*
