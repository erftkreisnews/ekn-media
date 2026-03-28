<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Klassischer E-Mail/Passwort-Login
    |--------------------------------------------------------------------------
    |
    | Über diese Einstellungen kann gesteuert werden, ob der klassische
    | E-Mail/Passwort-Login erlaubt ist. Standard: deaktiviert.
    |
    | Optional können bestimmte E-Mail-Adressen explizit für den lokalen
    | Login freigeschaltet werden (z.B. ein technischer Notfall-Account).
    |
    */

    'enabled' => env('LOCAL_LOGIN_ENABLED', false),

    'allowed_emails' => array_filter(array_map('trim', explode(',', (string) env('LOCAL_LOGIN_ALLOWED_EMAILS', '')))),
];
