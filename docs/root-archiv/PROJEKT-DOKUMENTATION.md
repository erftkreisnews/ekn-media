# Projektdokumentation: Erftkreis News Media (Laravel 12)

**Stand:** 28.02.2026

Diese Datei dokumentiert **was**, **wie** und **wann** im Projekt geändert bzw. eingebaut wurde sowie den **aktuellen Stand**. Sie wird bei größeren Änderungen ergänzt.

---

## 1. Aktueller Stand (Kurzüberblick)

- **Projekt:** Laravel-12-Anwendung für Erftkreis News Media (Admin für News mit Bildern, Video, Audio).
- **Tech:** PHP 8.x, Laravel 12, Tailwind, Vite, Spatie Permission, Storage auf `public` (z. B. `storage/app/public` → `public/storage`).
- **Kernfunktionen:** News-Artikel (CRUD), Medien pro Artikel (Bilder/Video/Audio), Sichtbarkeit/Versand/Teaser, Bildmetadaten (Titel, Fotograf, Bildunterschrift, Schlagwörter, Beschreibung), 6-stellige Media-Dateinamen, optional „unkentlich“-Markierung.
- **Weitere Docs im Projekt:**
  - `EINBAU-ANLEITUNG-FORMULARFELDER.md` – Anleitung zum Einbau von Formularfeldern aus „lave hetzner neu“.
  - `UEBERNAHME-LAVE-HETZNER-NEU.md` – Prüfbericht, was aus dem Referenzprojekt übernommen werden kann.

---

## 2. Änderungshistorie (was wann umgesetzt wurde)

### Datenbank & Modelle

| Wann (Migration) | Was |
|------------------|-----|
| 27.02.2026 | **News-Artikel:** Tabelle `news_items` (u. a. title, teaser, body, published_at, author_id, status). |
| 27.02.2026 | **Titel-Länge:** `news_items.title` auf größere Länge angepasst. |
| 27.02.2026 | **Standort:** Standortfelder für News (`country`, `state`, `city`, `street` usw.). |
| 27.02.2026 | **Flags:** Zusätzliche Flags für News-Artikel (z. B. Breaking, Video-Upload, LiveU). |
| 27.02.2026 | **News-Medien:** Tabelle `news_item_media` (news_item_id, type, path, original_name, sort_order). |
| 28.02.2026 | **Keywords:** Schlagwörter für News-Artikel. |
| 28.02.2026 | **Unterzeile, Embargo, Autor-Credit:** Felder subheadline, embargo_at, author_credit für News. |
| 28.02.2026 | **Medien-Sichtbarkeit/Versand/Teaser:** is_visible, versand, is_teaser in `news_item_media`. |
| 28.02.2026 | **Bildunterschrift:** caption in `news_item_media`. |
| 28.02.2026 | **Unkenntlich:** is_unkentlich in `news_item_media`. |
| 28.02.2026 | **Bildinfos:** image_title, photographer, media_keywords, description in `news_item_media` (inkl. Länge 1800 für Bildunterschrift). |
| (vorher) | **Library Images:** Tabelle `library_images` (falls genutzt). |
| (vorher) | **Cache, Users, Jobs, Permissions:** Standard-Laravel + Spatie Permission. |

### Anwendungscode & Verhalten

| Wann / Wo | Was |
|-----------|-----|
| **NewsItemController** | Neue Uploads: Medien werden unter **6-stelligem Dateinamen** (Media-ID) gespeichert, z. B. `000042.jpg`; Pfad-Schema: `news-media/{news_item_id}/{type}/{id}.{ext}`. Original-Dateiname in `original_name`. |
| **NewsItemController** | Löschen/Ersetzen von Medien; Teaser setzen; „unkentlich“ umschalten; Metadaten aus Bilddatei auslesen (ImageMetadataReader). |
| **NewsItemMedia (Model)** | Attribute: path, original_name, caption, image_title, photographer, media_keywords, description, is_visible, versand, is_teaser, is_unkentlich; Accessor `url`, `file_size_kb`. |
| **Admin-Views (News)** | Formularfelder für Titel, Fotograf, Bildunterschrift (mit Zeichenzähler 1800), Schlagwörter, Beschreibung; Vorbelegung aus Bild-Metadaten. |
| **28.02.2026** | **Artisan-Befehl** `news:media-rename-to-numbered`: Bestehende Medien (z. B. alte Dateinamen mit Timestamp) auf das 6-stellige Schema umgestellt; 3 bereits vorhandene Medien wurden umbenannt (000001.jpg, 000002.jpg, 000003.jpg) und DB-Pfade angepasst. |

### Artisan-Befehle

| Befehl | Zweck |
|--------|------|
| `php artisan news:media-rename-to-numbered` | Einmalig bzw. bei Bedarf: Alle News-Medien, die noch nicht im Format `000001.jpg` usw. liegen, werden umbenannt und die Tabelle `news_item_media.path` wird aktualisiert. Bereits nummerierte werden übersprungen. |

---

## 3. Geplante Erweiterungen / Offene Punkte

### Kundenverwaltung (Admin)

- **Modell:** Kunden werden zentral in einer **Kundenverwaltung im Admin** angelegt und verwaltet. Ohne aktive Verwaltung durch Redaktion/Admin macht offene Selbstregistrierung wenig Sinn.
- **Geplante Stammdaten pro Kunde (noch zu erarbeiten):**
  - Medienunternehmen
  - Redaktion
  - Weitere fachliche Felder je nach Anforderung
- **Umsetzung:** Kundenverwaltung mit erweiterten Stammdaten (Medienunternehmen, Redaktion etc.) ist noch zu konzipieren und umzusetzen. Die öffentliche Registrierung (`/register`) kann bei Bedarf deaktiviert werden, bis die Admin-Kundenverwaltung und Zugangsvergabe stehen.

---

## 4. Pflege dieser Dokumentation

- Bei **neuen Migrationen** oder **größeren Features**: Eintrag unter „Änderungshistorie“ ergänzen (Datum, Kurzbeschreibung).
- **Neue Markdown-Dokumente** im Projektroot hier unter „Weitere Docs“ verlinken.
- **Stand**-Datum oben anpassen, wenn die Dokumentation geändert wird.

Wenn du möchtest, kann die Historie bei der nächsten Änderung wieder gemeinsam ergänzt werden.
