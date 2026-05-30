<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Aktive Medien-Disk
    |--------------------------------------------------------------------------
    |
    | Neue Uploads werden auf dieser Disk gespeichert.
    | Standardmäßig folgt sie FILESYSTEM_DISK, kann aber separat überschrieben
    | werden (z. B. MEDIA_DISK=s3).
    |
    */
    'disk' => env('MEDIA_DISK', env('FILESYSTEM_DISK', 'public')),

    /*
    |--------------------------------------------------------------------------
    | Legacy-/Fallback-Disk
    |--------------------------------------------------------------------------
    |
    | Altbestände liegen historisch auf "public". Diese Disk wird als
    | Lese-Fallback genutzt, wenn Dateien auf der aktiven Disk fehlen.
    |
    */
    'fallback_disk' => env('MEDIA_FALLBACK_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Streaming: Presigned URLs (S3-kompatibel)
    |--------------------------------------------------------------------------
    |
    | true: Video/Audio nutzen bei S3 direkte temporaryUrl (Browser → Storage, schnell).
    | false: Immer Laravel-Stream (langsamer, falls Bucket-CORS nicht konfigurierbar ist).
    |
    */
    'prefer_presigned_streaming' => env('MEDIA_PREFER_PRESIGNED_STREAMING', true),

    /*
    |--------------------------------------------------------------------------
    | Bild-Editor: Original direkt von S3 (Presigned URL)
    |--------------------------------------------------------------------------
    |
    | true: Browser lädt das Original direkt vom Object Storage (schnell, kein
    | Kopieren auf den App-Server). Fallback bleibt die Same-Origin-Proxy-Route.
    | Voraussetzung: S3-CORS für die Admin-Domain (GET/HEAD), z. B.:
    |   AllowedOrigins: https://erftkreis-news.media
    |   AllowedMethods: GET, HEAD
    |   AllowedHeaders: *
    |
    */
    'editor_use_presigned_source' => env('MEDIA_EDITOR_USE_PRESIGNED_SOURCE', env('MEDIA_PREFER_PRESIGNED_STREAMING', true)),

    /** Gültigkeit der Presigned-URL für den Editor (Minuten). */
    'editor_presigned_ttl_minutes' => (int) env('MEDIA_EDITOR_PRESIGNED_TTL_MINUTES', 30),
];
