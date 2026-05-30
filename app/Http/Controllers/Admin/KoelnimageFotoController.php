<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreKoelnimageFotoImageRequest;
use App\Http\Requests\StoreKoelnimageFotoRequest;
use App\Models\Brand;
use App\Models\NewsItem;
use App\Models\PlannedEvent;
use App\Models\User;
use App\Services\Admin\NewsItemImageIngestService;
use App\Support\AdminBrandNewsEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class KoelnimageFotoController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($redirect = AdminBrandNewsEntry::redirectIfWrongCreateFlow($request, 'koelnimage_foto')) {
            return $redirect;
        }

        $plannedEvents = class_exists(PlannedEvent::class) && Schema::hasTable('planned_events')
            ? PlannedEvent::queryForNewsSelect(null, $request->user())
            : collect();

        $koelnimageBrand = Brand::query()->where('key', 'koelnimage')->where('is_active', true)->first();

        return view('admin.koelnimage.foto-create', [
            'plannedEvents' => $plannedEvents,
            'koelnimageBrand' => $koelnimageBrand,
            'currentUserId' => (int) auth()->id(),
        ]);
    }

    public function store(StoreKoelnimageFotoRequest $request): RedirectResponse
    {
        if ($redirect = AdminBrandNewsEntry::redirectIfWrongCreateFlow($request, 'koelnimage_foto')) {
            return $redirect;
        }

        $brand = Brand::query()->where('key', 'koelnimage')->where('is_active', true)->first();
        if (! $brand) {
            return redirect()
                ->route('admin.koelnimage.foto.create')
                ->withInput()
                ->withErrors(['brand' => 'Marke Kölnimage ist nicht aktiv konfiguriert.']);
        }

        $newsItem = NewsItem::create([
            'title' => $request->validated('title'),
            'status' => 'draft',
            'author_id' => auth()->id(),
            'author_credit' => $this->authorCreditNameFromUserId((int) $request->validated('author_credit_user_id')),
            'brand_id' => $brand->id,
            'planned_event_id' => $request->validated('planned_event_id'),
            'update_type' => 'final',
            'published_at' => now(),
            'is_wdr_job' => false,
        ]);

        return redirect()
            ->route('admin.koelnimage.foto.upload', $newsItem)
            ->with('status', 'Galerie angelegt. Fotos können jetzt hochgeladen werden.');
    }

    public function upload(Request $request, NewsItem $newsItem): View|RedirectResponse
    {
        if ($redirect = AdminBrandNewsEntry::redirectIfWrongCreateFlow($request, 'koelnimage_foto')) {
            return $redirect;
        }

        $this->abortUnlessKoelnimageNewsItem($newsItem);
        $this->abortIfCannotManageNewsItem($newsItem);

        $newsItem->loadCount('images');

        return view('admin.koelnimage.foto-upload', [
            'newsItem' => $newsItem,
            'uploadUrl' => route('admin.koelnimage.foto.images.store', $newsItem),
            'editUrl' => route('admin.news.edit', ['newsItem' => $newsItem, 'tab' => 'bilder']).'#section-media',
        ]);
    }

    public function storeImage(
        StoreKoelnimageFotoImageRequest $request,
        NewsItem $newsItem,
        NewsItemImageIngestService $ingestService
    ): JsonResponse {
        $this->abortUnlessKoelnimageNewsItem($newsItem);
        $this->abortIfCannotManageNewsItem($newsItem);

        $result = $ingestService->ingest($newsItem, $request->file('image'));

        if ($result['skipped_duplicate']) {
            return response()->json([
                'success' => false,
                'skipped_duplicate' => true,
                'message' => 'Datei mit gleichem Namen ist dieser Galerie bereits zugeordnet.',
            ], 409);
        }

        $media = $result['media'];
        if ($media === null) {
            return response()->json([
                'success' => false,
                'message' => 'Upload fehlgeschlagen.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'media_id' => $media->id,
            'original_name' => $media->original_name,
            'preview_url' => $media->preview_url ?? $media->public_url,
            'images_count' => $newsItem->images()->count(),
        ], 201);
    }

    private function authorCreditNameFromUserId(int $userId): string
    {
        $name = User::query()->whereKey($userId)->value('name');

        return is_string($name) ? $name : '';
    }

    private function abortUnlessKoelnimageNewsItem(NewsItem $newsItem): void
    {
        if (! Schema::hasColumn('news_items', 'brand_id')) {
            abort(404);
        }

        $isKoelnimage = Brand::query()
            ->whereKey((int) $newsItem->brand_id)
            ->where('key', 'koelnimage')
            ->exists();

        if (! $isKoelnimage) {
            abort(404, 'Diese Galerie gehört nicht zur Marke Kölnimage.');
        }
    }

    private function abortIfCannotManageNewsItem(NewsItem $newsItem): void
    {
        $user = auth()->user();
        if ($user !== null && $user->hasRole('admin')) {
            return;
        }

        if ((int) $newsItem->author_id !== (int) auth()->id()) {
            abort(403, 'Sie dürfen nur eigene Beiträge bearbeiten.');
        }
    }
}
