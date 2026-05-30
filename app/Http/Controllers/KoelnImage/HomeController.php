<?php

namespace App\Http\Controllers\KoelnImage;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\NewsItem;
use App\Support\KoelnimageHomeNewsSelection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $newsItems = collect();

        // Immer Marke „koelnimage“ per Schlüssel (Route liegt auf Kölnimage-Domain; Middleware kann sonst falschen Host markieren).
        $koelnimageBrand = Brand::query()
            ->where('key', 'koelnimage')
            ->where('is_active', true)
            ->first();

        if ($koelnimageBrand && Schema::hasColumn('news_items', 'brand_id')) {
            $pool = NewsItem::query()
                ->publicVisible()
                ->where('brand_id', $koelnimageBrand->id)
                ->with([
                    'plannedEvent',
                    'images' => static fn ($q) => $q->limit(1),
                ])
                ->orderByDesc('published_at')
                ->limit(KoelnimageHomeNewsSelection::DEFAULT_POOL_SIZE)
                ->get();

            $newsItems = KoelnimageHomeNewsSelection::diversifyByPlannedEvent($pool);
        }

        return view('koelnimage.home', compact('newsItems'));
    }
}
