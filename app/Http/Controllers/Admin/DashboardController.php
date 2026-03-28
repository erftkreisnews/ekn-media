<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $newsCount = 0;
        if (class_exists(\App\Models\NewsItem::class)) {
            try {
                $newsCount = \App\Models\NewsItem::count();
            } catch (\Throwable) {
                $newsCount = 0;
            }
        }

        return view('admin.dashboard', compact('newsCount'));
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
