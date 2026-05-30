<?php

namespace App\Services\MediaAi;

use App\Exceptions\MediaAiRateLimitException;
use App\Models\NewsItemMedia;
use App\Models\PlannedEvent;
use App\Models\PlannedEventTeam;
use App\Support\ImportTextNormalizer;
use App\Support\PlannedEventTeamReferenceImageLocator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Ordnet Pressefotos per Vision-Vergleich mit ADAC-Referenzbildern aus der Starterliste zu,
 * wenn keine Karosserie-Startnummer erkannt wurde.
 */
class ReferenceImageTeamMatcher
{
    /**
     * @param  array<string, mixed>  $visionResult
     */
    public function shouldDisambiguateProsportGt3Match(int $detectedStartNumber, array $visionResult): bool
    {
        if ($detectedStartNumber <= 0) {
            return false;
        }

        $hints = $this->buildVisionHints($visionResult);
        if (! $this->hintsSuggestOrangeBlack($hints) || ! $this->hintsMentionProsport($hints)) {
            return false;
        }

        if ($hints['mentions_gt4']) {
            return false;
        }

        return in_array($detectedStartNumber, [26, 37], true);
    }

    /**
     * @param  array<string, mixed>  $visionResult
     * @return array{start_number:int, confidence:string, reason:string}|null
     */
    public function match(NewsItemMedia $media, PlannedEvent $plannedEvent, array $visionResult): ?array
    {
        if (! config('media_ai.reference_match_enabled', true)) {
            return null;
        }

        if (! config('media_ai.api_key')) {
            return null;
        }

        $candidates = $this->rankCandidates($plannedEvent, $visionResult);
        if ($candidates === []) {
            return null;
        }

        try {
            $pressUrl = app(ImageMetadataGenerator::class)->resolvePreviewUrlForVision($media);
        } catch (\Throwable $e) {
            Log::warning('ReferenceImageTeamMatcher: preview failed', [
                'media_id' => $media->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $validNumbers = array_map(static fn (array $c): int => (int) $c['start_number'], $candidates);

        if ($this->isProsportGt4SiblingPair($candidates)) {
            try {
                $siblingMatch = $this->resolveVisionCompareMatch(
                    $this->callVisionSiblingCompare($pressUrl, $candidates, $visionResult),
                    $validNumbers
                );
                if ($siblingMatch !== null) {
                    return $this->applyProsportGt4SiblingLiveryBoostOverride(
                        $siblingMatch,
                        $candidates,
                        $visionResult
                    );
                }
            } catch (MediaAiRateLimitException $e) {
                throw $e;
            } catch (\Throwable $e) {
                Log::warning('ReferenceImageTeamMatcher: sibling compare failed', [
                    'media_id' => $media->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            $decoded = $this->callVisionCompare($pressUrl, $candidates);
        } catch (MediaAiRateLimitException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('ReferenceImageTeamMatcher: vision compare failed', [
                'media_id' => $media->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $resolved = $this->resolveVisionCompareMatch($decoded, $validNumbers);
        if ($resolved === null || ! $this->isProsportGt4SiblingPair($candidates)) {
            return $resolved;
        }

        return $this->applyProsportGt4SiblingLiveryBoostOverride($resolved, $candidates, $visionResult);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @param  list<int>  $validNumbers
     * @return array{start_number:int, confidence:string, reason:string}|null
     */
    private function resolveVisionCompareMatch(array $decoded, array $validNumbers): ?array
    {
        $bestNr = $this->toPositiveInt($decoded['best_start_number'] ?? null);
        if ($bestNr === null || ! in_array($bestNr, $validNumbers, true)) {
            return null;
        }

        $confidence = $this->normalizeConfidence($decoded['confidence'] ?? null);
        if (! $this->confidenceMeetsMinimum($confidence)) {
            return null;
        }

        $reason = trim((string) ($decoded['reason'] ?? ''));
        if ($reason === '') {
            $reason = 'Zuordnung über ADAC-Referenzfoto (Startnr. '.$bestNr.')';
        }

        return [
            'start_number' => $bestNr,
            'confidence' => $confidence,
            'reason' => $reason,
        ];
    }

    /**
     * @param  array<string, mixed>  $visionResult
     * @return list<array{start_number:int, team_name:string, vehicle_label:?string, data_url:string, score:int}>
     */
    public function rankCandidates(PlannedEvent $plannedEvent, array $visionResult): array
    {
        $hints = $this->buildVisionHints($visionResult);
        $max = max(2, (int) config('media_ai.reference_match_max_candidates', 10));

        $teams = $plannedEvent->relationLoaded('teams')
            ? $plannedEvent->teams
            : $plannedEvent->teams()->get();

        $scored = [];
        foreach ($teams as $team) {
            if (! $team instanceof PlannedEventTeam) {
                continue;
            }
            $startNumber = $team->startNumber();
            if ($startNumber === null) {
                continue;
            }
            $path = trim((string) ($team->reference_image_path ?? ''));
            if ($path === '' || ! PlannedEventTeamReferenceImageLocator::exists($path)) {
                continue;
            }
            $dataUrl = PlannedEventTeamReferenceImageLocator::dataUrl($path);
            if ($dataUrl === null) {
                continue;
            }

            $score = $this->scoreTeamAgainstHints($team, $hints);
            $scored[] = [
                'start_number' => $startNumber,
                'team_name' => trim((string) ($team->teamTitleFromName() ?? $team->name)),
                'vehicle_label' => $team->vehicleLabel(),
                'data_url' => $dataUrl,
                'score' => $score,
            ];
        }

        if ($scored === []) {
            return [];
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        $positive = array_values(array_filter($scored, static fn (array $row): bool => $row['score'] > 0));

        if ($positive !== [] && $this->hintsSuggestOrangeBlack($hints) && $this->hintsMentionProsport($hints)) {
            $prosportOrange = array_values(array_filter($positive, function (array $row): bool {
                $team = mb_strtolower($row['team_name'], 'UTF-8');
                $vehicle = mb_strtolower((string) ($row['vehicle_label'] ?? ''), 'UTF-8');

                return str_contains($team, 'prosport')
                    && str_contains($vehicle, 'mercedes')
                    && str_contains($vehicle, 'gt4');
            }));
            if (count($prosportOrange) >= 1) {
                $prosportOrange = $this->sortProsportGt4Candidates($prosportOrange, $hints, $visionResult);

                return array_slice($prosportOrange, 0, min($max, count($prosportOrange)));
            }
        }

        if ($positive !== []) {
            return array_slice($positive, 0, $max);
        }

        if ($hints['brands'] !== []) {
            $teamByNumber = [];
            foreach ($teams as $team) {
                if ($team instanceof PlannedEventTeam && ($nr = $team->startNumber()) !== null) {
                    $teamByNumber[$nr] = $team;
                }
            }
            $brandFallback = array_values(array_filter(
                $scored,
                fn (array $row): bool => $this->teamMatchesAnyBrand(
                    $teamByNumber[$row['start_number']] ?? null,
                    $hints['brands']
                )
            ));

            if ($brandFallback !== []) {
                return array_slice($brandFallback, 0, $max);
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $visionResult
     * @return array{brands:list<string>, tokens:list<string>, text:string}
     */
    private function buildVisionHints(array $visionResult): array
    {
        $chunks = [
            (string) ($visionResult['caption'] ?? ''),
            (string) ($visionResult['image_title'] ?? ''),
            (string) ($visionResult['description'] ?? ''),
            (string) ($visionResult['primary_car_color'] ?? ''),
            implode(' ', $this->toStringList($visionResult['livery_cues'] ?? [])),
            implode(' ', $this->toStringList($visionResult['keywords'] ?? [])),
        ];
        $text = ImportTextNormalizer::normalize(implode("\n", array_filter($chunks)));
        $textLower = mb_strtolower($text, 'UTF-8');

        $brands = [];
        foreach ($this->vehicleBrandKeywords() as $brand => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($textLower, $keyword)) {
                    $brands[] = $brand;
                    break;
                }
            }
        }
        $brands = array_values(array_unique($brands));

        $tokens = [];
        foreach ($this->toStringList($visionResult['livery_cues'] ?? []) as $cue) {
            $cue = ImportTextNormalizer::normalize($cue);
            if (mb_strlen($cue, 'UTF-8') >= 3) {
                $tokens[] = mb_strtolower($cue, 'UTF-8');
            }
        }
        foreach (preg_split('/\s+/u', $textLower, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            if (mb_strlen($word, 'UTF-8') >= 4 && ! in_array($word, $tokens, true)) {
                $tokens[] = $word;
            }
        }

        $colors = [];
        foreach (['orange', 'schwarz', 'blau', 'gold', 'gelb', 'grün', 'weiß', 'rot'] as $color) {
            if (str_contains($textLower, $color)) {
                $colors[] = $color;
            }
        }

        $mentionsGt3 = str_contains($textLower, 'gt3');
        $mentionsGt4 = str_contains($textLower, 'gt4');

        return [
            'brands' => $brands,
            'tokens' => array_values(array_unique($tokens)),
            'colors' => $colors,
            'mentions_gt3' => $mentionsGt3,
            'mentions_gt4' => $mentionsGt4,
            'text' => $textLower,
        ];
    }

    /**
     * @param  array{brands:list<string>, tokens:list<string>, colors:list<string>, mentions_gt3:bool, mentions_gt4:bool, text:string}  $hints
     */
    private function scoreTeamAgainstHints(PlannedEventTeam $team, array $hints): int
    {
        $score = 0;
        $nameLower = mb_strtolower($team->name.' '.(string) ($team->notes ?? ''), 'UTF-8');
        $vehicleLower = mb_strtolower((string) ($team->vehicleLabel() ?? ''), 'UTF-8');

        if ($hints['brands'] !== [] && $this->teamMatchesAnyBrand($team, $hints['brands'])) {
            $score += 20;
        }

        $ambiguousSponsors = ['ravenol', 'gran turismo', 'h&r', 'goodyear', 'liquimoly'];

        foreach ($hints['tokens'] as $token) {
            if ($token === '' || mb_strlen($token, 'UTF-8') < 3) {
                continue;
            }
            if (! str_contains($nameLower, $token) && ! str_contains($vehicleLower, $token)) {
                continue;
            }
            if (in_array($token, $ambiguousSponsors, true)) {
                $score += 3;
            } elseif (str_contains($token, 'prosport') || str_contains($token, 'pro sport')) {
                $score += 18;
            } elseif (str_contains($token, 'matecra')) {
                $score += 16;
            } else {
                $score += str_contains($token, ' ') ? 10 : 8;
            }
        }

        if (str_contains($nameLower, 'prosport') && $this->hintsMentionProsport($hints)) {
            $score += 16;
        }

        if ($hints['mentions_gt4'] && str_contains($vehicleLower, 'gt4')) {
            $score += 14;
        } elseif ($hints['mentions_gt3'] && str_contains($vehicleLower, 'gt3') && ! str_contains($vehicleLower, 'gt4')) {
            $score += 14;
        } elseif (! $hints['mentions_gt3'] && $hints['mentions_gt4'] === false
            && $this->hintsSuggestOrangeBlack($hints) && str_contains($vehicleLower, 'gt4')) {
            $score += 10;
        }

        if ($this->hintsSuggestOrangeBlack($hints) && $this->teamLooksFactoryRavenolGt3($nameLower, $vehicleLower)) {
            $score -= 12;
        }

        return max(0, $score);
    }

    /**
     * @param  array{tokens:list<string>, colors:list<string>, text:string}  $hints
     */
    private function hintsMentionProsport(array $hints): bool
    {
        foreach ($hints['tokens'] as $token) {
            if (str_contains($token, 'prosport')) {
                return true;
            }
        }

        return str_contains($hints['text'], 'prosport');
    }

    /**
     * @param  array{colors:list<string>, text:string}  $hints
     */
    private function hintsSuggestOrangeBlack(array $hints): bool
    {
        $hasOrange = in_array('orange', $hints['colors'], true) || str_contains($hints['text'], 'orange');
        $hasBlack = in_array('schwarz', $hints['colors'], true) || str_contains($hints['text'], 'schwarz');

        return $hasOrange && $hasBlack;
    }

    private function teamLooksFactoryRavenolGt3(string $nameLower, string $vehicleLower): bool
    {
        return str_contains($nameLower, 'ravenol')
            && ! str_contains($nameLower, 'prosport')
            && str_contains($vehicleLower, 'gt3')
            && ! str_contains($vehicleLower, 'gt4');
    }

    /**
     * @param  list<array{start_number:int, team_name:string, vehicle_label:?string, data_url:string, score:int}>  $candidates
     * @param  array{brands:list<string>, tokens:list<string>, colors:list<string>, mentions_gt3:bool, mentions_gt4:bool, text:string}  $hints
     * @param  array<string, mixed>  $visionResult
     * @return list<array{start_number:int, team_name:string, vehicle_label:?string, data_url:string, score:int}>
     */
    private function sortProsportGt4Candidates(array $candidates, array $hints, array $visionResult): array
    {
        $bodyNumbers = $this->extractKarosserieStartNumbers($visionResult, $hints);

        $siblingBoost = $this->prosportGt4SiblingLiveryBoostScores($visionResult, $candidates);

        usort($candidates, static function (array $a, array $b) use ($bodyNumbers, $siblingBoost): int {
            $aBody = in_array($a['start_number'], $bodyNumbers, true);
            $bBody = in_array($b['start_number'], $bodyNumbers, true);
            if ($aBody !== $bBody) {
                return $bBody <=> $aBody;
            }
            $aBoost = $siblingBoost[$a['start_number']] ?? 0;
            $bBoost = $siblingBoost[$b['start_number']] ?? 0;
            if ($aBoost !== $bBoost) {
                return $bBoost <=> $aBoost;
            }
            if ($a['score'] !== $b['score']) {
                return $b['score'] <=> $a['score'];
            }

            return $a['start_number'] <=> $b['start_number'];
        });

        return $candidates;
    }

    /**
     * Startnummern, die Vision explizit an der Karosserie nennt (nicht Marshal/LED).
     *
     * @param  array<string, mixed>  $visionResult
     * @param  array{mentions_gt3:bool, mentions_gt4:bool, text:string}  $hints
     * @return list<int>
     */
    private function extractKarosserieStartNumbers(array $visionResult, array $hints): array
    {
        $numbers = [];
        $gt3Misreads = [26, 37];

        foreach ($this->toStringList($visionResult['detected_car_numbers'] ?? []) as $raw) {
            $n = $this->toPositiveInt($raw);
            if ($n !== null) {
                $numbers[] = $n;
            }
        }

        $primary = $this->toPositiveInt($visionResult['detected_start_number'] ?? null);
        if ($primary !== null) {
            $numbers[] = $primary;
        }

        $text = (string) ($hints['text'] ?? '');
        if (preg_match_all('/(?:startnummer|startnr\.?|nr\.?|#)\s*(\d{1,4})\b/ui', $text, $matches) === 1) {
            foreach ($matches[1] as $digits) {
                $n = (int) $digits;
                if ($n > 0) {
                    $numbers[] = $n;
                }
            }
        }

        $numbers = array_values(array_unique(array_filter($numbers, static fn (int $n): bool => $n > 0)));

        if ($this->hintsSuggestOrangeBlack($hints) && $this->hintsMentionProsport($hints)
            && ($hints['mentions_gt4'] || ! $hints['mentions_gt3'])) {
            $numbers = array_values(array_filter(
                $numbers,
                static fn (int $n): bool => ! in_array($n, $gt3Misreads, true)
            ));
        }

        return $numbers;
    }

    /**
     * @param  array<string, mixed>  $visionResult
     * @param  list<array{start_number:int}>  $candidates
     * @return array<int, int>
     */
    private function prosportGt4SiblingLiveryBoostScores(array $visionResult, array $candidates): array
    {
        if (! $this->isProsportGt4SiblingPair($candidates)) {
            return [];
        }

        $hints = $this->buildVisionHints($visionResult);
        $text = $hints['text'];
        $boosts = [];

        if (str_contains($text, 'matecra')) {
            $boosts[175] = ($boosts[175] ?? 0) + 14;
        }
        if (str_contains($text, 'gran turismo') || str_contains($text, 'gt gran turismo')) {
            $boosts[175] = ($boosts[175] ?? 0) + 12;
        }
        if (str_contains($text, 'ravenol')) {
            $boosts[175] = ($boosts[175] ?? 0) + 4;
        }
        if (str_contains($text, 'falken') || str_contains($text, 'dorint')) {
            $boosts[176] = ($boosts[176] ?? 0) + 12;
        }
        if ($hints['mentions_gt4']) {
            $boosts[175] = ($boosts[175] ?? 0) + 6;
            $boosts[176] = ($boosts[176] ?? 0) + 6;
        }
        if ($hints['mentions_gt3'] && ! $hints['mentions_gt4']) {
            $boosts[176] = ($boosts[176] ?? 0) + 4;
        }

        return $boosts;
    }

    /**
     * @param  array{start_number:int, confidence:string, reason:string}  $match
     * @param  list<array{start_number:int, team_name:string, vehicle_label:?string, data_url:string, score:int}>  $candidates
     * @param  array<string, mixed>  $visionResult
     * @return array{start_number:int, confidence:string, reason:string}
     */
    private function applyProsportGt4SiblingLiveryBoostOverride(
        array $match,
        array $candidates,
        array $visionResult
    ): array {
        if (! $this->isProsportGt4SiblingPair($candidates)) {
            return $match;
        }

        $boosts = $this->prosportGt4SiblingLiveryBoostScores($visionResult, $candidates);
        if ($boosts === []) {
            return $match;
        }

        arsort($boosts);
        $preferredNr = (int) array_key_first($boosts);
        $preferredScore = $boosts[$preferredNr];
        $currentNr = (int) $match['start_number'];
        $currentScore = $boosts[$currentNr] ?? 0;

        if ($preferredNr === $currentNr || $preferredScore < $currentScore + 8) {
            return $match;
        }

        $validNumbers = array_map(static fn (array $c): int => (int) $c['start_number'], $candidates);
        if (! in_array($preferredNr, $validNumbers, true)) {
            return $match;
        }

        return [
            'start_number' => $preferredNr,
            'confidence' => $match['confidence'],
            'reason' => 'Zuordnung über ADAC-Referenzfoto (Startnr. '.$preferredNr.'): '
                .'Pressefoto-Hinweise (Lackierung/Sponsoren) passen besser zu Nr. '.$preferredNr
                .' als zu Nr. '.$currentNr.'.',
        ];
    }

    /**
     * @param  list<string>  $brands
     */
    private function teamMatchesAnyBrand(?PlannedEventTeam $team, array $brands): bool
    {
        if (! $team instanceof PlannedEventTeam || $brands === []) {
            return false;
        }

        $blob = mb_strtolower($team->name.' '.(string) ($team->notes ?? ''), 'UTF-8');
        foreach ($brands as $brand) {
            foreach ($this->vehicleBrandKeywords()[$brand] ?? [] as $keyword) {
                if (str_contains($blob, $keyword)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<string, list<string>>
     */
    private function vehicleBrandKeywords(): array
    {
        return [
            'mercedes' => ['mercedes', 'mercedes-amg', 'amg gt'],
            'porsche' => ['porsche', '911 gt3'],
            'toyota' => ['toyota', 'gr supra', 'supra'],
            'bmw' => ['bmw', 'm4 gt'],
            'audi' => ['audi', 'r8', 'rs3'],
            'ford' => ['ford', 'mustang'],
            'aston' => ['aston martin', 'aston-martin'],
            'lamborghini' => ['lamborghini', 'huracan'],
            'ferrari' => ['ferrari', '488', '296'],
            'mclaren' => ['mclaren', '720s'],
            'corvette' => ['corvette'],
            'honda' => ['honda', 'nsx'],
            'bentley' => ['bentley'],
            'alpine' => ['alpine'],
        ];
    }

    /**
     * @param  list<array{start_number:int, team_name:string, vehicle_label:?string, data_url:string, score:int}>  $candidates
     * @return array<string, mixed>
     */
    private function callVisionCompare(string $pressUrl, array $candidates): array
    {
        $this->respectRateLimit();

        $content = [
            [
                'type' => 'text',
                'text' => 'Bild 1 ist ein Pressefoto vom Rennstrecke-Motiv. Die weiteren Bilder sind offizielle ADAC-Referenzfotos '
                    .'einzelner Starter (nur diese Kandidaten sind möglich). '
                    .'Entscheide anhand von: (1) Lackierung/Farbschema (z. B. orange-schwarz vs. blau-gold), '
                    .'(2) Stoßstangen- und Frontsplitter-Aufkleber (Position und Kombination, z. B. PROsport Racing auf dem Splitter, H&R auf dem Kotflügel), '
                    .'(3) Fahrzeugklasse/Karosserie (GT3 vs. GT4). '
                    .'RAVENOL allein reicht nicht – mehrere Fahrzeuge haben RAVENOL. '
                    .'Gelbe Motorhauben-Akzente sind kein Hauptmerkmal – Stoßstange/Frontsplitter wichtiger. '
                    .'Marshal- oder LED-Ziffern an der Windschutzscheibe ignorieren. '
                    .'Bei mehreren ähnlichen PROsport Mercedes GT4 (z. B. 175 und 176): Stoßstangen-Aufkleber, '
                    .'Box-Nummer und die auf der Karosserie sichtbare Startnummer genau vergleichen – nicht nur Sponsoren. '
                    .'Antworte nur mit JSON: {"best_start_number": number|null, "confidence": "high"|"medium"|"low", "reason": string}',
            ],
            ['type' => 'text', 'text' => 'Pressefoto'],
            ['type' => 'image_url', 'image_url' => ['url' => $pressUrl]],
        ];

        foreach ($candidates as $candidate) {
            $vehicle = trim((string) ($candidate['vehicle_label'] ?? ''));
            $label = 'Referenz Startnr. '.$candidate['start_number'].' – '.$candidate['team_name'];
            if ($vehicle !== '') {
                $label .= ' (Fahrzeug laut Liste: '.$vehicle.')';
            }
            $content[] = [
                'type' => 'text',
                'text' => $label,
            ];
            $content[] = [
                'type' => 'image_url',
                'image_url' => ['url' => $candidate['data_url']],
            ];
        }

        return $this->postVisionCompare($content, 'Du vergleichst Rennfahrzeug-Fotos für eine Nachrichtenredaktion. '
            .'Priorisiere Stoßstangen-Aufkleber und das Farbschema vor einzelnen Sponsorenlogos. '
            .'best_start_number nur setzen, wenn Lackierung, Stoßstangen-Details und Fahrzeugtyp klar passen.');
    }

    /**
     * @param  list<array{start_number:int, team_name:string, vehicle_label:?string, data_url:string, score:int}>  $candidates
     */
    private function isProsportGt4SiblingPair(array $candidates): bool
    {
        if (count($candidates) !== 2) {
            return false;
        }

        $teamNames = array_unique(array_map(
            static fn (array $c): string => mb_strtolower(trim($c['team_name']), 'UTF-8'),
            $candidates
        ));
        if (count($teamNames) !== 1 || ! str_contains($teamNames[0], 'prosport')) {
            return false;
        }

        foreach ($candidates as $candidate) {
            $vehicle = mb_strtolower((string) ($candidate['vehicle_label'] ?? ''), 'UTF-8');
            if (! str_contains($vehicle, 'gt4')) {
                return false;
            }
        }

        $numbers = array_map(static fn (array $c): int => (int) $c['start_number'], $candidates);
        sort($numbers);

        return abs($numbers[1] - $numbers[0]) === 1;
    }

    /**
     * @param  list<array{start_number:int, team_name:string, vehicle_label:?string, data_url:string, score:int}>  $candidates
     * @return array<string, mixed>
     */
    /**
     * @param  list<array{start_number:int, team_name:string, vehicle_label:?string, data_url:string, score:int}>  $candidates
     * @param  array<string, mixed>  $visionResult
     */
    private function callVisionSiblingCompare(string $pressUrl, array $candidates, array $visionResult): array
    {
        $this->respectRateLimit();

        $pressHints = $this->formatPressLiveryHintsForSiblingCompare($visionResult);
        $referenceNotes = $this->prosportGt4SiblingReferenceNotes($candidates);

        $content = [
            [
                'type' => 'text',
                'text' => 'Bild 1 ist ein Pressefoto. Bild 2 und 3 sind ADAC-Referenzfotos zweier sehr ähnlicher '
                    .'PROsport Mercedes-AMG GT4 desselben Teams (nur diese Startnummern sind möglich). '
                    .($pressHints !== '' ? 'Hinweise aus der Pressefoto-Analyse: '.$pressHints.'. ' : '')
                    .'Entscheide anhand von: (1) Frontstoßstange/Frontsplitter-Aufklebern '
                    .'(Position und Kombination, z. B. PROsport Racing auf dem Splitter, H&R), '
                    .'(2) Seiten-/Tür-Aufklebern und Kotflügel-Logos, (3) Gesamtlackierung orange-schwarz, '
                    .'(4) typische Sponsoren-Kombination laut Referenz (siehe Bildbeschriftungen). '
                    .'IGNORIERE gelbe Motorhauben-Bereiche – die unterscheiden die beiden Fahrzeuge nicht zuverlässig. '
                    .'Marshal- oder LED-Ziffern an der Windschutzscheibe ignorieren. '
                    .'Antworte nur mit JSON: {"best_start_number": number|null, "confidence": "high"|"medium"|"low", "reason": string}',
            ],
            ['type' => 'text', 'text' => 'Pressefoto'],
            ['type' => 'image_url', 'image_url' => ['url' => $pressUrl]],
        ];

        foreach ($candidates as $candidate) {
            $vehicle = trim((string) ($candidate['vehicle_label'] ?? ''));
            $nr = (int) $candidate['start_number'];
            $label = 'Referenz Startnr. '.$nr.' – '.$candidate['team_name'];
            if ($vehicle !== '') {
                $label .= ' ('.$vehicle.')';
            }
            if (isset($referenceNotes[$nr])) {
                $label .= '. Typische Merkmale laut ADAC-Referenz: '.$referenceNotes[$nr];
            }
            $content[] = ['type' => 'text', 'text' => $label];
            $content[] = ['type' => 'image_url', 'image_url' => ['url' => $candidate['data_url']]];
        }

        return $this->postVisionCompare($content, 'Du unterscheidest zwei nahezu identische PROsport GT4. '
            .'Priorisiere Stoßstangen- und Frontsplitter-Aufkleber sowie Sponsoren-Kombinationen aus den Referenz-Merkmalen. '
            .'Motorhauben-Farbe allein ist kein Entscheidungskriterium.');
    }

    /**
     * @param  array<string, mixed>  $visionResult
     */
    private function formatPressLiveryHintsForSiblingCompare(array $visionResult): string
    {
        $parts = [];
        foreach ($this->toStringList($visionResult['livery_cues'] ?? []) as $cue) {
            $parts[] = $cue;
        }
        $color = trim((string) ($visionResult['primary_car_color'] ?? ''));
        if ($color !== '') {
            $parts[] = 'Farbe: '.$color;
        }

        return implode(', ', array_unique(array_filter($parts)));
    }

    /**
     * @param  list<array{start_number:int}>  $candidates
     * @return array<int, string>
     */
    private function prosportGt4SiblingReferenceNotes(array $candidates): array
    {
        $known = [
            175 => 'GT4, Gran-Turismo-Banner an der Windschutzscheibe, NEXEN am Kotflügel, MATECRA und H&R an der Frontstoßstange, PROsport auf dem Frontsplitter',
            176 => 'GT4, Falken auf der Haube, Dorint an der Tür, andere Sponsoren-Anordnung als Nr. 175',
        ];

        $notes = [];
        foreach ($candidates as $candidate) {
            $nr = (int) $candidate['start_number'];
            if (isset($known[$nr])) {
                $notes[$nr] = $known[$nr];
            }
        }

        return $notes;
    }

    /**
     * @param  list<array<string, mixed>>  $userContent
     * @return array<string, mixed>
     */
    private function postVisionCompare(array $userContent, string $systemPrompt): array
    {
        $headers = ['Authorization' => 'Bearer '.config('media_ai.api_key')];
        if ($org = config('media_ai.organization')) {
            $headers['OpenAI-Organization'] = $org;
        }

        $response = Http::withHeaders($headers)
            ->timeout(config('media_ai.timeout', 75))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('media_ai.vision_model', 'gpt-4.1-mini'),
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userContent],
                ],
            ]);

        if (! $response->successful()) {
            if ($response->status() === 429) {
                throw new MediaAiRateLimitException('OpenAI Rate Limit (429) beim Referenzbild-Abgleich.');
            }
            throw new \RuntimeException('OpenAI Fehler beim Referenzbild-Abgleich: '.$response->status());
        }

        $raw = Arr::get($response->json(), 'choices.0.message.content');
        if (! is_string($raw)) {
            throw new \RuntimeException('Referenzbild-Abgleich: ungültige Antwort.');
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function respectRateLimit(): void
    {
        $limit = (int) config('media_ai.rate_limit_per_minute', 3);
        if ($limit <= 0) {
            return;
        }

        $key = 'media_ai:images:minute';
        $count = cache()->add($key, 1, 60) ? 1 : cache()->increment($key);
        if ($count > $limit) {
            throw new MediaAiRateLimitException('Media AI rate limit reached (Referenzbild-Abgleich).');
        }
    }

    private function normalizeConfidence(mixed $value): string
    {
        if (is_numeric($value)) {
            $num = (float) $value;
            if ($num >= 0.85) {
                return 'high';
            }
            if ($num >= 0.65) {
                return 'medium';
            }

            return 'low';
        }

        return mb_strtolower(trim((string) $value), 'UTF-8');
    }

    private function confidenceMeetsMinimum(string $confidence): bool
    {
        $min = mb_strtolower((string) config('media_ai.reference_match_min_confidence', 'medium'), 'UTF-8');
        if ($min === 'high') {
            return $confidence === 'high';
        }

        return in_array($confidence, ['high', 'medium'], true);
    }

    private function toPositiveInt(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }
        if (is_string($value) && preg_match('/^\s*(\d{1,4})\s*$/u', $value, $match) === 1) {
            $n = (int) $match[1];

            return $n > 0 ? $n : null;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function toStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            if (is_string($item) && trim($item) !== '') {
                $out[] = trim($item);
            }
        }

        return $out;
    }
}
