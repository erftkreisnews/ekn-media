<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OpenAI API Konfiguration für Bild-Metadaten
    |--------------------------------------------------------------------------
    */

    'api_key' => env('OPENAI_API_KEY'),

    'organization' => env('OPENAI_ORG'),

    // Vision-fähiges Modell, z. B. gpt-4.1-mini
    'vision_model' => env('OPENAI_MODEL_VISION', 'gpt-4.1-mini'),

    // Längere Kante des an die KI gesendeten Preview-JPEG (höher = mehr Detail, teurer/langsamer)
    'vision_preview_long_edge' => (int) env('MEDIA_AI_VISION_PREVIEW_LONG_EDGE', 2048),

    'vision_preview_jpeg_quality' => (int) env('MEDIA_AI_VISION_PREVIEW_JPEG_QUALITY', 88),

    // Optional: globales Rate-Limit für Bild-KI (Jobs pro Minute)
    // Leicht erhöht, damit mehrere Bilder am Stück flüssig analysiert werden können.
    // Default konservativ, damit OpenAI (429) nicht zu schnell eskaliert.
    'rate_limit_per_minute' => env('MEDIA_AI_RATE_LIMIT_PER_MINUTE', 3),

    // API-Timeout in Sekunden (Vision kann bei großen Kontexten länger brauchen)
    'timeout' => (int) env('MEDIA_AI_TIMEOUT', 75),

    // Retries bei temporären Fehlern
    'retries' => (int) env('MEDIA_AI_RETRIES', 1),

    // Auto-Analyse: Bei Bild-Upload automatisch KI-Metadaten-Job starten
    'auto_analyze' => env('MEDIA_AI_AUTO_ANALYZE', true),

    // Referenzbild-Abgleich (Lackierung/Stoßstangen-Sponsoren vs. ADAC-Referenzfoto), wenn keine
    // eindeutige Karosserie-Startnummer sichtbar ist
    'reference_match_enabled' => filter_var(env('MEDIA_AI_REFERENCE_MATCH_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    'reference_match_max_candidates' => (int) env('MEDIA_AI_REFERENCE_MATCH_MAX_CANDIDATES', 10),

    // Bildunterschriften: agency (Gruppe C / Getty-ähnlich), imago, legacy
    'caption_style' => env('MEDIA_AI_CAPTION_STYLE', 'agency'),

    /** @deprecated Nutze MEDIA_AI_CAPTION_STYLE=imago|legacy */
    'imago_style_captions' => filter_var(env('MEDIA_AI_IMAGO_STYLE_CAPTIONS', true), FILTER_VALIDATE_BOOLEAN),
    'reference_match_min_confidence' => env('MEDIA_AI_REFERENCE_MATCH_MIN_CONFIDENCE', 'medium'),

];
