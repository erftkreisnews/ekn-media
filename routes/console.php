<?php

use App\Jobs\ExtractAudioMetadata;
use App\Jobs\ExtractVideoMetadata;
use App\Jobs\ProcessMediaRedaction;
use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Wird vom Cron aufgerufen (z. B. jede Minute). Schreibt Zeitstempel in den Cache,
 * damit die Admin-Seite „Jobs“ anzeigen kann, ob der Cron läuft (Ampel).
 */
Artisan::command('queue:heartbeat {queue?}', function () {
    Cache::put('cron_heartbeat_at', now()->toIso8601String(), 300);
})->purpose('Cron-Heartbeat: Zeitstempel für Ampel-Anzeige setzen');

Artisan::command('queue:worker-heartbeat', function () {
    Cache::put('queue_worker_heartbeat_at', now()->toIso8601String(), 300);
})->purpose('Wird vom Heartbeat-Job aufgerufen – nur für manuellen Test.');

/**
 * Setzt beide Heartbeats (Cron + Queue-Worker) sofort – z. B. zum Testen der Ampeln.
 * Nach dem Aufruf sollten beide Ampeln auf der Jobs-Seite grün sein.
 */
Artisan::command('heartbeat:now', function () {
    Cache::put('cron_heartbeat_at', now()->toIso8601String(), 300);
    Cache::put('queue_worker_heartbeat_at', now()->toIso8601String(), 300);
    $this->info('Cron- und Queue-Worker-Heartbeat gesetzt. Beide Ampeln sollten jetzt grün sein.');
})->purpose('Cron- und Queue-Worker-Heartbeat sofort setzen (für Ampel-Test).');

/**
 * Bestehende Videos nachträglich für die Metadaten-Analyse (ffprobe) in die Queue stellen.
 *
 * Beispiel:
 *  php artisan media:analyze-videos
 *  php artisan media:analyze-videos 500
 */
Artisan::command('media:analyze-videos {limit=100}', function (int $limit = 100) {
    $query = NewsItemMedia::where('type', 'video')
        ->whereNull('duration_s')
        ->orderBy('id');

    $media = $query->limit($limit)->get();
    foreach ($media as $m) {
        ExtractVideoMetadata::dispatch($m);
    }

    $this->info($media->count().' Video(s) für Metadaten-Extraktion in die Warteschlange gestellt.');
})->purpose('Bestehende Videos für ffprobe-Analyse in die Queue stellen');

/**
 * Bestehende Audios nachträglich für die Metadaten-Analyse (ffprobe + Qualitätscheck) in die Queue stellen.
 *
 * Beispiel:
 *  php artisan media:analyze-audios
 *  php artisan media:analyze-audios 500
 */
Artisan::command('media:analyze-audios {limit=100}', function (int $limit = 100) {
    $query = NewsItemMedia::where('type', 'audio')
        ->whereNull('duration_s')
        ->orderBy('id');

    $media = $query->limit($limit)->get();
    foreach ($media as $m) {
        ExtractAudioMetadata::dispatch($m);
    }

    $this->info($media->count().' Audio(s) für Metadaten-Extraktion in die Warteschlange gestellt.');
})->purpose('Bestehende Audios für ffprobe-Analyse in die Queue stellen');

/**
 * Bild-Kennzeichnung (Python/ONNX) für eine Meldung erneut anstoßen – z. B. nach Fehlern auf dem Medienpaket-Link.
 *
 * Beispiele:
 *   php artisan media:retry-redaction 14
 *   php artisan media:retry-redaction 14 --failed-only
 *   php artisan media:retry-redaction 14 --sync
 *
 * Ohne --sync: Jobs gehen in die Queue (queue:work muss laufen). Mit --sync: sofort nacheinander (kann bei vielen Bildern lange dauern).
 */
