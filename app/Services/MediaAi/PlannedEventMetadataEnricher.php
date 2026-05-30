<?php

namespace App\Services\MediaAi;

use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use App\Models\PlannedEvent;
use App\Models\PlannedEventTeam;
use App\Services\PlannedEvents\ScheduleSlotMatcher;
use App\Support\ImportTextNormalizer;
use App\Support\MediaCaptionLocationDateTail;
use App\Support\PlannedEventTeamReferenceImageLocator;
use App\Support\RaceStartNumberSanitizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PlannedEventMetadataEnricher
{
    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    public function enrich(NewsItemMedia $media, array $result): array
    {
        $result = app(RaceStartNumberSanitizer::class)->sanitizeBeforeEnrichment($result);

        $news = $media->newsItem;
        $plannedEventId = (int) ($news?->planned_event_id ?? 0);
        if ($plannedEventId <= 0) {
            return $this->attachEnrichmentInfo($result, false, null, null, [], 'kein_event');
        }

        $plannedEvent = PlannedEvent::query()->with('teams')->find($plannedEventId);
        if (! $plannedEvent) {
            return $this->appendNeedsReview(
                $this->attachEnrichmentInfo($result, false, null, null, [], 'event_nicht_gefunden'),
                'Starterliste nicht verfügbar'
            );
        }

        $mapping = $this->buildStartNumberMapping($plannedEvent);
        if ($mapping === []) {
            return $this->appendNeedsReview(
                $this->attachEnrichmentInfo($result, false, null, null, [], 'keine_starterliste'),
                'Starterliste ohne strukturierte Startnummern'
            );
        }

        $eventCaptionLabel = $this->resolveEventCaptionLabel($plannedEvent, $media);

        $detectedNumbers = $this->detectStartNumbers($result);
        $detectedStartNumber = $detectedNumbers[0] ?? null;
        $sanitizer = app(RaceStartNumberSanitizer::class);
        if ($detectedStartNumber !== null && $sanitizer->shouldDiscardMisreadStartNumber($detectedStartNumber, $mapping, $result)) {
            $detectedStartNumber = null;
            $detectedNumbers = [];
            $result = $sanitizer->clearDetectedStartNumbers($result);
        } elseif (
            $detectedStartNumber !== null
            && $this->shouldAttemptReferenceImageMatch($plannedEvent)
            && ! $sanitizer->isBodyStartNumberClearlyVisible($detectedStartNumber, $result)
        ) {
            $detectedStartNumber = null;
            $detectedNumbers = [];
            $result = $sanitizer->clearDetectedStartNumbers($result);
        }

        $matchedViaReferenceImage = false;
        $referenceMatchNote = null;
        $referenceMatcher = app(ReferenceImageTeamMatcher::class);

        if ($detectedStartNumber === null && $this->shouldAttemptReferenceImageMatch($plannedEvent)) {
            $referenceMatch = $referenceMatcher->match($media, $plannedEvent, $result);
            if ($referenceMatch !== null) {
                $detectedStartNumber = (int) $referenceMatch['start_number'];
                $result['detected_start_number'] = $detectedStartNumber;
                $result['detected_car_numbers'] = [$detectedStartNumber];
                $detectedNumbers = [$detectedStartNumber];
                $matchedViaReferenceImage = true;
                $referenceMatchNote = (string) ($referenceMatch['reason'] ?? '');
            }
        }

        if ($detectedStartNumber === null) {
            $appearanceMatch = $this->recallAppearanceMapping($plannedEvent->id, (int) $news->id, $result);
            if ($appearanceMatch !== null) {
                $appearanceEntry = $this->mappingEntryForStartNumber($mapping, $appearanceMatch['start_number'], $appearanceMatch['team']);
                $result = $this->applyDeterministicMetadata(
                    $result,
                    (string) $appearanceEntry['team'],
                    $appearanceMatch['start_number'],
                    (array) $appearanceEntry['drivers'],
                    $appearanceEntry['vehicle'] ?? null,
                    $eventCaptionLabel,
                    null,
                    $media
                );

                $result = $this->appendNeedsReview(
                    $result,
                    'Startnummer unscharf, Zuordnung über Farb-/Design-Treffer-Historie'
                );

                return $this->attachEnrichmentInfo(
                    $result,
                    true,
                    $appearanceMatch['start_number'],
                    $appearanceMatch['team'],
                    [],
                    'matched_from_appearance_memory'
                );
            }

            return $this->appendNeedsReview(
                $this->attachEnrichmentInfo($result, false, null, null, [], 'keine_startnummer_erkannt'),
                'Startnummer im Bild nicht eindeutig erkannt'
            );
        }

        $entry = $mapping[$detectedStartNumber] ?? null;

        if (is_array($entry) && ! $matchedViaReferenceImage && $this->shouldAttemptReferenceImageMatch($plannedEvent)
            && $referenceMatcher->shouldDisambiguateProsportGt3Match($detectedStartNumber, $result)) {
            $referenceMatch = $referenceMatcher->match($media, $plannedEvent, $result);
            if ($referenceMatch !== null) {
                $candidateNr = (int) $referenceMatch['start_number'];
                if (isset($mapping[$candidateNr])) {
                    $detectedStartNumber = $candidateNr;
                    $entry = $mapping[$candidateNr];
                    $result['detected_start_number'] = $detectedStartNumber;
                    $result['detected_car_numbers'] = [$detectedStartNumber];
                    $detectedNumbers = [$detectedStartNumber];
                    $matchedViaReferenceImage = true;
                    $referenceMatchNote = (string) ($referenceMatch['reason'] ?? '');
                }
            }
        }

        if (! is_array($entry)) {
            if (! $matchedViaReferenceImage && $this->shouldAttemptReferenceImageMatch($plannedEvent)) {
                $referenceMatch = $referenceMatcher->match($media, $plannedEvent, $result);
                if ($referenceMatch !== null) {
                    $detectedStartNumber = (int) $referenceMatch['start_number'];
                    $entry = $mapping[$detectedStartNumber] ?? null;
                    $matchedViaReferenceImage = true;
                    $referenceMatchNote = (string) ($referenceMatch['reason'] ?? '');
                }
            }

            if (! is_array($entry)) {
                $rememberedTeam = $this->recallCarMapping($plannedEvent->id, (int) $news->id, $detectedStartNumber);
                if (is_string($rememberedTeam) && $rememberedTeam !== '') {
                    $rememberedEntry = $this->mappingEntryForStartNumber($mapping, $detectedStartNumber, $rememberedTeam);
                    $result = $this->applyDeterministicMetadata(
                        $result,
                        (string) $rememberedEntry['team'],
                        $detectedStartNumber,
                        (array) $rememberedEntry['drivers'],
                        $rememberedEntry['vehicle'] ?? null,
                        $eventCaptionLabel,
                        null,
                        $media
                    );

                    return $this->attachEnrichmentInfo($result, true, $detectedStartNumber, $rememberedTeam, [], 'matched_from_memory');
                }

                $appearanceMatch = $this->recallAppearanceMapping($plannedEvent->id, (int) $news->id, $result);
                if ($appearanceMatch !== null) {
                    $appearanceEntry = $this->mappingEntryForStartNumber($mapping, $appearanceMatch['start_number'], $appearanceMatch['team']);
                    $result = $this->applyDeterministicMetadata(
                        $result,
                        (string) $appearanceEntry['team'],
                        $appearanceMatch['start_number'],
                        (array) $appearanceEntry['drivers'],
                        $appearanceEntry['vehicle'] ?? null,
                        $eventCaptionLabel,
                        null,
                        $media
                    );
                    $result = $this->appendNeedsReview(
                        $result,
                        'Startnummer nicht in Liste, Zuordnung über Farb-/Design-Treffer-Historie'
                    );

                    return $this->attachEnrichmentInfo(
                        $result,
                        true,
                        $appearanceMatch['start_number'],
                        $appearanceMatch['team'],
                        [],
                        'matched_from_appearance_memory'
                    );
                }

                return $this->appendNeedsReview(
                    $this->attachEnrichmentInfo($result, false, $detectedStartNumber, null, [], 'startnummer_ohne_match'),
                    'Startnummer '.$detectedStartNumber.' nicht in Starterliste gefunden'
                );
            }
        }

        $teamName = (string) ($entry['team'] ?? '');
        $drivers = array_values(array_filter(
            array_map(static fn ($name) => trim((string) $name), (array) ($entry['drivers'] ?? [])),
            static fn ($name) => $name !== ''
        ));

        $background = $this->resolveBackgroundTeam(
            $detectedNumbers,
            $detectedStartNumber,
            $mapping
        );

        $vehicle = isset($entry['vehicle']) && is_string($entry['vehicle']) && trim($entry['vehicle']) !== ''
            ? trim($entry['vehicle'])
            : null;

        $result = $this->applyDeterministicMetadata(
            $result,
            $teamName,
            $detectedStartNumber,
            $drivers,
            $vehicle,
            $eventCaptionLabel,
            $background,
            $media
        );

        $this->rememberCarMapping($plannedEvent->id, (int) $news->id, $detectedStartNumber, $teamName);
        $this->rememberAppearanceMapping($plannedEvent->id, (int) $news->id, $result, $detectedStartNumber, $teamName);
        if ($background !== null) {
            $this->rememberCarMapping($plannedEvent->id, (int) $news->id, (int) $background['start_number'], (string) $background['team']);
        }

        $matchReason = $matchedViaReferenceImage ? 'matched_from_reference_image' : 'matched';

        return $this->attachEnrichmentInfo($result, true, $detectedStartNumber, $teamName, $drivers, $matchReason);
    }

    /**
     * @return array<int, array{team:string, drivers:list<string>, vehicle:?string}>
     */
    private function buildStartNumberMapping(PlannedEvent $plannedEvent): array
    {
        $mapping = [];
        foreach ($plannedEvent->teams as $team) {
            $name = trim((string) $team->name);
            if ($name === '') {
                continue;
            }

            if (preg_match('/Box\s*(\d+)\s*\|\s*Startnr\.?\s*(\d+)\s*\|\s*(.+)$/iu', $name, $matches) !== 1) {
                continue;
            }

            $startNumber = (int) $matches[2];
            $teamLabel = trim((string) preg_replace('/\s+/u', ' ', (string) $matches[3]));
            if ($startNumber <= 0 || $teamLabel === '') {
                continue;
            }

            $drivers = $this->extractDriverNames((string) ($team->notes ?? ''));
            $vehicle = $team instanceof PlannedEventTeam ? $team->vehicleLabel() : null;
            $mapping[$startNumber] = [
                'team' => $teamLabel,
                'drivers' => $drivers,
                'vehicle' => $vehicle !== null && $vehicle !== '' ? $vehicle : null,
            ];
        }

        return $mapping;
    }

    /**
     * @param  array<int, array{team:string, drivers:list<string>, vehicle:?string}>  $mapping
     * @return array{team:string, drivers:list<string>, vehicle:?string}
     */
    private function mappingEntryForStartNumber(array $mapping, int $startNumber, ?string $fallbackTeam = null): array
    {
        $entry = $mapping[$startNumber] ?? null;
        if (is_array($entry)) {
            return $entry;
        }

        return [
            'team' => trim((string) $fallbackTeam),
            'drivers' => [],
            'vehicle' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    /**
     * @param  array<string,mixed>  $result
     * @return list<int>
     */
    private function detectStartNumbers(array $result): array
    {
        $numbers = [];
        $add = static function (int $num) use (&$numbers): void {
            if ($num > 0 && ! in_array($num, $numbers, true)) {
                $numbers[] = $num;
            }
        };

        $aggregateText = $this->aggregateTextForNumberDetection($result);
        $sanitizer = app(RaceStartNumberSanitizer::class);

        $detected = $result['detected_start_number'] ?? null;
        if (is_int($detected) && $detected > 0) {
            if (! $sanitizer->shouldIgnoreAsMarshalDisplay($detected, $aggregateText)) {
                $add($detected);
            }
        } elseif (is_string($detected) && preg_match('/^\s*(\d{1,4})\s*$/u', $detected, $match) === 1) {
            $nr = (int) $match[1];
            if ($nr > 0 && ! $sanitizer->shouldIgnoreAsMarshalDisplay($nr, $aggregateText)) {
                $add($nr);
            }
        }

        foreach ((array) ($result['detected_car_numbers'] ?? []) as $n) {
            if (is_int($n)) {
                if (! $sanitizer->shouldIgnoreAsMarshalDisplay($n, $aggregateText)) {
                    $add($n);
                }
            } elseif (is_string($n) && preg_match('/^\s*(\d{1,4})\s*$/u', $n, $m) === 1) {
                $nr = (int) $m[1];
                if ($nr > 0 && ! $sanitizer->shouldIgnoreAsMarshalDisplay($nr, $aggregateText)) {
                    $add($nr);
                }
            }
        }

        foreach ((array) ($result['keywords'] ?? []) as $keyword) {
            $k = trim((string) $keyword);
            if (preg_match('/^\#\s*(\d{1,4})$/u', $k, $match) === 1) {
                $nr = (int) $match[1];
                if (! $sanitizer->shouldIgnoreAsMarshalDisplay($nr, $aggregateText)) {
                    $add($nr);
                }
            }
            if (preg_match('/(?:startnummer|startnr\.?|nr\.?)\s*#?\s*(\d{1,4})/iu', $k, $match) === 1) {
                $nr = (int) $match[1];
                if (! $sanitizer->shouldIgnoreAsMarshalDisplay($nr, $aggregateText)) {
                    $add($nr);
                }
            }
        }

        $textCandidates = [
            (string) ($result['caption'] ?? ''),
            (string) ($result['image_title'] ?? ''),
        ];
        foreach ($textCandidates as $text) {
            if (preg_match_all('/\#\s*(\d{1,4})/u', $text, $matches) > 0) {
                foreach ($matches[1] as $n) {
                    $nr = (int) $n;
                    if (! $sanitizer->shouldIgnoreAsMarshalDisplay($nr, $aggregateText)) {
                        $add($nr);
                    }
                }
            }
            if (preg_match_all('/(?:Startnummer|Startnr\.?)\s*#?\s*(\d{1,4})/iu', $text, $matches) > 0) {
                foreach ($matches[1] as $n) {
                    $nr = (int) $n;
                    if (! $sanitizer->shouldIgnoreAsMarshalDisplay($nr, $aggregateText)) {
                        $add($nr);
                    }
                }
            }
        }

        return $numbers;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function aggregateTextForNumberDetection(array $result): string
    {
        $chunks = [
            (string) ($result['caption'] ?? ''),
            (string) ($result['image_title'] ?? ''),
            (string) ($result['description'] ?? ''),
            implode("\n", array_map('strval', (array) ($result['keywords'] ?? []))),
            implode("\n", array_map('strval', (array) ($result['livery_cues'] ?? []))),
        ];

        return trim(implode("\n", array_filter($chunks, fn ($c) => trim($c) !== '')));
    }

    /**
     * @return list<string>
     */
    private function extractDriverNames(string $notes): array
    {
        $notes = ImportTextNormalizer::normalize($notes);
        if ($notes === '') {
            return [];
        }

        $normalized = preg_replace('/^Fahrer\s*\/\s*Fahrzeugdetails:\s*/iu', '', $notes) ?? $notes;
        $normalized = preg_replace('/\bFahrzeug:\s*.+?(?=\s+Fahrer\b|\s*;|\n|$)/iu', ' ', $normalized) ?? $normalized;
        $segments = preg_split('/\s*[;\n|]\s*/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $drivers = [];
        foreach ($segments as $segment) {
            $segment = ImportTextNormalizer::normalize((string) $segment);
            if ($segment === '') {
                continue;
            }
            $segment = preg_replace('/^Fahrer:\s*/iu', '', $segment) ?? $segment;
            if (preg_match('/^Fahrzeug\b/iu', $segment)) {
                continue;
            }
            if (preg_match('/^\s*(.*)\s+\(([A-Z]{2,3})\)\s*(?:\s+.*)?$/u', $segment, $match) !== 1) {
                continue;
            }
            $nameWithCity = trim((string) ($match[1] ?? ''));
            $countryCode = trim((string) ($match[2] ?? ''));
            if ($nameWithCity === '' || $countryCode === '') {
                continue;
            }
            $pureName = $this->stripTrailingCityToken($nameWithCity);
            if ($pureName === '') {
                continue;
            }
            $drivers[] = $pureName.' ('.$countryCode.')';
        }

        return array_values(array_unique($drivers));
    }

    private function stripTrailingCityToken(string $nameWithCity): string
    {
        $nameWithCity = ImportTextNormalizer::normalize($nameWithCity);
        if ($nameWithCity === '') {
            return '';
        }

        // Bevorzugtes Format in Starterlisten: "Vorname Nachname, Ort"
        if (str_contains($nameWithCity, ',')) {
            $parts = explode(',', $nameWithCity, 2);

            return trim((string) ($parts[0] ?? ''));
        }

        $tokens = preg_split('/\s+/u', $nameWithCity, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($tokens) <= 2) {
            return $nameWithCity;
        }

        $last = (string) end($tokens);
        $prev = (string) ($tokens[count($tokens) - 2] ?? '');
        // Heuristik für übliche Starterlistenform "Vorname Nachname Ort".
        if ($this->looksLikeSingleWordLocation($last) && $this->looksLikePersonToken($prev)) {
            array_pop($tokens);
        }

        return trim(implode(' ', $tokens));
    }

    private function looksLikeSingleWordLocation(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        return preg_match('/^[A-ZÄÖÜ][\p{L}\-]+$/u', $token) === 1;
    }

    private function looksLikePersonToken(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        return preg_match('/^[A-ZÄÖÜ][\p{L}\-\'’]+$/u', $token) === 1;
    }

    /**
     * Veranstaltungsname plus Programmpunkt aus dem hinterlegten Zeitplan (PDF-Text), falls Aufnahmezeit in ein Slot fällt.
     */
    private function resolveEventCaptionLabel(PlannedEvent $plannedEvent, NewsItemMedia $media): string
    {
        $name = trim((string) $plannedEvent->name);
        if (! $media->capture_time) {
            return $name;
        }

        $match = app(ScheduleSlotMatcher::class)->matchMediaToSchedule($media, $plannedEvent);
        if ($match === null) {
            return $name;
        }

        $slot = trim((string) ($match['label'] ?? ''));
        if ($slot === '') {
            return $name;
        }
        if ($name === '') {
            return $slot;
        }

        $nameLower = mb_strtolower($name, 'UTF-8');
        $slotLower = mb_strtolower($slot, 'UTF-8');
        if (str_contains($slotLower, $nameLower)) {
            return $slot;
        }
        if (str_contains($nameLower, $slotLower)) {
            return $name;
        }

        return trim($name.' '.$slot);
    }

    /**
     * Kürzt wiederholende Startnummern-Formulierungen in der BU: nur noch (#n), keine ausgeschriebenen Varianten.
     *
     * @param  list<int>  $extraNumbers  Weitere erkannte Nummern (z. B. Hintergrundfahrzeug), optional bereinigen.
     */
    private function normalizeCaptionStartNumberPhrasing(string $caption, int $primary, ?int $background, array $extraNumbers = []): string
    {
        $nums = $primary > 0 ? [$primary] : [];
        if ($background !== null && $background > 0) {
            $nums[] = $background;
        }
        foreach ($extraNumbers as $n) {
            if (is_int($n) && $n > 0 && ! in_array($n, $nums, true)) {
                $nums[] = $n;
            }
        }
        $nums = array_values(array_unique($nums));
        if ($nums === []) {
            return trim($caption);
        }

        $out = $caption;
        foreach ($nums as $n) {
            $nStr = (string) $n;
            $hasParen = preg_match('/\(\#'.$nStr.'\)/u', $out) === 1;
            $repl = $hasParen ? '' : '(#'.$nStr.')';
            $patterns = [
                '/\bmit\s+Startnummer\s*'.$nStr.'\b/iu',
                '/\bStartnummer\s*'.$nStr.'\b/iu',
                '/\bStartnr\.?\s*'.$nStr.'\b/iu',
            ];
            foreach ($patterns as $re) {
                $out = preg_replace($re, $repl, $out) ?? $out;
                if (! $hasParen && $repl !== '') {
                    $hasParen = preg_match('/\(\#'.$nStr.'\)/u', $out) === 1;
                    $repl = $hasParen ? '' : '(#'.$nStr.')';
                }
            }
            $out = preg_replace('/(?<!\()\#'.$nStr.'(?!\d)/u', '(#'.$nStr.')', $out) ?? $out;
            $out = preg_replace('/(\(\#'.$nStr.'\))\s*(?:\1\s*)+/u', '$1 ', $out) ?? $out;
        }

        $out = preg_replace('/\s{2,}/u', ' ', $out) ?? $out;
        $out = preg_replace('/\s+([,.])/u', '$1', $out) ?? $out;
        $out = trim(preg_replace('/\s*\.\s*\./u', '.', $out) ?? $out);

        return trim($out);
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  list<string>  $drivers
     * @param  array{start_number:int,team:string,drivers:list<string>,vehicle:?string}|null  $backgroundEntry
     * @return array<string, mixed>
     */
    private function applyDeterministicMetadata(
        array $result,
        string $teamName,
        int $startNumber,
        array $drivers,
        ?string $vehicle,
        string $eventCaptionLabel,
        ?array $backgroundEntry,
        NewsItemMedia $media
    ): array {
        [$teamBase, $teamClass] = $this->splitTeamAndClass($teamName);
        $eventLabel = trim($eventCaptionLabel);

        $rawCaption = trim((string) ($result['caption'] ?? ''));
        $locationTail = $this->extractLocationDateTail($rawCaption);
        $news = $media->relationLoaded('newsItem') ? $media->newsItem : $media->newsItem()->first();
        $existingCaption = $rawCaption;
        if ($news && $media->isImage()) {
            $existingCaption = MediaCaptionLocationDateTail::stripKnownTails($news, $media, $existingCaption);
        }
        $existingCaption = MediaCaptionLocationDateTail::stripGenericTrailingLocationDateTails($existingCaption);

        $resolvedVehicle = $vehicle !== null && trim($vehicle) !== ''
            ? trim($vehicle)
            : $this->inferVehicleFromVisionCaption($existingCaption);

        $plannedEvent = null;
        if ($news && (int) ($news->planned_event_id ?? 0) > 0) {
            $plannedEvent = $news->relationLoaded('plannedEvent')
                ? $news->plannedEvent
                : $news->plannedEvent()->first();
        }

        $captionStyle = $this->captionStyle();

        if ($captionStyle === 'agency') {
            $carBlocks = [
                $this->buildAgencyCarBlock($startNumber, $resolvedVehicle, $teamBase, $drivers),
            ];
            if (is_array($backgroundEntry)) {
                [$bgBase] = $this->splitTeamAndClass((string) $backgroundEntry['team']);
                $bgVehicle = isset($backgroundEntry['vehicle']) && is_string($backgroundEntry['vehicle'])
                    ? trim($backgroundEntry['vehicle'])
                    : null;
                $carBlocks[] = $this->buildAgencyCarBlock(
                    (int) $backgroundEntry['start_number'],
                    $bgVehicle !== '' ? $bgVehicle : null,
                    $bgBase,
                    (array) ($backgroundEntry['drivers'] ?? [])
                );
            }

            $result['caption'] = ImportTextNormalizer::normalize(
                $this->buildAgencyCaption($plannedEvent, $eventLabel, $carBlocks, $media, $news)
            );
            $result['caption_append_location_tail'] = false;

            $shortTitle = $this->buildAgencyShortTitle($eventLabel, $startNumber, $resolvedVehicle, $teamBase);
            $result['image_title'] = Str::limit($shortTitle, 90, '');
            $result['description'] = $result['image_title'];
        } elseif ($captionStyle === 'imago') {
            $primaryBlock = $this->buildImagoCarBlock($drivers, $startNumber, $resolvedVehicle, $teamBase);
            $blocks = [$primaryBlock];
            if (is_array($backgroundEntry)) {
                [$bgBase] = $this->splitTeamAndClass((string) $backgroundEntry['team']);
                $bgVehicle = isset($backgroundEntry['vehicle']) && is_string($backgroundEntry['vehicle'])
                    ? trim($backgroundEntry['vehicle'])
                    : null;
                $blocks[] = $this->buildImagoCarBlock(
                    (array) ($backgroundEntry['drivers'] ?? []),
                    (int) $backgroundEntry['start_number'],
                    $bgVehicle !== '' ? $bgVehicle : null,
                    $bgBase
                );
            }

            $scene = $this->extractSceneFromVisionCaption($existingCaption, $teamBase, is_array($backgroundEntry) ? (string) $backgroundEntry['team'] : null);
            $captionParts = [implode(', ', array_filter($blocks, static fn ($b) => $b !== ''))];
            if ($scene !== '') {
                $captionParts[] = $scene;
            }
            if ($eventLabel !== '') {
                $captionParts[] = $eventLabel;
            }
            $caption = ImportTextNormalizer::normalize(trim(implode('. ', $captionParts).'.'));
            if ($locationTail !== null && ! $media->capture_time) {
                $caption = trim($caption.' '.rtrim($locationTail, '.!?').'.');
            }
            $result['caption'] = $caption;

            $shortTitle = $this->buildImagoShortTitle($drivers, $startNumber, $resolvedVehicle, $teamBase);
            $result['image_title'] = Str::limit($shortTitle, 90, '');
            $result['description'] = $result['image_title'];
        } else {
            $carLabel = $teamBase.' (#'.$startNumber.')'.($teamClass !== '' ? ' '.$teamClass : '');
            $driverSnippet = $drivers === [] ? '' : ' mit den Fahrern '.implode(', ', $drivers);
            $title = trim((string) ($result['image_title'] ?? ''));
            if ($title === '' || ! Str::contains(mb_strtolower($title, 'UTF-8'), mb_strtolower($teamBase, 'UTF-8'))) {
                $result['image_title'] = $eventLabel !== ''
                    ? $carLabel.' beim '.$eventLabel
                    : $carLabel.' auf der Rennstrecke';
            }

            $introSentence = $carLabel.$driverSnippet.'.';
            $bgTeam = is_array($backgroundEntry) ? (string) $backgroundEntry['team'] : null;
            $bgNr = is_array($backgroundEntry) ? (int) $backgroundEntry['start_number'] : null;
            $vehicleSentence = $this->buildVehicleSentence($existingCaption, $eventLabel, $teamBase, $bgTeam, $bgNr);

            $caption = trim($introSentence.' '.$vehicleSentence);
            if ($locationTail !== null && ! $media->capture_time) {
                $caption = trim($caption.' '.rtrim($locationTail, '.!?').'.');
            }
            $result['caption'] = $caption;

            $detectedExtras = array_values(array_filter(
                $this->detectStartNumbers($result),
                static fn ($n): bool => is_int($n) && $n > 0 && $n !== $startNumber && $n !== ($bgNr ?? -1)
            ));

            $result['caption'] = ImportTextNormalizer::normalize($this->normalizeCaptionStartNumberPhrasing(
                (string) $result['caption'],
                $startNumber,
                $bgNr,
                array_slice($detectedExtras, 0, 3)
            ));
        }

        $keywords = array_values(array_filter(
            array_map(static fn ($kw) => trim((string) $kw), (array) ($result['keywords'] ?? [])),
            static fn ($kw) => $kw !== ''
        ));
        $keywords[] = $teamBase;
        if ($teamClass !== '') {
            $keywords[] = $teamClass;
        }
        $keywords[] = '#'.$startNumber;
        foreach ($drivers as $driver) {
            $keywords[] = $driver;
            if (preg_match('/^(.+?)\s*\([A-Z]{2,3}\)$/u', $driver, $match) === 1) {
                $keywords[] = trim((string) $match[1]);
            }
        }
        $result['keywords'] = $this->appendMotorsportSearchKeywords(
            array_values(array_unique($keywords)),
            $eventLabel,
            $news
        );

        return $result;
    }

    private function captionStyle(): string
    {
        $style = strtolower(trim((string) config('media_ai.caption_style', '')));
        if (in_array($style, ['agency', 'imago', 'legacy'], true)) {
            return $style;
        }

        return config('media_ai.imago_style_captions', true) ? 'imago' : 'legacy';
    }

    /**
     * Agentur-Stil (Gruppe C / Getty-ähnlich): Event, Nr. Fahrzeug, Team: Fahrer, … - picture by … Ort.
     *
     * @param  list<string>  $carBlocks
     */
    private function buildAgencyCaption(
        ?PlannedEvent $plannedEvent,
        string $eventCaptionLabel,
        array $carBlocks,
        NewsItemMedia $media,
        ?NewsItem $news
    ): string {
        $editionPrefix = $this->resolveAgencyEditionPrefix($plannedEvent);
        $eventName = $this->resolveAgencyEventName($plannedEvent, $eventCaptionLabel);
        $header = trim($editionPrefix.$eventName);
        $body = trim($header.' '.implode(', ', array_filter($carBlocks, static fn ($b) => trim($b) !== '')));

        $credit = $this->resolvePictureByCredit($media, $news);
        if ($credit !== '') {
            $body .= ' - picture by '.$credit;
        }

        $location = $this->buildAgencyLocationTail($plannedEvent, $media);
        if ($location !== '') {
            $body .= ' '.$location;
        }

        $copyright = trim((string) ($media->copyright ?? ''));
        if ($copyright !== '') {
            $body .= ' Copyright: '.$copyright;
        }

        return trim($body);
    }

    /**
     * @param  list<string>  $drivers
     */
    private function buildAgencyCarBlock(int $startNumber, ?string $vehicle, string $teamBase, array $drivers): string
    {
        $vehicleLabel = $vehicle !== null && trim($vehicle) !== '' ? trim($vehicle) : 'Rennwagen';
        $teamLabel = trim($teamBase);
        $driverList = $this->formatDriverNamesForAgencyCaption($drivers);
        if ($driverList !== '') {
            return $startNumber.' '.$vehicleLabel.', '.$teamLabel.': '.$driverList;
        }

        return $startNumber.' '.$vehicleLabel.', '.$teamLabel;
    }

    private function buildAgencyShortTitle(string $eventLabel, int $startNumber, ?string $vehicle, string $teamBase): string
    {
        $eventShort = $this->resolveAgencyEventName(null, $eventLabel);
        $vehicleLabel = $vehicle !== null && trim($vehicle) !== '' ? trim($vehicle) : '';
        $parts = array_filter([$eventShort, (string) $startNumber, $vehicleLabel, trim($teamBase)], static fn ($p) => $p !== '');

        return implode(' ', $parts);
    }

    /**
     * @param  list<string>  $drivers
     */
    private function formatDriverNamesForAgencyCaption(array $drivers): string
    {
        $names = [];
        foreach ($drivers as $driver) {
            $name = $this->stripCountryCodeFromDriverName((string) $driver);
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return implode(', ', $names);
    }

    private function stripCountryCodeFromDriverName(string $driver): string
    {
        $driver = trim($driver);
        if ($driver === '') {
            return '';
        }
        if (preg_match('/^(.+?)\s*\([A-Z]{2,3}\)\s*$/u', $driver, $match) === 1) {
            return trim((string) ($match[1] ?? ''));
        }

        return $driver;
    }

    private function resolveAgencyEditionPrefix(?PlannedEvent $plannedEvent): string
    {
        if (! $plannedEvent) {
            return '';
        }

        $context = (string) ($plannedEvent->ai_context ?? '');
        if (preg_match('/(?:caption[_\s-]*edition|ausgabe|edition|lauf)\s*[:=]\s*(\d{1,3})\b/iu', $context, $match) === 1) {
            return trim((string) $match[1]).'. ';
        }

        $name = trim((string) $plannedEvent->name);
        if (preg_match('/^(\d{1,3})\.\s+/u', $name, $match) === 1) {
            return trim((string) $match[1]).'. ';
        }

        return '';
    }

    private function resolveAgencyEventName(?PlannedEvent $plannedEvent, string $eventCaptionLabel): string
    {
        $label = trim($eventCaptionLabel);
        if ($label === '' && $plannedEvent) {
            $label = trim((string) $plannedEvent->name);
        }

        return trim(preg_replace('/^\d{1,3}\.\s+/u', '', $label) ?? $label);
    }

    private function resolvePictureByCredit(NewsItemMedia $media, ?NewsItem $news): string
    {
        $photographer = trim((string) ($media->photographer ?? ''));
        if ($photographer !== '') {
            return $photographer;
        }

        $credit = trim((string) ($media->credit ?? ''));
        if ($credit !== '') {
            return $credit;
        }

        if ($news) {
            $authorCredit = trim((string) ($news->author_credit ?? ''));
            if ($authorCredit !== '') {
                return $authorCredit;
            }
        }

        return 'Erftkreis News Media';
    }

    private function buildAgencyLocationTail(?PlannedEvent $plannedEvent, NewsItemMedia $media): string
    {
        $city = trim((string) ($plannedEvent?->venue_city ?? $media->city ?? ''));
        $track = trim((string) ($plannedEvent?->location ?? ''));
        $state = trim((string) ($plannedEvent?->venue_state ?? $media->state ?? ''));
        $country = $this->countryNameForAgencyCaption(
            trim((string) ($plannedEvent?->venue_country ?? $media->country ?? 'Deutschland'))
        );

        $parts = [];
        if ($city !== '' && ($track === '' || mb_strtolower($city, 'UTF-8') !== mb_strtolower($track, 'UTF-8'))) {
            $parts[] = $city;
        }
        if ($track !== '') {
            $parts[] = $track;
        } elseif ($city !== '') {
            $parts[] = $city;
        }
        if ($state !== '') {
            $parts[] = $state;
        }
        if ($country !== '') {
            $parts[] = $country;
        }

        return implode(' ', $parts);
    }

    private function countryNameForAgencyCaption(string $country): string
    {
        $normalized = mb_strtolower(trim($country), 'UTF-8');
        if ($normalized === '' || $normalized === 'deutschland' || $normalized === 'de') {
            return 'Germany';
        }

        return trim($country);
    }

    /**
     * IMAGO-Motorsport-Zeile: Fahrer (NAT), Startnr., Fahrzeug, Team: Name.
     *
     * @param  list<string>  $drivers
     */
    private function buildImagoCarBlock(array $drivers, int $startNumber, ?string $vehicle, string $teamBase): string
    {
        $parts = [];
        if ($drivers !== []) {
            $parts[] = implode(', ', $drivers);
        }
        $parts[] = (string) $startNumber;
        if ($vehicle !== null && trim($vehicle) !== '') {
            $parts[] = trim($vehicle);
        }
        $parts[] = 'Team: '.trim($teamBase);

        return implode(', ', array_filter($parts, static fn ($p) => $p !== ''));
    }

    /**
     * @param  list<string>  $drivers
     */
    private function buildImagoShortTitle(array $drivers, int $startNumber, ?string $vehicle, string $teamBase): string
    {
        if ($drivers !== []) {
            $lead = $drivers[0];
            if ($vehicle !== null && trim($vehicle) !== '') {
                return $lead.', '.$startNumber.', '.trim($vehicle);
            }

            return $lead.', '.$startNumber.', '.trim($teamBase);
        }

        if ($vehicle !== null && trim($vehicle) !== '') {
            return trim($teamBase).', '.$startNumber.', '.trim($vehicle);
        }

        return trim($teamBase).', '.$startNumber;
    }

    private function extractSceneFromVisionCaption(string $existingCaption, string $teamBase, ?string $backgroundTeamBase = null): string
    {
        $text = trim($existingCaption);
        if ($text === '') {
            return 'Auf der Rennstrecke';
        }

        $sentences = preg_split('/(?<=[.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($sentences as $sentence) {
            $normalized = trim($sentence);
            if ($normalized === '') {
                continue;
            }
            $lower = mb_strtolower($normalized, 'UTF-8');
            if (Str::contains($lower, mb_strtolower($teamBase, 'UTF-8'))) {
                continue;
            }
            if ($backgroundTeamBase !== null && Str::contains($lower, mb_strtolower($backgroundTeamBase, 'UTF-8'))) {
                continue;
            }
            if (preg_match('/^(?:das\s+)?bild\s+zeigt\b/iu', $normalized) === 1) {
                $normalized = preg_replace('/^(?:das\s+)?bild\s+zeigt\s*/iu', '', $normalized) ?? $normalized;
                $normalized = trim($normalized);
            }
            $normalized = preg_replace('/\b(?:Startnummer|Startnr\.?)\s*#?\s*\d{1,4}\b/iu', '', $normalized) ?? $normalized;
            $normalized = preg_replace('/\(\#\s*\d{1,4}\)/u', '', $normalized) ?? $normalized;
            $normalized = preg_replace('/\bmit\s*$/iu', '', $normalized) ?? $normalized;
            $normalized = preg_replace('/\bDer\s+Wagen\s+mit\s*$/iu', 'Der Wagen', $normalized) ?? $normalized;
            $normalized = preg_replace('/\s{2,}/u', ' ', $normalized) ?? $normalized;
            $normalized = trim($normalized, " \t\n\r\0\x0B,;");
            if ($normalized === '' || mb_strlen($normalized, 'UTF-8') < 12) {
                continue;
            }
            if (preg_match('/\bmit\s+fährt\b/iu', $normalized) === 1) {
                continue;
            }
            if (! preg_match('/[.!?]$/u', $normalized)) {
                $normalized = rtrim($normalized, ',').'.';
            }

            return rtrim($normalized, '.!?');
        }

        return 'Auf der Rennstrecke';
    }

    private function inferVehicleFromVisionCaption(string $caption): ?string
    {
        $patterns = [
            '/\b(Porsche\s+911\s+GT3\s*R)\b/iu',
            '/\b(Porsche\s+911\s+GT3)\b/iu',
            '/\b(Porsche\s+718\s+Cayman\s+GT4)\b/iu',
            '/\b(Mercedes-AMG\s+GT\d(?:\s+EVO)?)\b/iu',
            '/\b(Audi\s+R8\s+LMS\s+GT3)\b/iu',
            '/\b(BMW\s+M\d(?:\s+GT\d(?:\s+EVO)?)?)\b/iu',
            '/\b(Aston\s+Martin\s+Vantage(?:\s+AMR)?(?:\s+GT\d(?:\s+EVO)?)?)\b/iu',
            '/\b(Ford\s+Mustang\s+GT\d)\b/iu',
            '/\b(Lamborghini\s+Hurac[aá]n(?:\s+GT3(?:\s+Evo)?)?)\b/iu',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $caption, $match) === 1) {
                return trim((string) ($match[1] ?? ''));
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $keywords
     * @return list<string>
     */
    private function appendMotorsportSearchKeywords(array $keywords, string $eventLabel, ?NewsItem $news): array
    {
        $keywords[] = 'Motorsport';
        $eventLower = mb_strtolower($eventLabel, 'UTF-8');
        if (str_contains($eventLower, '24h') || str_contains($eventLower, '24-stunden') || str_contains($eventLower, '24 stunden')) {
            $keywords[] = '24h Nürburgring';
            $keywords[] = 'Langstreckenrennen';
        }
        if (str_contains($eventLower, 'nürburgring') || str_contains($eventLower, 'nuerburgring')) {
            $keywords[] = 'Nürburgring';
        }
        if (str_contains($eventLower, 'dtm')) {
            $keywords[] = 'DTM';
        }
        if ($news && (int) ($news->planned_event_id ?? 0) > 0) {
            $event = $news->relationLoaded('plannedEvent')
                ? $news->plannedEvent
                : $news->plannedEvent()->first();
            if ($event instanceof PlannedEvent) {
                $city = trim((string) $event->venue_city);
                if ($city !== '') {
                    $keywords[] = $city;
                }
            }
        }

        return array_values(array_unique($keywords));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitTeamAndClass(string $teamName): array
    {
        $team = trim($teamName);
        if ($team === '') {
            return ['', ''];
        }

        if (preg_match('/^(.*?)(\s+SP\s*9\s*(?:PRO|AM)?)$/iu', $team, $match) === 1) {
            return [trim((string) $match[1]), strtoupper(trim((string) $match[2]))];
        }
        if (preg_match('/^(.*?)(\s+SP9\s*(?:PRO|AM)?)$/iu', $team, $match) === 1) {
            return [trim((string) $match[1]), strtoupper(trim((string) $match[2]))];
        }

        return [$team, ''];
    }

    private function buildVehicleSentence(
        string $existingCaption,
        string $eventLabel,
        string $teamBase,
        ?string $backgroundTeam = null,
        ?int $backgroundStartNumber = null
    ): string {
        if ($backgroundTeam !== null && $backgroundStartNumber !== null) {
            [$bgBase, $bgClass] = $this->splitTeamAndClass($backgroundTeam);
            $bgLabel = $bgBase.' (#'.$backgroundStartNumber.')'.($bgClass !== '' ? ' '.$bgClass : '');
            if ($eventLabel !== '') {
                return 'Fahrt auf der Rennstrecke beim '.$eventLabel.', weiter hinten '.$bgLabel.'.';
            }

            return 'Fahrt auf der Rennstrecke, weiter hinten '.$bgLabel.'.';
        }

        $sentences = preg_split('/(?<=[.!?])\s+/u', trim($existingCaption), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($sentences as $sentence) {
            $normalized = trim($sentence);
            if ($normalized === '') {
                continue;
            }
            if (Str::contains(mb_strtolower($normalized, 'UTF-8'), mb_strtolower($teamBase, 'UTF-8'))) {
                continue;
            }
            if (preg_match('/\bfährt\b/iu', $normalized) === 1 || preg_match('/\bauf der Rennstrecke\b/iu', $normalized) === 1) {
                $normalized = preg_replace('/^[^:]{2,80}:\s*/u', '', $normalized) ?? $normalized;

                return rtrim(trim($normalized), '.').'.';
            }
        }

        if ($eventLabel !== '') {
            return 'Das Fahrzeug fährt auf der Rennstrecke beim '.$eventLabel.'.';
        }

        return 'Das Fahrzeug fährt auf der Rennstrecke.';
    }

    /**
     * @param  list<int>  $detectedNumbers
     * @param  array<int, array{team:string, drivers:list<string>, vehicle:?string}>  $mapping
     * @return array{start_number:int,team:string,drivers:list<string>,vehicle:?string}|null
     */
    private function resolveBackgroundTeam(array $detectedNumbers, int $primaryStartNumber, array $mapping): ?array
    {
        foreach ($detectedNumbers as $num) {
            if ($num === $primaryStartNumber) {
                continue;
            }
            if (isset($mapping[$num]) && is_array($mapping[$num])) {
                return [
                    'start_number' => $num,
                    'team' => (string) $mapping[$num]['team'],
                    'drivers' => (array) ($mapping[$num]['drivers'] ?? []),
                    'vehicle' => $mapping[$num]['vehicle'] ?? null,
                ];
            }
        }

        return null;
    }

    private function rememberCarMapping(int $eventId, int $newsId, int $startNumber, string $teamName): void
    {
        $key = 'media_ai:event:'.$eventId.':news:'.$newsId.':car_map';
        $map = Cache::get($key, []);
        if (! is_array($map)) {
            $map = [];
        }
        $map[(string) $startNumber] = $teamName;
        Cache::put($key, $map, now()->addHours(12));
    }

    private function recallCarMapping(int $eventId, int $newsId, int $startNumber): ?string
    {
        $key = 'media_ai:event:'.$eventId.':news:'.$newsId.':car_map';
        $map = Cache::get($key, []);
        if (! is_array($map)) {
            return null;
        }
        $team = $map[(string) $startNumber] ?? null;

        return is_string($team) && trim($team) !== '' ? trim($team) : null;
    }

    /**
     * @param  array<string,mixed>  $result
     */
    private function rememberAppearanceMapping(int $eventId, int $newsId, array $result, int $startNumber, string $teamName): void
    {
        $signature = $this->buildAppearanceSignature($result);
        if ($signature === null) {
            return;
        }

        $key = 'media_ai:event:'.$eventId.':news:'.$newsId.':appearance_map';
        $map = Cache::get($key, []);
        if (! is_array($map)) {
            $map = [];
        }
        $map[$signature] = ['start_number' => $startNumber, 'team' => $teamName];
        Cache::put($key, $map, now()->addHours(12));
    }

    /**
     * @param  array<string,mixed>  $result
     * @return array{start_number:int,team:string}|null
     */
    private function recallAppearanceMapping(int $eventId, int $newsId, array $result): ?array
    {
        $signature = $this->buildAppearanceSignature($result);
        if ($signature === null) {
            return null;
        }

        $key = 'media_ai:event:'.$eventId.':news:'.$newsId.':appearance_map';
        $map = Cache::get($key, []);
        if (! is_array($map)) {
            return null;
        }
        $entry = $map[$signature] ?? null;
        if (! is_array($entry)) {
            return null;
        }

        $startNumber = (int) ($entry['start_number'] ?? 0);
        $team = trim((string) ($entry['team'] ?? ''));
        if ($startNumber <= 0 || $team === '') {
            return null;
        }

        return ['start_number' => $startNumber, 'team' => $team];
    }

    /**
     * @param  array<string,mixed>  $result
     */
    private function buildAppearanceSignature(array $result): ?string
    {
        $tokens = [];
        $color = $this->normalizeSignatureToken((string) ($result['primary_car_color'] ?? ''));
        if ($color !== null) {
            $tokens[] = 'color:'.$color;
        }

        $foreground = $this->normalizeSignatureToken((string) ($result['foreground_subject'] ?? ''));
        if ($foreground !== null) {
            $tokens[] = 'fg:'.$foreground;
        }

        foreach ((array) ($result['livery_cues'] ?? []) as $cue) {
            $normalized = $this->normalizeSignatureToken((string) $cue);
            if ($normalized !== null) {
                $tokens[] = 'cue:'.$normalized;
            }
        }

        $tokens = array_values(array_unique($tokens));
        sort($tokens);
        if (count($tokens) < 2) {
            return null;
        }

        return sha1(implode('|', array_slice($tokens, 0, 8)));
    }

    private function normalizeSignatureToken(string $value): ?string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        if ($value === '') {
            return null;
        }

        $value = preg_replace('/#\s*\d{1,4}/u', '', $value) ?? $value;
        $value = preg_replace('/\b(startnummer|startnr\.?|nr\.?)\s*\d{1,4}\b/iu', '', $value) ?? $value;
        $value = preg_replace('/[^\p{L}\p{N}\s\-]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = trim($value);

        return mb_strlen($value, 'UTF-8') >= 3 ? $value : null;
    }

    private function extractLocationDateTail(string $caption): ?string
    {
        $text = trim($caption);
        if ($text === '') {
            return null;
        }

        if (preg_match('/(?:[A-ZÄÖÜa-zäöüß\-\s\(\)]+,\s*)+\d{1,2}\.\d{1,2}\.\d{4}\.?$/u', $text, $matches) !== 1) {
            return null;
        }

        $tail = trim((string) ($matches[0] ?? ''));
        if ($tail === '') {
            return null;
        }
        if (! preg_match('/[.!?]$/u', $tail)) {
            $tail .= '.';
        }

        return $tail;
    }

    private function shouldAttemptReferenceImageMatch(PlannedEvent $plannedEvent): bool
    {
        if (! config('media_ai.reference_match_enabled', true)) {
            return false;
        }

        foreach ($plannedEvent->teams as $team) {
            if (! $team instanceof PlannedEventTeam) {
                continue;
            }
            $path = trim((string) ($team->reference_image_path ?? ''));
            if ($path !== '' && PlannedEventTeamReferenceImageLocator::exists($path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  list<string>  $drivers
     * @return array<string, mixed>
     */
    private function attachEnrichmentInfo(
        array $result,
        bool $matched,
        ?int $startNumber,
        ?string $teamName,
        array $drivers,
        string $reason
    ): array {
        $result['planned_event_enrichment'] = [
            'matched' => $matched,
            'start_number' => $startNumber,
            'team' => $teamName,
            'drivers' => $drivers,
            'reason' => $reason,
        ];

        return app(RaceStartNumberSanitizer::class)->stripMarshalFromResult($result);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function appendNeedsReview(array $result, string $message): array
    {
        $needsReview = array_values(array_filter(
            array_map(static fn ($item) => trim((string) $item), (array) ($result['needs_review'] ?? [])),
            static fn ($item) => $item !== ''
        ));
        if (! in_array($message, $needsReview, true)) {
            $needsReview[] = $message;
        }
        $result['needs_review'] = $needsReview;

        return app(RaceStartNumberSanitizer::class)->stripMarshalFromResult($result);
    }
}
