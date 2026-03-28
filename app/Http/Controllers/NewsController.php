<?php

namespace App\Http\Controllers;

use App\Models\NewsItem;
use App\Models\NewsSlugRedirect;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NewsController extends Controller
{
    /**
     * Öffentliche Nachrichten-Übersicht (tv7-ähnlich, EKN-CI).
     */
    public function index(Request $request): View
    {
        $query = NewsItem::query()
            ->publicVisible()
            ->with(['images', 'videos', 'audios'])
            ->orderByDesc('published_at');

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($qry) use ($q) {
                $qry->where('title', 'like', '%'.$q.'%')
                    ->orWhere('teaser', 'like', '%'.$q.'%')
                    ->orWhere('body', 'like', '%'.$q.'%');
            });
        }

        $news = $query->paginate(10)->withQueryString();

        return view('news.index', compact('news'));
    }

    /**
     * Einzelansicht einer Nachricht (weiterlesen).
     */
    public function show(string $slug): View
    {
        // 1) Versuche: existiert ein NewsItem unter diesem Slug?
        $newsItem = NewsItem::query()
            ->where('slug', $slug)
            ->with(['media', 'author'])
            ->first();

        // 2) Anforderung: Redirect darf NICHT einen publicVisible Artikel überschreiben.
        //    Wenn der Artikel direkt gefunden wurde und publicVisible ist -> sofort rendern.
        if ($newsItem && $this->isPublicVisibleNow($newsItem)) {
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
                    if ($this->isPublicVisibleNow($target)) {
                        return redirect()->route('news.show', $target->slug, 301);
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
}
