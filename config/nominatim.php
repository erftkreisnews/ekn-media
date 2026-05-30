<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Nominatim (OpenStreetMap) – Server-Proxy
    |--------------------------------------------------------------------------
    |
    | Nutzungsbedingungen: https://operations.osmfoundation.org/policies/nominatim/
    | User-Agent muss Anwendung und Kontakt identifizieren.
    |
    */

    'base_url' => rtrim((string) env('NOMINATIM_BASE_URL', 'https://nominatim.openstreetmap.org'), '/'),

    'contact_email' => env('NOMINATIM_CONTACT_EMAIL', config('mail.from.address')),

    'user_agent' => env('NOMINATIM_USER_AGENT', ''),

    /*
    | Optional: ISO-3166-1 alpha-2, kommagetrennt (z. B. "de" oder "de,nl").
    | Leer = keine Einschränkung.
    */
    'countrycodes' => env('NOMINATIM_COUNTRYCODES', 'de'),

    'cache_ttl_seconds' => (int) env('NOMINATIM_CACHE_TTL', 3600),

];
