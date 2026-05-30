<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\NewsItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $newsCount = 0;
        $isAdmin = auth()->user()?->hasRole('admin') ?? false;
        $selectedBrandId = $this->selectedAdminBrandId($request);
        $brandFilterLabel = null;
        if ($selectedBrandId !== null && Schema::hasTable('brands')) {
            $brandFilterLabel = Brand::query()
                ->where('id', $selectedBrandId)
                ->where('is_active', true)
                ->value('name');
        }

        if (class_exists(NewsItem::class)) {
            try {
                $query = NewsItem::query();
                if (! $isAdmin) {
                    $query->where('author_id', auth()->id());
                }
                if ($selectedBrandId !== null
                    && Schema::hasTable('news_items')
                    && Schema::hasColumn('news_items', 'brand_id')) {
                    $query->where('brand_id', $selectedBrandId);
                }
                $newsCount = $query->count();
            } catch (\Throwable) {
                $newsCount = 0;
            }
        }

        return view('admin.dashboard', compact('newsCount', 'isAdmin', 'brandFilterLabel'));
    }

    private function selectedAdminBrandId(Request $request): ?int
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

    /** Erlaubte Status-Werte für den Filter (verhindert leere Liste durch ungültige Parameter). */
    private const ALLOWED_STATUSES = ['draft', 'review', 'published', 'archived'];

    /**
     * Gemeinsame Abfrage für Dashboard und News-Index (Suche + Status-Filter).
     */
    public static function newsListQuery(Request $request)
    {
        $query = NewsItem::query()
            ->with('author')
            ->orderByDesc('updated_at');

        if ($request->filled('q')) {
            $query->where('title', 'like', '%'.$request->input('q').'%');
        }
        $status = $request->input('status');
        if ($status !== null && $status !== '' && in_array($status, self::ALLOWED_STATUSES, true)) {
            $query->where('status', $status);
        }

        return $query;
    }
}
