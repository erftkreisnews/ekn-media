<?php

namespace App\Support;

use App\Models\Organization;
use Illuminate\Support\Collection;

final class PublicationDomainMatcher
{
    /**
     * @return Collection<int, string>
     */
    public static function parseDomainList(?string $raw): Collection
    {
        if ($raw === null || trim($raw) === '') {
            return collect();
        }

        return collect(preg_split('/[\s,;]+/', $raw) ?: [])
            ->map(fn ($d) => self::normalizeHost((string) $d))
            ->filter()
            ->unique()
            ->values();
    }

    public static function extractHost(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (! str_contains($url, '://')) {
            $url = 'https://'.$url;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? self::normalizeHost($host) : null;
    }

    public static function normalizeHost(string $host): ?string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('#^www\.#', '', $host) ?? $host;

        return $host !== '' ? $host : null;
    }

    public static function hostMatches(string $urlHost, string $patternHost): bool
    {
        $urlHost = self::normalizeHost($urlHost);
        $patternHost = self::normalizeHost($patternHost);

        if ($urlHost === null || $patternHost === null) {
            return false;
        }

        if ($urlHost === $patternHost) {
            return true;
        }

        return str_ends_with($urlHost, '.'.$patternHost);
    }

    public static function findLicensedOrganization(string $url): ?Organization
    {
        $host = self::extractHost($url);
        if ($host === null) {
            return null;
        }

        $organizations = Organization::query()
            ->where('active', true)
            ->whereNotNull('publication_domains')
            ->where('publication_domains', '!=', '')
            ->get(['id', 'name', 'publication_domains']);

        foreach ($organizations as $organization) {
            $domains = self::parseDomainList($organization->publication_domains);
            foreach ($domains as $domain) {
                if (self::hostMatches($host, $domain)) {
                    return $organization;
                }
            }
        }

        return null;
    }
}
