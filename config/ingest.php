<?php

use App\Services\Ingest\IngestPathResolver;

/*
|--------------------------------------------------------------------------
| Ingest – zentrale Pfade (Video-Rohmaterial)
|--------------------------------------------------------------------------
|
| Scan-Ziel ist ausschließlich config('ingest.paths.inbox') – ermittelt über
| IngestPathResolver (eine Stelle, keine String-Konkatenation im restlichen Code).
|
| INGEST_ROOT_PATH
|   Optional. Basisverzeichnis der Installation bzw. des Medien-Stamms, z. B.
|   /usr/home/admin/public_html/media/laravel12
|   Daraus wird die Inbox als {ROOT}/upload abgeleitet (entspricht /laravel12/upload).
|
| INGEST_INBOX_PATH
|   Optional. Absoluter Pfad zum Inbox-Ordner. Hat Vorrang vor INGEST_ROOT_PATH.
|   Bestehende Deployments, die nur diese Variable setzen, verhalten sich unverändert.
|
| Wenn weder INGEST_INBOX_PATH noch INGEST_ROOT_PATH gesetzt ist:
|   Inbox = storage_path('app/ingest/inbox') (Laravel-Standard wie bisher).
|
| Unterordner: Der Scan (IngestScanService) liest nur die oberste Ebene der Inbox,
| keine rekursive Suche (siehe Dokumentation in docs/VIDEO_INGEST.md).
|
*/

$defaultInboxPath = storage_path('app/ingest/inbox');

$inboxResolved = IngestPathResolver::resolveInboxPath(
    env('INGEST_INBOX_PATH'),
    env('INGEST_ROOT_PATH'),
    $defaultInboxPath
);

$ingestLowMemoryProfile = filter_var(env('INGEST_LOW_MEMORY_PROFILE', false), FILTER_VALIDATE_BOOLEAN);