Artisan::command('media:retry-redaction {newsItemId} {--failed-only : Nur Bilder mit Status „fehlgeschlagen“} {--sync : Ohne Queue, nacheinander ausführen}', function (int $newsItemId) {
    $newsItem = NewsItem::findOrFail($newsItemId);
    $query = $newsItem->media()->where('type', 'image');
    if ($this->option('failed-only')) {
        $query->where('redaction_status', NewsItemMedia::REDACTION_FAILED);
    }
    $media = $query->orderBy('id')->get();
    $started = 0;
    $skippedDisabled = 0;
    foreach ($media as $m) {
        if ($m->redaction_status === NewsItemMedia::REDACTION_DISABLED) {
            $skippedDisabled++;

            continue;
        }
        $m->update([
            'redaction_status' => NewsItemMedia::REDACTION_PENDING,
            'redaction_error' => null,
        ]);
        $fresh = $m->fresh();
        if ($this->option('sync')) {
            ProcessMediaRedaction::dispatchSync($fresh);
        } else {
            ProcessMediaRedaction::dispatch($fresh);
        }
        $started++;
    }
    $this->info("Meldung #{$newsItemId}: {$started} Bild(er) neu angestoßen, {$skippedDisabled} übersprungen (Kennzeichnung deaktiviert).");
})->purpose('Bild-Kennzeichnung für eine Meldung erneut ausführen (Queue oder --sync)');

/**
 * Prüft Pfade für die automatische Kennzeichnung (Python, Skript, ONNX-Modell).
 * Hilft bei „Fehlgeschlagen“, wenn z. B. der Queue-Worker andere Pfade sieht als die Web-App.
 */
Artisan::command('redaction:diagnose', function () {
    $base = rtrim((string) config('redaction.ai_worker_path'), '/');
    $py = config('redaction.python_bin') ?: ($base.'/env/bin/python');
    $script = $base.'/scripts/'.config('redaction.script_name', 'detect_and_redact.py');
    $model = config('redaction.model_path') ?: ($base.'/models/plate_detector.onnx');
    $face = (string) config('redaction.face_model_path', '');

    $this->line('AI_WORKER_PATH / Konfiguration:');
    $this->line('  ai_worker_path: '.$base.' '.(is_dir($base) ? '✓' : '✗ fehlt'));
    $this->line('  python:         '.$py.' '.(is_file($py) ? '✓' : '✗ fehlt'));
    $this->line('  Skript:         '.$script.' '.(is_file($script) ? '✓' : '✗ fehlt'));
    $this->line('  Kennzeichen-Modell: '.$model.' '.(is_file($model) ? '✓' : '✗ fehlt (ohne Modell keine Auto-Erkennung)'));
    if ($face !== '') {
        $this->line('  Gesicht-Modell:     '.$face.' '.(is_file($face) ? '✓' : '✗ fehlt (wird an Skript übergeben)'));
    } else {
        $this->line('  Gesicht-Modell:     (nicht gesetzt – nur Kennzeichen-Erkennung)');
    }

    if (is_file($py) && is_file($script)) {
        $p = \Symfony\Component\Process\Process::fromShellCommandline(
            escapeshellarg($py).' '.escapeshellarg($script).' --help'
        );
        $p->run();
        if ($p->getExitCode() === 0) {
            $this->info('Skript antwortet auf --help (Argumente inkl. --face-model ok).');
        } else {
            $this->warn('Skript --help Exit '.$p->getExitCode().': '.$p->getErrorOutput());
        }
    }

    $this->newLine();
    $this->comment('Typische Ursachen für „Fehlgeschlagen“:');
    $this->line('  • Kein Kennzeichen erkannt → manuelle Boxen in der Medien-Bearbeitung zeichnen, dann Redaction erneut starten.');
    $this->line('  • Modell-Pfad falsch/leer → siehe „Kennzeichen-Modell“ oben.');
    $this->line('  • Queue-Worker als anderer User → keine Leserechte auf '.$base.'.');
    $this->line('  • Nur Kennzeichen werden automatisch erkannt; Gesichter aktuell nur per manuellen Boxen.');
})->purpose('Redaction/Kennzeichnung: Pfade und Abhängigkeiten prüfen');

// Cron- und Queue-Worker-Heartbeats für Ampel auf der Jobs-Seite
Schedule::call(function () {
    Cache::put('cron_heartbeat_at', now()->toIso8601String(), 300);
})->everyMinute()->name('cron_heartbeat');

Schedule::job(new \App\Jobs\QueueWorkerHeartbeatJob)->everyMinute()->name('queue_worker_heartbeat');

// Spatie Backup: tägliche Sicherung und tägliches Aufräumen
Schedule::command('backup:run --disable-notifications')
    ->dailyAt((string) config('backup.schedule.run_at', '02:00'))
    ->name('backup_run');

Schedule::command('backup:clean')
    ->dailyAt((string) config('backup.schedule.clean_at', '03:00'))
    ->name('backup_clean');

Schedule::command('inspire')->hourly();
