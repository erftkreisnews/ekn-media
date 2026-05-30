# Prüfbericht: Übernahme aus „lave hetzner neu“

**Stand:** Nur Prüfung und Empfehlung – es wurde **nichts überschrieben** und **nichts am laufenden Projekt geändert**.

---

## 1. Was wurde geprüft?

- Ordner: `lave hetzner neu/resources/`
- Verglichen mit: `resources/` im Hauptprojekt (Laravel 12, Admin mit Tailwind, Erftkreis News)

---

## 2. Wichtig: Nicht überschreiben (Projekt würde gefährdet)

| Datei / Bereich | Grund |
|-----------------|--------|
| **layouts/admin.blade.php** | Euer aktuelles Admin-Layout (Tailwind, Erftkreis-Header). Bleibt unverändert. |
| **views/admin/** (news, dashboard, image, video, audio) | Eure bestehende Admin-Struktur mit euren Routes (`admin.news.*` usw.). Nicht ersetzen. |
| **layouts/app.blade.php** (im Projekt-Root) | Euer aktuelles App-Layout. Nicht mit der Bootstrap-Version aus „lave hetzner neu“ überschreiben. |
| **auth/login.blade.php** usw. | Eure Auth-Views. Nicht überschreiben. |
| **resources/css/app.css** | Euer Vite/Tailwind-Setup. Nicht durch die alte app.css ersetzen. |
| **resources/js/app.js** | Euer aktuelles Frontend-JS. Nicht überschreiben. |

---

## 3. Was könnt ihr sicher übernehmen (ohne Überschreiben)

### 3.1 Als **neue** Dateien ins Projekt kopieren (kein Ersetzen)

- **Portal-Views** (habt ihr so noch nicht):
  - `lave hetzner neu/resources/views/portal/index.blade.php` → z.B. nach `resources/views/portal/index.blade.php` (wenn ihr ein Kundenportal wollt)
  - `lave hetzner neu/resources/views/portal/show.blade.php` → `resources/views/portal/show.blade.php`
  - Hinweis: Routes (`portal.news.show` usw.) und Controller müsst ihr im Projekt anlegen/anpassen.

- **Witness (Zeugen)** (habt ihr so noch nicht):
  - `lave hetzner neu/resources/views/witness/show.blade.php` → z.B. `resources/views/witness/show.blade.php`

- **Components** (können ergänzen, ohne bestehende zu ersetzen):
  - `lave hetzner neu/resources/views/components/action-bar.blade.php` → `resources/views/components/action-bar.blade.php`
  - `lave hetzner neu/resources/views/components/alert-banner.blade.php` → `resources/views/components/alert-banner.blade.php`
  - Die „neu“-Views nutzen Bootstrap (`.btn-enm`, `.enm-container`). Wenn ihr nur Tailwind nutzt, müsst ihr die Klassen beim Übernehmen anpassen.

### 3.2 Als **Vorlage / Referenz** nutzen (Inhalt übernehmen, Struktur anpassen)

- **News-Formularfelder** aus `lave hetzner neu/resources/views/news/_form.blade.php`:
  - Enthält: Kicker, Unterzeile, Veröffentlicht am, Embargo, Eilig/Top/Live, Angebotstext, Fließtext HTML/Plain, Autor, Autor-Credit, Ortsangabe, Taxonomien (Kategorie, County, Interest, Tag, Keyword).
  - Könnt ihr in eure **admin/news**-Views (create/edit) übernehmen, indem ihr die gewünschten Felder in eure bestehenden Blade-Dateien einbaut – **ohne** die ganze Datei zu ersetzen.

- **News-Tabs** aus `lave hetzner neu/resources/views/news/`:
  - Tabs: Bilder, Videos, Audios, Downloads, Zeugen, Aussendungen, Verwendungen.
  - Ihr habt bereits `admin/news/partials/` (media-tab-bilder usw.). Die „neu“-Tab-**Inhalte** könnt ihr als Ideen/Code-Snippets nutzen und in eure Partials integrieren, statt die „neu“-Dateien 1:1 zu übernehmen (wegen anderem Layout/Routes).

- **Layout-Ideen** aus `lave hetzner neu/resources/views/layouts/app.blade.php`:
  - CSS-Variablen (z.B. `--enm-primary`, `--enm-container-max`) und Klassen wie `.navbar-enm`, `.btn-enm` könnt ihr in eurem eigenen CSS/Layout nachbauen, **ohne** die bestehende `layouts/app.blade.php` zu ersetzen.

---

## 4. Kurzfassung

- **Nichts überschreiben**, was unter Abschnitt 2 steht – sonst riskiert ihr Layout, Routes und Funktionalität.
- **Sicher übernehmen:** neue Views (portal, witness, action-bar, alert-banner) als **zusätzliche** Dateien; Formularfelder und Tab-Ideen aus „lave hetzner neu“ als **Vorlage** in eure bestehenden Admin-Views einarbeiten.
- **frontend/** und **assets/** unter „lave hetzner neu“ sind leer – nichts zu übernehmen.

Wenn ihr wollt, kann im nächsten Schritt konkret vorgeschlagen werden, welche Felder aus `_form.blade.php` in welche eurer admin/news-Blade-Dateien eingetragen werden (weiterhin ohne Überschreiben eurer Dateien, nur Anweisungen zum Einfügen).
