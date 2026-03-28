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

return [

    'enabled' => env('INGEST_ENABLED', false),

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
    */
    'allowed_extensions' => ['mp4', 'mov', 'mxf', 'mkv', 'avi', 'mts', 'm2ts'],

    /*
    | Sendefähige MP4 (Ziel nach Render) – konsistent mit Projektvorgaben.
    */
    'output' => [
        'video_bitrate' => env('INGEST_OUTPUT_VIDEO_BITRATE', '12M'),
        'audio_bitrate' => env('INGEST_OUTPUT_AUDIO_BITRATE', '256k'),
        'audio_sample_rate' => (int) env('INGEST_OUTPUT_AUDIO_HZ', 48000),
        'fps' => env('INGEST_OUTPUT_FPS', '25'),
        'max_width' => (int) env('INGEST_OUTPUT_MAX_WIDTH', 1920),
        'max_height' => (int) env('INGEST_OUTPUT_MAX_HEIGHT', 1080),
        'pixel_format' => 'yuv420p',
        'movflags' => '+faststart',
    ],

    /*
    | Nach erfolgreicher Übernahme ins News-System: Rohclips archivieren (kopieren).
    */
    'archive_raw_after_success' => env('INGEST_ARCHIVE_RAW_AFTER_SUCCESS', false),

];
