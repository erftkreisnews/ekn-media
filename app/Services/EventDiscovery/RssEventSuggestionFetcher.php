<?php

namespace App\Services\EventDiscovery;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SimpleXMLElement;

class RssEventSuggestionFetcher
{
    /**
     * @return list<array<string, mixed>>
     */
    public function fetchFromFeeds(array $urls): array
    {
        $urls = array_values(array_filter(array_map('trim', $urls), fn ($u) => is_string($u) && Str::startsWith($u, ['http://', 'https://'])));

        $rows = [];

        foreach ($urls as $feedUrl) {
            foreach ($this->fetchSingleFeed($feedUrl) as $row) {
                $key = (string) ($row['external_id'] ?? '');
                if ($key !== '') {
                    $rows[$key] = $row;
                }
            }
        }

        return array_values($rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchSingleFeed(string $feedUrl): array
    {
        try {
            $xmlString = Http::timeout(30)->accept('application/rss+xml, application/xml, text/xml')->get($feedUrl)->body();
        } catch (\Throwable $e) {
            Log::warning('Event-Discovery: RSS konnte nicht geladen werden ('.$feedUrl.'): '.$e->getMessage());

            return [];
        }

        try {
            $xml = @new SimpleXMLElement($xmlString);
        } catch (\Throwable $e) {
            Log::warning('Event-Discovery: RSS Parse-Fehler ('.$feedUrl.'): '.$e->getMessage());

            return [];
        }

        $channel = $xml->channel ?? $xml;

        $out = [];

        foreach ($channel->item ?? [] as $item) {
            $title = trim((string) ($item->title ?? ''));
            $link = trim((string) ($item->link ?? ''));
            if ($title === '' && $link === '') {
                continue;
            }

            $guid = trim((string) ($item->guid ?? ''));
            $externalBasis = $link !== '' ? $link : $guid;
            if ($externalBasis === '') {
                $externalBasis = Str::substr($title, 0, 200);
            }

            $sourceTag = parse_url((string) $feedUrl, PHP_URL_HOST) ?: 'rss';
            $hash = md5($sourceTag.'|'.$externalBasis);

            $description = trim(strip_tags((string) ($item->description ?? '')));

            $pubRaw = trim((string) ($item->pubDate ?? ''));
            $startsAt = null;
            if ($pubRaw !== '') {
                try {
                    $startsAt = Carbon::parse($pubRaw)->toDateString();
                } catch (\Throwable) {
                    $startsAt = null;
                }
            }

            $out[] = [
                'external_id' => $hash,
                'title' => $title !== '' ? $title : 'Eintrag ohne Titel',
                'description' => $description !== '' ? Str::limit($description, 8000, '') : null,
                'info_url' => $link !== '' ? $link : null,
                'image_url' => null,
                'starts_at' => $startsAt,
                'ends_at' => null,
                'venue_name' => null,
                'venue_street' => null,
                'venue_postal_code' => null,
                'venue_city' => null,
                'venue_state' => null,
                'venue_country' => 'Deutschland',
                'venue_country_code' => 'DE',
                'category' => 'RSS',
                'raw_payload' => [
                    'feed' => $feedUrl,
                    'title' => $title,
                    'link' => $link,
                    'pubDate' => $pubRaw !== '' ? $pubRaw : null,
                ],
            ];
        }

        return $out;
    }
}
