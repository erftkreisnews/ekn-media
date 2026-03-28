# KI-gestützte Anonymisierung (Kennzeichen & Gesichter)

## Übersicht

- **Ein System** für alle Anonymisierungen: Kennzeichen (optional ONNX), Gesichter (optional ONNX), manuelle Boxen (z. B. für Gesichter oder andere Bereiche). Der frühere separate „Unkenntlich machen“-Editor leitet auf die Bearbeitungsseite zur Karte **Bereiche unkenntlich machen** um.
- **Original** wird einmal gespeichert und **niemals überschrieben** (archiviert unter `storage/app/public/media/original/`).
- **Öffentliche Auslieferung** erfolgt nur über die **redigierte Version** (`media/redacted/`), sofern `redaction_status = done`.
- Bei **pending** oder **failed** wird das Original **nicht** öffentlich angezeigt (Fail-safe).
- **Admin** kann Redaction deaktivieren (`disabled`); dann wird bei vorhandener redacted-Version diese ausgeliefert, sonst das Original (rechtlich zu prüfen).

## Installation

### 1. Ordner auf dem Server (außerhalb Webroot)

```bash
mv ~/ai_test ~/ai_worker
cd ~/ai_worker
python3 -m venv env
source env/bin/activate
pip install --upgrade pip
pip install -r requirements.txt
mkdir -p models scripts tmp
```

Skripte und `requirements.txt` aus dem Laravel-Repo (`ai_worker/`) nach `~/ai_worker/` kopieren (z. B. `scripts/detect_and_redact.py`).

### 2. Laravel

```bash
cd /pfad/zu/laravel12
php artisan migrate   # Migration add_redaction_columns_to_news_item_media_table
```

ENV (optional in `.env`):

- `AI_WORKER_PATH=/usr/home/admin/ai_worker`
- `REDACTION_MODEL_PATH=` (leer = `ai_worker/models/plate_detector.onnx`) – Kennzeichen
- `REDACTION_FACE_MODEL_PATH=` (leer = keine Gesichtserkennung) – optional Gesichter-ONNX
- `REDACTION_METHOD=blur` oder `black_box`
- `REDACTION_PYTHON=` (leer = `$AI_WORKER_PATH/env/bin/python`)

### 3. Queue

Der Job **ProcessMediaRedaction** läuft über die Queue. Worker starten:

```bash
php artisan queue:work
```

(Supervisor empfohlen, siehe SETUP-QUEUE.md.)

## Modelle (ONNX)

- **Kennzeichen:** `REDACTION_MODEL_PATH` bzw. `config('redaction.model_path')`. Bezug: z. B. „license plate detection ONNX“ (Hugging Face/GitHub). Format: Eingabe (1, 3, H, W), Ausgabe Bounding Boxes (x1, y1, x2, y2, conf, …).
- **Gesichter (optional):** `REDACTION_FACE_MODEL_PATH` bzw. `config('redaction.face_model_path')`. Gleiches Ein-/Ausgabeformat wie Kennzeichen (z. B. Face-Detection-ONNX). Boxen werden mit Kennzeichen und manuellen Boxen zusammengeführt und per NMS bereinigt.
- **Ohne Modell(e):** Keine automatischen Boxen; in der Admin-UI können **manuelle Boxen** (z. B. für Gesichter) gesetzt und „Speichern & Redaction neu rendern“ ausgeführt werden.

## Tests

- `php artisan test tests/Unit/NewsItemMediaRedactionTest.php` – prüft, dass `public_path`/`public_url` bei pending/failed null sind und bei done die redigierte Version genutzt wird.
- Hinweis: Wenn das Projekt mit SQLite-Tests läuft und eine Migration MySQL-spezifische Syntax (z. B. `MODIFY`) nutzt, die Tests mit der gleichen DB wie die App (z. B. MySQL) ausführen.

## TODOs (falls Modell extern)

- [ ] ONNX-Modell für Kennzeichenerkennung besorgen oder trainieren und unter `ai_worker/models/plate_detector.onnx` ablegen (oder Pfad in ENV setzen).
- [ ] Optional: `onnxruntime-openvino` als Fallback testen, wenn `onnxruntime` auf dem Server Probleme macht (siehe ai_worker/README.md).
- [ ] Nach erstem Deployment: Bestehende Bilder (Legacy) behalten `redaction_status = null` und werden weiterhin über `path` ausgeliefert; neue Uploads erhalten `pending` und werden nach Job mit redacted Version versehen.

## Logging / Audit

- Es wird **kein Kennzeichen-Text** gespeichert (kein OCR).
- Laravel loggt: Auto-Redaction gestartet/abgeschlossen/fehlgeschlagen, Anzahl Boxen, Methode (blur/black_box).

## Sicherheit

- `/ai_worker` liegt **nicht** im Webroot.
- Öffentliche Controller/Views nutzen ausschließlich `public_url` / `public_path`; bei `null` wird kein Original ausgeliefert.
- Delivery-Download liefert nur die redigierte Datei (bzw. bei disabled die konfigurierte Variante).
