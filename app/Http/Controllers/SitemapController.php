<?php

namespace App\Http\Controllers;

use App\Models\NewsItem;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * robots.txt – erlaubt Crawler, verweist auf Sitemap, optional KI-Crawler.
     */
    public function robots(): Response
    {
        $sitemapUrl = rtrim(config('app.url'), '/').'/sitemap.xml';

        $baseUrl = rtrim(config('app.url'), '/');
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /dashboard',
            'Disallow: /d/',
            '',
            '# KI-/AI-Crawler (dürfen öffentliche News indexieren)',
            'User-agent: GPTBot',
            'Allow: /',
            'Allow: /news/',
            'Disallow: /admin',
            'Disallow: /dashboard',
            'Disallow: /d/',
            '',
            'User-agent: Google-Extended',
            'Allow: /',
            'Allow: /news/',
            'Disallow: /admin',
            'Disallow: /dashboard',
            'Disallow: /d/',
            '',
            'User-agent: Claude-Web',
            'Allow: /',
            'Allow: /news/',
            'Disallow: /admin',
            'Disallow: /dashboard',
            'Disallow: /d/',
            '',
            'User-agent: PerplexityBot',
            'Allow: /',
            'Allow: /news/',
            'Disallow: /admin',
            'Disallow: /dashboard',
            'Disallow: /d/',
            '',
            'User-agent: Cohere-AI',
            'Allow: /',
            'Allow: /news/',
            'Disallow: /admin',
            'Disallow: /dashboard',
            'Disallow: /d/',
            '',
            'Sitemap: '.$sitemapUrl,
            'Sitemap: '.$baseUrl.'/sitemap-news.xml',
        ];

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Haupt-Sitemap (Index oder einzige Sitemap mit allen URLs).
     * Enthält Startseite + alle veröffentlichten News-Artikel.
     */
    public function index(): Response
    {
        $baseUrl = rtrim(config('app.url'), '/');

        $urls = [];

        // Startseite / Nachrichten-Übersicht
        $urls[] = [
            'loc' => $baseUrl.'/'.ltrim(route('home', [], false), '/'),
            'lastmod' => now()->toIso8601String(),
            'changefreq' => 'hourly',
            'priority' => '1.0',
        ];

        $news = NewsItem::query()
            ->publicVisible()
            ->orderByDesc('published_at')
            ->get(['id', 'slug', 'published_at', 'updated_at']);

        foreach ($news as $item) {
            $lastmod = ($item->updated_at ?? $item->published_at)?->toIso8601String() ?? now()->toIso8601String();
            $urls[] = [
                'loc' => $baseUrl.'/news/'.$item->slug,
                'lastmod' => $lastmod,
                'changefreq' => 'monthly',
                'priority' => '0.8',
            ];
        }

        $xml = $this->buildSitemapXml($urls);

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function buildSitemapXml(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $u) {
            $loc = trim((string) ($u['loc'] ?? ''));
            $lastmod = trim((string) ($u['lastmod'] ?? ''));
            $changefreq = trim((string) ($u['changefreq'] ?? ''));
            $priority = trim((string) ($u['priority'] ?? ''));

            $xml .= '  <url>'."\n";
            $xml .= '    <loc>'.htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc>'."\n";
            $xml .= '    <lastmod>'.htmlspecialchars($lastmod, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</lastmod>'."\n";
            $xml .= '    <changefreq>'.htmlspecialchars($changefreq, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</changefreq>'."\n";
            $xml .= '    <priority>'.htmlspecialchars($priority, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</priority>'."\n";
            $xml .= '  </url>'."\n";
        }

        $xml .= '</urlset>';

        return $xml;
    }

    /**
     * Google-News-Sitemap (Format 0.9).
     * Nur Meldungen der letzten 48 Stunden (Vorgabe Google), max. 1000 Einträge.
     */
    public function newsSitemap(): Response
    {
        $baseUrl = rtrim(config('app.url'), '/');
        $publicationName = config('app.name', 'Erftkreis News');
        $language = str_replace('_', '-', app()->getLocale());
        if ($language === '') {
            $language = 'de';
        }

        $baseQuery = NewsItem::query()
            ->publicVisible()
            ->whereNotNull('published_at')
            ->orderByDesc('published_at');

        $news = (clone $baseQuery)
            ->where('published_at', '>=', now()->subHours(48))
            ->limit(1000)
            ->get(['id', 'slug', 'title', 'keywords', 'published_at', 'updated_at']);

        // Fallback: Wenn in den letzten 48h nichts publiziert wurde, die neuesten
        // öffentlichen Meldungen ausliefern, damit die News-Sitemap nicht leer ist.
        if ($news->isEmpty()) {
            $news = (clone $baseQuery)
                ->limit(1000)
                ->get(['id', 'slug', 'title', 'keywords', 'published_at', 'updated_at']);
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
        $xml .= ' xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">'."\n";

        foreach ($news as $item) {
            if (! $item->published_at) {
                continue;
            }
            $loc = $baseUrl.'/news/'.$item->slug;
            $pubDate = $item->published_at->toIso8601String();
            $lastmod = ($item->updated_at ?? $item->published_at)?->toIso8601String() ?? $pubDate;
            $title = $item->title;
            $keywords = trim((string) ($item->keywords ?? ''));

            $xml .= '  <url>'."\n";
            $xml .= '    <loc>'.e($loc).'</loc>'."\n";
            $xml .= '    <lastmod>'.e($lastmod).'</lastmod>'."\n";
            $xml .= '    <news:news>'."\n";
            $xml .= '      <news:publication>'."\n";
            $xml .= '        <news:name>'.e($publicationName).'</news:name>'."\n";
            $xml .= '        <news:language>'.e($language).'</news:language>'."\n";
            $xml .= '      </news:publication>'."\n";
            $xml .= '      <news:publication_date>'.e($pubDate).'</news:publication_date>'."\n";
            $xml .= '      <news:title>'.e($title).'</news:title>'."\n";
            if ($keywords !== '') {
                $xml .= '      <news:keywords>'.e($keywords).'</news:keywords>'."\n";
            }
            $xml .= '    </news:news>'."\n";
            $xml .= '  </url>'."\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=1800',
        ]);
    }
}
