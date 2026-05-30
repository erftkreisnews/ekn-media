# Koelnimage Galerie-Blueprint (SpeedShot-Style)

Dieses Blueprint ist auf den aktuellen Projektstand zugeschnitten:

- `NewsController@show` rendert fuer Koelnimage bereits `resources/views/koelnimage/news-show.blade.php`.
- Bilder liegen bereits als `NewsItemMedia` vor (`type=image`, `caption`, `media_keywords`, `capture_time`, `width`, `height`, `preview_url`, `public_url`).
- Die bestehende Galerie hat schon Grid + Lightbox, aber noch keine echte SpeedShot-Filter-/Hub-Logik.

## Zielbild

Die Galerie auf einer News-Seite soll sich wie ein "Hub" anfuehlen:

1. **Toolbar** mit Suche, Sortierung, Min-Rating, Zeitbereich, Pro-Seite.
2. **Layout-Umschaltung**: Grid, Masonry, Vollbild.
3. **Paginierte/inkrementelle Liste** mit vielen Bildern.
4. **Detailpanel/Lightbox** mit IDs, Metadaten, Keywords, Navigation.
5. **Merken/Sammlung/Warenkorb** (zunaechst als Session-MVP, spaeter persistent pro User).

---

## Phase 1 (MVP, 2-4 Tage)

### 1) API fuer Galerie-Daten am News-Artikel

#### Route (Brand Koelnimage)

In `routes/brand_koelnimage.php`:

- `GET /news/{slug}/gallery/photos` -> JSON-Feed fuer die Galerie.

Beispiel:

```php
Route::get('/news/{slug}/gallery/photos', [\App\Http\Controllers\KoelnImage\NewsGalleryController::class, 'index'])
    ->name('koelnimage.news.gallery.photos');
```

#### Controller

Neue Datei: `app/Http/Controllers/KoelnImage/NewsGalleryController.php`

Verantwortung:

- NewsItem per `slug` laden (inkl. Brand-Schutz analog `NewsController`).
- Nur sichtbare Bilder ausgeben:
  - `type = image`
  - `is_visible = true`
  - `versand = true`
  - `isVisibleOnPublicArticle() === true`
  - `public_url || preview_url` vorhanden
- Filter anwenden:
  - `q` (caption, image_title, media_keywords)
  - `sort` (`newest`, `oldest`, `rating_desc`, `rating_asc`, `filename_az`)
  - `min_rating` (0-5)
  - `from`, `to` (Zeitbereich auf `capture_time`)
  - `per_page` (10, 25, 50, 100, 200)

Rueckgabe JSON:

- `data[]` (id, urls, title, caption, rating, capture_time, keywords, photographer, dimensions)
- `meta` (current_page, per_page, total, last_page, available_sorts)

### 2) Rating-Basis einfuehren (optional in MVP, empfohlen)

Falls du "Min. Rating" wie bei SpeedShot willst:

Neue Tabelle: `news_item_media_ratings`

- `id`
- `news_item_media_id` (FK)
- `user_id` nullable (oder `session_id` fuer Gast-MVP)
- `rating` tinyint (1-5)
- `created_at`, `updated_at`
- unique index auf `(news_item_media_id, user_id)` wenn User-basiert

Dann im API-Feed:

- `avg_rating` via Subquery/Join
- `ratings_count`

### 3) Frontend in `resources/views/koelnimage/news-show.blade.php` auf "data-driven" umbauen

Die vorhandene Alpine-Logik erweitern:

- State:
  - `layout` (`grid|masonry|fullscreen`)
  - `filters` (`q`, `sort`, `min_rating`, `from`, `to`, `per_page`)
  - `photos`, `meta`, `loading`, `error`
- Methoden:
  - `fetchPhotos(page = 1)`
  - `applyFilters()`
  - `resetFilters()`
  - `setLayout(layout)`
  - `openLightbox(index)`

UI-Bloecke:

1. Top-Toolbar:
   - Suche
   - Sortierung Select
   - Min-Rating Select
   - Zeitbereich (from/to)
   - Pro-Seite Select
2. Layout-Toggles:
   - Grid
   - Masonry
   - Vollbild
3. Ergebnisliste:
   - Grid/Masonry per CSS-Klasse
   - "Details anzeigen" Overlay
4. Pagination:
   - Vor/Zurueck + Seitenzahlen oder "Mehr laden"

### 4) CSS sauber in `resources/css/app.css` zentralisieren

Aktuell liegt viel Galerie-CSS inline im Blade. Fuer wartbare Entwicklung:

- Neue Sektion `.koelnimage-hub-gallery-v2`
- getrennte Modifier:
  - `.is-grid`
  - `.is-masonry`
  - `.is-fullscreen`

