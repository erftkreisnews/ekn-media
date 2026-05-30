<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Klassischer E-Mail/Passwort-Login
    |--------------------------------------------------------------------------
    |
    | LOCAL_LOGIN_ENABLED: true = Anmeldung mit E-Mail/Passwort erlaubt (Standard).
    | false = nur Microsoft-Login (LOCAL_LOGIN_* in .env).
    |
    | LOCAL_LOGIN_ALLOWED_EMAILS: optional, kommagetrennt. Wenn nicht leer, nur diese
    | Adressen dürfen den lokalen Login nutzen (zusätzliche Einschränkung).
    |
    */

    'enabled' => env('LOCAL_LOGIN_ENABLED', true),

    'allowed_emails' => array_values(array_filter(array_map(
        static fn (string $e): string => strtolower(trim($e)),
        array_map('trim', explode(',', (string) env('LOCAL_LOGIN_ALLOWED_EMAILS', '')))
    ))),
];
