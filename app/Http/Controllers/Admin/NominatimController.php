<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\NominatimAddressMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class NominatimController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:200'],
        ]);

        $q = trim($validated['q']);
        $ttl = (int) config('nominatim.cache_ttl_seconds', 3600);
        $cacheKey = 'nominatim.search.v1.'.md5(Str::lower($q).':'.(string) config('nominatim.countrycodes'));

        try {
            $payload = Cache::remember($cacheKey, now()->addSeconds(max(60, $ttl)), function () use ($q) {
                $params = [
                    'q' => $q,
                    'format' => 'json',
                    'addressdetails' => 1,
                    'limit' => 8,
                ];
                $cc = trim((string) config('nominatim.countrycodes'));
                if ($cc !== '') {
                    $params['countrycodes'] = $cc;
                }

                $response = Http::withHeaders($this->nominatimHeaders())
                    ->timeout(12)
                    ->get(config('nominatim.base_url').'/search', $params);

                if (! $response->successful()) {
                    throw new \RuntimeException('Nominatim HTTP '.$response->status());
                }

                $data = $response->json();
                if (! is_array($data)) {
                    throw new \RuntimeException('Nominatim ungültige JSON-Antwort');
                }

                $results = [];
                foreach ($data as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $results[] = NominatimAddressMapper::formatSearchHit($row);
                }

                return ['error' => null, 'results' => $results];
            });
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'error' => 'Ortssuche momentan nicht möglich. Bitte später erneut versuchen.',
                'results' => [],
            ], 503);
        }

        return response()->json($payload);
    }

    public function reverse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lon' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $lat = round((float) $validated['lat'], 7);
        $lon = round((float) $validated['lon'], 7);

        $cacheKey = 'nominatim.rev.v1.'.md5($lat.':'.$lon);
        $ttl = (int) config('nominatim.cache_ttl_seconds', 3600);

        try {
            $payload = Cache::remember($cacheKey, now()->addSeconds(max(60, $ttl)), function () use ($lat, $lon) {
                $response = Http::withHeaders($this->nominatimHeaders())
                    ->timeout(12)
                    ->get(config('nominatim.base_url').'/reverse', [
                        'lat' => $lat,
                        'lon' => $lon,
                        'format' => 'json',
                        'addressdetails' => 1,
                    ]);

                if (! $response->successful()) {
                    throw new \RuntimeException('Nominatim reverse HTTP '.$response->status());
                }

                $row = $response->json();
                if (! is_array($row)) {
                    throw new \RuntimeException('Nominatim reverse ungültige Antwort');
                }

                $hit = NominatimAddressMapper::formatSearchHit($row);

                return [
                    'error' => null,
                    'display_name' => $hit['display_name'],
                    'lat' => $hit['lat'],
                    'lon' => $hit['lon'],
                    'fields' => $hit['fields'],
                ];
            });
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'error' => 'Adressauflösung momentan nicht möglich. Bitte später erneut versuchen.',
            ], 503);
        }

        return response()->json($payload);
    }

    /**
     * @return array<string, string>
     */
    private function nominatimHeaders(): array
    {
        $ua = trim((string) config('nominatim.user_agent'));
        $email = trim((string) config('nominatim.contact_email'));

        if ($ua === '') {
            $app = config('app.name', 'Laravel');
            $ua = $app.'/1.0';
        }
        if ($email !== '') {
            $ua .= ' (contact: '.$email.')';
        }

        return [
            'User-Agent' => $ua,
            'Accept' => 'application/json',
            'Accept-Language' => 'de',
        ];
    }
}
