<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\NewsItem;
use App\Models\NewsSlugRedirect;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class NewsController extends Controller
{
    /**
     * Öffentliche Nachrichten-Übersicht (tv7-ähnlich, EKN-CI).
     */
    public function index(Request $request): View
    {
        $searchQuery = trim((string) $request->query('q', ''));

        $query = NewsItem::query()
            ->publicVisible()
            ->with(['images', 'videos', 'audios'])
            ->orderByDesc('published_at');

        $this->applyBrandScopeToPublicNewsQuery($query);

        if ($searchQuery !== '') {
            $searchableColumns = array_values(array_filter([
                'title',
                Schema::hasColumn('news_items', 'headline') ? 'headline' : null,
                'slug',
                Schema::hasColumn('news_items', 'excerpt') ? 'excerpt' : null,
                Schema::hasColumn('news_items', 'description') ? 'description' : null,
                'body',
                Schema::hasColumn('news_items', 'location') ? 'location' : null,
                'city',
                Schema::hasColumn('news_items', 'news_id') ? 'news_id' : null,
                Schema::hasColumn('news_items', 'type') ? 'type' : null,
                // Kompatibilität: Bestandsfeld für Teasertexte im aktuellen Datenmodell.
                Schema::hasColumn('news_items', 'teaser') ? 'teaser' : null,
            ]));

            $query->where(function (Builder $qry) use ($searchQuery, $searchableColumns) {
                foreach ($searchableColumns as $idx => $column) {
                    if ($idx === 0) {
                        $qry->where($column, 'like', '%'.$searchQuery.'%');
                    } else {
                        $qry->orWhere($column, 'like', '%'.$searchQuery.'%');
                    }
                }

                if (is_numeric($searchQuery)) {
                    $qry->orWhere('id', (int) $searchQuery);
                }
            });
        }

        $news = $query->paginate(10)->withQueryString();

        return view('news.index', [
            'news' => $news,
            'searchQuery' => $searchQuery,
            'hasSearch' => $searchQuery !== '',
        ]);
    }

    /**
     * Einzelansicht einer Nachricht (weiterlesen).
     */
    public function show(string $slug): View|RedirectResponse
    {
        // 1) Versuche: existiert ein NewsItem unter diesem Slug?
        $newsItem = NewsItem::query()
            ->where('slug', $slug)
            ->with(['media', 'author'])
            ->first();

        // 2) Anforderung: Redirect darf NICHT einen publicVisible Artikel überschreiben.
        //    Wenn der Artikel direkt gefunden wurde und publicVisible ist -> sofort rendern.
        if ($newsItem && $this->isPublicVisibleNow($newsItem)) {
            if (! $this->newsItemMatchesCurrentHostBrand($newsItem)) {
                abort(404);
            }

            $brand = app()->bound('currentBrand') ? app('currentBrand') : null;
            if ($brand && $brand->key === 'koelnimage') {
                return redirect()->route('koelnimage.gallery.photos', ['slug' => $newsItem->slug], 301);
            }

            return view('news.show', compact('newsItem'));
        }

        // 3) Redirects sollen nur greifen, wenn:
        //    - der Artikel nicht gefunden wurde ODER
        //    - der Artikel zu diesem Slug aktuell nicht publicVisible ist.
        $redirect = NewsSlugRedirect::query()
            ->where('from_slug', $slug)
            ->first();

        if ($redirect) {
            if ($redirect->is_gone) {
                abort(410, 'Dieser Inhalt wurde entfernt (Gone).');
            }

            if (filled($redirect->to_slug)) {
                $target = NewsItem::query()
                    ->where('slug', $redirect->to_slug)
                    ->with(['media', 'author'])
                    ->first();

                if ($target) {
                    if ($this->isPublicVisibleNow($target) && $this->newsItemMatchesCurrentHostBrand($target)) {
                        $routeName = request()->route()?->getName() === 'koelnimage.news.show' ? 'koelnimage.gallery.photos' : 'news.show';

                        return redirect()->to(route($routeName, $target->slug), 301);
                    }

                    if (($target->status ?? null) === 'archived') {
                        abort(410, 'Ziel wurde entfernt (Gone).');
                    }
                }

                abort(404);
            }

            abort(404);
        }

        // 4) Kein Redirect-Mapping: Entscheide anhand der aktuellen Sichtbarkeit.
        if (! $newsItem) {
            abort(404);
        }

        // Konservativ: nur bei archived -> 410, Embargo/Zeit in Zukunft -> 404.
        if (($newsItem->status ?? null) === 'archived') {
            abort(410, 'Dieser Inhalt wurde entfernt (Gone).');
        }

        abort(404);
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

    private function applyBrandScopeToPublicNewsQuery(Builder $query): void
    {
        if (! Schema::hasColumn('news_items', 'brand_id')) {
            return;
        }

        $brand = app()->bound('currentBrand') ? app('currentBrand') : null;
        if (! $brand instanceof Brand) {
            return;
        }

        if ($brand->key === 'koelnimage') {
            $query->where('brand_id', $brand->id);

            return;
        }

        if ($brand->key === 'erftkreis_news') {
            $query->where(function (Builder $q) use ($brand) {
                $q->whereNull('brand_id')->orWhere('brand_id', $brand->id);
            });
        }
    }

    private function newsItemMatchesCurrentHostBrand(NewsItem $newsItem): bool
    {
        if (! Schema::hasColumn('news_items', 'brand_id')) {
            return true;
        }

        $host = mb_strtolower((string) request()->getHost());
        $erftkreisHost = mb_strtolower(trim((string) config('brands.hosts.erftkreis_news', '')));
        $koelnimageHost = mb_strtolower(trim((string) config('brands.hosts.koelnimage', '')));
        $bid = $newsItem->brand_id;

        // Defensive fallback: when middleware brand resolution is stale/misconfigured,
        // enforce brand visibility by actual request host first.
        if ($host !== '' && $host === $erftkreisHost) {
            $erftkreisBrandId = Brand::query()->where('key', 'erftkreis_news')->value('id');

            return $bid === null || ((int) $bid === (int) $erftkreisBrandId);
        }

        if ($host !== '' && $host === $koelnimageHost) {
            $koelnimageBrandId = Brand::query()->where('key', 'koelnimage')->value('id');

            return (int) $bid === (int) $koelnimageBrandId;
        }

        $brand = app()->bound('currentBrand') ? app('currentBrand') : null;
        if (! $brand instanceof Brand) {
            return true;
        }

        if ($brand->key === 'koelnimage') {
            return (int) $bid === (int) $brand->id;
        }

        if ($brand->key === 'erftkreis_news') {
            return $bid === null || (int) $bid === (int) $brand->id;
        }

        return (int) $bid === (int) $brand->id;
    }
}