return [

    'enabled' => env('INGEST_ENABLED', false),

    /*
    | INGEST_LOW_MEMORY_PROFILE=true: nur wenn INGEST_OUTPUT_X264_PRESET nicht gesetzt ist → Default ultrafast
    | (weniger Encoder-RAM). Sende-Vorgaben (1920×1080p, 12,4 Mbit/s, 50 fps) werden dadurch nicht verkleinert.
    */
    'low_memory_profile' => $ingestLowMemoryProfile,

    /*
    | Wie paths.inbox gewählt wurde (für Admin-Hinweise): explicit_inbox | root_upload | default_storage
    */
    'inbox_path_source' => $inboxResolved['source'],

    /*
    | Lokale Verzeichnisse auf dem MC60 / Ingest-Server (absolute Pfade).
    | Rohmaterial bleibt hier, bis Render + S3 erfolgreich sind.
    | Hinweis: paths.inbox entspricht dem effektiven Scan-Ordner (siehe Auflösung oben).
    */
    'paths' => [
        'inbox' => $inboxResolved['path'],
        'processing' => env('INGEST_PROCESSING_PATH', storage_path('app/ingest/processing')),
        'thumbs' => env('INGEST_THUMBS_PATH', storage_path('app/ingest/processing/thumbs')),
        'rendered' => env('INGEST_RENDERED_PATH', storage_path('app/ingest/rendered')),
        'archive' => env('INGEST_ARCHIVE_PATH', storage_path('app/ingest/archive')),
        'failed' => env('INGEST_FAILED_PATH', storage_path('app/ingest/failed')),
        'tmp' => env('INGEST_TMP_PATH', storage_path('app/ingest/tmp')),
    ],

    /*
    | Datei gilt als „stabil“, wenn Größe/Mtime zwischen zwei Prüfungen unverändert bleibt.
    */
    'stable_check_interval_seconds' => (int) env('INGEST_STABLE_INTERVAL', 2),
    'stable_checks_required' => (int) env('INGEST_STABLE_CHECKS', 2),

    /*
    | Nur Dateien mit diesen Endungen aus der Inbox übernehmen (klein geschrieben).
    | JPEG: Einlesen für News (Zuordnung, ggf. in Sendefassung als kurzes Standbild-Video).
    */
    'allowed_extensions' => ['mp4', 'mov', 'mxf', 'mkv', 'avi', 'mts', 'm2ts', 'jpg', 'jpeg'],

    /*
    | Standbilder (JPEG) werden beim Finalrender zu einem kurzen MP4-Segment (Dauer in Sekunden).
    */
    'image_still_seconds' => (float) env('INGEST_IMAGE_STILL_SECONDS', 5),

    /*
    | Lokale Ingest-Thumbs (für Admin-Sichtung) – bewusst nicht im Object Storage.
    */
    'thumbs' => [
        'enabled' => filter_var(env('INGEST_THUMBS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'max_width' => (int) env('INGEST_THUMBS_MAX_WIDTH', 480),
        'quality' => (int) env('INGEST_THUMBS_QUALITY', 60),
        'generate_video_posters' => filter_var(env('INGEST_VIDEO_POSTERS', true), FILTER_VALIDATE_BOOLEAN),
        'video_poster_seek_seconds' => (float) env('INGEST_VIDEO_POSTER_SEEK', 1.0),
        'video_poster_timeout_seconds' => (int) env('INGEST_VIDEO_POSTER_TIMEOUT', 90),
    ],

    /*
    | Browser-Vorschau pro Ingest-Clip (leichtes MP4, Admin-UI — standard lokal auf „public“, nicht S3).
    */
    'preview' => [
        'disk' => env('INGEST_PREVIEW_DISK', 'public'),
        'auto_after_validation' => filter_var(env('INGEST_PREVIEW_AUTO', true), FILTER_VALIDATE_BOOLEAN),
        'max_seconds' => max(5, (int) env('INGEST_PREVIEW_MAX_SECONDS', 60)),
        'max_width' => max(320, (int) env('INGEST_PREVIEW_MAX_WIDTH', 960)),
        'max_height' => max(180, (int) env('INGEST_PREVIEW_MAX_HEIGHT', 540)),
        'video_bitrate' => (string) env('INGEST_PREVIEW_VIDEO_BITRATE', '2M'),
        'audio_bitrate' => (string) env('INGEST_PREVIEW_AUDIO_BITRATE', '128k'),
        'x264_preset' => (string) env('INGEST_PREVIEW_X264_PRESET', 'veryfast'),
        'ffmpeg_threads' => max(1, (int) env('INGEST_PREVIEW_FFMPEG_THREADS', 1)),
        'timeout_seconds' => max(120, (int) env('INGEST_PREVIEW_TIMEOUT_SECONDS', 600)),
    ],

    /*
    | Sendefähige MP4 – Mindestvorgabe: 1920×1080p, 12,4 Mbit/s, 50 fps (progressiv).
    */
    'output' => [
        // Nur Video; Gesamtbitrate in MediaInfo ≈ Video + Audio (z. B. 12,4 M + ~0,26 M ≈ 12,6 Mbit/s).
        'video_bitrate' => env('INGEST_OUTPUT_VIDEO_BITRATE', '12.4M'),
        'audio_bitrate' => env('INGEST_OUTPUT_AUDIO_BITRATE', '256k'),
        'audio_sample_rate' => (int) env('INGEST_OUTPUT_AUDIO_HZ', 48000),
        // Vorgaben Audio: 48 kHz / 4 Kanäle (typisch FX6 u. ä.: 1 Handmicro, 2 Admo, 3–4 Intern)
        'audio_channels' => (int) env('INGEST_OUTPUT_AUDIO_CHANNELS', 4),
        'audio_channel_layout' => env('INGEST_OUTPUT_AUDIO_CHANNEL_LAYOUT', 'quad'),
        'fps' => env('INGEST_OUTPUT_FPS', '50'),
        'max_width' => (int) env('INGEST_OUTPUT_MAX_WIDTH', 1920),
        'max_height' => (int) env('INGEST_OUTPUT_MAX_HEIGHT', 1080),
        // false = 1080p; true = 1080i-Feld-Encoding (nur wenn Sender explizit Interlace will)
        'force_interlaced' => filter_var(env('INGEST_OUTPUT_FORCE_INTERLACED', false), FILTER_VALIDATE_BOOLEAN),
        'pixel_format' => 'yuv420p',
        'movflags' => '+faststart',
        // scale-Filter: fast_bilinear = weniger RAM als Standard-Lanczos
        'scale_flags' => env('INGEST_SCALE_FLAGS', 'fast_bilinear'),

        /*
        | Speicher / Parallelität: libx264 nutzt sonst oft viele Threads (z. B. 8–12) und hohen
        | rc_lookahead → bei vielen Clips nacheinander eher OOM/SIGKILL. Niedrigere Werte =
        | langsamer, aber stabiler auf kleinen Servern.
        */
        'ffmpeg_threads' => max(1, (int) env('INGEST_FFMPEG_THREADS', 1)),
        'ffmpeg_filter_threads' => max(1, (int) env('INGEST_FFMPEG_FILTER_THREADS', 1)),
        'x264_threads' => max(1, (int) env('INGEST_OUTPUT_X264_THREADS', 1)),
        // 0 = wenig RAM (10+ erhöht Encoder-Puffer bei kleinen Servern → OOM/SIGKILL)
        'x264_rc_lookahead' => max(0, (int) env('INGEST_OUTPUT_X264_RC_LOOKAHEAD', 0)),
        // ref=1 spart Referenz-Frame-Puffer; Qualität minimal geringer
        'x264_ref' => max(1, (int) env('INGEST_OUTPUT_X264_REF', 1)),
        // veryfast/ultrafast = weniger Encoder-RAM als „medium“; Low-Memory-Profil: ultrafast
        'x264_preset' => env('INGEST_OUTPUT_X264_PRESET') !== null && env('INGEST_OUTPUT_X264_PRESET') !== ''
            ? (string) env('INGEST_OUTPUT_X264_PRESET')
            : ($ingestLowMemoryProfile ? 'ultrafast' : 'veryfast'),
        // B-Frames = 0 spart Referenzpuffer (RAM)
        'x264_bframes' => max(0, (int) env('INGEST_OUTPUT_X264_BFRAMES', 0)),
        // typisch in Größenordnung der Ziel-Bitrate (12,4 Mbit/s)
        'vbv_bufsize' => (string) env('INGEST_OUTPUT_VBV_BUFSIZE', '12.4M'),

        /*
        | Optional: Nur Normalisierung/Segmente — Kanäle begrenzen (z. B. 2) spart RAM bei aformat/upmix.
        | Null = wie audio_channels (z. B. 4). Für volle Ausgabe: nicht setzen oder leer.
        */
        'normalize_audio_channels' => env('INGEST_NORMALIZE_AUDIO_CHANNELS') !== null && env('INGEST_NORMALIZE_AUDIO_CHANNELS') !== ''
            ? max(1, (int) env('INGEST_NORMALIZE_AUDIO_CHANNELS'))
            : null,

        /*
        | Final-MP4 aus dem Ingest-Render: automatisch in mehrere Teile splitten, wenn die
        | geschätzte Dateigröße das Limit überschreitet (Bitrate × Dauer aller Clips im Teil).
        | Dateiname: …_teil1_AF(…).mp4, _teil2_… (IngestSendefassungFilenameService).
        */
        'max_output_gigabytes' => max(0.1, (float) env('INGEST_OUTPUT_MAX_GIGABYTES', 0.9)),
        'size_estimate_overhead' => max(1.0, (float) env('INGEST_OUTPUT_SIZE_OVERHEAD', 1.08)),
    ],

    /*
    | Wenn ein Video-Clip einer Meldung zugeordnet wird:
    | automatisch "für Finalschnitt" markieren, damit er im Render-Workspace bereits
    | ausgewählt/aktiv ist.
    */
    'auto_select_assigned_videos' => env('INGEST_AUTO_SELECT_ASSIGNED_VIDEOS', true),

    /*
    | Nach erfolgreichem Final-Render:
    |  - true: Rohclips in ingest.paths.archive verschieben (archivieren)
    |  - false: Rohclips löschen (Platz sparen)
    */
    'archive_raw_after_success' => filter_var(env('INGEST_ARCHIVE_RAW_AFTER_SUCCESS', false), FILTER_VALIDATE_BOOL),

    /*
    | Nach erfolgreichem Finalrender: Workspace/Sichtung leeren (kein Mischen mit neuem Material).
    | Rohdatei: löschen (Standard) oder ins Archiv (archive_raw_after_success=true).
    */
    'auto_clear_after_render' => [
        'enabled' => filter_var(env('INGEST_AUTO_CLEAR_AFTER_RENDER', true), FILTER_VALIDATE_BOOL),
        'detach_news_item_link' => filter_var(env('INGEST_CLEAR_DETACH_NEWS_LINK', true), FILTER_VALIDATE_BOOL),
        'purge_previews' => filter_var(env('INGEST_CLEAR_PURGE_PREVIEWS', true), FILTER_VALIDATE_BOOL),
        'purge_posters' => filter_var(env('INGEST_CLEAR_PURGE_POSTERS', true), FILTER_VALIDATE_BOOL),
    ],

    /*
    | Automatisches Aufräumen per `ingest:cleanup` (Cron): Dateien älter als X Stunden.
    | 0 = diese Stelle nicht anfassen (z. B. Inbox-Aufräumen deaktivieren).
    | Inbox: alles, was der Scan nicht übernommen hat (falsche Endung, Duplikat, Bilder …).
    */
    'cleanup' => [
        'inbox_max_age_hours' => (int) env('INGEST_INBOX_MAX_AGE_HOURS', 24),
        'tmp_max_age_hours' => (int) env('INGEST_TMP_MAX_AGE_HOURS', 24),
        'failed_max_age_hours' => (int) env('INGEST_FAILED_MAX_AGE_HOURS', 24),
    ],

    /*
    | Aktive Render-Jobs (queued/rendering/uploading) blockieren neue Jobs für dieselbe Meldung.
    | Sehr alte Einträge werden automatisch auf „failed“ gesetzt (Workspace öffnen / neuen Job starten).
    */
    'stale_job' => [
        'queued_after_minutes' => max(5, (int) env('INGEST_STALE_QUEUED_MINUTES', 30)),
        'rendering_uploading_after_minutes' => max(15, (int) env('INGEST_STALE_ACTIVE_MINUTES', 300)),
    ],

];
