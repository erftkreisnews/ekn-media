<?php

namespace App\Http\Controllers\KoelnImage;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;

class SitemapController extends Controller
{
    /**
     * Google Image Sitemap: je Galerieseite ({@see route('koelnimage.gallery.photos')}) die öffentlich ausspielbaren Bild-URLs.
     */
    public function imagesXml(): Response
    {
        $configuredHost = trim((string) config('brands.hosts.koelnimage'), '/');
        $requestRoot = rtrim((string) request()->getSchemeAndHttpHost(), '/');
        $requestHost = (string) parse_url($requestRoot !== '' ? $requestRoot : 'https://'.$configuredHost, PHP_URL_HOST);
        // Kanonische Seiten-URLs: https + konfigurierter Host (passt zur GSC-Property „https://koelnimage.de/…“).
        $pageBase = $configuredHost !== ''
            ? 'https://'.$configuredHost
            : ($requestRoot !== '' ? preg_replace('#^http:#', 'https:', $requestRoot) : 'https://localhost');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">'."\n";

        $brand = Brand::query()
            ->where('key', 'koelnimage')
            ->where('is_active', true)
            ->first();

        if ($brand && Schema::hasColumn('news_items', 'brand_id')) {
            $newsItems = NewsItem::query()
                ->publicVisible()
                ->where('brand_id', $brand->id)
                ->with(['images' => function ($q): void {
                    $q->where('is_visible', true)
                        ->where('versand', true)
                        ->where(function (Builder $b): void {
                            $b->whereNull('delivery_visible_for_organization_ids')
                                ->orWhereJsonLength('delivery_visible_for_organization_ids', 0);
                        })
                        ->orderBy('sort_order')
                        ->orderBy('id');
                }])
                ->orderByDesc('published_at')
                ->get(['id', 'slug', 'title', 'published_at', 'updated_at']);

            foreach ($newsItems as $item) {
                $path = route('koelnimage.gallery.photos', ['slug' => $item->slug], false);
                $path = is_string($path) ? $path : '/';
                $loc = rtrim($pageBase, '/').(str_starts_with($path, '/') ? $path : '/'.$path);
                $loc = $this->absoluteUrlForSitemap($loc, $pageBase, $requestHost);

                $lastmod = ($item->updated_at ?? $item->published_at)?->toAtomString();
                $newsTitle = trim((string) ($item->title ?? ''));

                $blocks = [];
                /** @var NewsItemMedia $media */
                foreach ($item->images as $media) {
                    if (! $media->isVisibleOnPublicArticle()) {
                        continue;
                    }
                    $imgLoc = $media->preview_url ?? $media->public_url;
                    if (! filled($imgLoc)) {
                        continue;
                    }
                    $imgLoc = $this->absoluteUrlForSitemap((string) $imgLoc, $pageBase, $requestHost);
                    if ($imgLoc === '' || ! str_starts_with(strtolower($imgLoc), 'http')) {
                        continue;
                    }
                    $title = trim((string) ($media->image_title ?? $media->caption ?? ''));
                    if ($title === '' && $newsTitle !== '') {
                        $title = $newsTitle;
                    }
                    $caption = trim((string) ($media->caption ?? ''));
                    $block = "    <image:image>\n";
                    $block .= '      <image:loc>'.$this->xmlEsc($imgLoc)."</image:loc>\n";
                    if ($title !== '') {
                        $block .= '      <image:title>'.$this->xmlEsc($title)."</image:title>\n";
                    }
                    if ($caption !== '' && $caption !== $title) {
                        $block .= '      <image:caption>'.$this->xmlEsc($caption)."</image:caption>\n";
                    }
                    $block .= "    </image:image>\n";
                    $blocks[] = $block;
                }
                if ($blocks === []) {
                    continue;
                }
                $xml .= "  <url>\n";
                $xml .= '    <loc>'.$this->xmlEsc($loc)."</loc>\n";
                if (is_string($lastmod) && $lastmod !== '') {
                    $xml .= '    <lastmod>'.$this->xmlEsc($lastmod)."</lastmod>\n";
                }
                $xml .= implode('', $blocks);
                $xml .= "  </url>\n";
            }
        }

        $xml .= '</urlset>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function xmlEsc(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Google verlangt vollständige http(s)-URLs. Relative Pfade (z. B. von der „public“-Disk) werden an die Seiten-Domain gehängt.
     * Nur bei gleicher Host-Angabe wie die Seite wird http→https umgestellt (Presigned-Fremd-URLs bleiben unverändert).
     */
    private function absoluteUrlForSitemap(string $url, string $pageBase, string $requestHost): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (! preg_match('#^https?://#i', $url)) {
            return rtrim($pageBase, '/').'/'.ltrim($url, '/');
        }
        $host = parse_url($url, PHP_URL_HOST);
        $canonicalHost = parse_url(rtrim($pageBase, '/').'/', PHP_URL_HOST);
        if (is_string($host) && is_string($canonicalHost)
            && strcasecmp($host, $canonicalHost) === 0
            && str_starts_with(strtolower($url), 'http://')) {
            return 'https://'.substr($url, 7);
        }
        if (is_string($host) && $requestHost !== ''
            && strcasecmp($host, $requestHost) === 0
            && str_starts_with(strtolower($url), 'http://')) {
            return 'https://'.substr($url, 7);
        }

        return $url;
    }
}
