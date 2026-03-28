<?php

return [
    'delivery_recipient' => env('DELIVERY_RECIPIENT', 'afranz@erftkreis-news.de'),

    /*
    | WDR (Westdeutscher Rundfunk): Bei MoID darf nur an WDR versendet werden.
    | - organization_names: Kontakte mit Organisation, deren Name einen dieser Strings enthält, gelten als WDR.
    | - allowed_emails: Diese E-Mail-Adressen/Domains (z. B. @wdr.de) gelten immer als WDR-Empfänger.
    */
    'wdr_organization_names' => ['WDR', 'Westdeutscher Rundfunk'],
    'wdr_allowed_emails' => [], // z. B. ['newsroom@wdr.de'] oder Domains prüfen

    /*
    | IPTC-Metadaten für Urheberschaft (werden in die Original-JPG beim Speichern geschrieben, bleiben beim Download erhalten).
    */
    'iptc_credit' => env('IPTC_CREDIT', 'Erftkreis News'),
    'iptc_copyright' => env('IPTC_COPYRIGHT', '© Erftkreis News. Alle Rechte vorbehalten.'),

    /*
    | Doppelte Download-Events (gleicher Versand + gleiche Datei innerhalb weniger Sekunden)
    | unterdrücken – z. B. Doppelklick oder zweiter Request durch den Browser.
    | 0 = jeder Download wird geloggt (strikte Nachverfolgung).
    */
    'delivery_download_dedupe_seconds' => (int) env('DELIVERY_DOWNLOAD_DEDUPE_SECONDS', 5),

    /*
    | E-Mail-Benachrichtigung bei jedem Download (Link-Empfänger lädt eine Datei).
    | DELIVERY_DOWNLOAD_NOTIFY_EMAIL: eine Adresse oder mehrere, kommagetrennt.
    | Leer = keine Mails. Versand per Queue (queue:work).
    */
    'delivery_download_notify_emails' => array_values(array_filter(array_map('trim', explode(',', (string) env('DELIVERY_DOWNLOAD_NOTIFY_EMAIL', ''))))),
];
