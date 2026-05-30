<?php

return [
    /*
    | Automatische Beweismittelmappe (ZIP) bei neuen Fundstellen.
    | Standard: nur bei Art „Urheberrechtsverstoß“.
    */
    'auto_build_on_store' => (bool) env('PUBLICATION_EVIDENCE_AUTO_BUILD', true),

    'auto_build_kinds' => array_filter(array_map(
        trim(...),
        explode(',', (string) env('PUBLICATION_EVIDENCE_AUTO_BUILD_KINDS', 'infringement'))
    )),

    'storage_directory' => trim((string) env('PUBLICATION_EVIDENCE_DIRECTORY', 'publication-evidence'), '/'),

    'yt_dlp_path' => env('YT_DLP_PATH', 'yt-dlp'),

    /** Zusätzliche yt-dlp-Argumente (z. B. JS-Runtime für YouTube). */
    'yt_dlp_extra_args' => array_values(array_filter(array_map(
        trim(...),
        explode(' ', (string) env('YT_DLP_EXTRA_ARGS', '--js-runtimes node:/usr/bin/node'))
    ))),

    'ffmpeg_path' => env('FFMPEG_PATH', config('media.ffmpeg_path', 'ffmpeg')),

    /*
    | Optional: Node-Skript für Screenshots von YouTube-Seite (Einblendung, Kanal).
    | Pfad relativ zu base_path(), z. B. scripts/youtube-evidence-screenshots.mjs
    */
    'playwright_script' => env('PUBLICATION_EVIDENCE_PLAYWRIGHT_SCRIPT'),

    'video_screenshot_seconds' => (float) env('PUBLICATION_EVIDENCE_VIDEO_SCREENSHOT_SEC', 3),

    /** Zusätzliche Screenshots aus dem heruntergeladenen Video (Sekunden, kommagetrennt). */
    'video_screenshot_extra_seconds' => array_filter(array_map(
        'floatval',
        array_map('trim', explode(',', (string) env('PUBLICATION_EVIDENCE_VIDEO_SCREENSHOT_EXTRA_SEC', '1,5,10')))
    )),

    /** Eigenes News-Video aus S3 in die Mappe kopieren (Bytes, 0 = aus). Standard 800 MB. */
    'max_own_video_bytes' => (int) env('PUBLICATION_EVIDENCE_MAX_OWN_VIDEO_BYTES', 838860800),

    /** YouTube-Rohvideo zusätzlich dauerhaft unter publication-evidence/{id}/archive/ ablegen. */
    'archive_youtube_to_storage' => (bool) env('PUBLICATION_EVIDENCE_ARCHIVE_YOUTUBE', true),

    /** Max. Upload manueller Beweise in KB (Standard 500 MB). */
    'manual_upload_max_kb' => (int) env('PUBLICATION_EVIDENCE_MANUAL_UPLOAD_MAX_KB', 512000),

    /** Behördenzugang (Polizei / STA): Gültigkeit des Portal-Links in Tagen. */
    'authority_link_days' => (int) env('PUBLICATION_EVIDENCE_AUTHORITY_LINK_DAYS', 30),

    /** Gültigkeit des signierten Download-Links auf der Behörden-Seite (Minuten). */
    'authority_download_url_minutes' => (int) env('PUBLICATION_EVIDENCE_AUTHORITY_DOWNLOAD_MINUTES', 60),
];
