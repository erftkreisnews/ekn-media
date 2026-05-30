<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Eigenes Hostname für das Zeugen-Portal (optional)
    |--------------------------------------------------------------------------
    |
    | Wenn gesetzt (z. B. zeugen.erftkreis-news.media), werden die Upload-Routen
    | nur unter dieser Domain registriert. Sonst: /witness/e/{token} unter APP_URL.
    |
    */
    'portal_host' => env('WITNESS_PORTAL_HOST'),

    'portal_scheme' => env('WITNESS_PORTAL_SCHEME', 'https'),

    'disk' => env('WITNESS_DISK', 'local'),

    'max_file_kb' => (int) env('WITNESS_MAX_FILE_KB', 153600),

    /*
    | Max. Dateien pro Formular-Absenden (zusätzlich begrenzt durch verbleibende Slots am Link).
    */
    'max_files_per_submit' => (int) env('WITNESS_MAX_FILES_PER_SUBMIT', 20),

    /*
    | Muss bei inhaltlicher Änderung des Einwilligungstextes erhöht werden;
    | wird in jeder Einreichung gespeichert (Nachweis).
    */
    'legal_version' => env('WITNESS_LEGAL_VERSION', '2026-04-01-v1'),

    /*
    | Mindestzeit zwischen Formular-Aufruf und Absenden (Sekunden).
    */
    'min_seconds_before_submit' => (int) env('WITNESS_MIN_SECONDS', 4),

    'locale' => env('WITNESS_LOCALE', 'de'),

    /*
    | Anzeige im Feld „Fotograf“, wenn der Zeuge keine Nennung wünscht
    | und die Redaktion keinen eigenen Text einträgt.
    */
    'anonymous_photographer_label' => env(
        'WITNESS_ANONYMOUS_PHOTOGRAPHER_LABEL',
        'Leser / Zeuge (ohne Namensnennung)'
    ),

];
