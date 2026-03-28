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

    // Optional: globales Rate-Limit für Bild-KI (Jobs pro Minute)
    // Leicht erhöht, damit mehrere Bilder am Stück flüssig analysiert werden können.
    // Default konservativ, damit OpenAI (429) nicht zu schnell eskaliert.
    'rate_limit_per_minute' => env('MEDIA_AI_RATE_LIMIT_PER_MINUTE', 3),

    // API-Timeout in Sekunden (Analyse dauert typisch unter 10–20 s)
    'timeout' => (int) env('MEDIA_AI_TIMEOUT', 20),

    // Retries bei temporären Fehlern
    'retries' => (int) env('MEDIA_AI_RETRIES', 1),

    // Auto-Analyse: Bei Bild-Upload automatisch KI-Metadaten-Job starten
    'auto_analyze' => env('MEDIA_AI_AUTO_ANALYZE', true),

];
