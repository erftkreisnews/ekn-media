<?php

namespace App\Support;

use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Erzwingt getrennte Anlege-Flows pro Admin-Markenfilter:
 * - erftkreis_news → nur admin.news.create
 * - koelnimage → nur admin.koelnimage.foto.create
 */
final class AdminBrandNewsEntry
{
    public const KEY_ERFTKREIS = 'erftkreis_news';

    public const KEY_KOELNIMAGE = 'koelnimage';

    public static function selectedBrand(Request $request): ?Brand
    {
        if (! Schema::hasTable('brands')) {
            return null;
        }

        $raw = $request->session()->get('admin.brand_filter');
        $brandId = null;
        if (is_int($raw)) {
            $brandId = $raw;
        } elseif (is_string($raw) && ctype_digit($raw)) {
            $brandId = (int) $raw;
        }

        if ($brandId === null) {
            return null;
        }

        return Brand::query()
            ->where('is_active', true)
            ->whereKey($brandId)
            ->first(['id', 'name', 'key']);
    }

    public static function selectedBrandKey(Request $request): ?string
    {
        $key = self::selectedBrand($request)?->key;

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * @param  'erftkreis_news'|'koelnimage_foto'  $intendedFlow
     */
    public static function redirectIfWrongCreateFlow(Request $request, string $intendedFlow): ?RedirectResponse
    {
        $brandKey = self::selectedBrandKey($request);
        if ($brandKey === null) {
            return null;
        }

        if ($brandKey === self::KEY_KOELNIMAGE && $intendedFlow !== 'koelnimage_foto') {
            return redirect()
                ->route('admin.koelnimage.foto.create')
                ->with('status', 'Für Kölnimage bitte nur „Foto (Kölnimage)“ zum Anlegen verwenden.');
        }

        if ($brandKey === self::KEY_ERFTKREIS && $intendedFlow !== 'erftkreis_news') {
            return redirect()
                ->route('admin.news.create')
                ->with('status', 'Für Erftkreis News bitte nur „Neue Nachricht“ zum Anlegen verwenden.');
        }

        return null;
    }
}
