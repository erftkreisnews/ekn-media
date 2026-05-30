<?php

/**
 * Täglicher Abruf von öffentlich gelisteten Großveranstaltungen (Sport, Konzerte, Show)
 * als Vorschläge für die geplante Veranstaltungsplanung.
 *
 * Cron (serverseitig): * * * * * cd /pfad/zum/projekt && php artisan schedule:run >> /dev/null 2>&1
 *
 * @see https://developer.ticketmaster.com/products-and-docs/apis/discovery-api/v2/
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Aktivierung
    |--------------------------------------------------------------------------
    */
    'enabled' => (bool) env('EVENT_DISCOVERY_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Zeitzone & Uhrzeit (Laravel Scheduler)
    |--------------------------------------------------------------------------
    */
    'timezone' => env('EVENT_DISCOVERY_TIMEZONE', 'Europe/Berlin'),

    'run_at' => env('EVENT_DISCOVERY_RUN_AT', '06:00'),

    /*
    |--------------------------------------------------------------------------
    | Ticketmaster Discovery API (kostenloser Entwickler-Schlüssel)
    |--------------------------------------------------------------------------
    |
    | Ohne Schlüssel wird der Abruf übersprungen (nur Log-Hinweis).
    |
    */
    'ticketmaster' => [
        'api_key' => env('EVENT_DISCOVERY_TICKETMASTER_KEY', env('TICKETMASTER_API_KEY')),
        'country_code' => env('EVENT_DISCOVERY_COUNTRY', 'DE'),
        'locale' => env('EVENT_DISCOVERY_LOCALE', 'de-de'),
        /*
         * Anzahl Tage ab heute (lokale Zeitzone) für die Suche.
         */
        'lookahead_days' => max(1, min(180, (int) env('EVENT_DISCOVERY_LOOKAHEAD_DAYS', 60))),
        /*
         * Maximale Events pro Segment (es werden mehrere Segment-Requests zusammengeführt).
         */
        'page_size' => max(1, min(200, (int) env('EVENT_DISCOVERY_PAGE_SIZE', 40))),
        /*
         * Segment-IDs (Discovery API): Sport, Music, Arts & Theatre …
         */
        'segment_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'EVENT_DISCOVERY_SEGMENT_IDS',
            'KZFzniwnSyZfZ7v7nE,KZFzniwnSyZfZ7v7na,KZFzniwnSyZfZ7v7nJ'
        ))))),
        /*
         * Geo-Radius um latlong (Ticketmaster Discovery). Reduziert „ganz Deutschland“.
         * Standard: Zentrum Rheinland + Radius, der Köln, Bonn, Aachen, Düsseldorf und Nürburgring grob abdeckt.
         */
        'geo_enabled' => (bool) env('EVENT_DISCOVERY_TM_GEO_ENABLED', true),
        'latlong' => env('EVENT_DISCOVERY_TM_LATLONG', '50.94,6.92'),
        'radius_km' => max(5, min(500, (int) env('EVENT_DISCOVERY_TM_RADIUS_KM', 115))),
        /*
         * Zusätzliche Textsuche im Veranstaltungsort (Stadt/Ort/Adresszeile/Venue-Name), kommagetrennt.
         * Leer = nur Geo-Filter (es können dann z. B. noch andere NRW-Städte im Kreis vorkommen).
         */
        'city_allowlist' => array_values(array_filter(array_map(static function (string $s): string {
            return mb_strtolower(trim($s), 'UTF-8');
        }, explode(',', (string) env(
            'EVENT_DISCOVERY_TM_CITY_ALLOWLIST',
            'Köln,Cologne,Düsseldorf,Dusseldorf,Aachen,Bonn,Nürburg,Nürburgring,Meuspath'
        ))))),
        /*
         * false = nur Geo-Filter, keine Text-Whitelist (mehr Treffer im Kreis, z. B. auch Leverkusen).
         */
        'use_city_allowlist' => (bool) env('EVENT_DISCOVERY_TM_USE_CITY_ALLOWLIST', true),
        /*
         * Mehrere Seiten pro Segment abrufen (Ticketmaster paginiert mit „size“).
         */
        'max_pages' => max(1, min(25, (int) env('EVENT_DISCOVERY_TM_MAX_PAGES', 8))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Optionale RSS-/Atom-Feeds (z. B. lokale Kalender mit öffentlicher URL)
    |--------------------------------------------------------------------------
    |
    | Komma-getrennte URLs. Jeder Feed-Eintrag (item) wird als Vorschlag gespeichert.
    |
    */
    'rss_feed_urls' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'EVENT_DISCOVERY_RSS_FEEDS',
        ''
    ))))),

    /*
    |--------------------------------------------------------------------------
    | LANXESS arena Köln (öffentlicher JSON-Export des Eventkalenders)
    |--------------------------------------------------------------------------
    |
    | Strukturierte Termine direkt von der Arena-Website — unabhängig von Ticketshops.
    | Standard-URL (Stand 2026): …/eventkalender/export/JSON/out.json
    |
    */
    'lanxess_arena' => [
        'enabled' => (bool) env('EVENT_DISCOVERY_LANXESS_ARENA_ENABLED', true),
        'json_url' => env(
            'EVENT_DISCOVERY_LANXESS_ARENA_JSON_URL',
            'https://www.lanxess-arena.de/events-tickets/eventkalender/export/JSON/out.json'
        ),
        'venue_name' => env('EVENT_DISCOVERY_LANXESS_ARENA_VENUE_NAME', 'LANXESS arena'),
        'venue_street' => env('EVENT_DISCOVERY_LANXESS_ARENA_STREET', 'Willy-Brandt-Platz 3'),
        'venue_postal_code' => env('EVENT_DISCOVERY_LANXESS_ARENA_POSTAL', '50679'),
        'venue_city' => env('EVENT_DISCOVERY_LANXESS_ARENA_CITY', 'Köln'),
        'venue_state' => env('EVENT_DISCOVERY_LANXESS_ARENA_STATE', 'Nordrhein-Westfalen'),
        'venue_country' => env('EVENT_DISCOVERY_LANXESS_ARENA_COUNTRY', 'Deutschland'),
        'venue_country_code' => env('EVENT_DISCOVERY_LANXESS_ARENA_CC', 'DE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | E-Mail nach dem Abruf
    |--------------------------------------------------------------------------
    |
    | Komma-getrennte Adressen. Wird nur versandt, wenn mindestens ein neuer
    | Eintrag seit dem letzten Lauf angelegt wurde.
    |
    */
    'mail_recipients' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'EVENT_DISCOVERY_MAIL_TO',
        ''
    ))))),
];
