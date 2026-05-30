# Queue-Worker (Betrieb)

Kurze Anleitung, wie die Laravel-Queue auf dem Server betrieben wird und was nach Deploys zu tun ist.

## Wie es bei uns läuft (Stand Erhebung)

- **`supervisorctl`** ist auf dem Host **nicht** verfügbar → **kein** Supervisor im Einsatz.
- Die Worker werden per **Cron** (User `admin`) mit **`flock`** gestartet (nur eine Instanz pro Lock gleichzeitig).
- Zwei getrennte Aufrufe:
  - **Default-Queue** (ohne `--queue`): allgemeine Jobs inkl. **`ProcessIngestRenderJob`** (Ingest-Finalrender).
  - **Queue `ai`**: `--queue=ai` für KI-Jobs.

Typische Cron-Zeilen (Kernbefehle):

```text
php84 …/artisan queue:work --stop-when-empty --sleep=5 --tries=3 --timeout=14400
php84 …/artisan queue:work --queue=ai --stop-when-empty --sleep=5 --tries=3 --timeout=600
```

**Produktion (User `admin`):** Die **Default-Queue** nutzt `--timeout=14400` (angepasst an `ProcessIngestRenderJob`). Die **`ai`-Queue** bleibt bei `--timeout=600`, sofern KI-Jobs kurz sind.

Heartbeat/Jobs-Seite u. a.: `queue:heartbeat default|ai`, `queue:worker-heartbeat`, `schedule:run`.

## Timeout: Worker vs. lange Jobs

- Der Queue-Worker-Parameter **`--timeout`** (Sekunden) muss **mindestens** so groß sein wie das **Job-Timeout** in PHP, sonst bricht Laravel den Job vorzeitig ab.
- **`ProcessIngestRenderJob`** ([`app/Jobs/ProcessIngestRenderJob.php`](app/Jobs/ProcessIngestRenderJob.php)) hat **`$timeout = 14400`** (4 Stunden) für lange FFmpeg-Läufe.
- Wenn im Cron für die **Default-Queue** noch **`--timeout=600`** (10 Minuten) steht, bricht Laravel lange Ingest-Renders **vorzeitig** ab (unabhängig von RAM). Das muss auf mindestens **`14400`** stehen (siehe Cron-Beispiel oben).

Die **`ai`-Queue** kann bei kurzen Jobs weiter **`--timeout=600`** nutzen.

## Supervisor-Vorlage im Repo (Referenz)

Unter [`etc/supervisor/conf.d/laravel-worker.conf`](etc/supervisor/conf.d/laravel-worker.conf) liegt eine **Vorlage**, falls später Supervisor genutzt wird. Dort ist das Worker-Timeout an **`ProcessIngestRenderJob`** angeglichen (`--timeout=14400`). Auf dem Server ist diese Datei **nicht** automatisch aktiv (kein Supervisor).

## Nach Code- oder Config-Deploy

1. **`php artisan config:clear`** (bzw. `optimize:clear`), falls Config gecacht wird.
2. **Worker neu laden:** Lange laufende `queue:work`-Prozesse halten ggf. **alten PHP-Code** (OPcache/Prozess). Mit **`--stop-when-empty`** enden Worker nach leerer Queue von selbst; bei einem **hängenden** Worker Prozess beenden (PID per `ps` ermitteln) oder bis zum Ende der Minute warten und Lock beachten.
3. **Ingest-Render erneut anstoßen**, falls ein Job zuvor mit altem Code fehlgeschlagen ist.

## OOM / Signal 9 bei ffmpeg (Ingest-Finalrender)

Zusätzlich zum Worker-Timeout können **RAM-Spitzen** beim Encodieren den Prozess beenden (Kernel: SIGKILL).

Im Code/`config/ingest.php` stehen Optionen wie **`x264_preset`**, **`vbv_bufsize`**, **`x264_rc_lookahead`**, **`normalize_audio_channels`** (Stereo statt 8-Kanal-Upmix in der Normalisierung – spart viel RAM). In der **`.env`** z. B.:

- `INGEST_NORMALIZE_AUDIO_CHANNELS=2` (Stereo für Segmente; Standard-Ausgabe ist 4 Kanäle wie typische FX6; für 8-Kanal: `INGEST_OUTPUT_AUDIO_CHANNELS=8` und passendes Layout setzen, wenn genug RAM)
- `INGEST_OUTPUT_X264_PRESET=veryfast`, `INGEST_OUTPUT_VBV_BUFSIZE=8M`, niedrige Thread-Werte

Nach Änderungen: `php artisan config:clear` und Queue-Worker neu.

## Kurz-Checkliste

| Prüfung | Befehl / Ort |
|--------|----------------|
| Läuft `queue:work`? | `ps aux \| grep '[a]rtisan queue:work'` |
| Cron | `crontab -l` |
| Supervisor (falls irgendwann) | `supervisorctl status` |
