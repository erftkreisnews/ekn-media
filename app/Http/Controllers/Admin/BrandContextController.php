<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class BrandContextController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $value = (string) $request->input('brand_context', 'all');
        if ($value === 'all') {
            $request->session()->forget('admin.brand_filter');

            return back()->with('status', 'Brand-Filter auf „Alle Marken“ gesetzt.');
        }

        if (! ctype_digit($value)) {
            return back()->with('error', 'Ungültiger Brand-Filter.');
        }

        if (! Schema::hasTable('brands')) {
            return back()->with('error', 'Brands-Tabelle ist noch nicht migriert.');
        }

        $brandId = (int) $value;
        $brandExists = Brand::query()
            ->where('id', $brandId)
            ->where('is_active', true)
            ->exists();

        if (! $brandExists) {
            return back()->with('error', 'Gewählte Marke ist nicht verfügbar.');
        }

        $request->session()->put('admin.brand_filter', $brandId);

        return back()->with('status', 'Brand-Filter wurde aktualisiert.');
    }
}