Masonry pragmatisch:

- Variante A (schnell): CSS columns (`columns: 2/3/4`)
- Variante B (sauber): CSS grid + JS item spans (aufwaendiger)

---

## Phase 2 (2-5 Tage): SpeedShot-Features nachziehen

### 5) Merkliste, Sammlung, Warenkorb

#### MVP (ohne Loginpflicht)

- Session-Storage + optionale DB-Schattenpersistenz.

Tabellen:

- `gallery_favorites` (`session_key` oder `user_id`, `news_item_media_id`)
- `gallery_collections` (`name`, owner)
- `gallery_collection_items`
- `gallery_cart_items`

#### API

- `POST /gallery/favorites/toggle`
- `POST /gallery/collections`
- `POST /gallery/collections/{id}/items`
- `POST /gallery/cart/add`
- `DELETE /gallery/cart/{mediaId}`

### 6) "Bilder im gleichen Zeitfenster"

Auf Basis `capture_time`:

- Endpoint `GET /news/{slug}/gallery/time-window`
- Query: `reference_media_id`, `window` (`30s|60s|2m|5m`), `direction` (`before|both|after`)

SQL-Idee:

- Referenzzeit laden
- zwischen `ref - window` und `ref + window` filtern
- gleiche Sichtbarkeitsregeln wie Hauptfeed

### 7) Team/Fahrer/Startnummer-Facetten

Wenn du SpeedShot-nahe Motorsportsuche willst:

Neue normalisierte Tags:

- `media_entities` (id, type: `team|driver|car_number|keyword`, label, slug)
- `media_entity_assignments` (news_item_media_id, media_entity_id)

So kannst du facettieren statt nur Freitextsuche.

---

## Datenmodell-Empfehlung (kompatibel zu deinem Ist)

Du hast schon viele Felder in `news_item_media`. Deshalb:

- **Nicht** sofort eine komplett neue "gallery_photos"-Domaintabelle bauen.
- Stattdessen `NewsItemMedia` als Master beibehalten und nur gezielt ergaenzen:
  - `gallery_rating_cached` (optional)
  - `gallery_flags` (json, optional)
  - relationale Zusatztabellen fuer Ratings/Favorites/Entities

Das minimiert Migrationsrisiko und beschleunigt den Rollout.

---

## Performance-Plan

1. **Bildgroessen**
   - Thumbs in mehreren Groessen (z. B. 320, 640, 1280) bereitstellen.
   - Falls nur `preview_path` vorhanden, zusaetzliche Varianten beim Ingest erzeugen.
2. **Query-Performance**
   - Indexe:
     - `news_item_media(news_item_id, type, is_visible, versand, sort_order)`
     - `news_item_media(capture_time)`
3. **API-Antwortzeit**
   - Nur notwendige Felder selektieren.
   - Pagination strikt serverseitig.
4. **Frontend**
   - Lazy loading, `decoding="async"`, `fetchpriority` nur fuer erste Kacheln.

---

## Konkrete Reihenfolge fuer Umsetzung

1. `NewsGalleryController` + Route bauen.
2. JSON-Feed in `news-show` anbinden (zunaechst nur grid + sort + pagination).
3. Layout-Toggle (grid/masonry/fullscreen) aktivieren.
4. Zeitbereich und Min-Rating aktivieren.
5. Merken/Sammlung/Warenkorb als Session-MVP.
6. Danach persistente User-Features + Facetten.

---

## Tests (Pflicht)

### Feature-Tests

Neue Datei: `tests/Feature/KoelnImage/NewsGalleryApiTest.php`

Testfaelle:

- liefert nur Bilder der richtigen News
- liefert keine nicht-oeffentlichen Medien
- Filter `q` funktioniert
- `sort` funktioniert
- `per_page` Grenzen funktionieren
- Zeitbereich `from/to` funktioniert

### Browser-Manuell

1. News-Seite oeffnen.
2. Filter setzen -> Ergebniszahl aendert sich.
3. Layout wechseln -> ohne Reload.
4. Lightbox Navigation per Tastatur.
5. Mobile Ansicht pruefen (2-spaltig, scrollbar toolbar).

---

## Was du sofort tun kannst (erste Commits)

1. Commit A: Route + `NewsGalleryController@index` + Feature-Tests.
2. Commit B: Frontend-Toolbar + API-Fetch + Pagination.
3. Commit C: Masonry/Fullscreen + UX-Polish.
4. Commit D: Ratings/Favorites Session-MVP.

Damit bist du sehr nah am SpeedShot-Bediengefuehl, ohne dein bestehendes Medienmodell umzubauen.
