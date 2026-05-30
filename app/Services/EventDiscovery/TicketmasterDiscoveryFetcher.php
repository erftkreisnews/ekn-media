<?php

namespace App\Services\EventDiscovery;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TicketmasterDiscoveryFetcher
{
    /**
     * @return list<array<string, mixed>>
     */
    public function fetchEvents(): array
    {
        $key = (string) config('event_discovery.ticketmaster.api_key', '');
        if ($key === '') {
            Log::info('Event-Discovery: Ticketmaster-API-Key fehlt (.env EVENT_DISCOVERY_TICKETMASTER_KEY oder TICKETMASTER_API_KEY).');

            return [];
        }

        $timezone = (string) config('event_discovery.timezone', 'Europe/Berlin');
        $lookahead = (int) config('event_discovery.ticketmaster.lookahead_days', 60);
        $country = (string) config('event_discovery.ticketmaster.country_code', 'DE');
        $locale = (string) config('event_discovery.ticketmaster.locale', 'de-de');
        $pageSize = (int) config('event_discovery.ticketmaster.page_size', 40);

        $from = Carbon::now($timezone)->startOfDay()->utc();
        $to = Carbon::now($timezone)->addDays($lookahead)->endOfDay()->utc();

        $segmentIds = (array) config('event_discovery.ticketmaster.segment_ids', []);
        $segmentIds = array_values(array_filter($segmentIds, fn ($id) => is_string($id) && $id !== ''));

        $out = [];
        $allowlistEnabled = (bool) config('event_discovery.ticketmaster.use_city_allowlist', true);
        $allowlist = (array) config('event_discovery.ticketmaster.city_allowlist', []);
        $allowlist = array_values(array_filter($allowlist, fn ($t) => is_string($t) && $t !== ''));

        foreach ($segmentIds as $segmentId) {
            $merged = $this->requestSegment(
                apiKey: $key,
                countryCode: $country,
                locale: $locale,
                segmentId: (string) $segmentId,
                startUtc: $from,
                endUtc: $to,
                size: $pageSize
            );

            foreach ($merged as $row) {
                if (! is_array($row)) {
                    continue;
                }
                if ($allowlistEnabled && $allowlist !== [] && ! $this->eventMatchesCityAllowlist($row, $allowlist)) {
                    continue;
                }
                $out[(string) ($row['id'] ?? '')] = $row;
            }
        }

        return array_values($out);
    }

