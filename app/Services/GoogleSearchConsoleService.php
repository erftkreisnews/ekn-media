<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class GoogleSearchConsoleService
{
    private const REFRESH_TOKEN_CACHE_KEY = 'google_search_console_refresh_token';

    private const PERFORMANCE_CACHE_KEY = 'google_search_console_performance';

    public function isConnected(): bool
    {
        return ! empty(Cache::get(self::REFRESH_TOKEN_CACHE_KEY));
    }

    /**
     * Kurzübersicht Suchperformance (z. B. Klicks, Impressionen). Optional aus Search Console API.
     */
    public function getPerformanceSummary(): ?array
    {
        if (! $this->isConnected()) {
            return null;
        }

        $cached = Cache::get(self::PERFORMANCE_CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        return null;
    }

    public function clearPerformanceCache(): void
    {
        Cache::forget(self::PERFORMANCE_CACHE_KEY);
    }

    public function storeRefreshToken(string $refreshToken): void
    {
        Cache::forever(self::REFRESH_TOKEN_CACHE_KEY, $refreshToken);
        $this->clearPerformanceCache();
    }

    public function disconnect(): void
    {
        Cache::forget(self::REFRESH_TOKEN_CACHE_KEY);
        $this->clearPerformanceCache();
    }
}
