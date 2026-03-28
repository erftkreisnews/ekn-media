# Jetzt-Stand: Erftkreis News Media (Laravel 12)

**Stand:** März 2026 · Abbild des gesamten Projekts inkl. aller Funktionen und Seiten.

---

## 1. Technik

| Komponente | Technologie |
|------------|-------------|
| Framework | Laravel 12, PHP 8.x |
| Frontend | Vite, Tailwind CSS, Alpine.js |
| Rechte | Spatie Laravel Permission |
| Datenbank | MySQL (Default in `config/database.php`: `env('DB_CONNECTION', 'mysql')`) |
| Storage | `public` Disk → `storage/app/public` (Symlink `public/storage`) |
| CI-Farben | Tailwind: `ekn-900` (#092E48), `ekn-800` (#0A3A5C), `ekn-50` (#F3F8FB) |
| Bild-Previews | Intervention Image (v3), WebP-Preview + optional Thumbnail per Queue-Job |
| Queue | `QUEUE_CONNECTION=database`; Worker nötig für Preview- und Video-Metadaten-Jobs (Supervisor, siehe SETUP-QUEUE.md) |
| Video-Metadaten | ffprobe (ffmpeg); Job **ExtractVideoMetadata** liest width, height, fps, bitrate, codec, field_order, duration und speichert in DB |
| Mail | Bestehender Laravel-Mailer (z. B. MS365); Delivery-Mails über `app/Mail/NewsDeliveryMail.php`, Template `resources/views/emails/news-delivery.blade.php` |

---

## 2. Routen & Seiten

### Öffentlich (ohne Login)

| Route | Methode | Controller/Action | Beschreibung |
|-------|---------|-------------------|--------------|
| `/` | GET | `NewsController@index` | Startseite = Nachrichten-Liste (nur veröffentlichte News, Suche `?q=`, Paginierung) |
| `/news/{slug}` | GET | `NewsController@show` | Einzelartikel (Volltext, Teaser-Bild, Autor) |
| `/robots.txt` | GET | `SitemapController@robots` | Dynamische robots.txt (Sitemap-URLs, AI-Crawler-Regeln) |
| `/sitemap.xml` | GET | `SitemapController@index` | Haupt-Sitemap (Seiten) |
| `/sitemap-news.xml` | GET | `SitemapController@newsSitemap` | Google-News-Sitemap (letzte 48h) |

### Delivery (Token-Seite, signed URLs, ohne Login)

| Route | Methode | Controller/Action | Beschreibung |
|-------|---------|-------------------|--------------|
| `/d/{token}` | GET | `DeliveryController@show` | Medienpaket-Seite (signed, throttle 60/min): Artikel + Confirm Redaktion/Produkt, danach Medienliste mit 10-Min-Download-Links |
| `/d/{token}/confirm` | POST | `DeliveryController@confirm` | Redaktion/Produkt bestätigen (signed, throttle 30/min) |
| `/d/{token}/m/{media}` | GET | `DeliveryController@download` | Einzelmedium herunterladen (signed, throttle 30/min), nur serverseitig |

### Auth (Gast)

| Route | Beschreibung |
|-------|--------------|
| `GET /login` | Anmeldeseite (inkl. „Mit Microsoft anmelden“, klassischer Login wenn `auth_local` aktiv) |
| `POST /login` | Anmeldung ausführen (Lokaler Login) |
| `GET /auth/microsoft/redirect` | Redirect zu Microsoft 365 OAuth |
| `GET /auth/microsoft/callback` | Callback nach Microsoft-Login (Domains über `auth_ms.allowed_domains`) |
| `GET /forgot-password` | Passwort vergessen |
| `POST /forgot-password` | Link anfordern |
| `GET /reset-password/{token}` | Passwort zurücksetzen (Formular) |
| `POST /reset-password` | Neues Passwort speichern |
| `GET /verify-email`, `GET /verify-email/{id}/{hash}` | E-Mail-Verifizierung |
| `GET /confirm-password` | Passwort bestätigen (vor sensiblen Aktionen) |
| *(Registrierung)* | **Deaktiviert** – Kunden werden im Admin angelegt |

### Nach Login (auth)

| Route | Beschreibung |
|-------|--------------|
| `GET /dashboard` | Wenn `access_admin` → Redirect zu `/admin`; sonst Kunden-Dashboard-View |
| `GET/PATCH/DELETE /profile` | Profil bearbeiten, Passwort, Konto löschen |

### Admin (`/admin`, Middleware: auth + permission `access_admin`)

| Route | Controller/Action | Beschreibung |
|-------|--------------------|--------------|
| `GET /admin` | `AdminDashboardController@index` | Admin-Dashboard |
| `GET /admin/news` | `NewsItemController@index` | Nachrichten-Liste (Tabelle, Paginierung) |
| `GET /admin/news/create` | `NewsItemController@create` | Formular: Neue Nachricht anlegen |
| `POST /admin/news` | `NewsItemController@store` | Nachricht speichern, Medien hochladen |
| `GET /admin/news/{newsItem}/edit` | `NewsItemController@edit` | Nachricht bearbeiten (Tabs: Inhalt, Medien, Zeugen, Downloads, Versand, Nutzungen) |
| `PATCH /admin/news/{newsItem}` | `NewsItemController@update` | Nachricht aktualisieren, Medien-Uploads, Teaser setzen, Medien löschen/verknüpfen |
| `DELETE /admin/news/{newsItem}` | `NewsItemController@destroy` | Nachricht löschen |
| `GET /admin/news/{newsItem}/media/{mediaId}/edit` | `NewsItemController@editMedia` | Einzelmedium bearbeiten (Metadaten, Vorbelegung aus EXIF/IPTC) |
| `PATCH /admin/news/{newsItem}/media/{mediaId}` | `NewsItemController@updateMedia` | Metadaten des Mediums speichern, Qualitätsprüfung für Bilder |
| `GET /admin/news/{newsItem}/media/{mediaId}/unkentlich` | `NewsItemController@showUnkenntlichEditor` | Redirect → Media Edit mit Anker `#redaction` (ein Anonymisierungs-System) |
| `POST /admin/news/{newsItem}/media/{mediaId}/unkentlich` | `NewsItemController@applyUnkenntlich` | Legacy: Regionen (Prozent) → Redaction-Boxen, Job ProcessMediaRedaction (Original bleibt erhalten) |
| `POST /admin/news/{newsItem}/media/{mediaId}/unkentlich-aufheben` | `NewsItemController@toggleUnkenntlich` | Flag „unkentlich“ entfernen (Legacy) |
| `PATCH …/media/{mediaId}/redaction` | `NewsItemController@updateRedaction` | Redaction-Boxen/Methode/Disabled speichern, ggf. Job dispatchen |
| `POST …/media/{mediaId}/redaction/run` | `NewsItemController@runRedaction` | ProcessMediaRedaction in Queue stellen |
| `POST …/media/{mediaId}/redaction/run-now` | `NewsItemController@runRedactionNow` | ProcessMediaRedaction sofort (sync) ausführen |
| `DELETE /admin/news/{newsItem}/media/{mediaId}` | `NewsItemController@destroyMedia` | Medium löschen (Datei + DB) |
| `POST /admin/news/{newsItem}/media/{mediaId}/unlink` | `NewsItemController@unlinkMedia` | Nur Verknüpfung entfernen (Datei bleibt) |
| `GET /admin/news/{newsItem}/send` | `Admin\NewsDeliveryController@prepareSend` | Versand vorbereiten |
| `POST /admin/news/{newsItem}/send` | `Admin\NewsDeliveryController@send` | Medienpaket versenden (48h-Link per E-Mail) |
| `GET /admin/news/{newsItem}/send-summary` | `Admin\NewsDeliveryController@summary` | Versand-Zusammenfassung (E-Mail-Text) anzeigen |
| `POST /admin/news/{newsItem}/send-summary` | `Admin\NewsDeliveryController@applySummary` | Zusammenfassung übernehmen |
| `GET /admin/deliveries` | `Admin\DeliveryController@index` | Übersicht aller Versände, Revoke-Button |
| `GET /admin/deliveries/{delivery}/activity` | `Admin\DeliveryController@show` | Aktivitätslog eines Versands |
| `POST /admin/deliveries/{delivery}/revoke` | `Admin\DeliveryController@revoke` | Versand sofort widerrufen |
| `GET /admin/video` | `VideoController@index` | Video-Übersichtsseite |
| `GET /admin/audio` | `AudioController@index` | Audio-Übersichtsseite |
| `GET /admin/settings` | `SettingsController@index` | Einstellungen-Übersicht |
| `GET /admin/settings/seo` | `SettingsController@seo` | SEO-Verwaltung (Sitemaps, robots, Hinweise) |
| `GET /admin/settings/backup` | `SettingsController@backup` | Backup-Einstellungen |
| `POST /admin/settings/backup/run` | `SettingsController@runBackup` | Backup ausführen |
| `GET /admin/settings/ai` | `SettingsController@ai` | KI/ChatGPT-Einstellungen |
| `POST /admin/settings/ai/test` | `SettingsController@testAi` | KI-Test |
| `GET /admin/settings/jobs` | `SettingsController@jobs` | Jobs/Warteschlange (Ampel, manueller Start) |
| `POST /admin/settings/jobs/run` | `SettingsController@runJobs` | Jobs manuell starten |
| `GET /admin/backoffice` | `BackofficeController@index` | Backoffice-Übersicht |
| `GET /admin/backoffice/users` | `AdminUserController@index` | Benutzer- & Rollenverwaltung |
| `GET /admin/backoffice/users/{user}/edit` | `AdminUserController@edit` | Benutzer bearbeiten |
| `GET /admin/customers` | `CustomerController@index` | Kundenliste |
| … (customers CRUD, products, contacts, destinations, billing, usage) | siehe `routes/web.php` | Kundenverwaltung, Abrechnung, Nutzungsberichte |

### Kundenbereich (`/kunden`, Middleware: auth + permission `view_customer_area`)

| Route | Beschreibung |
|-------|--------------|
| `GET /kunden` | `CustomerDashboardController@index` – Kunden-Dashboard |

---

## 3. Modelle & Datenbank

### NewsItem

| Feld | Typ / Hinweis |
|------|----------------|
| title, slug | Titel; Slug auto aus Titel, eindeutig |
| teaser, body, subheadline | Text; body HTML |
| keywords | Schlagwörter |
| region, country, federal_state, city, street | Standort |
| is_breaking, planned_video_upload, liveu_on_site | Boolean-Flags |
| status | draft, review, published, archived |
| published_at, embargo_at | Datum/Zeit |
| author_id, author_credit | Autor (User) bzw. Freitext-Credit |

**Scope:** `publicVisible()` – status=published, published_at ≤ jetzt, **embargo_at** null oder ≤ jetzt (für Portal, Sitemap, Listen).  
**Relations:** `author` (User), `media`, `images`, `videos`, `audios`, `deliveries`.  
**Accessor:** `teaser_image` (erstes Bild mit `is_teaser`, sonst erstes Bild), `location_label` (formatierter Standort).

### NewsItemMedia (Tabelle `news_item_media`)

| Feld | Typ / Hinweis |
|------|----------------|
| news_item_id, type | type: image, video, audio |
| path | Speicherpfad, z. B. `news-media/1/image/000001.jpg` (Original, für Versand) |
| preview_path | Optional: WebP-Preview, z. B. `news-media/1/image/preview/000001.webp` |
| original_name | Original-Dateiname |
| caption | Bildunterschrift (max. 1800 Zeichen) |
| image_title, photographer, media_keywords, description | Bildmetadaten |
| sort_order | Reihenfolge |
| is_visible, versand, is_teaser | Boolean: sichtbar, zum Versand, als Teaser |
| is_unkentlich | Boolean: Anzeige „unkentlich“ (Legacy bzw. nach Redaction gesetzt) |
| original_path, redacted_path | Bild: Original archiviert unter `media/original/`, Redaktion unter `media/redacted/` |
| redaction_status | null = Legacy (path ausliefern), pending, done, failed, disabled |
| redaction_method, redaction_boxes | blur \| black_box; JSON [[x1,y1,x2,y2], …] (Pixel) |
| redacted_at, redaction_error | Zeitpunkt letzte Redaction; Fehlermeldung bei failed |
| quality_status, quality_notes | Ergebnis Qualitätsprüfung (ok, warning, fail) |
| **width, height** | Video: Auflösung (aus ffprobe) |
| **fps** | Video: Frames pro Sekunde (decimal) |
| **bitrate_bps** | Video: Bitrate in Bit/s (bigint) |
| **codec, field_order** | Video: Codec-Name, progressive/interlaced |
| **duration_s** | Video: Dauer in Sekunden (decimal) |

**Accessors:** `url` (Original-URL inkl. Cache-Buster), `preview_url` (Preview-URL falls `preview_path` gesetzt und nicht is_unkentlich, sonst null), **`public_path`** / **`public_url`** (nur Bild: bei redaction_status=done die redigierte Version, sonst bei Legacy/disabled path; bei pending/failed null – Fail-safe), `redacted_url` (Admin-Vorschau), `display_name`, `file_size_kb`, `quality_badge_class`, **`video_metazeile`** (nur Video: z. B. „1920x1080p \| 5.2 Mbit/s \| 25 fps“, null wenn noch nicht analysiert).  
**Methoden:** `isImage()`, `isVideo()`, `isAudio()`, `isRedactionEnabled()`, `isSafeForPublic()`.  
**Frontend/Auslieferung:** Galerie/Listing bevorzugt `preview_url ?? url`; **öffentliche Auslieferung nur über `public_path`/`public_url`** (redigierte Version bei done; Original wird nie öffentlich ausgeliefert bei pending/failed).

### Delivery, Organization, Product, DeliveryEvent

- **Delivery** (UUID-PK): `news_item_id`, `recipient_email`, `token` (UUID), `expires_at` (48h), `revoked_at`, `first_opened_at`, `last_access_at`, `confirmed_at`, `organization_id`, `product_id`, `ip_hash`, `ua_hash`, `created_by`. Relations: `newsItem`, `organization`, `product`, `createdByUser`, `events`. Methoden: `isExpired()`, `isRevoked()`, `isValid()`.
- **DeliveryEvent:** `delivery_id`, `event_type` (opened, confirmed, download, revoked), `media_id`, `organization_id`, `product_id`, `ip_hash`, `ua_hash`, `created_at`. Audit-Logging für jeden Zugriff. **DSGVO:** ip_hash/ua_hash = HMAC-SHA256 mit APP_KEY (keyed hash, nicht re-identifizierbar ohne Schlüssel). Aufbewahrungsfristen/Löschkonzept siehe Datenschutz-Dokumentation.
- **Organization:** `id`, `name`; **Product:** `id`, `organization_id`, `name`. Redaktion/Produkt-Pflicht beim Öffnen des Medienpakets (Confirm-Formular).

---

## 4. Funktionen im Detail

### 4.1 Öffentliche Nachrichten (NewsController) & Sichtbarkeit

- **index:**  
  - Nur `status = published`, `published_at` gesetzt und ≤ jetzt; **Embargo:** `embargo_at` null oder ≤ jetzt (Scope `publicVisible()`).  
  - Optional Suche in title, teaser, body (GET `q`).  
  - Paginierung 10 pro Seite, mit `images` vorgeladen.  
  - **Intro-Block (Startseite):** Drei Absätze (dritter persönlich: „Über diese Seite biete ich Redaktionen …“); rechts daneben Porträtfoto (Alexander Franz am Nürburgring), schmale Spalte, `object-top`; Bildunterschrift: „Alexander Franz beim 24-Stunden-Rennen am Nürburgring.“  
  - View: Portal-Layout (EKN-CI) – Toolbar „Aktuelle Nachrichten“ + Ergebnisanzahl; 2-Spalten-Grid: links Feed (Cards mit Teaser-Bild, Meta-Zeile, Foto/Video/Audio-Badges, Titel, Teaser, „Details →“), rechts Sidebar (Filter, 24h Redaktionsdesk, Kundenlogin). Bilder bevorzugt `preview_url` (WebP-Preview), Fallback Original. Pagination unter dem Feed.  
  - **SEO:** Eigenes title/meta_description/canonical, `og:image` = `og-media-index.png` (1200×630), JSON-LD WebSite mit areaServed.

- **show:**  
  - Einzelartikel per `slug`, gleiche Filter (published, published_at, Embargo).  
  - Mit `media`, `author`.  
  - **Layout:** Hintergrund-Wrapper `bg-ekn-50`, Container `max-w-7xl`, 2-Spalten-Grid (8/4): links Artikel, rechts **Sidebar** (Ansprechpartner + Telefon 02236 480 9488, 24h Redaktionsdesk, Kundenlogin „Anmelden“, LIVEU). EB-Team-Block entfernt. Mobile: untereinander.  
  - **Titel:** Zeilenumbruch nach Doppelpunkt nur ab Breakpoint `sm` (Desktop).  
  - **Inhalt:** Blauer Header nur mit Titel (ekn-900). Darunter Teaser-Bild (falls vorhanden), dann **Meta-Block:** links Datum (d.m.Y, H:i Uhr), rechts NEWSID und Ort; Leerzeile; **Dachzeile (Subheadline)** fett; Leerzeile; Body (HTML).  
  - **Nach Body:** Galerie (Bilder mit `versand`) wie zuvor (Thumbnail-Grid, Lightbox).  
  - **Nach Galerie** (nur wenn Bilder): Quelle (Autor aus author_credit/author), Honorarhinweis, rechts in derselben Zeile „← Zurück zur Übersicht“.  
  - Sidebar-Cards: gleiche Optik wie Startseite (rounded-2xl, shadow-sm, ring-1, p-5 sm:p-6).  
  - **SEO:** Eigenes title/meta_description/canonical, og:image (Teaser oder Fallback), JSON-LD NewsArticle mit contentLocation.

### 4.2 Admin: Nachrichten CRUD (NewsItemController)

- **index:** Alle News mit `author`, `images`, `videos`, sortiert nach `updated_at`, 15 pro Seite. Tabelle: Status, Teaserbild, News-ID, Titel, Anzahl Bilder/Videos, Datum/Uhrzeit, Autor, Aktionen (Bearbeiten, Löschen).

- **create/store:**  
  - Formular: Stammdaten der Nachricht.  
  - Beim Speichern: `author_id = auth()->id()`, falls kein `published_at` → jetzt.  
  - Anschließend `processMediaUploads` (Bilder, Videos, Audios aus Request).

- **edit/update:**  
  - Stammdaten aktualisieren.  
  - **Aktion-Dropdown (ein Auswahl-Button):** „Aktion“ öffnet Menü mit: **Änderungen speichern** (CI-Blau dezent), **Speichern & Veröffentlichen** (grün, setzt status=published, published_at), **Speichern & Versenden** (rot, versendet Medienpaket per E-Mail – geht an Kunden), **Zurück zur Übersicht** (grau, Link). Parameter `publish_and_save` beim Submit → Controller setzt Status und Meldung „gespeichert und veröffentlicht“.  
  - `delete_media`: ausgewählte Medien – Datei löschen, Datensatz löschen.  
  - `media`: pro Medium `is_visible`, `versand`, ggf. `caption` aktualisieren.  
  - **Teaser:** Alle Bilder `is_teaser = false`, dann gewähltes Bild (oder erstes) auf `is_teaser = true`.  
  - Erneut `processMediaUploads` für neue Dateien.  
  - **Video-Upload:** Formular `id="newsEditForm"`. Wenn Dateien in `input[type="file"][name^="videos"]` ausgewählt sind, wird Submit per XHR abgefangen: Fortschrittsanzeige (%, MB/s, hochgeladene MB / Gesamt-MB), Spinner, Status-Text („Upload läuft…“, „Fast fertig…“, „Verarbeite…“, „Fertig ✓“), bei fehlendem `lengthComputable` Indeterminate-Animation. Kein Seitenreload bis Upload abgeschlossen.

- **destroy:** NewsItem löschen (inkl. abhängiger Medien-Logik je nach Konfiguration).

### 4.3 Admin: Medien (Bilder, Video, Audio)

- **Hochladen (processMediaUploads):**  
  - Request-Keys: `images`, `videos`, `audios` (einzelne oder mehrere Dateien).  
  - Basis-Pfad: `news-media/{news_item_id}/{type}/`.  
  - Pro Datei: zuerst Media-Datensatz anlegen (path vorerst `.pending`), dann Datei speichern unter **6-stelligem Namen**: `sprintf('%06d', $media->id).'.'.$ext` (z. B. `000042.jpg`).  
  - `original_name` wird gespeichert.  
  - Bei Typ `image`: nach Speichern `MediaQualityCheck::runAndSave($media)`; `preview_path` wird gesetzt (`…/preview/000042.webp`), Job **GenerateNewsMediaPreview** wird gedischt (Queue). Original bleibt unverändert für Versand.  
  - Bei Typ **video**: nach Speichern Job **ExtractVideoMetadata** dispatch – liest per ffprobe width, height, fps, bitrate, codec, field_order, duration und speichert in DB. Erfordert ffmpeg/ffprobe und laufenden Queue-Worker.

- **editMedia (Einzelmedium):**  
  - Nur für dieses Medium: View mit Metadaten-Formular.  
  - Bei Bildern: `ImageMetadataReader::read($path)` für Vorbelegung (EXIF/IPTC).  
  - Galerie-Infos (IDs, URLs, display_name) für Navigation.

- **updateMedia:**  
  - Validierung: image_title, photographer, caption (max 1800), media_keywords, description, is_visible, versand.  
  - Speichern in `news_item_media`.  
  - Bei Bild: erneut `MediaQualityCheck::runAndSave`.

- **destroyMedia:** Datei von Disk löschen, Datensatz löschen, Redirect mit Status.

- **unlinkMedia:** Nur Datensatz löschen, Datei bleibt auf der Disk.

### 4.4 Funktionen zu Bildern (wie bei „Bilder“ umgesetzt)

- **6-stellige Dateinamen:** Alle neuen Uploads als `000001.jpg`, `000002.png` usw. im Ordner `news-media/{id}/image/`. Alte Bestände: Artisan-Befehl `news:media-rename-to-numbered`.

- **Metadaten aus Datei (ImageMetadataReader):**  
  - Liest aus EXIF/IPTC: Titel, Fotograf (By-line), Bildunterschrift (Caption), Schlagwörter, Beschreibung/Headline.  
  - Unterstützte Formate: JPG, JPEG, TIFF.  
  - Wird bei „Medium bearbeiten“ zur Vorbelegung der Felder genutzt.

- **Qualitätsprüfung (MediaQualityCheck):**  
  - Nur für `type = image`.  
  - Lange Kante: Mindestwert 3500 px („Altbestand“), Ziel Presse 4500 px.  
  - Fehlt Fotograf in Metadaten → Hinweis.  
  - Speichert `quality_status` (ok, warning, fail) und `quality_notes` im Medium.  
  - Nach Upload und nach updateMedia ausgeführt.

- **Anonymisierung (ein System: Redaction):**  
  - Nur für Bilder. **Eine Karte** auf der Medien-Bearbeitungsseite: „Bereiche unkenntlich machen (Kennzeichen & Gesichter)“. Link „Anonymisierung“ in der Medienliste führt zu Media Edit mit Anker `#redaction`; GET `/unkentlich` leitet dorthin um.  
  - **Original** bleibt in `media/original/` erhalten; **redigierte Version** in `media/redacted/`; öffentliche Auslieferung nur über `public_path`/`public_url` (bei redaction_status=done). Bei pending/failed wird das Original **nicht** ausgeliefert (Fail-safe).  
  - **Automatisch:** Optional ONNX für Kennzeichen (`REDACTION_MODEL_PATH`), optional ONNX für Gesichter (`REDACTION_FACE_MODEL_PATH`). Boxen werden zusammengeführt (NMS).  
  - **Manuell:** Boxen in Pixel (x1, y1, x2, y2) hinzufügen (z. B. Gesichter, weitere Bereiche). Methode: **blur** (weich) oder **black_box** (schwarze Maske).  
  - Job **ProcessMediaRedaction** (Queue): Python-Skript `ai_worker/scripts/detect_and_redact.py` liest Original, erkennt ggf. Kennzeichen/Gesichter, wendet manuelle Boxen an, schreibt redigierte Datei.  
  - „Keine Anonymisierung“ (z. B. Feuerwehr/Polizei): redaction_status=disabled, dann wird redacted oder path ausgeliefert.  
  - **Legacy:** POST `/unkentlich` (alte Regionen in Prozent) wird in Pixel-Boxen umgerechnet und über die gleiche Redaction-Pipeline ausgeführt (kein Überschreiben mehr). `is_unkentlich` Flag und „Unkenntlich aufheben“ bleiben für Anzeige/Rückwärtskompatibilität.  
  - Doku: **REDACTION-SETUP.md**, Config: **config/redaction.php**.

- **Teaser-Bild:**  
  - Pro NewsItem genau ein Bild mit `is_teaser = true`.  
  - Im Frontend: `teaser_image` (Accessor) für Listen- und Artikelansicht; Wasserzeichen und Overlays nutzen einheitlich **Logo** `public/images/erftkreis-news-logo.png` (weiß/transparent, „ERFTKREIS“ + „Bilder und Videos für Redaktionen“).

- **Preview-WebP (GenerateNewsMediaPreview, Queue-Job):**  
  - Erzeugt aus dem Original eine **Preview** unter `news-media/{id}/image/preview/{name}.webp`: max. 1600 px Breite, WebP-Qualität 80, Wasserzeichen ~10 % Bildbreite, Opacity 15. Verzeichnis wird per `makeDirectory` angelegt.  
  - Optional: **Thumbnail** unter `news-media/{id}/image/thumb/{name}.webp`: max. 480 px, Qualität 60, Wasserzeichen Opacity 18.  
  - Implementierung: Intervention Image (v3), GD-Treiber. Job wird beim Bild-Upload gedischt; für sofortige Erzeugung z. B. `dispatchSync` oder Queue-Worker (`php artisan queue:work`) ausführen.

- **Anzeige im Frontend:**  
  - Liste: Feed-Cards mit Teaser-Bild (bevorzugt `preview_url`), Seitenverhältnis 16:10, Logo-Wasserzeichen dezent.  
  - Artikel: Teaser-Bild mit Wasserzeichen; Body; Galerie (Bilder mit `versand`) mit Preview-URL, Lightbox (Alpine).
  - Versand/Export: immer Original (`path`/`url`), nie Preview.

### 4.5 Video & Audio (Admin)

- **VideoController@index**, **AudioController@index:** Nur Auslieferung der jeweiligen View (admin/video/index, admin/audio/index). Keine eigenen CRUD- oder Medienlisten im Scope dieses Dokuments; Medien hängen an NewsItems (news_item_media mit type video/audio).  
- **News Edit – Video-Tab:** Liste der Videos der Meldung (`$newsItem->videos`): pro Video `<video controls>` mit `url`, Dateiname (original_name/display_name), **Metazeile** aus DB (`video_metazeile`: z. B. „1920x1080p \| 5.2 Mbit/s \| 25 fps“). Sind Metadaten noch nicht ausgelesen → „Analysiere…“. Download, Verkn. löschen, Löschen wie zuvor.  
- **Video-Validierung:** Upload max. 2 GB pro Datei (max:2097152 KB) in StoreNewsItemRequest und UpdateNewsItemRequest; Mimes: mp4, mov, webm, avi.

### 4.6 High-Security Medienauslieferung (Delivery)

- **Ablauf:** Admin klickt „Speichern & Versenden“ (oder im Aktion-Dropdown) → System erstellt Delivery (Token, 48h Laufzeit), sendet E-Mail mit signiertem Link; Empfänger öffnet `/d/{token}`, bestätigt Redaktion/Produkt, kann Medien mit 10-Min-Download-Links herunterladen.  
- **Mail:** `NewsDeliveryMail` (Subject: „EKN Medienpaket: {Titel} (48h)“), View `resources/views/emails/news-delivery.blade.php` (Artikel-Infos, Button „Medienpaket öffnen“, Gültig bis, Kontaktblock). Nutzt bestehenden Mailer (z. B. MS365). Empfänger: `config('newsdesk.delivery_recipient')` bzw. ENV `DELIVERY_RECIPIENT`.  
- **Token-Seite:** `DeliveryController@show` – prüft nicht revoked/nicht abgelaufen, setzt first_opened_at/last_access_at, loggt Event „opened“. Bei unbestätigt: Formular Organization + Product (Alpine-Dropdown, optional); danach Medienliste (nur `versand=true`) mit temporären Signed-URLs (10 Min) für Download. View: `resources/views/deliveries/show.blade.php` – Styling an Frontend angeglichen, Redaktionsdesk 24h, 02236 480 9488; Titel mit Zeilenumbruch nach Doppelpunkt (ab sm); EB-Team-Kasten entfernt.  
- **confirm:** Validiert organization_id/product_id, setzt confirmed_at, loggt „confirmed“, Redirect show.  
- **download:** Nur bei gültiger Delivery und confirmed; Medium muss zum NewsItem gehören und versand=true; bei Bildern wird die **redigierte Version** (`public_path`) ausgeliefert, bei Video/Audio `path`; Auslieferung per `response()->download()` aus Storage (kein öffentlicher Link); Event „download“ mit media_id, org/product, ip_hash, ua_hash.  
- **Admin:** `Admin\NewsDeliveryController@send` erstellt Delivery, generiert 48h-signed URL, versendet Mail. `Admin\DeliveryController@index` listet Deliveries, `revoke` setzt revoked_at und loggt „revoked“.  
- **Security:** 48h Entry + 10-Min-Download-URLs (signed), Revoke, Audit in delivery_events, Rate-Limits (show 60/min, confirm/download 30/min). **CSRF:** Route `POST /d/{token}/confirm` ist von CSRF-Prüfung ausgenommen (`bootstrap/app.php`), da externe Empfänger (E-Mail-Link) oft keine Session haben; Schutz durch **signed URL** + Throttle. **IP/UA:** HMAC-SHA256 mit APP_KEY (keyed hash, DSGVO-konform); Aufbewahrungsfristen/Löschkonzept in Datenschutz-Dokumentation definieren. Details: **DELIVERY-SYSTEM.md**.

### 4.7 Kundenbereich

- **CustomerDashboardController@index:** Liefert View `customer.dashboard` (Layout frontend). Inhalt nach aktuellem Stand: Kunden-Dashboard ohne erweiterte Stammdaten.

### 4.8 Auth & Profil

- **Login:** Standard-Laravel (POST /login) plus **Microsoft 365** (Socialite): `GET /auth/microsoft/redirect`, `GET /auth/microsoft/callback`. Erlaubte Domains über `config('auth_ms.allowed_domains')`. Lokaler Login optional über `auth_local` (ENV). Login-Seite: Button „Mit Microsoft anmelden“.  
- Passwort vergessen, Reset, E-Mail-Verifizierung, Passwort bestätigen: Standard-Laravel mit Views unter `auth/`.  
- Nach Login: bei `access_admin` Redirect auf `/admin`, sonst Dashboard-View.  
- Profil: Bearbeiten (Name, E-Mail), Passwort ändern, Konto löschen (ProfileController, Layout frontend).

### 4.9 SEO (Sitemaps, robots, Meta, Open Graph)

- **SitemapController:** `/robots.txt` (dynamisch: Sitemap-URLs + Regeln für AI-Crawler z. B. GPTBot, Google-Extended, Claude-Web), `/sitemap.xml` (Haupt-Sitemap), `/sitemap-news.xml` (Google-News-Format, letzte 48h).  
- **X-Robots-Tag (Middleware):** Öffentliche Seiten `index, follow`; Admin, Login, Dashboard, `/d/…` etc. `noindex`.  
- **Layout frontend:** Meta description, canonical, Open Graph (og:title, og:description, og:image, og:url, og:type), Twitter Card; `@stack('head_jsonld')` für JSON-LD.  
- **News index/show:** Eigene title, meta_description, canonical (`request()->url()`), og:image (Startseite: `og-media-index.png`), JSON-LD (WebSite mit areaServed bzw. NewsArticle mit contentLocation). Locale default `de` (config/app.php).  
- **Admin:** `/admin/settings/seo` – Links zu Sitemaps, Hinweise (inkl. GZIP: siehe `app/seo/GZIP-HINWEIS.md`).

---

## 5. Services & Jobs

| Service/Job | Zweck |
|-------------|--------|
| **ImageMetadataReader** | Liest aus Bilddateien (EXIF/IPTC): Titel, Fotograf, Caption, Keywords, Beschreibung. |
| **MediaQualityCheck** | Prüft Bilder (lange Kante, Fotograf), setzt quality_status und quality_notes. |
| **ProcessMediaRedaction** (Job, ShouldQueue) | Liest Original aus `media/original/`, ruft Python-Skript `detect_and_redact.py` auf (optional Kennzeichen- + Gesichter-ONNX, manuelle Boxen), schreibt redigierte Datei nach `media/redacted/`, setzt redaction_status/done/failed. Config: `config/redaction.php`, ENV `REDACTION_MODEL_PATH`, `REDACTION_FACE_MODEL_PATH`. |
| **ImagePixelationService** | Legacy: blur/pixelate auf Bildbereiche (wird von der Admin-Anonymisierung nicht mehr genutzt; Anonymisierung läuft über Redaction-Pipeline). |
| **GenerateNewsMediaPreview** (Job, ShouldQueue) | Erzeugt WebP-Preview (max 1600 px, Quality 80) und optional Thumbnail (max 480 px, Quality 60) mit Wasserzeichen; speichert unter `preview/` bzw. `thumb/`. Nutzt Intervention Image. |
| **ExtractVideoMetadata** (Job, ShouldQueue) | Liest per ffprobe (ffmpeg) aus Video: width, height, avg_frame_rate/r_frame_rate, bit_rate, codec_name, field_order, format.duration/bit_rate; speichert in news_item_media (width, height, fps, bitrate_bps, codec, field_order, duration_s). Wird nach Video-Upload gedischt. |

---

## 6. Artisan-Befehle & Queue

| Befehl | Zweck |
|--------|--------|
| `php artisan news:media-rename-to-numbered` | Bestehende News-Medien auf 6-stelliges Dateinamen-Schema umstellen (Pfad in DB anpassen); bereits nummerierte werden übersprungen. Definiert in `routes/console.php`. |
| `php artisan storage:link` | Symlink `public/storage` → `storage/app/public` (für Auslieferung von Uploads und Previews). |
| `php artisan queue:work` | Queue-Worker starten; notwendig für **GenerateNewsMediaPreview** (Bilder), **ExtractVideoMetadata** (Videos) und **ProcessMediaRedaction** (Anonymisierung). Ohne Worker bleiben Previews, Video-Metadaten und Redaction aus. Dauerhaft z. B. mit Supervisor (Konfiguration: `laravel-worker.conf`, Anleitung: **SETUP-QUEUE.md**). |
| `php artisan queue:failed` | Fehlgeschlagene Jobs anzeigen. |
| `php artisan optimize:clear` | Config-, Route-, View-, Cache leeren (z. B. nach Deployment). |

**Server-Anforderungen für Video-Metadaten:** ffmpeg (ffprobe) installieren (`apt install ffmpeg`). Supervisor-Einrichtung und Backfill bestehender Videos siehe **SETUP-QUEUE.md**.

---

## 7. Rechte (Permissions)

- **access_admin:** Zugriff auf `/admin` (Dashboard, News, Video, Audio).  
- **view_customer_area:** Zugriff auf `/kunden`.  
- Vergabe typisch über Spatie Permission (Rollen/Permissions in DB); genaue Seeder/UI pro Projekt prüfen.

---

## 8. Layouts & wichtige Views

| Layout | Verwendung |
|--------|------------|
| **frontend** | Startseite, News-Liste, Artikel-Detail, Kunden-Dashboard, Profil. Header #092E48 (ekn-900), Logo `images/erftkreis-news-logo.png`, Favicon `favicon.png` (32×32), Footer Impressum/Datenschutz, **Cookie-Hinweis** (Komponente), Link „Cookie-Hinweis erneut anzeigen“. |
| **admin** | Alle Admin-Seiten. Header mit gleichem Logo; Mobile: Hamburger-Menü (Overlay); Suchleiste auf news/index ausgeblendet, wenn Menü offen. |
| **guest** | Login, Passwort vergessen, Reset, Verify, Confirm. Logo + Favicon wie frontend, Cookie-Hinweis + „erneut anzeigen“. |
| **app-bootstrap** | Alternative Layout-Variante (Tailwind), derzeit von keiner Route genutzt. |

**Cookie-Hinweis:** Komponente `components/cookie-notice.blade.php` – Text + Link Datenschutz + Button „Verstanden“ (Speicherung in localStorage). **Platzierung:** am Seitenende unter dem Footer (nicht fixiert), damit auf Mobil lesbar. Footer-Link „Cookie-Hinweis erneut anzeigen“ löscht localStorage und lädt die Seite neu.

**Logo:** Systemweit eine Datei: `public/images/erftkreis-news-logo.png` (Header frontend/admin/guest, Wasserzeichen in Previews/Teaser-Overlays, OG-Fallback).  

Wichtige Views (Auswahl):  
`news/index`, `news/show`; `admin/dashboard`, `admin/news/index`, `create`, `edit` (inkl. Aktion-Dropdown), `media/edit` (inkl. Karte „Bereiche unkenntlich machen“ #redaction), `media/unkentlich` (GET leitet auf edit#redaction um); `admin/video/index`, `admin/audio/index`; `admin/deliveries/index`, `admin/deliveries/show` (Aktivität); `admin/settings/seo`, `admin/settings/backup`, `admin/settings/ai`, `admin/settings/jobs`; `admin/backoffice/index`, `admin/backoffice/users`; `deliveries/show` (Token-Seite); `emails/news-delivery` (Mail-Template); `customer/dashboard`; `auth/*`; `profile/edit`; `dashboard` (für eingeloggte User ohne Admin/Kunden-Permission).  
`welcome.blade.php` wird nicht mehr geroutet (Startseite = News-Liste).

---

## 9. Konfiguration (Tailwind, Vite, Alpine, Newsdesk)

- **config/newsdesk.php:** `delivery_recipient` (Default `afranz@erftkreis-news.de`, optional ENV `DELIVERY_RECIPIENT`) für den E-Mail-Versand des Medienpakets.  
- **config/auth_ms.php** (Microsoft Login): `allowed_domains` für OAuth-Callback; **auth_local** (ENV): ob klassischer E-Mail/Passwort-Login erlaubt ist.

- **tailwind.config.js:**  
  - `content`: `./resources/**/*.blade.php`, `./resources/**/*.js`, `./resources/**/*.vue`, Laravel-Pagination, `storage/framework/views`.  
  - `theme.extend.colors.ekn`: 900, 800, 50 (EKN-CI).  
- News-Liste und Artikel nutzen EKN-Klassen (bg-ekn-50, bg-ekn-900, hover:bg-ekn-800, text-ekn-900).  
- **Vite:** `@vite(['resources/css/app.css', 'resources/js/app.js'])` im Layout frontend; Production: `npm run build`, Auslieferung über `public/build/`.  
- **Alpine.js:** In `resources/js/app.js` importiert und nach `DOMContentLoaded` gestartet; wird für Lightbox und Mobile-Menü genutzt.

---

## 10. Offen / Geplant (laut Projekt)

- **Kundenverwaltung im Admin:** Umgesetzt (Kunden, Produkte, Kontakte, Versandziele, Abrechnung, Nutzungsberichte unter `/admin/customers`, `/admin/backoffice`).  
- Öffentliche Registrierung bleibt deaktiviert; Kunden werden im Admin angelegt.

---

## 11. Dateien-Übersicht (wichtigste)

| Zweck | Dateien |
|--------|---------|
| Routen | `routes/web.php`, `routes/auth.php`, `routes/console.php` |
| Öffentliche News | `app/Http/Controllers/NewsController.php`, `resources/views/news/index.blade.php`, `resources/views/news/show.blade.php` (inkl. Intro-Block, Sidebar, Galerie + Lightbox, Meta/Quelle/Honorar, SEO/OG/JSON-LD) |
| SEO | `app/Http/Controllers/SitemapController.php` (robots, sitemap, sitemap-news); `app/Http/Middleware/XRobotsTag.php`; Meta/OG/JSON-LD in `layouts/frontend.blade.php` und news views; `resources/views/admin/settings/seo.blade.php`; `app/seo/GZIP-HINWEIS.md` |
| Admin News & Medien | `app/Http/Controllers/Admin/NewsItemController.php`, `resources/views/admin/news/*` (Edit inkl. **Aktion-Dropdown** Speichern/Veröffentlichen/Versenden/Zurück, Video-Upload mit XHR-Fortschritt, Video-Tab mit `<video controls>` und Metazeile, send-summary, media ai-status/request-ai) |
| Delivery-System | `app/Http/Controllers/DeliveryController.php`, `Admin/NewsDeliveryController.php`, `Admin/DeliveryController.php`; `app/Models/Delivery.php`, `DeliveryEvent.php`, `Organization.php`, `Product.php`; `app/Mail/NewsDeliveryMail.php`; `resources/views/emails/news-delivery.blade.php`, `resources/views/deliveries/show.blade.php`, `admin/deliveries/index.blade.php`; `config/newsdesk.php`. Details: **DELIVERY-SYSTEM.md** |
| Preview-Generierung | `app/Jobs/GenerateNewsMediaPreview.php` (Intervention Image) |
| Anonymisierung (Redaction) | `app/Jobs/ProcessMediaRedaction.php`; `config/redaction.php` (model_path, face_model_path, method, blur_strength); `ai_worker/scripts/detect_and_redact.py` (ONNX Kennzeichen + optional Gesichter, manuelle Boxen); Doku **REDACTION-SETUP.md** |
| Video-Metadaten | `app/Jobs/ExtractVideoMetadata.php` (ffprobe); Migration `*_add_video_metadata_to_news_item_media.php` |
| Queue-Worker | `laravel-worker.conf` (Supervisor-Beispiel), `SETUP-QUEUE.md` (ffmpeg, Supervisor, Backfill) |
| Admin Video/Audio | `app/Http/Controllers/Admin/VideoController.php`, `AudioController.php` |
| Auth | `app/Http/Controllers/MicrosoftAuthController.php` (Socialite); config `auth_ms` (allowed_domains), `auth_local` |
| Backoffice/Einstellungen | `app/Http/Controllers/Admin/BackofficeController.php`, `AdminUserController.php`, `SettingsController.php` (seo, backup, ai, jobs) |
| Modelle | `app/Models/NewsItem.php`, `app/Models/NewsItemMedia.php` (inkl. video_metazeile), `Delivery.php`, `DeliveryEvent.php`, `Organization.php`, `Product.php` |
| Services | `app/Services/ImageMetadataReader.php`, `MediaQualityCheck.php`, `ImagePixelationService.php` (Legacy; Anonymisierung nutzt Redaction-Pipeline) |
| Layouts / UI | `resources/views/layouts/frontend.blade.php`, `admin.blade.php` (Nav inkl. „Versand“, Mobile Hamburger), `guest.blade.php`; `resources/views/components/cookie-notice.blade.php`; Logo `public/images/erftkreis-news-logo.png`, Favicon `public/favicon.png`, OG-Startseite `public/images/og-media-index.png` |
| CI/Styles | `tailwind.config.js`, `resources/css/app.css` |
| Doku | `PROJEKT-DOKUMENTATION.md`, `JETZT-STAND.md` (diese Datei), `SETUP-QUEUE.md`, `DELIVERY-SYSTEM.md`, `REDACTION-SETUP.md` |

---

*Dieses Dokument bildet den aktuellen Funktions- und Seitenumfang des Projekts ab. Bei Änderungen am System sollte es angepasst werden.*
