# Datenschutz: Delivery & Versand-Logging

## IP- und User-Agent-Hashing (DSGVO)

Bei Versand-Zugriffen (Öffnen, Bestätigen, Download) werden **ip_hash** und **ua_hash** in den Tabellen `deliveries` und `delivery_events` gespeichert.

- **Verfahren:** HMAC-SHA256 mit **APP_KEY** als Schlüssel (keyed hash). Ohne Kenntnis des Schlüssels sind die Werte nicht re-identifizierbar; damit gelten sie als pseudonymisiert im Sinne der DSGVO.
- **Implementierung:** `DeliveryController::hashIpUa()` verwendet `hash_hmac('sha256', $ip, config('app.key'))` bzw. analog für User-Agent.

## Aufbewahrungsfristen & Löschkonzept

- **Empfehlung:** Definieren Sie eine maximale Aufbewahrungsdauer für `deliveries` und `delivery_events` (z. B. 12 oder 24 Monate nach `expires_at` bzw. `created_at`) und dokumentieren Sie diese in der Datenschutzerklärung.
- **Technisch:** Löschung kann per Artisan-Befehl oder geplantem Job erfolgen (z. B. `Delivery::where('expires_at', '<', now()->subMonths(12))->delete()` inkl. zugehöriger `DeliveryEvent`-Einträge). Ein vordefinierter Befehl ist derzeit nicht im Projekt angelegt und kann bei Bedarf ergänzt werden.
