<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Master-Bildgröße (max. Upload)
    |--------------------------------------------------------------------------
    |
    | Größte erlaubte Abmessungen für Pressebilder inkl. aktueller Smartphone-Originale.
    | Lange Kante max. 8064 px, kurze Kante max. 6048 px.
    | Wird für Upload-Validierung und Qualitätsprüfung verwendet.
    |
    */
    'image_master_long_edge_max' => (int) env('IMAGE_MASTER_LONG_EDGE_MAX', 8064),
    'image_master_short_edge_max' => (int) env('IMAGE_MASTER_SHORT_EDGE_MAX', 6048),
    'image_long_edge_min' => (int) env('IMAGE_LONG_EDGE_MIN', 1800),

    /*
    | Max. Dateigröße für Bild-Uploads in Kilobyte (Smartphone-Originale oft 8–20 MB).
    */
    'image_upload_max_kb' => (int) env('IMAGE_UPLOAD_MAX_KB', 20480), // 20 MB

    /*
    | News-Admin: Dateien pro HTTP-Request beim Bild-/Video-Upload (unter PHP max_file_uploads).
    | Größere Auswahlen werden im Browser automatisch in mehrere Requests aufgeteilt.
    */
    'news_upload_batch_size' => max(1, min(100, (int) env('NEWS_UPLOAD_BATCH_SIZE', 18))),

    /*
    | Max. Größe Audio-Uploads (News-Admin), Kilobyte. Standard 50 MB.
    */
    'audio_upload_max_kb' => (int) env('AUDIO_UPLOAD_MAX_KB', 51200),

    /*
    | Max. Größe Video-Uploads (News-Admin), Kilobyte. Standard 3 GB.
    */
    'video_upload_max_kb' => (int) env('VIDEO_UPLOAD_MAX_KB', 3145728),

    /*
    |--------------------------------------------------------------------------
    | Audio ÖRR-Qualität (Sendefähigkeit)
    |--------------------------------------------------------------------------
    |
    | Mindest-Bitrate in kbit/s für „ok“ (grün). Darunter = warning, keine Metadaten = Hinweis.
    | Typisch ÖRR/ARD: mind. 128 kbit/s für Sendung/Austausch.
    |
    */
    'audio_min_bitrate_kbps' => (int) env('AUDIO_MIN_BITRATE_KBPS', 128),

    /*
    |--------------------------------------------------------------------------
    | FFmpeg / FFprobe Binaries
    |--------------------------------------------------------------------------
    |
    | Pfade zu ffmpeg und ffprobe (ohne sudo). Auf dem Server ermitteln mit:
    |   which ffmpeg
    |   which ffprobe
    | Die Werte in .env als FFMPEG_PATH / FFPROBE_PATH oder MEDIA_FFPROBE_PATH setzen.
    |
    | Hinweis Hetzner: ffprobe kann z. B. unter
    |   /usr/home/admin/.linuxbrew/bin/ffprobe
    | liegen.
    |
    */
    'ffmpeg_path' => env('FFMPEG_PATH', '/usr/bin/ffmpeg'),
    'ffprobe_path' => env('FFPROBE_PATH', env('MEDIA_FFPROBE_PATH', 'ffprobe')),

    /*
    |--------------------------------------------------------------------------
    | Video-Stills (Auto-Extraktion für Presse)
    |--------------------------------------------------------------------------
    */
    'video_stills' => [
        'enabled' => (bool) env('VIDEO_STILLS_ENABLED', true),
        'count' => (int) env('VIDEO_STILLS_COUNT', 12),
        // Weniger Kandidaten + niedrigere Auflösung senken RAM/CPU von ffmpeg (verhindert oft OOM → Signal 9).
        'candidate_limit' => (int) env('VIDEO_STILLS_CANDIDATE_LIMIT', 40),
        // Mindestabstand zwischen Roh-Kandidaten in Sekunden; bei längeren Videos wird automatisch vergrößert,
        // damit bis zu candidate_limit Frames über die gesamte Länge verteilt werden (nicht nur der Anfang).
        'sampling_seconds' => (int) env('VIDEO_STILLS_SAMPLING_SECONDS', 1),
        'min_seconds_between' => (int) env('VIDEO_STILLS_MIN_SECONDS_BETWEEN', 2),
        // Laplacian-Mittelwert; 12 war für viele Smartphone-/Drohnen-Clips zu streng.
        'min_sharpness' => (float) env('VIDEO_STILLS_MIN_SHARPNESS', 6.0),
        // Strenger = weniger optisch gleiche Presseframes (vorher 6 / 4.0 → oft 5× dasselbe Motiv).
        'duplicate_phash_distance_max' => (int) env('VIDEO_STILLS_DUPLICATE_PHASH_DISTANCE_MAX', 4),
        'duplicate_frame_diff_max' => (float) env('VIDEO_STILLS_DUPLICATE_FRAME_DIFF_MAX', 2.5),
        // Automatischer Lauf überspringen, wenn für dieses Video schon Standbilder existieren (manueller Neu-Lauf ersetzt sie).
        'skip_if_existing_for_source_video' => filter_var(env('VIDEO_STILLS_SKIP_IF_EXISTING', true), FILTER_VALIDATE_BOOL),
        'max_motion_delta' => (float) env('VIDEO_STILLS_MAX_MOTION_DELTA', 32.0),
        'motion_penalty_weight' => (float) env('VIDEO_STILLS_MOTION_PENALTY_WEIGHT', 0.35),
        'long_edge' => (int) env('VIDEO_STILLS_LONG_EDGE', 2560),
        // Presse-Format: 3:2 (Breite:Höhe). „source“ = Seitenverhältnis des Videos beibehalten.
        'aspect_ratio' => (string) env('VIDEO_STILLS_ASPECT_RATIO', '3:2'),
        'max_bytes' => (int) env('VIDEO_STILLS_MAX_BYTES', 2097152),
        // FFmpeg: mehrere kurze Läufe statt einem riesigen (weniger Spitzenlast im Decoder/Filter).
        'extract_chunk_max_frames' => (int) env('VIDEO_STILLS_EXTRACT_CHUNK_MAX_FRAMES', 12),
        // Kindprozess-Timeout (Symfony); bei langen Clips sonst SIGKILL durch Timeout möglich.
        'extract_timeout_seconds' => (int) env('VIDEO_STILLS_EXTRACT_TIMEOUT', 600),
        // Laravel-Job-Timeout (Sek.) — muss > Extraktion + PHP-Nachbearbeitung sein.
        'queue_timeout_seconds' => (int) env('VIDEO_STILLS_QUEUE_TIMEOUT', 900),
        // FFmpeg Decoder/Filter-Parallelität begrenzen (RAM). 1 Thread = deutlich weniger OOM auf Shared Hosting.
        'ffmpeg_threads' => (int) env('VIDEO_STILLS_FFMPEG_THREADS', 1),
        'ffmpeg_filter_threads' => (int) env('VIDEO_STILLS_FFMPEG_FILTER_THREADS', 1),
        // Bei Fehler (Codec, OOM, Filter): zweiter Lauf mit leichter Kette (fps + kleines bilinear-scale, ohne Look/Unsharp).
        'fallback_on_ffmpeg_error' => filter_var(env('VIDEO_STILLS_FALLBACK_ON_FFMPEG_ERROR', true), FILTER_VALIDATE_BOOL),
        // Nach Signal 9 / OOM: gesamte Extraktion mit kleineren Chunks und weniger Kandidaten wiederholen.
        'fallback_on_oom' => filter_var(env('VIDEO_STILLS_FALLBACK_ON_OOM', true), FILTER_VALIDATE_BOOL),
        'oom_long_edge' => (int) env('VIDEO_STILLS_OOM_LONG_EDGE', 1920),
        'oom_candidate_limit' => (int) env('VIDEO_STILLS_OOM_CANDIDATE_LIMIT', 16),
        'oom_chunk_max_frames' => (int) env('VIDEO_STILLS_OOM_CHUNK_MAX_FRAMES', 8),
        'fallback_long_edge' => (int) env('VIDEO_STILLS_FALLBACK_LONG_EDGE', 1280),
        // Wenn nach Schärfe/Bewegung kein Kandidat übrig bleibt: zweiter Durchlauf mit lockeren Grenzen.
        'fallback_on_empty_scored' => filter_var(env('VIDEO_STILLS_FALLBACK_ON_EMPTY_SCORED', true), FILTER_VALIDATE_BOOL),
        'fallback_min_sharpness' => (float) env('VIDEO_STILLS_FALLBACK_MIN_SHARPNESS', 3.0),
        'fallback_max_motion_delta' => (float) env('VIDEO_STILLS_FALLBACK_MAX_MOTION_DELTA', 56.0),
        // Nachbearbeitung beim Extrakt (Kontrast/Sättigung/Unsharp). Standard AUS = 1:1 wie Videoplayer.
        // Nur aktivieren, wenn bewusst ein Presse-Look gewünscht ist (VIDEO_STILLS_APPLY_LOOK_FILTERS=true).
        'apply_look_filters' => filter_var(env('VIDEO_STILLS_APPLY_LOOK_FILTERS', false), FILTER_VALIDATE_BOOL),
        // Finale JPEGs per exaktem -ss neu aus dem Quellvideo (nicht nur fps-Kandidaten-JPEG).
        'exact_frame_extract' => filter_var(env('VIDEO_STILLS_EXACT_FRAME_EXTRACT', true), FILTER_VALIDATE_BOOL),
        // Mindestens N Standbilder speichern, wenn Dublettenfilter sonst alle Kandidaten verwirft (Job darf nicht scheitern).
        'force_save_min_count' => (int) env('VIDEO_STILLS_FORCE_SAVE_MIN', 1),
        'look' => [
            'contrast' => (float) env('VIDEO_STILLS_LOOK_CONTRAST', 1.10),
            'brightness' => (float) env('VIDEO_STILLS_LOOK_BRIGHTNESS', 0.02),
            'saturation' => (float) env('VIDEO_STILLS_LOOK_SATURATION', 1.10),
            // < 1.0 hebt Schatten leicht an (wirkt weniger „ausgewaschen“).
            'gamma' => (float) env('VIDEO_STILLS_LOOK_GAMMA', 0.94),
            'apply_unsharp' => filter_var(env('VIDEO_STILLS_LOOK_APPLY_UNSHARP', true), FILTER_VALIDATE_BOOL),
            // Sanftes Schärfen (nicht HDR-„Glanz“, aber knackiger als Roh-Frame).
            'unsharp' => (string) env('VIDEO_STILLS_LOOK_UNSHARP', '7:7:0.55:3:3:0.05'),
        ],
    ],

];

/*
|--------------------------------------------------------------------------
| Test-Befehle (Video-Metadaten / Queue)
|--------------------------------------------------------------------------
|
| 1) FFprobe-Pfad prüfen (Tinker):
|    php artisan tinker
|    >>> config('media.ffprobe_path')
|
| 2) Job erneut ausführen (Queue-Worker):
|    php artisan queue:work
|
*/