    /**
     * @param  array<string, mixed>  $event
     * @param  list<string>  $allowlist  bereits mb_strtolower-normalisierte Suchbegriffe
     */
    private function eventMatchesCityAllowlist(array $event, array $allowlist): bool
    {
        $venue = Arr::first(Arr::wrap(data_get($event, '_embedded.venues')));
        $venue = is_array($venue) ? $venue : [];

        $hay = mb_strtolower(implode(' ', array_filter([
            (string) data_get($venue, 'city.name'),
            (string) ($venue['name'] ?? ''),
            (string) data_get($venue, 'address.line1'),
            (string) data_get($venue, 'address.line2'),
            (string) ($event['name'] ?? ''),
        ])), 'UTF-8');

        foreach ($allowlist as $needle) {
            if ($needle !== '' && str_contains($hay, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function requestSegment(
        string $apiKey,
        string $countryCode,
        string $locale,
        string $segmentId,
        Carbon $startUtc,
        Carbon $endUtc,
        int $size
    ): array {
        $url = 'https://app.ticketmaster.com/discovery/v2/events.json';

        $geoEnabled = (bool) config('event_discovery.ticketmaster.geo_enabled', true);
        $latlong = trim((string) config('event_discovery.ticketmaster.latlong', ''));
        $radiusKm = (int) config('event_discovery.ticketmaster.radius_km', 115);
        $maxPages = (int) config('event_discovery.ticketmaster.max_pages', 8);

        $all = [];

        for ($page = 0; $page < $maxPages; $page++) {
            $query = [
                'apikey' => $apiKey,
                'countryCode' => $countryCode,
                'locale' => $locale,
                'segmentId' => $segmentId,
                'startDateTime' => $startUtc->format('Y-m-d\TH:i:s\Z'),
                'endDateTime' => $endUtc->format('Y-m-d\TH:i:s\Z'),
                'size' => $size,
                'page' => $page,
                'sort' => 'date,asc',
            ];

            if ($geoEnabled && $latlong !== '' && preg_match('/^-?\d+(\.\d+)?,-?\d+(\.\d+)?$/', $latlong) === 1) {
                $query['latlong'] = $latlong;
                $query['radius'] = max(5, min(500, $radiusKm));
                $query['unit'] = 'km';
            }

            try {
                $response = Http::timeout(45)
                    ->acceptJson()
                    ->get($url, $query);
            } catch (\Throwable $e) {
                Log::warning('Event-Discovery: Ticketmaster HTTP-Fehler: '.$e->getMessage());

                return $all;
            }

            if (! $response->successful()) {
                Log::warning('Event-Discovery: Ticketmaster Antwort '.$response->status().' für Segment '.$segmentId.' Seite '.$page);

                return $all;
            }

            $events = data_get($response->json(), '_embedded.events');
            if (! is_array($events) || $events === []) {
                break;
            }

            foreach ($events as $ev) {
                if (is_array($ev)) {
                    $all[] = $ev;
                }
            }

            if (count($events) < $size) {
                break;
            }
        }

        return $all;
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    public function normalizeEvent(array $event): array
    {
        $id = (string) ($event['id'] ?? '');
        $name = (string) ($event['name'] ?? '');
        $url = (string) ($event['url'] ?? '');

        $localDate = (string) data_get($event, 'dates.start.localDate');
        $localEndDate = (string) data_get($event, 'dates.end.localDate');
        if ($localEndDate === '') {
            $localEndDate = $localDate;
        }

        $imageUrl = '';
        $images = data_get($event, 'images');
        if (is_array($images) && $images !== []) {
            $best = collect($images)
                ->filter(fn ($img) => is_array($img) && ! empty($img['url']))
                ->sortByDesc(fn ($img) => (int) ($img['width'] ?? 0))
                ->first();
            if (is_array($best)) {
                $imageUrl = (string) ($best['url'] ?? '');
            }
        }

        $info = '';
        if (filled($event['info'] ?? null)) {
            $info = (string) $event['info'];
        }
        $pleaseNote = '';
        if (filled($event['pleaseNote'] ?? null)) {
            $pleaseNote = (string) $event['pleaseNote'];
        }

        $description = trim(implode("\n\n", array_filter([$info, $pleaseNote])));

        $venue = Arr::first(Arr::wrap(data_get($event, '_embedded.venues')));
        $venue = is_array($venue) ? $venue : [];

        $venueName = (string) ($venue['name'] ?? '');
        $line1 = (string) data_get($venue, 'address.line1');
        $postal = (string) data_get($venue, 'postalCode');
        if ($postal === '') {
            $postal = (string) data_get($venue, 'address.postalCode');
        }

        $city = (string) data_get($venue, 'city.name');
        $state = (string) data_get($venue, 'state.name');
        if ($state === '') {
            $state = (string) data_get($venue, 'state.stateCode');
        }

        $country = (string) data_get($venue, 'country.name');
        $cc = strtoupper((string) data_get($venue, 'country.countryCode'));

        $category = '';
        $classifications = data_get($event, 'classifications');
        if (is_array($classifications) && $classifications !== []) {
            $c0 = Arr::first($classifications);
            if (is_array($c0)) {
                $category = (string) data_get($c0, 'segment.name');
                if ($category === '') {
                    $category = (string) data_get($c0, 'genre.name');
                }
            }
        }

        return [
            'external_id' => $id,
            'title' => $name !== '' ? $name : 'Unbenannte Veranstaltung',
            'description' => $description !== '' ? $description : null,
            'info_url' => $url !== '' ? $url : null,
            'image_url' => $imageUrl !== '' ? $imageUrl : null,
            'starts_at' => $this->parseDateOrNull($localDate),
            'ends_at' => $this->parseDateOrNull($localEndDate !== '' ? $localEndDate : $localDate),
            'venue_name' => $venueName !== '' ? $venueName : null,
            'venue_street' => $line1 !== '' ? $line1 : null,
            'venue_postal_code' => $postal !== '' ? $postal : null,
            'venue_city' => $city !== '' ? $city : null,
            'venue_state' => $state !== '' ? $state : null,
            'venue_country' => $country !== '' ? $country : 'Deutschland',
            'venue_country_code' => strlen($cc) === 2 ? $cc : 'DE',
            'category' => $category !== '' ? $category : null,
            'raw_payload' => $event,
        ];
    }

    private function parseDateOrNull(string $ymd): ?string
    {
        $ymd = trim($ymd);
        if ($ymd === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd) !== 1) {
            return null;
        }

        return $ymd;
    }
}
