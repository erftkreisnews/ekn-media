<?php

namespace App\Support;

/**
 * Extrahiert aus Nominatim "address"-Objekten die Felder für die Meldungs-Einordnung.
 */
final class NominatimAddressMapper
{
    /**
     * @param  array<string, mixed>|null  $address
     * @return array{country: ?string, federal_state: ?string, city: ?string, street: ?string, region: ?string}
     */
    public static function toPublicationFields(?array $address): array
    {
        if ($address === null || $address === []) {
            return [
                'country' => null,
                'federal_state' => null,
                'city' => null,
                'street' => null,
                'region' => null,
            ];
        }

        $city = self::firstString($address, [
            'city', 'town', 'village', 'municipality', 'hamlet', 'suburb',
        ]);

        $street = self::firstString($address, [
            'road', 'pedestrian', 'footway', 'path', 'cycleway', 'residential',
        ]);

        if ($street !== null && ! empty($address['house_number'])) {
            $street = trim($street.' '.(string) $address['house_number']);
        }

        $district = self::firstString($address, [
            'county',
            'state_district',
        ]);

        return [
            'country' => self::stringOrNull($address['country'] ?? null),
            'federal_state' => self::stringOrNull($address['state'] ?? null),
            'city' => $city,
            'street' => $street,
            'region' => $district,
        ];
    }

    /**
     * @param  array<string, mixed>  $row  Ein Nominatim-Suchtreffer
     * @return array{display_name: string, lat: string, lon: string, fields: array{country: ?string, federal_state: ?string, city: ?string, street: ?string, region: ?string}}
     */
    public static function formatSearchHit(array $row): array
    {
        $addr = isset($row['address']) && is_array($row['address']) ? $row['address'] : null;

        return [
            'display_name' => (string) ($row['display_name'] ?? ''),
            'lat' => (string) ($row['lat'] ?? ''),
            'lon' => (string) ($row['lon'] ?? ''),
            'fields' => self::toPublicationFields($addr),
        ];
    }

    private static function firstString(array $address, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! empty($address[$key]) && is_string($address[$key])) {
                return trim($address[$key]);
            }
        }

        return null;
    }

    private static function stringOrNull(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }

        return is_string($v) ? trim($v) : null;
    }
}
