<?php

namespace App\Http\Controllers\KoelnImage;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use App\Services\ImageMetadataReader;
use App\Services\ImageMetadataWriter;
use App\Services\MediaStorage;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class NewsGalleryController extends Controller
{
    private const SESSION_FAVORITES_KEY = 'koelnimage_gallery.favorite_ids';

    private const SESSION_CART_KEY = 'koelnimage_gallery.cart_ids';

    public function index(Request $request, string $slug): View|JsonResponse
    {
        $validated = $request->validate($this->galleryFilterRules());

        $newsItem = $this->resolvePublicNewsItemOrFail($slug);

        // Direktaufruf im Browser: HTML-Galerieseite (JSON nur bei API-Anfrage, z. B. fetch mit Accept: application/json).
        if (! $request->wantsJson()) {
            $newsItem->loadMissing(['media', 'author', 'plannedEvent']);

            return view('koelnimage.news-show', compact('newsItem'));
        }

        return $this->galleryJsonResponse($request, $newsItem, $validated);
    }

    public function timeWindow(Request $request, string $slug): JsonResponse
    {
        $validated = $request->validate([
            'reference_media_id' => ['required', 'integer', 'min:1'],
            'window' => ['nullable', 'in:30s,60s,2m,5m'],
            'direction' => ['nullable', 'in:before,after,both'],
        ]);

        $newsItem = $this->resolvePublicNewsItemOrFail($slug);
        $reference = $this->resolveGalleryImageMediaByIdOrFail($newsItem, (int) $validated['reference_media_id']);

        if (! $reference->capture_time instanceof CarbonInterface) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'reference_media_id' => $reference->id,
                    'window' => $validated['window'] ?? '60s',
                    'direction' => $validated['direction'] ?? 'both',
                    'total' => 0,
                    'message' => 'Keine Aufnahmezeit am Referenzbild.',
                ],
            ]);
        }

        $seconds = $this->timeWindowSeconds((string) ($validated['window'] ?? '60s'));
        $direction = (string) ($validated['direction'] ?? 'both');
        $refTime = $reference->capture_time;

        $from = match ($direction) {
            'before' => $refTime->copy()->subSeconds($seconds),
            'after' => $refTime->copy(),
            default => $refTime->copy()->subSeconds($seconds),
        };
        $to = match ($direction) {
            'before' => $refTime->copy(),
            'after' => $refTime->copy()->addSeconds($seconds),
            default => $refTime->copy()->addSeconds($seconds),
        };

        $favoriteIds = $this->sessionIds($request, self::SESSION_FAVORITES_KEY);
        $cartIds = $this->sessionIds($request, self::SESSION_CART_KEY);
        $canLicensedDownload = $request->user() && $request->user()->canKoelnimageLicensedDownload();

        $items = $this->buildPublicGalleryQuery($newsItem, [])
            ->whereNotNull('capture_time')
            ->where('capture_time', '>=', $from)
            ->where('capture_time', '<=', $to)
            ->orderBy('capture_time')
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->filter(fn (NewsItemMedia $media) => filled($media->public_url ?? $media->preview_url))
            ->values();

        $data = $items
            ->map(fn (NewsItemMedia $media) => $this->mapMediaToApiArray(
                $media,
                $newsItem,
                $favoriteIds,
                $cartIds,
                (bool) $canLicensedDownload,
            ))
            ->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'reference_media_id' => $reference->id,
                'reference_capture_time' => $refTime->toIso8601String(),
                'window' => $validated['window'] ?? '60s',
                'direction' => $direction,
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
                'total' => count($data),
            ],
        ]);
    }

    /**
     * Redaktionsdatei (Master) nur für angemeldete Nutzer mit manuell gesetztem Lizenz-Flag.
     * Zahlungsabgleich erfolgt außerhalb (Rechnung / Vertrag); Flag setzt ihr in der Datenbank oder später im Admin.
     */
    public function download(Request $request, string $slug, int $media): BinaryFileResponse
    {
        abort_unless($request->user() && $request->user()->canKoelnimageLicensedDownload(), 403);

        $newsItem = $this->resolvePublicNewsItemOrFail($slug);
        $mediaModel = $this->resolveGalleryImageMediaByIdOrFail($newsItem, $media);

        $relativePath = $mediaModel->resolveDeliveryDownloadRelativePath();
        if ($relativePath === null) {
            abort(404, 'Datei derzeit nicht verfügbar.');
        }

        $storage = app(MediaStorage::class);
        $resolved = $storage->resolveReadableLocalPath($relativePath);
        $fullPath = $resolved['path'] ?? null;
        if (! is_string($fullPath) || ! is_file($fullPath)) {
            abort(404, 'Datei nicht gefunden.');
        }

        Log::info('koelnimage.gallery.download', [
            'user_id' => $request->user()->id,
            'news_item_id' => $newsItem->id,
            'media_id' => $mediaModel->id,
            'slug' => $slug,
        ]);

        if ($mediaModel->isImage()) {
            $mediaModel->loadMissing('newsItem');
            ImageMetadataWriter::write($fullPath, $mediaModel->resolvedIptcForEmbed());
            Log::debug('koelnimage.gallery.download.iptc', [
                'media_id' => $mediaModel->id,
                'iptc' => ImageMetadataReader::read($fullPath),
            ]);
        }

        $downloadName = $mediaModel->original_name ?: basename($relativePath);
        $response = response()->download($fullPath, $downloadName);
        if (($resolved['temporary'] ?? false) === true) {
            $response->deleteFileAfterSend(true);
        }

        return $response;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function galleryFilterRules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'in:newest,oldest,filename_az,rating_desc,rating_asc'],
            'min_rating' => ['nullable', 'integer', 'min:0', 'max:5'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50,100,200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function galleryJsonResponse(Request $request, NewsItem $newsItem, array $validated): JsonResponse
    {
        $favoriteIds = $this->sessionIds($request, self::SESSION_FAVORITES_KEY);
        $cartIds = $this->sessionIds($request, self::SESSION_CART_KEY);
        $sort = (string) ($validated['sort'] ?? 'newest');
        $minRating = (int) ($validated['min_rating'] ?? 0);
        $perPage = (int) ($validated['per_page'] ?? 25);

        $query = $this->buildPublicGalleryQuery($newsItem, $validated);
        $paginator = $query->paginate($perPage)->appends($request->query());

        $canLicensedDownload = $request->user() && $request->user()->canKoelnimageLicensedDownload();

        $data = collect($paginator->items())
            ->filter(fn (NewsItemMedia $media) => filled($media->public_url ?? $media->preview_url))
            ->values()
            ->map(fn (NewsItemMedia $media) => $this->mapMediaToApiArray(
                $media,
                $newsItem,
                $favoriteIds,
                $cartIds,
                (bool) $canLicensedDownload,
            ))
            ->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'news_item_id' => $newsItem->id,
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'sort' => $sort,
                'q' => $validated['q'] ?? null,
                'min_rating' => $minRating,
                'from' => $validated['from'] ?? null,
                'to' => $validated['to'] ?? null,
                'supports_ratings' => false,
                'available_sorts' => ['newest', 'oldest', 'filename_az', 'rating_desc', 'rating_asc'],
                'favorites_count' => count($favoriteIds),
                'cart_count' => count($cartIds),
                'licensed_download' => (bool) $canLicensedDownload,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function buildPublicGalleryQuery(NewsItem $newsItem, array $validated): Builder
    {
        $query = NewsItemMedia::query()
            ->where('news_item_id', $newsItem->id)
            ->where('type', 'image')
            ->where('is_visible', true)
            ->where('versand', true)
            ->where(function (Builder $builder) {
                $builder->whereNull('delivery_visible_for_organization_ids')
                    ->orWhereJsonLength('delivery_visible_for_organization_ids', 0);
            });

        if (! empty($validated['q'])) {
            $q = trim((string) $validated['q']);
            $query->where(function (Builder $builder) use ($q) {
                $builder->where('caption', 'like', '%'.$q.'%')
                    ->orWhere('image_title', 'like', '%'.$q.'%')
                    ->orWhere('media_keywords', 'like', '%'.$q.'%')
                    ->orWhere('original_name', 'like', '%'.$q.'%');
            });
        }

        if (! empty($validated['from'])) {
            $query->where('capture_time', '>=', $validated['from']);
        }

        if (! empty($validated['to'])) {
            $query->where('capture_time', '<=', $validated['to']);
        }

        $sort = (string) ($validated['sort'] ?? 'newest');
        if ($sort === 'oldest') {
            $query->orderByRaw('CASE WHEN capture_time IS NULL THEN 1 ELSE 0 END ASC')
                ->orderBy('capture_time')
                ->orderBy('id');
        } elseif ($sort === 'filename_az') {
            $query->orderBy('original_name')->orderBy('id');
        } else {
            $query->orderByRaw('CASE WHEN capture_time IS NULL THEN 1 ELSE 0 END ASC')
                ->orderByDesc('capture_time')
                ->orderByDesc('id');
        }

        $minRating = (int) ($validated['min_rating'] ?? 0);
        if ($minRating > 0) {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    /**
     * @param  array<int, int>  $favoriteIds
     * @param  array<int, int>  $cartIds
     * @return array<string, mixed>
     */
    private function mapMediaToApiArray(
        NewsItemMedia $media,
        NewsItem $newsItem,
        array $favoriteIds,
        array $cartIds,
        bool $canLicensedDownload,
    ): array {
        $webDisplayUrl = $media->preview_url ?? $media->public_url;
        $previewUrl = $media->preview_url ?? $webDisplayUrl;
        $thumbUrl = $this->resolveThumbUrl($media);
        $keywords = collect(explode(',', (string) ($media->media_keywords ?? '')))
            ->map(fn (string $keyword) => trim($keyword))
            ->filter()
            ->values()
            ->all();

        return [
            'id' => $media->id,
            'title' => $media->image_title ?: $media->caption ?: $media->original_name ?: ('Bild '.$media->id),
            'caption' => $media->caption,
            'url' => $webDisplayUrl,
            'preview_url' => $previewUrl,
            'thumb_url' => $thumbUrl,
            'webp_url' => $previewUrl,
            'avif_url' => $this->resolveAvifUrl($media),
            'original_name' => $media->original_name,
            'capture_time' => $media->capture_time?->toIso8601String(),
            'photographer' => $media->photographer,
            'keywords' => $keywords,
            'width' => $media->width,
            'height' => $media->height,
            'rating' => null,
            'ratings_count' => 0,
            'is_favorite' => in_array((int) $media->id, $favoriteIds, true),
            'is_in_cart' => in_array((int) $media->id, $cartIds, true),
            'download_url' => $canLicensedDownload
                ? route('koelnimage.gallery.download', ['slug' => $newsItem->slug, 'media' => $media->id])
                : null,
        ];
    }

    private function timeWindowSeconds(string $window): int
    {
        return match ($window) {
            '30s' => 30,
            '2m' => 120,
            '5m' => 300,
            default => 60,
        };
    }

    private function resolveThumbUrl(NewsItemMedia $media): ?string
    {
        $previewPath = trim((string) ($media->preview_path ?? ''));
        if ($previewPath === '') {
            return $media->preview_url ?? null;
        }

        $thumbPath = (string) preg_replace('/-preview-/', '-thumb-', $previewPath, 1);
        if ($thumbPath === '' || $thumbPath === $previewPath) {
            $previewDir = dirname($previewPath);
            $previewFilename = pathinfo($previewPath, PATHINFO_FILENAME);
            $thumbPath = $previewDir.'/thumb/'.$previewFilename.'.webp';
        }

        return app(MediaStorage::class)->url($thumbPath);
    }

    private function resolveAvifUrl(NewsItemMedia $media): ?string
    {
        $previewPath = trim((string) ($media->preview_path ?? ''));
        if ($previewPath === '') {
            return null;
        }

        $avifPath = preg_replace('/\.[a-zA-Z0-9]+$/', '.avif', $previewPath);
        if (! is_string($avifPath) || $avifPath === '' || $avifPath === $previewPath) {
            return null;
        }

        $storage = app(MediaStorage::class);
        if (! $storage->exists($avifPath)) {
            return null;
        }

        return $storage->url($avifPath);
    }

    public function toggleFavorite(Request $request, string $slug): JsonResponse
    {
        $newsItem = $this->resolvePublicNewsItemOrFail($slug);
        $media = $this->resolveNewsImageMediaOrFail($request, $newsItem);

        $favoriteIds = $this->sessionIds($request, self::SESSION_FAVORITES_KEY);
        $id = (int) $media->id;
        $isFavorite = in_array($id, $favoriteIds, true);
        if ($isFavorite) {
            $favoriteIds = array_values(array_filter($favoriteIds, fn (int $v): bool => $v !== $id));
        } else {
            $favoriteIds[] = $id;
        }
        $favoriteIds = array_values(array_unique($favoriteIds));
        $request->session()->put(self::SESSION_FAVORITES_KEY, $favoriteIds);

        return response()->json([
            'media_id' => $id,
            'is_favorite' => ! $isFavorite,
            'favorites_count' => count($favoriteIds),
            'cart_count' => count($this->sessionIds($request, self::SESSION_CART_KEY)),
        ]);
    }

    public function toggleCart(Request $request, string $slug): JsonResponse
    {
        $newsItem = $this->resolvePublicNewsItemOrFail($slug);
        $media = $this->resolveNewsImageMediaOrFail($request, $newsItem);

        $cartIds = $this->sessionIds($request, self::SESSION_CART_KEY);
        $id = (int) $media->id;
        $isInCart = in_array($id, $cartIds, true);
        if ($isInCart) {
            $cartIds = array_values(array_filter($cartIds, fn (int $v): bool => $v !== $id));
        } else {
            $cartIds[] = $id;
        }
        $cartIds = array_values(array_unique($cartIds));
        $request->session()->put(self::SESSION_CART_KEY, $cartIds);

        return response()->json([
            'media_id' => $id,
            'is_in_cart' => ! $isInCart,
            'cart_count' => count($cartIds),
            'favorites_count' => count($this->sessionIds($request, self::SESSION_FAVORITES_KEY)),
        ]);
    }

    private function resolvePublicNewsItemOrFail(string $slug): NewsItem
    {
        $newsItem = NewsItem::query()
            ->where('slug', $slug)
            ->first();

        if (! $newsItem || ! $this->isPublicVisibleNow($newsItem) || ! $this->newsItemMatchesCurrentHostBrand($newsItem)) {
            abort(404);
        }

        return $newsItem;
    }

    private function resolveNewsImageMediaOrFail(Request $request, NewsItem $newsItem): NewsItemMedia
    {
        $validated = $request->validate([
            'media_id' => ['required', 'integer', 'min:1'],
        ]);

        $media = NewsItemMedia::query()
            ->where('id', (int) $validated['media_id'])
            ->where('news_item_id', $newsItem->id)
            ->where('type', 'image')
            ->where('is_visible', true)
            ->where('versand', true)
            ->where(function (Builder $builder) {
                $builder->whereNull('delivery_visible_for_organization_ids')
                    ->orWhereJsonLength('delivery_visible_for_organization_ids', 0);
            })
            ->first();

        if (! $media || ! filled($media->public_url ?? $media->preview_url)) {
            abort(404);
        }

        return $media;
    }

    private function resolveGalleryImageMediaByIdOrFail(NewsItem $newsItem, int $mediaId): NewsItemMedia
    {
        $media = NewsItemMedia::query()
            ->where('id', $mediaId)
            ->where('news_item_id', $newsItem->id)
            ->where('type', 'image')
            ->where('is_visible', true)
            ->where('versand', true)
            ->where(function (Builder $builder) {
                $builder->whereNull('delivery_visible_for_organization_ids')
                    ->orWhereJsonLength('delivery_visible_for_organization_ids', 0);
            })
            ->first();

        if (! $media || ! filled($media->public_url ?? $media->preview_url)) {
            abort(404);
        }

        return $media;
    }

    /**
     * @return array<int, int>
     */
    private function sessionIds(Request $request, string $key): array
    {
        $value = $request->session()->get($key, []);
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', array_filter($value, fn ($id): bool => is_numeric($id)))));
    }

    private function isPublicVisibleNow(NewsItem $newsItem): bool
    {
        if (($newsItem->status ?? null) !== 'published') {
            return false;
        }

        $publishedAtOk = is_null($newsItem->published_at) || $newsItem->published_at->lte(now());
        $embargoOk = is_null($newsItem->embargo_at) || $newsItem->embargo_at->lte(now());

        return $publishedAtOk && $embargoOk;
    }

    private function newsItemMatchesCurrentHostBrand(NewsItem $newsItem): bool
    {
        if (! Schema::hasColumn('news_items', 'brand_id')) {
            return true;
        }

        $brand = app()->bound('currentBrand') ? app('currentBrand') : null;
        if (! $brand instanceof Brand) {
            return true;
        }

        $brandId = $newsItem->brand_id;

        if ($brand->key === 'koelnimage') {
            return (int) $brandId === (int) $brand->id;
        }

        if ($brand->key === 'erftkreis_news') {
            return $brandId === null || (int) $brandId === (int) $brand->id;
        }

        return (int) $brandId === (int) $brand->id;
    }
}
