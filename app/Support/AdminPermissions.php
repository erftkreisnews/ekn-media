<?php

namespace App\Support;

/**
 * Granulare Admin-Rechte (Spatie Permission).
 *
 * @see \Database\Seeders\RolesAndPermissionsSeeder
 */
final class AdminPermissions
{
    public const ACCESS = 'admin.access';

    public const NEWS = 'admin.news';

    public const DELIVERIES = 'admin.deliveries';

    public const MEDIA = 'admin.media';

    public const INGEST = 'admin.ingest';

    public const SETTINGS = 'admin.settings';

    public const CUSTOMERS = 'admin.customers';

    /** Löschen in der Kundenverwaltung (Organisationen, Produkte, Kontakte, Versandziele) */
    public const CUSTOMERS_DELETE = 'admin.customers.delete';

    /**
     * PV-/Kundenkennzeichen, Autorennummern, Lexware- und Rechnungsstammdaten in der Kundenverwaltung.
     * Ohne dieses Recht: nur Stammdaten (Name, Notizen, Aktiv, Versand) sichtbar/bearbeitbar.
     */
    public const CUSTOMERS_BILLING_SENSITIVE = 'admin.customers.billing_sensitive';

    public const BACKOFFICE = 'admin.backoffice';

    public const USERS = 'admin.users';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::ACCESS,
            self::NEWS,
            self::DELIVERIES,
            self::MEDIA,
            self::INGEST,
            self::SETTINGS,
            self::CUSTOMERS,
            self::CUSTOMERS_DELETE,
            self::CUSTOMERS_BILLING_SENSITIVE,
            self::BACKOFFICE,
            self::USERS,
        ];
    }
}
