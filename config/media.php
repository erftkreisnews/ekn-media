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
    'image_long_edge_min' => (int) env('IMAGE_LONG_EDGE_MIN', 3500),

    /*
    | Max. Dateigröße für Bild-Uploads in Kilobyte (Smartphone-Originale oft 8–20 MB).
    */
    'image_upload_max_kb' => (int) env('IMAGE_UPLOAD_MAX_KB', 20480), // 20 MB

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
