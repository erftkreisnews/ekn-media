<?php

return [
    'base_url' => env('LEXWARE_BASE_URL', ''),
    // Alias: Code und Blade prüfen teils api_key, der Client nutzt api_token.
    'api_token' => env('LEXWARE_API_TOKEN', env('LEXWARE_API_KEY', '')),
    'api_key' => env('LEXWARE_API_TOKEN', env('LEXWARE_API_KEY', '')),
    'timeout' => (int) env('LEXWARE_TIMEOUT', 20),
    /** Web-App (Deeplinks „Rechnung in Lexware bearbeiten“), nicht die API-Base-URL */
    'app_base_url' => rtrim((string) (env('LEXWARE_APP_BASE_URL') ?: 'https://app.lexware.de'), '/'),
];
