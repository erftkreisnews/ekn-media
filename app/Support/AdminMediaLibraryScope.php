<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\NewsItemMedia;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Gemeinsame Filter für zentrale Bild-/Video-Mediathek (Admin).
 */
final class AdminMediaLibraryScope
{
    /**
     * Vollzugriff auf die Mediathek (nicht nur eigene Meldungen).
     */
    public static function canBrowseAllMedia(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->can(AdminPermissions::ACCESS) || $user->can(AdminPermissions::MEDIA);
    }

    public static function selectedBrandId(Request $request): ?int
    {
        $raw = $request->session()->get('admin.brand_filter');
        if (is_int($raw)) {
            return $raw;
        }
        if (is_string($raw) && ctype_digit($raw)) {
            return (int) $raw;
        }

        return null;
    }

    public static function selectedBrandLabel(Request $request): ?string
    {
        $brandId = self::selectedBrandId($request);
        if ($brandId === null || ! Schema::hasTable('brands')) {
            return null;
        }

        $name = Brand::query()
            ->where('id', $brandId)
            ->where('is_active', true)
            ->value('name');

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * @param  Builder<NewsItemMedia>  $query
     */
    public static function applyAuthorScope(Builder $query, Request $request): void
    {
        $user = $request->user();
        if (self::canBrowseAllMedia($user)) {
            return;
        }

        $query->whereHas('newsItem', function (Builder $b) use ($user): void {
            $b->where('author_id', $user?->id);
        });
    }

    /**
     * Marke: medium.brand_id oder (null + Meldung mit passender brand_id).
     * Legacy: medium und Meldung ohne brand_id — zählen zur Standardmarke (Erftkreis News)
     * und zur per Host aufgelösten Marke (Admin läuft ohne brand.resolve-Middleware).
     *
     * @param  Builder<NewsItemMedia>  $query
     */
    public static function applyBrandScope(Builder $query, Request $request, ?int $selectedBrandId): void
    {
        if ($selectedBrandId === null
            || ! Schema::hasTable('news_item_media')
            || ! Schema::hasColumn('news_item_media', 'brand_id')) {
            return;
        }

        $legacyBrandIds = self::legacyUnbrandedMediaBrandIds($request, $selectedBrandId);

        $query->where(function (Builder $b) use ($selectedBrandId, $legacyBrandIds): void {
            $b->where('brand_id', $selectedBrandId)
                ->orWhere(function (Builder $fallback) use ($selectedBrandId): void {
                    $fallback->whereNull('brand_id')
                        ->whereHas('newsItem', function (Builder $news) use ($selectedBrandId): void {
                            $news->where('brand_id', $selectedBrandId);
                        });
                });

            if ($legacyBrandIds !== []) {
                $b->orWhere(function (Builder $legacy) use ($legacyBrandIds): void {
                    $legacy->whereNull('brand_id')
                        ->whereHas('newsItem', function (Builder $news) use ($legacyBrandIds): void {
                            $news->whereNull('brand_id');
                        });
                });
            }
        });
    }

    /**
     * Marken-IDs, für die „ohne brand_id“-Meldungen/Medien als zugehörig gelten.
     *
     * @return list<int>
     */
    private static function legacyUnbrandedMediaBrandIds(Request $request, int $selectedBrandId): array
    {
        $ids = [];

        $defaultKey = (string) config('brands.default_brand_key', 'erftkreis_news');
        if (Schema::hasTable('brands')) {
            $defaultId = Brand::query()
                ->where('key', $defaultKey)
                ->where('is_active', true)
                ->value('id');
            if (is_numeric($defaultId)) {
                $ids[] = (int) $defaultId;
            }
        }

        $hostBrand = $request->attributes->get('currentBrand');
        if ($hostBrand instanceof Brand) {
            $ids[] = (int) $hostBrand->id;
        } else {
            $host = mb_strtolower((string) $request->getHost());
            if ($host !== '' && Schema::hasTable('brands')) {
                $resolved = Brand::query()
                    ->where('is_active', true)
                    ->where(function (Builder $q) use ($host): void {
                        $q->whereRaw('LOWER(primary_host) = ?', [$host])
                            ->orWhereJsonContains('secondary_hosts', $host);
                    })
                    ->value('id');
                if (is_numeric($resolved)) {
                    $ids[] = (int) $resolved;
                }
            }
        }

        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));

        if (! in_array($selectedBrandId, $ids, true)) {
            return [];
        }

        return $ids;
    }
}
