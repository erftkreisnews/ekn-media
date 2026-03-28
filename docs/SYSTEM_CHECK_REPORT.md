# Systemprüfbericht

**Datum:** 2026-03-08  
**Laravel:** 12.53.0

## Durchgeführte Prüfungen

- **Laravel / Artisan:** App startet, Caches geleert (config, cache, view).
- **Routen:** `php artisan route:list` – 127 Routen, keine Fehler.
- **Migrationen:** `migrate:fresh` (inkl. Test-DB) – alle Migrationen laufen durch.
- **Tests:** `php artisan test` – **42 Tests bestanden** (127 Assertions).

## Behobene Punkte

### 1. Tabelle `organizations` – fehlende Spalten
- **Problem:** Migration `add_billing_and_lexware_fields_to_products_table` nutzte `organizations.buyer_reference` und `organizations.lexware_contact_id`, die nicht existierten.
- **Lösung:** Migration `2026_03_06_170000_add_buyer_reference_lexware_to_organizations.php` ergänzt, `Organization::$fillable` um die beiden Felder erweitert.

### 2. Tabelle `invoices` – fehlte komplett
- **Problem:** Es gab nur eine Migration, die `invoices` änderte (`add_product_id_to_invoices_table`), keine Migration zum Anlegen der Tabelle.
- **Lösung:** Migration `2026_03_06_175000_create_invoices_table.php` erstellt (id, organization_id, contact_id, lexware_invoice_id, voucher_number, status, Beträge, currency, voucher_date, meta, timestamps).

### 3. Tabelle `usage_records` – fehlte komplett
- **Problem:** Migration `add_invoice_metadata_to_usage_records_table` änderte eine nicht existierende Tabelle.
- **Lösung:** Migration `2026_03_06_175100_create_usage_records_table.php` erstellt (inkl. FK zu news_items, organizations, product, users, invoices); die Spalten usage_format, usage_rights, reference_code, line_item_note werden weiterhin von der bestehenden Migration 190000 ergänzt.

### 4. Tabelle `contacts` – fehlende Billing-Felder
- **Problem:** Tests und Model nutzten `use_for_invoice`, `billing_department`, `billing_type`, die in der `contacts`-Tabelle fehlten.
- **Lösung:** Migration `2026_03_06_174000_add_billing_fields_to_contacts_table.php` erstellt (Spalten mit hasColumn abgesichert).

## Aktueller Stand

- Alle genannten Migrationen laufen fehlerfrei (inkl. Test-Umgebung).
- Alle 42 Tests sind grün.
- Keine offenen Linter-Meldungen in den geänderten Migrationen.

## Empfehlung

- Auf Produktion: `php artisan migrate` ausführen, damit die neuen Migrationen (174000, 175000, 175100) angewendet werden. Vorher Backup der Datenbank empfohlen.
