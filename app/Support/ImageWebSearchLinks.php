<?php

namespace App\Support;

use App\Models\NewsItemMedia;

/**
 * Manuelle Websuche zu Bildern (Google Lens / News) — ohne externe API.
 */
final class ImageWebSearchLinks
{
    /**
     * @return array{google_lens: ?string, google_news: ?string, image_url: ?string}
     */
    public static function forMedia(NewsItemMedia $media): array
    {
        $media->loadMissing('newsItem');

        $imageUrl = self::resolvePublicImageUrl($media);
        $googleLens = $imageUrl !== null
            ? 'https://lens.google.com/uploadbyurl?url='.rawurlencode($imageUrl)
            : null;

        $newsQuery = self::buildNewsSearchQuery($media);

        return [
            'image_url' => $imageUrl,
            'google_lens' => $googleLens,
            'google_news' => $newsQuery !== null
                ? 'https://www.google.com/search?'.http_build_query(['q' => $newsQuery, 'tbm' => 'nws'])
                : null,
        ];
    }

    public static function resolvePublicImageUrl(NewsItemMedia $media): ?string
    {
        try {
            $url = $media->preview_url ?? $media->public_url ?? $media->url ?? null;
        } catch (\Throwable) {
            $url = null;
        }

        if (! is_string($url) || $url === '') {
            return null;
        }

        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            return null;
        }

        return $url;
    }

    private static function buildNewsSearchQuery(NewsItemMedia $media): ?string
    {
        $parts = array_filter([
            trim((string) ($media->image_title ?? '')),
            trim((string) ($media->newsItem?->title ?? '')),
            trim((string) ($media->photographer ?? '')),
            'Erftkreis News',
        ]);

        $query = trim(implode(' ', array_unique($parts)));

        return $query !== '' ? $query : null;
    }
}
