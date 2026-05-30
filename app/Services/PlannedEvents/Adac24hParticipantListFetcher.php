<?php

namespace App\Services\PlannedEvents;

use Illuminate\Support\Facades\Http;

/**
 * Lädt und parst die öffentliche Teilnehmerliste von 24h-rennen.de/teilnehmer/.
 */
final class Adac24hParticipantListFetcher
{
    public const DEFAULT_URL = 'https://www.24h-rennen.de/teilnehmer/';

    public const IMAGE_BASE_URL = 'https://www.24h-rennen.de/wp-content/uploads/teilnehmer_26h/small/';

    /**
     * @return list<array{
     *   start_number: int,
     *   class: string,
     *   box: string,
     *   team: string,
     *   drivers: list<string>,
     *   vehicle: string,
     *   image_url: string
     * }>
     */
    public function fetch(?string $url = null): array
    {
        $html = $this->downloadHtml($url ?? self::DEFAULT_URL);

        return $this->parseHtml($html);
    }

    public function downloadHtml(string $url): string
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (compatible; ErftkreisNewsMedia/1.0; +https://erftkreis-news.media)',
            'Accept' => 'text/html,application/xhtml+xml',
        ])
            ->timeout(60)
            ->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException('Teilnehmerliste konnte nicht geladen werden (HTTP '.$response->status().').');
        }

        $body = trim($response->body());
        if ($body === '' || ! str_contains($body, 'teilnehmerliste')) {
            throw new \RuntimeException('Teilnehmerliste: unerwartete Antwort (keine Starterdaten).');
        }

        return $body;
    }

    /**
     * @return list<array{
     *   start_number: int,
     *   class: string,
     *   box: string,
     *   team: string,
     *   drivers: list<string>,
     *   vehicle: string,
     *   image_url: string
     * }>
     */
    public function parseHtml(string $html): array
    {
        if (! preg_match_all('/<tr[^>]*class="teilnehmerliste[^"]*"[^>]*>(.*?)<\/tr>/is', $html, $rowMatches)) {
            return [];
        }

        $entries = [];
        foreach ($rowMatches[1] as $rowHtml) {
            $parsed = $this->parseRow($rowHtml);
            if ($parsed !== null) {
                $entries[] = $parsed;
            }
        }

        usort($entries, static fn (array $a, array $b): int => $a['start_number'] <=> $b['start_number']);

        return $entries;
    }

    /**
     * @return array{
     *   start_number: int,
     *   class: string,
     *   box: string,
     *   team: string,
     *   drivers: list<string>,
     *   vehicle: string,
     *   image_url: string
     * }|null
     */
    private function parseRow(string $rowHtml): ?array
    {
        if (! preg_match('/class="[^"]*stnr[^"]*"[^>]*>\s*(\d{1,4})\s*</i', $rowHtml, $nrMatch)) {
            return null;
        }

        $startNumber = (int) $nrMatch[1];
        if ($startNumber <= 0) {
            return null;
        }

        if (! preg_match_all('/<td[^>]*>(.*?)<\/td>/is', $rowHtml, $cellMatches)) {
            return null;
        }

        $cells = $cellMatches[1] ?? [];
        if (count($cells) < 6) {
            return null;
        }

        $class = $this->cellText($cells[2] ?? '');
        $box = $this->cellText($cells[3] ?? '');
        $teamCell = (string) ($cells[4] ?? '');
        $vehicle = $this->cellText($cells[5] ?? '');
        if ($vehicle === '' && isset($cells[6])) {
            $vehicle = $this->extractVehicleFromImageCell((string) $cells[6]);
        }

        $team = '';
        if (preg_match('/<b[^>]*>(.*?)<\/b>/is', $teamCell, $teamMatch)) {
            $team = $this->cellText($teamMatch[1]);
        }
        if ($team === '') {
            $team = $this->cellText($teamCell);
        }

        $drivers = $this->parseDrivers($teamCell);
        $imageUrl = $this->extractImageUrl((string) ($cells[6] ?? $rowHtml));
        if ($imageUrl === '') {
            $imageUrl = self::IMAGE_BASE_URL.$startNumber.'.jpg';
        }

        if ($team === '') {
            return null;
        }

        return [
            'start_number' => $startNumber,
            'class' => $class,
            'box' => $box,
            'team' => $team,
            'drivers' => $drivers,
            'vehicle' => $vehicle,
            'image_url' => $imageUrl,
        ];
    }

    /**
     * @return list<string>
     */
    private function parseDrivers(string $teamCellHtml): array
    {
        $fragment = preg_replace('/<b[^>]*>.*?<\/b>/is', '', $teamCellHtml) ?? $teamCellHtml;
        $fragment = preg_replace('/<\/?br\s*\/?>/i', "\n", $fragment) ?? $fragment;
        $plain = html_entity_decode(strip_tags($fragment), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines = preg_split("/\r\n|\n|\r/u", $plain) ?: [];

        $drivers = [];
        foreach ($lines as $line) {
            $line = trim(preg_replace('/\s+/u', ' ', (string) $line) ?: '');
            if ($line === '' || ! str_contains($line, ',')) {
                continue;
            }
            $drivers[] = $line;
        }

        return array_values(array_unique($drivers));
    }

    private function extractImageUrl(string $html): string
    {
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $match) !== 1) {
            return '';
        }

        $src = trim(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($src === '') {
            return '';
        }

        if (str_starts_with($src, '//')) {
            return 'https:'.$src;
        }

        if (str_starts_with($src, '/')) {
            return 'https://www.24h-rennen.de'.$src;
        }

        return $src;
    }

    private function extractVehicleFromImageCell(string $html): string
    {
        if (preg_match('/class="mobshow"[^>]*>(.*?)<\/div>/is', $html, $match)) {
            return $this->cellText($match[1]);
        }

        return '';
    }

    private function cellText(string $html): string
    {
        $normalized = preg_replace('/<br\s*\/?>/i', ' ', $html) ?? $html;
        $text = html_entity_decode(strip_tags($normalized), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?: '');
    }

    public function formatTeamName(array $entry): string
    {
        $class = trim((string) ($entry['class'] ?? ''));
        $suffix = $class !== '' ? ' '.$class : '';

        return 'Box '.trim((string) ($entry['box'] ?? ''))
            .' | Startnr. '.(int) ($entry['start_number'] ?? 0)
            .' | '.trim((string) ($entry['team'] ?? '')).$suffix;
    }

    public function formatTeamNotes(array $entry): string
    {
        $lines = [];
        $drivers = array_values(array_filter(
            array_map('strval', (array) ($entry['drivers'] ?? [])),
            static fn (string $d): bool => $d !== ''
        ));

        if ($drivers !== []) {
            $lines[] = 'Fahrer / Fahrzeugdetails: '.implode('; ', $drivers);
        }

        $vehicle = trim((string) ($entry['vehicle'] ?? ''));
        if ($vehicle !== '') {
            $lines[] = 'Fahrzeug: '.$vehicle;
        }

        return implode("\n", $lines);
    }

    public function downloadReferenceImage(string $imageUrl): string
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (compatible; ErftkreisNewsMedia/1.0)',
            'Accept' => 'image/*',
        ])
            ->timeout(45)
            ->get($imageUrl);

        if (! $response->successful()) {
            throw new \RuntimeException('Referenzfoto konnte nicht geladen werden: '.$imageUrl);
        }

        $body = $response->body();
        if ($body === '' || ! str_starts_with($response->header('Content-Type', ''), 'image/')) {
            $mime = $response->header('Content-Type', '');
            if ($mime === '' && strlen($body) > 100) {
                return $body;
            }

            throw new \RuntimeException('Referenzfoto: keine Bilddaten von '.$imageUrl);
        }

        return $body;
    }
}
