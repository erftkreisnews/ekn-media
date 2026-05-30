<?php

namespace App\Http\Controllers\KoelnImage;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class GalleryController extends Controller
{
    public function index(): View
    {
        $galleries = collect();
        $totalPublicPhotos = 0;

        $koelnimageBrand = Brand::query()
            ->where('key', 'koelnimage')
            ->where('is_active', true)
            ->first();

        if ($koelnimageBrand && Schema::hasColumn('news_items', 'brand_id')) {
            $galleries = NewsItem::query()
                ->publicVisible()
                ->where('brand_id', $koelnimageBrand->id)
                ->whereHas('media', function (Builder $query): void {
                    $query->where('type', 'image')
                        ->where('is_visible', true)
                        ->where('versand', true)
                        ->where(function (Builder $builder): void {
                            $builder->whereNull('delivery_visible_for_organization_ids')
                                ->orWhereJsonLength('delivery_visible_for_organization_ids', 0);
                        });
                })
                ->with([
                    'plannedEvent',
                    'media' => function ($query): void {
                        $query->where('type', 'image')
                            ->where('is_visible', true)
                            ->where('versand', true)
                            ->where(function (Builder $builder): void {
                                $builder->whereNull('delivery_visible_for_organization_ids')
                                    ->orWhereJsonLength('delivery_visible_for_organization_ids', 0);
                            })
                            ->orderBy('sort_order');
                    },
                ])
                ->withCount([
                    'media as public_images_count' => function ($query): void {
                        $query->where('type', 'image')
                            ->where('is_visible', true)
                            ->where('versand', true)
                            ->where(function (Builder $builder): void {
                                $builder->whereNull('delivery_visible_for_organization_ids')
                                    ->orWhereJsonLength('delivery_visible_for_organization_ids', 0);
                            });
                    },
                ])
                ->orderByDesc('published_at')
                ->paginate(12)
                ->withQueryString();

            $totalPublicPhotos = NewsItemMedia::query()
                ->where('type', 'image')
                ->where('is_visible', true)
                ->where('versand', true)
                ->where(function (Builder $builder): void {
                    $builder->whereNull('delivery_visible_for_organization_ids')
                        ->orWhereJsonLength('delivery_visible_for_organization_ids', 0);
                })
                ->whereHas('newsItem', function (Builder $query) use ($koelnimageBrand): void {
                    $query->publicVisible()
                        ->where('brand_id', $koelnimageBrand->id)
                        ->whereHas('media', function (Builder $mediaQuery): void {
                            $mediaQuery->where('type', 'image')
                                ->where('is_visible', true)
                                ->where('versand', true)
                                ->where(function (Builder $builder): void {
                                    $builder->whereNull('delivery_visible_for_organization_ids')
                                        ->orWhereJsonLength('delivery_visible_for_organization_ids', 0);
                                });
                        });
                })
                ->count();
        }

        return view('koelnimage.galleries.index', compact('galleries', 'totalPublicPhotos'));
    }
}
