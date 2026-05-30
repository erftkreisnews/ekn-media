<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Automatischer Bild-Monitor (TinEye o. ä.)
    |--------------------------------------------------------------------------
    | Standard: aus. Manuelle Recherche über Google Lens / News in der Mediathek.
    */
    'enabled' => (bool) env('PUBLICATION_MONITOR_ENABLED', false),

    'tineye' => [
        'api_url' => env('TINEYE_API_URL', 'https://api.tineye.com/rest/'),
        'username' => env('TINEYE_USERNAME'),
        'api_key' => env('TINEYE_API_KEY'),
    ],
];
