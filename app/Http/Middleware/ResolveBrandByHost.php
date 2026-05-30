<?php

namespace App\Http\Middleware;

use App\Models\Brand;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveBrandByHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = mb_strtolower((string) $request->getHost());
        $defaultBrandKey = (string) config('brands.default_brand_key', 'erftkreis_news');

        $brand = Brand::query()
            ->where('is_active', true)
            ->where(function ($query) use ($host) {
                $query->whereRaw('LOWER(primary_host) = ?', [$host])
                    ->orWhereJsonContains('secondary_hosts', $host);
            })
            ->first();

        if (! $brand) {
            $brand = Brand::query()
                ->where('key', $defaultBrandKey)
                ->where('is_active', true)
                ->first();
        }

        if ($brand) {
            app()->instance('currentBrand', $brand);
            $request->attributes->set('currentBrand', $brand);
        }

        return $next($request);
    }
}
