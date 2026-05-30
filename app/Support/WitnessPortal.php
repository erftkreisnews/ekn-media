<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;

class WitnessPortal
{
    /**
     * Liegt die Anfrage auf der konfigurierten Zeugen-Subdomain?
     */
    public static function requestUsesDedicatedPortalHost(?Request $request = null): bool
    {
        $configured = strtolower(trim((string) config('witness.portal_host')));
        if ($configured === '') {
            return false;
        }

        $request ??= request();
        $current = strtolower($request->getHost());

        return $current === $configured;
    }

    public static function routeNameForShow(?Request $request = null): string
    {
        return self::requestUsesDedicatedPortalHost($request)
            ? 'witness.portal.show'
            : 'witness.upload.show';
    }

    public static function routeNameForStore(?Request $request = null): string
    {
        return self::requestUsesDedicatedPortalHost($request)
            ? 'witness.portal.store'
            : 'witness.upload.store';
    }

    public static function uploadUrl(string $plainToken): string
    {
        $host = trim((string) config('witness.portal_host'));
        if ($host !== '') {
            return route('witness.portal.show', ['token' => $plainToken], absolute: true);
        }

        return route('witness.upload.show', ['token' => $plainToken], absolute: true);
    }

    /**
     * Prüfsumme des verbindlichen deutschen Rechtstextes (für Audit / Integrität).
     */
    public static function legalBodyChecksum(): string
    {
        return hash('sha256', Lang::get('witness.legal_text', [], 'de'));
    }

    public static function legalVersion(): string
    {
        return (string) config('witness.legal_version', '1');
    }
}
