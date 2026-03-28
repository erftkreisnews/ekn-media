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
];
