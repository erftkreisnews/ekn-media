# High-Security Medienauslieferungssystem

## Übersicht

- **Entry-Link:** 48h gültig (temporary signed route + DB `expires_at`)
- **Download-Links:** 10 Min gültig (temporary signed route), erneuerbar durch Reload
- **Mail:** Bestehender MS365-Mailer (Laravel Mail unverändert)
- **Downloads:** Nur serverseitig über Controller (kein öffentlicher `/storage`-Link)
- **Audit:** `delivery_events` (opened, confirmed, download, revoked) + ip_hash/ua_hash
- **Kill-Switch:** Admin kann Delivery revoken (sofort ungültig)
- **Confirm-Pflicht:** Redaktion + Produkt vor Freischaltung der Downloads
- **Rate Limiting:** show 60/min, confirm + download je 30/min
- **Download-Inhalt:** Es werden verbindlich die **Originaldateien** (`news_item_media.path`) ausgeliefert – keine Preview-/Thumb-/Poster-/Redaction-Dateien. Vorschau in der UI nutzt weiterhin `public_url` / `preview_url`. Logik: `NewsItemMedia::resolveDeliveryDownloadRelativePath()`.
- **Video/Audio-Wiedergabe auf der Medienpaket-Seite:** Bei **S3** zuerst **presigned URL** (`MediaStorage::temporaryPlaybackUrlForPath`) – Browser lädt direkt vom Object Storage (schnell, kein PHP-Proxy). Fallback: signierte Same-Origin-Route `delivery.stream`. Download bleibt `delivery.download`. Optional `.env`: `MEDIA_PREFER_PRESIGNED_STREAMING=false` erzwingt nur Laravel-Stream. **Bucket-CORS:** Für presigned GET vom Browser-Origin muss das S3-kompatible Bucket CORS erlauben (GET, Range, ggf. HEAD).

---

## Neu erstellte / angepasste Dateien

### Migrationen
- `database/migrations/2026_02_28_300001_create_organizations_table.php`
- `database/migrations/2026_02_28_300002_create_products_table.php`
- `database/migrations/2026_02_28_300003_create_deliveries_table.php`
- `database/migrations/2026_02_28_300004_create_delivery_events_table.php`
- `database/migrations/2026_02_28_300005_seed_default_organizations_and_products.php`

### Models
- `app/Models/Organization.php`
- `app/Models/Product.php`
- `app/Models/Delivery.php`
- `app/Models/DeliveryEvent.php`
- `app/Models/NewsItem.php` (Relation `deliveries()` ergänzt)

### Controller
- `app/Http/Controllers/DeliveryController.php` (show, confirm, download)
- `app/Http/Controllers/Admin/NewsDeliveryController.php` (send)
- `app/Http/Controllers/Admin/DeliveryController.php` (index, revoke)

### Mail
- `app/Mail/NewsDeliveryMail.php`
- `resources/views/emails/news-delivery.blade.php`

### Views
- `resources/views/deliveries/show.blade.php` (Token-Seite, ohne Sidebar)
- `resources/views/admin/deliveries/index.blade.php` (Admin-Übersicht)
- `resources/views/admin/news/edit.blade.php` (Button „Speichern & Versenden“)
- `resources/views/layouts/admin.blade.php` (Nav-Link „Versand“)

### Config & Routes
- `config/newsdesk.php` (delivery_recipient, optional `DELIVERY_RECIPIENT`)
- `routes/web.php` (Delivery-Routen + Admin send/revoke/deliveries.index)

---

## Testanleitung

1. **Migration (bereits ausgeführt)**  
   `php artisan migrate`

2. **Empfänger setzen (optional)**  
   In `.env`: `DELIVERY_RECIPIENT=afranz@erftkreis-news.de`  
   Oder in `config/newsdesk.php` anpassen.

3. **Als Admin: Versand auslösen**  
   - Nachricht bearbeiten (z. B. `/admin/news/{id}/edit`)
   - Mindestens ein Medium mit „Zum Versand“ (versand=true) markieren
   - Auf **„Speichern & Versenden“** klicken  
   → E-Mail geht an `config('newsdesk.delivery_recipient')` (bestehender Mailer).

4. **Token-Seite testen**  
   - In der E-Mail auf „Medienpaket öffnen“ klicken (signed URL, 48h gültig).  
   - Oder in der DB `deliveries.token` holen und manuell bauen:  
     `https://{APP_URL}/d/{token}?signature=...&expires=...`  
     Signierte URL erzeugen:  
     `php artisan tinker` →  
     `URL::temporarySignedRoute('deliveries.show', now()->addHours(48), ['token' => $delivery->token])`

5. **Confirm**  
   - Auf der Token-Seite Redaktion + Produkt wählen, „Bestätigen und Medien anzeigen“.  
   - Danach erscheinen die Medien mit Download-Buttons (Links 10 Min gültig).

6. **Download**  
   - Auf „Download“ klicken → Datei wird über Controller ausgeliefert (kein direkter Storage-Link).  
   - Nach 10 Min: Seite neu laden, neue Links erhalten.

7. **Ablauf (410)**  
   - `expires_at` in der Vergangenheit oder Link nach 48h aufrufen → 410.

8. **Revoke**  
   - Admin: **Versand** → Liste → bei einer aktiven Delivery **„Widerrufen“**.  
   - Danach: Aufruf der Token-URL → 410.

9. **Audit**  
   - In `delivery_events`: event_type (opened, confirmed, download, revoked), media_id, organization_id, product_id, ip_hash, ua_hash, created_at.

---

## Security-Layers (Kurz)

| Layer | Umsetzung |
|-------|-----------|
| 48h Entry | `expires_at` in DB + `URL::temporarySignedRoute(..., now()->addHours(48))` |
| 10-Min-Downloads | `URL::temporarySignedRoute('deliveries.download', now()->addMinutes(10), [...])` |
| Revoke | `revoked_at` gesetzt → sofort 410 |
| Audit | `delivery_events` für opened/confirmed/download/revoked, nur Hashes (ip_hash, ua_hash) |
| Private Serving | `response()->download($fullPath, $downloadName)` aus `storage/app/public/`, keine öffentlichen URLs für Delivery |
| Rate Limit | throttle:60,1 (show), throttle:30,1 (confirm, download) |
| Signed URLs | Middleware `signed` auf allen Delivery-Routen |
