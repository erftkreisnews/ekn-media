# Kundenverwaltung (Medienhäuser)

## Überblick

- **Organisationen** (Medienhäuser) mit Name, Notizen, Aktiv-Flag
- **Produkte/Abteilungen** pro Organisation
- **Kontakte** pro Organisation, optional einem Produkt zugeordnet
- **Versandziele** pro **Organisation** (Org-Level) oder pro **Produkt**: Typ **E-Mail**, **FTP**, **FTPS**, **SFTP**
- Zugangsdaten (Passwort, Private Key, Key-Passphrase) werden mit **Laravel Crypt** verschlüsselt gespeichert; **kein Klartext** in Views (Anzeige nur „Passwort gesetzt: Ja/Nein“, „Key gesetzt: Ja/Nein“)
- **Verbindung testen** pro Ziel: FTP, FTPS (`ftp_ssl_connect`), SFTP (ext-ssh2 oder phpseclib/phpseclib:^3)
- **Upload** serverseitig per Queue-Job `UploadMediaToDestinationJob` mit Logging in `delivery_runs` und `delivery_run_items`; Route `POST /admin/destinations/{id}/upload` mit `media_ids` (Throttle)
- Rate-Limit für Test- und Upload-Routen: `throttle:10,1`

---

## Neue / angepasste Dateien (Stand Erweiterung Zustellziele)

### Migrationen
- `database/migrations/2026_02_28_400001_add_notes_and_active_to_organizations.php`
- `database/migrations/2026_02_28_400002_add_active_to_products.php`
- `database/migrations/2026_02_28_400003_create_contacts_table.php`
- `database/migrations/2026_02_28_400004_create_delivery_destinations_table.php`
- `database/migrations/2026_02_28_400005_create_delivery_runs_table.php`
- `database/migrations/2026_02_28_400006_create_delivery_run_items_table.php`
- **`database/migrations/2026_02_28_500001_alter_delivery_destinations_spec.php`** (organization_id, host, port, username, password_encrypted, private_key_encrypted, private_key_passphrase_encrypted, remote_path, passive, timeout; product_id nullable)
- **`database/migrations/2026_02_28_500002_alter_delivery_run_items_media_nullable.php`** (news_item_media_id nullable)

### Models
- `app/Models/Organization.php` (inkl. deliveryDestinations)
- `app/Models/Product.php`
- `app/Models/Contact.php`
- **`app/Models/DeliveryDestination.php`** (neue Spalten, getHostOrConfig/getPortOrConfig/…, setEncryptedPassword, setPrivateKey, setPrivateKeyPassphrase, hasPasswordSet, hasPrivateKeySet, getDecryptedPassword/Key/Passphrase)
- `app/Models/DeliveryRun.php`
- `app/Models/DeliveryRunItem.php`

### Controller
- `app/Http/Controllers/Admin/CustomerController.php` (show lädt deliveryDestinations)
- `app/Http/Controllers/Admin/CustomerProductController.php`
- `app/Http/Controllers/Admin/CustomerContactController.php`
- **`app/Http/Controllers/Admin/DeliveryDestinationController.php`** (Produkt- und Org-Destinations: index/create/store/edit/update/destroy; test; upload mit media_ids; Validierung inkl. ftps, host, port, username, remote_path, passive, timeout, Private Key/Passphrase)

### Service
- **`app/Services/DeliveryDestinationConnectionTester.php`** (FTP, FTPS, SFTP via ext-ssh2 oder phpseclib)

### Job
- **`app/Jobs/UploadMediaToDestinationJob.php`** (FTP/FTPS/SFTP-Upload, Run + Items, Lesen aus neuen Spalten bzw. config_json-Fallback)

### Views
- `resources/views/admin/customers/show.blade.php` (Tab **Versandziele (Org)**)
- **`resources/views/admin/destinations/index.blade.php`** (für Produkt- und Org-Kontext)
- **`resources/views/admin/destinations/create.blade.php`** (Typ ftp/ftps/sftp/email, host, port, username, password, private_key, remote_path, passive, timeout)
- **`resources/views/admin/destinations/edit.blade.php`** (wie create + „Passwort/Key gesetzt: Ja/Nein“, Verbindung testen, **Upload-Formular** mit Media-IDs)

### Routes
- **`routes/web.php`**: `admin.customers.destinations.*` (index, create, store, edit, update, destroy); `admin.destinations.test` und `admin.destinations.upload` in `throttle:10,1`

### Abhängigkeit
- **`phpseclib/phpseclib:^3`** (Composer) für SFTP, falls ext-ssh2 nicht verfügbar

---

## Kurzanleitung: WDR (SFTP), imago (SFTP), BILD (FTP/FTPS)

1. **Admin → Kunden** (`/admin/customers`).

2. **Organisation anlegen** (z. B. WDR, imago, BILD)**  
   - „+ Neue Organisation“ → Name, optional Notizen, Aktiv. Speichern.

3. **Versandziele (Organisationsebene)**  
   - Organisation öffnen → Tab **„Versandziele (Org)“** → „+ Versandziel anlegen“ (oder „Alle Versandziele verwalten“).  
   - **WDR / imago (SFTP):** Typ **SFTP**, Bezeichnung z. B. „WDR Lokalzeit Köln SFTP“. Host, Port 22, Benutzername, Passwort und/oder **Private Key** (PEM) + optional Key-Passphrase, Zielpfad. Speichern.  
   - **BILD (FTP/FTPS):** Typ **FTP** oder bevorzugt **FTPS**, Bezeichnung z. B. „BILD Redaktion FTPS“. Host, Port 21 (bzw. 990 bei implizitem FTPS), Benutzername, Passwort, Zielpfad, Passiv-Modus. Speichern.

4. **Verbindung testen**  
   - Beim jeweiligen Ziel auf **„Testen“** klicken (oder Bearbeiten → „Verbindung testen“).  
   - Erfolg/Fehler erscheint als Meldung. Bei FTPS: Hinweis, falls `ftp_ssl_connect` fehlt.

5. **Upload ausführen**  
   - Versandziel bearbeiten → Abschnitt **„Upload ausführen“**.  
   - Media-IDs eingeben (komma- oder leerzeichengetrennt, z. B. `1, 2, 3`).  
   - „In Warteschlange stellen“ → Job lädt Dateien aus dem Storage (NewsItemMedia) an das Ziel (FTP/FTPS/SFTP).  
   - Status in `delivery_runs` / `delivery_run_items` (queued → running → success/failed).

Optional: Versandziele auch **pro Produkt** anlegen (unter Produkt → Versandziele), gleiche Felder und Test/Upload.

---

## Technik

- **FTP/FTPS:** `ftp_connect` / `ftp_ssl_connect`, `ftp_login`, `ftp_pasv`, `ftp_chdir`, `ftp_nlist` (Test), `ftp_put` (Job).  
- **SFTP:** Zuerst `function_exists('ssh2_connect')` → ext-ssh2 (connect, auth, sftp, nlist/put); sonst **phpseclib/phpseclib:^3** (SFTP login, nlist, put).  
- **Verschlüsselung:** `Crypt::encryptString()` / `decryptString()` für Passwort und Private Key/Passphrase in Spalten `password_encrypted`, `private_key_encrypted`, `private_key_passphrase_encrypted`; Fallback für Alt-Daten aus `config_json`.  
- **Logging:** `delivery_runs` (status: queued|running|success|failed), `delivery_run_items` (status: pending|success|failed); Run wird im Controller angelegt, Job aktualisiert Run und Items.
