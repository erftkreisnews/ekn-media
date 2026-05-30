<?php

namespace App\Services\EventDiscovery;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Öffentlicher Eventkalender-Export der LANXESS arena Köln (JSON).
 *
 * @see https://www.lanxess-arena.de/events-tickets/eventkalender
 */
class LanxessArenaJsonFetcher
{
    /**
     * @return list<array<string, mixed>>
     */
    public function fetchNormalizedRows(): array
    {
        $url = (string) config('event_discovery.lanxess_arena.json_url', '');
        if ($url === '' || ! config('event_discovery.lanxess_arena.enabled', false)) {
            return [];
        }

        try {
            $response = Http::timeout(45)
                ->acceptJson()
                ->withHeaders(['User-Agent' => 'ErftkreisMediaEventDiscovery/1.0'])
                ->get($url);
        } catch (\Throwable $e) {
            Log::warning('Event-Discovery: LANXESS-JSON nicht erreichbar: '.$e->getMessage());

            return [];
        }

        if (! $response->successful()) {
            Log::warning('Event-Discovery: LANXESS-JSON HTTP '.$response->status());

            return [];
        }

        $rows = data_get($response->json(), 'eventdates');
        if (! is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $n = $this->normalizeRow($row);
            if ($n !== null) {
                $out[] = $n;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function normalizeRow(array $row): ?array
    {
        $uid = trim((string) ($row['uid'] ?? ''));
        if ($uid === '') {
            return null;
        }

        $title = trim((string) ($row['title'] ?? ''));
        if ($title === '') {
            return null;
        }

        $subtitle = trim((string) ($row['subtitle'] ?? ''));
        if ($subtitle !== '') {
            $title .= ' ('.$subtitle.')';
        }

        $dateRaw = data_get($row, 'datestart.date');
        $dateRaw = is_string($dateRaw) ? trim($dateRaw) : '';
        $startsAt = null;
        if ($dateRaw !== '') {
            try {
                $startsAt = Carbon::parse($dateRaw)->toDateString();
            } catch (\Throwable) {
                $startsAt = null;
            }
        }

        $text = (string) ($row['text'] ?? '');
        $description = $text !== '' ? Str::limit(trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 8000, '') : null;

        $infoUrl = 'https://www.lanxess-arena.de/eventdetail/'.$uid;
        if (preg_match('#https://www\.lanxess-arena\.de/eventdetail/\d+#', (string) ($row['html'] ?? ''), $m) === 1) {
            $infoUrl = $m[0];
        }

        $imageUrl = null;
        if (preg_match('/data-src="(https:\/\/www\.lanxess-arena\.de\/[^"]+)"/', (string) ($row['html'] ?? ''), $im) === 1) {
            $imageUrl = $im[1];
        }

        $venueName = (string) config('event_discovery.lanxess_arena.venue_name', 'LANXESS arena');

        return [
            'external_id' => $uid,
            'title' => $title,
            'description' => $description,
            'info_url' => $infoUrl,
            'image_url' => $imageUrl,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt,
            'venue_name' => $venueName,
            'venue_street' => (string) config('event_discovery.lanxess_arena.venue_street', 'Willy-Brandt-Platz 3'),
            'venue_postal_code' => (string) config('event_discovery.lanxess_arena.venue_postal_code', '50679'),
            'venue_city' => (string) config('event_discovery.lanxess_arena.venue_city', 'Köln'),
            'venue_state' => (string) config('event_discovery.lanxess_arena.venue_state', 'Nordrhein-Westfalen'),
            'venue_country' => (string) config('event_discovery.lanxess_arena.venue_country', 'Deutschland'),
            'venue_country_code' => strtoupper(substr((string) config('event_discovery.lanxess_arena.venue_country_code', 'DE'), 0, 2)),
            'category' => 'LANXESS arena',
            'raw_payload' => [
                'uid' => $uid,
                'datestart' => $row['datestart'] ?? null,
                'title' => $row['title'] ?? null,
            ],
        ];
    }
}
