<?php

// Basis-URL der API V2 (muss auf …/api/v2 enden). Nur Host ohne Pfad wird unten ergänzt.
$base = env('PRESSEPORTAL_API_BASE_URL', 'https://api.presseportal.de/api/v2');
$base = rtrim((string) $base, '/');
if (preg_match('~^https?://api\.presseportal\.(?:de|ch)$~i', $base)) {
    $base .= '/api/v2';
}

return [

    'api_key' => env('PRESSEPORTAL_API_KEY'),

    'base_url' => $base,

    'timeout' => (int) env('PRESSEPORTAL_API_TIMEOUT', 20),

    // Fallback: bei Einzelabruf „Ressource unknown“ (Code 200) Meldungen in der Dienststellen-Liste suchen
    'office_list_max_pages' => 40,

    'office_list_page_size' => 50,

];
