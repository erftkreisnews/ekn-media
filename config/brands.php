<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Multi-Brand Domain Routing
    |--------------------------------------------------------------------------
    |
    | false = bestehendes Routing bleibt unverändert aktiv.
    | true  = zusätzliche Domain-Routen für KoelnImage werden registriert.
    |
    */
    'domain_routing_enabled' => (bool) env('BRANDS_DOMAIN_ROUTING_ENABLED', false),

    'default_brand_key' => env('DEFAULT_BRAND_KEY', 'erftkreis_news'),

    'hosts' => [
        'erftkreis_news' => env('BRAND_ERFTKREIS_HOST', 'erftkreis-news.media'),
        'koelnimage' => env('BRAND_KOELNIMAGE_HOST', 'koelnimage.de'),
    ],

    /** Optional: z. B. @KölnimageHandle für Twitter-Cards (Search-Console / SEO-Tools). */
    'koelnimage_twitter_site' => env('KOELNIMAGE_TWITTER_SITE', ''),
];
