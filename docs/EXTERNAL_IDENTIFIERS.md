# Externe Identifikatoren (Delivery Destinations)

Pro Versandziel (Delivery Destination) können externe Kennungen gepflegt werden (z. B. dpa Autorennummer, BILD Lieferanten-ID, WDR Lieferantennummer). Diese werden automatisch bei E-Mail-Versand und FTP/SFTP-Upload genutzt.

## Geänderte/Neue Dateien

- **Migration:** `database/migrations/2026_03_01_100000_add_external_identifiers_to_delivery_destinations.php`
- **Model:** `app/Models/DeliveryDestination.php` (fillable, casts, `getExternalIdentifiers()`, `getFilenameSuffix()`)
- **Controller:** `app/Http/Controllers/Admin/DeliveryDestinationController.php` (Validierung + Speichern der neuen Felder)
- **Views:**  
  - `resources/views/admin/destinations/create.blade.php` (Sektion „Externe Identifikatoren“)  
  - `resources/views/admin/destinations/edit.blade.php` (Sektion „Externe Identifikatoren“)  
  - `resources/views/admin/destinations/index.blade.php` (Spalte „Externe ID“)
- **Mail:**  
  - `app/Mail/NewsDeliveryMail.php` (optionaler 4. Parameter `$destination`)  
  - `resources/views/emails/partials/external-ids.blade.php` (Footer-Block)  
  - `resources/views/emails/news-delivery.blade.php` (Einbindung des Partials)
- **Upload-Job:** `app/Jobs/UploadMediaToDestinationJob.php` (Dateinamen-Suffix, Sidecar `delivery.meta.json`)

## Kurzanleitung

1. **Destination bearbeiten**  
   Admin → Kunde/Produkt → Versandziele → Versandziel bearbeiten.

2. **Sektion „Externe Identifikatoren“**  
   - **Bezeichnung (Label):** z. B. „dpa Autorennummer“ oder „WDR Lieferantennummer“.  
   - **Autorennummer / Lieferanten-ID / Vendor-Code:** je nach Abnehmer ausfüllen.  
   - **In E-Mail-Footer ausgeben:** aktivieren, damit die Kennung im E-Mail-Footer erscheint (sofern beim Versand eine Destination übergeben wird).  
   - **In Dateinamen anhängen:** aktivieren, dann werden hochgeladene Dateien z. B. als `Dateiname_AFR12345.mp4` hochgeladen (Suffix = erster gesetzter Wert: vendor_code, author_id, supplier_id).  
   - **Sidecar-Datei:** aktivieren, dann wird beim FTP/SFTP-Upload zusätzlich `delivery.meta.json` mit news_id, title, published_at, external IDs und Dateiliste (original_name, delivered_name, size_bytes, sha256) erzeugt und mit hochgeladen.

3. **Test**  
   - **E-Mail:** Wenn der Versand künftig an ein Versandziel (Typ E-Mail) gebunden wird, wird die Destination an `NewsDeliveryMail` übergeben; der Footer zeigt dann „Externe Kennung: {Label}: {Wert}“.  
   - **Upload:** Versandziel bearbeiten → „Upload ausführen“ → Media-IDs eingeben → Upload starten. Prüfen: Dateinamen mit Suffix (wenn aktiviert), Vorhandensein von `delivery.meta.json` (wenn aktiviert).

## Hinweise

- IDs sind pro Destination gespeichert, keine globale Nutzung.
- Keine Änderung am Mail-Transport (MS365 bleibt unverändert).
- Gespeicherte Medienpfade bleiben unverändert; nur der **ausgelieferte** Dateiname (Upload/Download) wird ggf. um das Suffix ergänzt.
