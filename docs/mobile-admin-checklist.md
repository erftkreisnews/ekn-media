## Mobile Admin UX – Test-Checkliste

### 1. Navigation & Layout

- **Login & Einstieg**
  - `/admin` im mobilen Browser aufrufen (iOS + Android).
  - Topbar ist sichtbar und bleibt beim Scrollen sticky.

- **Mobiler Drawer**
  - Hamburger oben links antippen → Drawer fährt von links ein, Overlay erscheint, Body-Scroll ist gesperrt.
  - Tap auf Overlay oder Schließen-Icon → Drawer schließt, Scroll ist wieder möglich.
  - ESC (externe Tastatur) schließt den Drawer.

- **Aktiver Menüpunkt**
  - Nacheinander `Dashboard`, `Nachrichten`, `Versand`, `Kunden`, `Einstellungen` wählen.
  - In Sidebar/Drawer ist der aktuelle Menüpunkt hervorgehoben.

### 2. Listen (News, Kunden, Deliveries)

- **News-Liste `/admin/news`**
  - Mobile: Suchfeld „Suche nach Titel, ID, Autor …“ ist sichtbar.
  - Einträge werden als Cards dargestellt (Status, Teaser-Info, Titel, ID, Datum, Autor, Medienanzahl).
  - Suche mit Stichwort (Titel/ID/Autor) filtert Cards clientseitig.
  - Desktop: klassische Tabelle sichtbar, keine Cards.

- **Kunden-Liste `/admin/customers`**
  - Mobile: Suchfeld „Suche nach Name …“.
  - Cards zeigen Name, Produkte, Kontakte, Status (Aktiv/Inaktiv).
  - Aktionen (Anzeigen, Bearbeiten, Löschen) sind gut klickbar, ohne horizontales Scrollen.

- **Versand-Liste `/admin/deliveries`**
  - Mobile: Suchfeld für Empfänger, Titel oder News-ID.
  - Cards pro Versand: Nachrichtentitel, News-ID, Empfänger, Ablauf, erstellt, Status-Badge (Aktiv/Abgelaufen/Widerrufen).
  - Button „Widerrufen“ nur bei aktiven Versänden; Bestätigungsdialog funktioniert.
  - Desktop: Tabelle + Pagination am Tabellenende.

### 3. News bearbeiten `/admin/news/{id}/edit`

- **Allgemeines Layout**
  - Mobile: Einspaltiges Layout, Sektionen in klaren Cards.
  - Tabs (Nachricht, Bilder, Videos, Audios, Downloads, Zeugen, Verwendungen) sind horizontal erreichbar, Inhalt ohne horizontales Scrollen.

- **Sticky Bottom Actionbar (Mobile)**
  - Beim Scrollen nach unten erscheint eine weiße Actionbar mit:
    - „Änderungen speichern“
    - „Speichern & Veröffentlichen“
    - „Speichern & Versenden“
  - Buttons sind full-width (insbesondere auf sehr kleinen Screens) und gut antippbar.
  - Aktionen:
    - **Änderungen speichern** → Formular wird gespeichert, Status-Flash sichtbar.
    - **Speichern & Veröffentlichen** → Status der Nachricht wird published, keine Validierungsfehler.
    - **Speichern & Versenden** → Weiterleitung auf Prepare-Send-Ansicht.

- **Validierungsfehler**
  - Einen Pflichtwert (z. B. Titel) leeren und speichern:
  - Fehlerhinweis erscheint oberhalb des Felds, ohne horizontales Scrollen.

### 4. Upload-UI & Medien-Tabs

- **Bilder-Tab**
  - Uploadbereich zeigt:
    - Datei-Input + „Hochladen“-Button (Mobile: full-width, min. ca. 44px hoch, `rounded-2xl`).
  - Bild-Grid:
    - 2 Spalten (kleine Phones), 3–4 Spalten auf Tablet, bis zu 5 auf Desktop.
  - Bild antippen → Lightbox öffnet:
    - Hintergrund dunkel, Body-Scroll gesperrt.
    - ESC, Klick auf Overlay oder „Schließen“-Button schließen die Lightbox.
  - Qualität-Badges (Presse-OK/Warnung/Mängel), Teaser-Badge und Toggles sind auf Mobil ohne Zoom gut bedienbar.

- **Videos-Tab**
  - Upload-UI identisch komfortabel (full-width Button & Input).
  - Video-Kacheln sind nicht abgeschnitten, Controls bedienbar; keine horizontale Scroll-Leiste.

- **Audios-Tab**
  - Upload wie bei Bildern/Videos.
  - Audio-Karten: Icon, Dateiname, Größe + Schalter (Sichtbar/Versand) und Aktionen in einer übersichtlichen Zeile.

### 5. Media-Edit `/admin/news/{news}/media/{media}/edit`

- **Layout**
  - Mobile:
    - Bild/Vorschau oben, Metadaten-Karten darunter einspaltig.
  - Desktop:
    - Zwei-Spalten-Layout (links Bild, rechts Metadaten).

- **Sticky Bottom Actionbar**
  - Am unteren Rand: Actionbar mit Button „Metadaten speichern“ (full-width, `rounded-2xl`).
  - Button speichert Metadaten-Formular, Validierungsfehler/Status werden korrekt angezeigt.

- **KI-Status**
  - Bei Status `queued` / `running`: Badge „KI analysiert …“ sichtbar.
  - Nach Abschluss/Fehler:
    - Seite wird automatisch neu geladen (Polling), Status wechselt auf „KI abgeschlossen“ bzw. „KI-Fehler“.

### 6. Modals & Overlays

- **Bild-Lightbox (Tabs & Media-Edit)**
  - Overlay dunkelt den Hintergrund ab, Bild wird mittig gezeigt.
  - Body-Scroll ist gesperrt, solange Lightbox offen ist.
  - ESC / Klick auf Hintergrund / „Schließen“-Button schließen das Overlay zuverlässig.

- **Upload-Progress (große Videos)**
  - Beim Speichern mit Video-Upload:
    - Fortschrittsanzeige zeigt Prozentwert, MB/s und Gesamtgröße.
    - Bei Erfolg: Fortschrittsbalken läuft bis 100 %, die Seite lädt neu und das neue Medium erscheint.
    - Bei Fehler: Rote Fehlermeldung, Oberfläche bleibt stabil (Buttons springen nicht).

