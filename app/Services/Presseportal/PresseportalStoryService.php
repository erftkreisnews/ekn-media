<?php

namespace App\Services\Presseportal;

use App\Models\NewsItemStatement;
use App\Models\PresseportalOffice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PresseportalStoryService
{
    /**
     * Erkennt Presseportal-URLs (de/ch) mit Pfad …/pm/{officeId}/{storyId}.
     *
     * @return array{office_id:int, story_id:int, url:string}|null
     */
    public function parseUrl(string $url): ?array
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (! preg_match('~^https?://(?:www\.)?presseportal\.(?:de|ch)/(?:[^/\s]+/)*pm/(\d+)/(\d+)/?(?:[#!?].*)?$~i', $url, $m)) {
            return null;
        }

        return [
            'office_id' => (int) $m[1],
            'story_id' => (int) $m[2],
            'url' => $url,
        ];
    }

    /**
     * Wenn mindestens eine aktive Dienststelle gepflegt ist, nur diese IDs erlauben.
     */
    public function assertOfficeAllowed(int $officeId): void
    {
        $count = PresseportalOffice::query()->active()->count();
        if ($count === 0) {
            return;
        }

        $allowed = PresseportalOffice::query()
            ->active()
            ->where('office_id', $officeId)
            ->exists();

        if (! $allowed) {
            throw new \RuntimeException(
                'Diese Dienststelle ist nicht in den Einstellungen unter Presseportal hinterlegt. Bitte dort die Dienststellen-ID '.$officeId.' ergänzen oder die URL prüfen.'
            );
        }
    }

    /**
     * @return array{
     *   title: string,
     *   body: string,
     *   story_url: string,
     *   story_id: int,
     *   office_id: ?int,
     *   office_name: ?string,
     *   published_at: ?Carbon,
     *   source_type: ?string,
     *   source_label: ?string
     * }
     */
    public function fetchByUrl(string $url): array
    {
        $parsed = $this->parseUrl($url);
        if ($parsed === null) {
            throw new \InvalidArgumentException('Keine gültige Presseportal-URL (erwartet: …/pm/Dienststellen-ID/Meldungs-ID).');
        }

        $this->assertOfficeAllowed($parsed['office_id']);

        return $this->fetchByStoryId($parsed['story_id'], $parsed['url'], $parsed['office_id']);
    }

    /**
     * @return array{
     *   title: string,
     *   body: string,
     *   story_url: string,
     *   story_id: int,
     *   office_id: ?int,
     *   office_name: ?string,
     *   published_at: ?Carbon,
     *   source_type: ?string,
     *   source_label: ?string
     * }
     */
    public function fetchByStoryId(int $storyId, ?string $canonicalUrl = null, ?int $expectedOfficeId = null): array
    {
        $apiKey = config('presseportal.api_key');
        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new \RuntimeException('PRESSEPORTAL_API_KEY ist in der .env nicht gesetzt.');
        }

        $base = (string) config('presseportal.base_url', 'https://api.presseportal.de/api/v2');
        $timeout = (int) config('presseportal.timeout', 20);
        $endpoint = rtrim($base, '/').'/story/'.$storyId;

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->get($endpoint, ['api_key' => $apiKey]);

        $json = $response->json();

        // Die API liefert bei Key-/Fehlerfällen oft HTTP 403 mit JSON { error: … } – vor „successful()“ auswerten.
        if (is_array($json) && isset($json['error']) && is_array($json['error'])) {
            $code = trim((string) ($json['error']['code'] ?? ''));
            $msg = (string) ($json['error']['msg'] ?? 'Unbekannter Fehler');
            // Einzelabruf /story/{id} liefert für manche Blaulicht-Meldungen Code 200 – gleiche Meldung steht oft in /stories/office/{id}
            if ($code === '200' && $expectedOfficeId !== null) {
                $item = $this->fetchStoryViaOfficeList($storyId, $expectedOfficeId, $apiKey, $base, $timeout);
                if ($item !== null) {
                    Log::info('Presseportal: Meldung über Dienststellen-Liste geladen (Fallback nach Einzelabruf 200).', [
                        'story_id' => $storyId,
                        'office_id' => $expectedOfficeId,
                    ]);

                    return $this->storyItemToResult($item, $storyId, $canonicalUrl, $expectedOfficeId);
                }
            }
            throw new \RuntimeException($this->mapApiError($code, $msg));
        }

        if (! $response->successful()) {
            Log::warning('Presseportal API HTTP-Fehler', ['status' => $response->status(), 'body' => $response->body()]);
            if ($response->status() === 404) {
                throw new \RuntimeException(
                    'Presseportal-API: HTTP 404 – die Basis-URL ist vermutlich falsch. In der .env muss PRESSEPORTAL_API_BASE_URL die API V2 ansprechen, z. B. https://api.presseportal.de/api/v2 (nicht nur den Host ohne /api/v2).'
                );
            }

            throw new \RuntimeException('Presseportal-API antwortet nicht (HTTP '.$response->status().').');
        }

        if (! is_array($json)) {
            throw new \RuntimeException('Ungültige API-Antwort.');
        }

        $item = $this->extractStoryItem($json);
        if ($item === null) {
            throw new \RuntimeException('In der API-Antwort wurde keine Meldung gefunden.');
        }

        return $this->storyItemToResult($item, $storyId, $canonicalUrl, $expectedOfficeId);
    }

    /**
     * Sucht die Meldung in den Listen der Dienststelle (entspricht eher dem Abruf „Newsroom“-Feeds).
     *
     * @return array<string, mixed>|null
     */
    private function fetchStoryViaOfficeList(int $storyId, int $officeId, string $apiKey, string $base, int $timeout): ?array
    {
        $endpoint = rtrim($base, '/').'/stories/office/'.$officeId;
        $limit = max(1, min(50, (int) config('presseportal.office_list_page_size', 50)));
        $maxPages = max(1, (int) config('presseportal.office_list_max_pages', 40));
        $start = 0;

        for ($page = 0; $page < $maxPages; $page++) {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->get($endpoint, [
                    'api_key' => $apiKey,
                    'limit' => $limit,
                    'start' => $start,
                ]);

            $json = $response->json();
            if (is_array($json) && isset($json['error']) && is_array($json['error'])) {
                $code = trim((string) ($json['error']['code'] ?? ''));
                $msg = (string) ($json['error']['msg'] ?? 'Unbekannter Fehler');
                if (in_array($code, ['100', '101', '102'], true)) {
                    throw new \RuntimeException($this->mapApiError($code, $msg));
                }
                if ($code === '150') {
                    throw new \RuntimeException($this->mapApiError($code, $msg));
                }
                Log::warning('Presseportal: office list API error', [
                    'office_id' => $officeId,
                    'error' => $json['error'],
                ]);

                return null;
            }

            if (! $response->successful() || ! is_array($json)) {
                return null;
            }

            $batch = $this->extractStoriesFromListJson($json);
            foreach ($batch as $item) {
                if (! isset($item['id'])) {
                    continue;
                }
                if ((int) $item['id'] === $storyId) {
                    return $item;
                }
            }
            if (count($batch) < $limit) {
                break;
            }
            $start += $limit;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{
     *   title: string,
     *   body: string,
     *   story_url: string,
     *   story_id: int,
     *   office_id: ?int,
     *   office_name: ?string,
     *   published_at: ?Carbon,
     *   source_type: ?string,
     *   source_label: ?string
     * }
     */
    private function storyItemToResult(array $item, int $storyId, ?string $canonicalUrl, ?int $expectedOfficeId): array
    {
        $title = (string) ($item['title'] ?? '');
        $body = (string) ($item['body'] ?? $item['teaser'] ?? '');
        if ($body === '' && $title === '') {
            throw new \RuntimeException('Die Meldung enthielt keinen Text.');
        }

        $storyUrl = (string) ($item['url'] ?? $canonicalUrl ?? '');
        $officeId = null;
        $officeName = null;

        if (isset($item['office']) && is_array($item['office'])) {
            $officeId = isset($item['office']['id']) ? (int) $item['office']['id'] : null;
            $officeName = isset($item['office']['name']) ? (string) $item['office']['name'] : null;
        } elseif (isset($item['company']) && is_array($item['company'])) {
            $officeId = isset($item['company']['id']) ? (int) $item['company']['id'] : null;
            $officeName = isset($item['company']['name']) ? (string) $item['company']['name'] : null;
        }

        if ($expectedOfficeId !== null && $officeId !== null && $officeId !== $expectedOfficeId) {
            Log::info('Presseportal: office_id aus API weicht von URL ab', [
                'url_office' => $expectedOfficeId,
                'api_office' => $officeId,
            ]);
        }

        $publishedAt = null;
        if (! empty($item['published'])) {
            try {
                $publishedAt = Carbon::parse((string) $item['published'])->timezone(config('app.timezone'));
            } catch (\Throwable) {
                $publishedAt = null;
            }
        }

        return [
            'title' => $title,
            'body' => PresseportalBodyNormalizer::normalize($body),
            'story_url' => $storyUrl !== '' ? $storyUrl : (string) $canonicalUrl,
            'story_id' => (int) ($item['id'] ?? $storyId),
            'office_id' => $officeId ?? $expectedOfficeId,
            'office_name' => $officeName,
            'published_at' => $publishedAt,
            'source_type' => $this->guessSourceType($officeName),
            'source_label' => $officeName,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractStoriesFromListJson(array $json): array
    {
        $content = $json['content'] ?? null;
        if (! is_array($content)) {
            return [];
        }

        $story = $content['story'] ?? null;
        if (! is_array($story)) {
            return [];
        }

        if (isset($story['id'])) {
            return [$story];
        }

        $out = [];
        foreach ($story as $row) {
            if (is_array($row) && isset($row['id'])) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * @param  string|int  $code  API-Fehlercode (nicht HTTP-Status); z. B. 200 = Ressource unknown.
     */
    private function mapApiError(string|int $code, string $msg): string
    {
        $c = trim((string) $code);

        return match ($c) {
            '100' => 'API-Key fehlt.',
            '101' => 'API-Key wird von der API nicht akzeptiert (nicht gefunden). Bitte prüfen Sie PRESSEPORTAL_API_KEY.',
            '102' => 'API-Key ungültig oder nicht mehr aktiv. Bitte prüfen Sie PRESSEPORTAL_API_KEY.',
            '150' => 'Das Presseportal-API-Kontingent ist aufgebraucht (Quota exceeded). Bitte bei news aktuell ein höheres Kontingent beantragen oder bis zur nächsten Abrechnungsperiode warten.',
            '200' => 'Die Meldung konnte weder direkt noch über die Dienststellen-Liste gefunden werden (Ressource unknown). Prüfen Sie die URL, ob die Meldung sehr alt ist (Listenabruf begrenzt), oder ob der API-Key Zugriff auf Öffentlicher-Dienst-Meldungen hat.',
            default => 'Presseportal: '.$msg.' (Code '.$c.')',
        };
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>|null
     */
    private function extractStoryItem(array $json): ?array
    {
        $content = $json['content'] ?? null;
        if (! is_array($content)) {
            return null;
        }

        $story = $content['story'] ?? null;
        if (is_array($story)) {
            if (isset($story['id'])) {
                return $story;
            }
            if (isset($story[0]) && is_array($story[0])) {
                return $story[0];
            }
        }

        return null;
    }

    private function guessSourceType(?string $officeName): ?string
    {
        if ($officeName === null || $officeName === '') {
            return NewsItemStatement::SOURCE_PRESS_OFFICE;
        }
        $n = mb_strtolower($officeName, 'UTF-8');
        if (str_contains($n, 'polizei')) {
            return NewsItemStatement::SOURCE_POLICE;
        }
        if (str_contains($n, 'feuerwehr')) {
            return NewsItemStatement::SOURCE_FIRE_DEPARTMENT;
        }

        return NewsItemStatement::SOURCE_PRESS_OFFICE;
    }
}
