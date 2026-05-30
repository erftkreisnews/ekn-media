<?php

namespace App\Services\MediaAi;

use App\Exceptions\MediaAiRateLimitException;
use App\Models\NewsItemMedia;
use App\Models\PlannedEvent;
use App\Models\PlannedEventTeam;
use App\Services\MediaStorage;
use App\Services\PlannedEvents\ScheduleSlotMatcher;
use App\Support\MediaCaptionLocationDateTail;
use App\Support\PlannedEventTeamReferenceImageLocator;
use App\Support\RaceStartNumberSanitizer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ImageMetadataGenerator
{
    /**
     * Generiert Metadaten für ein Bild mittels OpenAI Vision.
     *
     * @return array{
     *   image_title:string,
     *   photographer:?string,
     *   caption:string,
     *   foreground_subject:?string,
     *   background_subject:?string,
     *   keywords:array,
     *   description:?string,
     *   detected_start_number:?int,
     *   detected_car_numbers:list<int>,
     *   number_readability:?string,
     *   primary_car_color:?string,
     *   livery_cues:list<string>,
     *   detected_box_number:?int,
     *   pit_lane_or_garage_context:bool,
     *   confidence:array,
     *   needs_review:array
     * }
     */
    /**
     * @param  array{hint: string, existing_caption?: string, existing_keywords?: string}|null  $refinement
     */
    public function resolvePreviewUrlForVision(NewsItemMedia $media): string
    {
        return $this->ensurePreviewUrl($media);
    }

    public function generate(NewsItemMedia $media, ?array $refinement = null): array
    {
        $apiKey = config('media_ai.api_key');
        $model = config('media_ai.vision_model', 'gpt-4.1-mini');

        if (! $apiKey) {
            throw new \RuntimeException('OPENAI_API_KEY ist nicht gesetzt.');
        }

        // Globales Rate-Limit (Jobs/Minute) – Retry mit Backoff
        $limit = (int) config('media_ai.rate_limit_per_minute', 3);
        if ($limit > 0) {
            $key = 'media_ai:images:minute';
            $count = cache()->add($key, 1, 60) ? 1 : cache()->increment($key);
            if ($count > $limit) {
                Log::info('MediaAi: app rate limit reached', ['media_id' => $media->id, 'count' => $count]);
                throw new MediaAiRateLimitException('Media AI rate limit reached, bitte später erneut versuchen.');
            }
        }

        $previewUrl = $this->ensurePreviewUrl($media);
        $context = $this->buildContext($media);
        if (is_array($refinement)) {
            $hint = trim((string) ($refinement['hint'] ?? ''));
            if ($hint !== '') {
                $context['refinement_hint'] = $hint;
                $context['refinement_existing_caption'] = (string) ($refinement['existing_caption'] ?? '');
                $context['refinement_existing_keywords'] = (string) ($refinement['existing_keywords'] ?? '');
            }
        }

        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt = $this->buildUserPrompt($context);

        $userContent = [
            [
                'type' => 'text',
                'text' => $userPrompt,
            ],
            [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $previewUrl,
                ],
            ],
        ];

        $referenceDataUrl = $context['starter_reference_image_data_url'] ?? null;
        if (is_string($referenceDataUrl) && $referenceDataUrl !== '') {
            $userContent[] = [
                'type' => 'text',
                'text' => 'Zweites Bild: offizielles Referenzfoto des Fahrzeugs aus der ADAC-Starterliste '
                    .'(Startnummer '.(string) ($context['starter_reference_start_number'] ?? '?').'). '
                    .'Nutze es zur Erkennung von Lackierung, Sponsoren und Fahrzeugtyp; die Caption bezieht sich auf das erste Bild (Pressefoto).',
            ];
            $userContent[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $referenceDataUrl,
                ],
            ];
        }

        $payload = [
            'model' => $model,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => $userContent,
                ],
            ],
        ];

        $headers = [
            'Authorization' => 'Bearer '.$apiKey,
        ];
        if ($org = config('media_ai.organization')) {
            $headers['OpenAI-Organization'] = $org;
        }

        $response = Http::withHeaders($headers)
            ->timeout(config('media_ai.timeout', 75))
            ->post('https://api.openai.com/v1/chat/completions', $payload);

        if (! $response->successful()) {
            $status = $response->status();
            $body = Str::limit($response->body(), 500);
            Log::warning('MediaAi: OpenAI call failed', [
                'media_id' => $media->id,
                'status' => $status,
                'body' => $body,
            ]);
            if ($status === 429) {
                throw new MediaAiRateLimitException('OpenAI Rate Limit (429). Bitte später erneut versuchen. Response: '.$body);
            }
            throw new \RuntimeException('OpenAI API Fehler: '.$status.'. '.$body);
        }

        $data = $response->json();
        $content = Arr::get($data, 'choices.0.message.content');

        if (! is_string($content)) {
            throw new \RuntimeException('OpenAI Antwort ohne gültigen content.');
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            throw new \RuntimeException('OpenAI Antwort ist kein gültiges JSON.');
        }

        // Minimal validieren und Defaults setzen
        $result = [
            'image_title' => (string) ($decoded['image_title'] ?? ''),
            'photographer' => $decoded['photographer'] ?? null,
            'caption' => (string) ($decoded['caption'] ?? ''),
            'foreground_subject' => $this->toNullableString($decoded['foreground_subject'] ?? null),
            'background_subject' => $this->toNullableString($decoded['background_subject'] ?? null),
            'keywords' => array_values(array_filter($decoded['keywords'] ?? [], fn ($k) => is_string($k) && $k !== '')),
            'description' => $decoded['description'] ?? null,
            'detected_start_number' => $this->toNullableInt($decoded['detected_start_number'] ?? null),
            'detected_car_numbers' => $this->toIntList($decoded['detected_car_numbers'] ?? []),
            'number_readability' => $this->toNullableString($decoded['number_readability'] ?? null),
            'primary_car_color' => $this->toNullableString($decoded['primary_car_color'] ?? null),
            'livery_cues' => $this->toStringList($decoded['livery_cues'] ?? []),
            'detected_box_number' => $this->toNullableInt($decoded['detected_box_number'] ?? null),
            'pit_lane_or_garage_context' => (bool) ($decoded['pit_lane_or_garage_context'] ?? false),
            'confidence' => [
                'caption' => (float) Arr::get($decoded, 'confidence.caption', 0.0),
                'location' => (float) Arr::get($decoded, 'confidence.location', 0.0),
            ],
            'needs_review' => array_values(array_filter($decoded['needs_review'] ?? [], fn ($k) => is_string($k) && $k !== '')),
        ];

        return app(RaceStartNumberSanitizer::class)->sanitizeVisionResult($result);
    }

    protected function ensurePreviewUrl(NewsItemMedia $media): string
    {
        $mediaStorage = app(MediaStorage::class);
        $disk = $mediaStorage->activeDisk();
        $ctx = [
            'media_id' => $media->id,
            'media_path' => $media->path,
        ];
        $step = 'preview_path_reuse_check';
        Log::debug('MediaAi preview step start', $ctx + ['step' => $step]);

        if ($media->preview_path && $mediaStorage->exists($media->preview_path)) {
            Log::debug('MediaAi preview reused existing path', $ctx + [
                'step' => $step,
                'preview_rel_path' => $media->preview_path,
            ]);

            return $mediaStorage->url($media->preview_path);
        }

        try {
            $step = 'resolveReadableLocalPath';
            Log::debug('MediaAi preview step start', $ctx + ['step' => $step]);
            $resolved = $mediaStorage->resolveReadableLocalPath($media->path);
            $sourcePath = $resolved['path'] ?? null;
            Log::debug('MediaAi preview step done', $ctx + [
                'step' => $step,
                'resolved_source_path' => is_string($sourcePath) ? $sourcePath : null,
                'resolved_disk' => is_array($resolved) ? ($resolved['disk'] ?? null) : null,
                'resolved_temporary' => is_array($resolved) ? ($resolved['temporary'] ?? null) : null,
            ]);
            if (! is_string($sourcePath) || ! is_file($sourcePath) || ! is_readable($sourcePath)) {
                throw new \RuntimeException('Quelldatei für Preview nicht lesbar.');
            }

            $manager = new ImageManager(new Driver);

            $step = 'image_read';
            Log::debug('MediaAi preview step start', $ctx + ['step' => $step]);
            $image = $manager->read($sourcePath);
            Log::debug('MediaAi preview step done', $ctx + ['step' => $step]);

            $step = 'image_orient';
            Log::debug('MediaAi preview step start', $ctx + ['step' => $step]);
            $image = $image->orient();
            Log::debug('MediaAi preview step done', $ctx + ['step' => $step]);

            $step = 'image_scale_down';
            Log::debug('MediaAi preview step start', $ctx + ['step' => $step]);
            $image->scaleDown(1280, 1280);
            Log::debug('MediaAi preview step done', $ctx + ['step' => $step]);

            $previewRelPath = $mediaStorage->isStructuredNewsMediaPath($media->path)
                ? $mediaStorage->generateDerivedMediaPath(
                    $media->newsItem,
                    $media,
                    pathinfo($media->original_name ?: basename($media->path), PATHINFO_FILENAME).'.jpg',
                    'preview-ai'
                )
                : 'news-media/'.$media->news_item_id.'/image/preview_'.$media->id.'.jpg';
            Log::debug('MediaAi preview target prepared', $ctx + ['preview_rel_path' => $previewRelPath]);

            $step = 'image_to_jpeg';
            Log::debug('MediaAi preview step start', $ctx + ['step' => $step]);
            $jpeg = (string) $image->toJpeg(80);
            Log::debug('MediaAi preview step done', $ctx + ['step' => $step, 'jpeg_bytes' => strlen($jpeg)]);

            $step = 'disk_put';
            Log::debug('MediaAi preview step start', $ctx + ['step' => $step, 'preview_rel_path' => $previewRelPath]);
            $disk->put($previewRelPath, $jpeg, ['visibility' => 'public']);
            Log::debug('MediaAi preview step done', $ctx + ['step' => $step]);

            $media->preview_path = $previewRelPath;
            $media->save();

            $mediaStorage->cleanupResolvedPath($resolved);

            $previewUrl = $mediaStorage->url($previewRelPath);
            Log::debug('MediaAi preview url created', $ctx + ['preview_rel_path' => $previewRelPath, 'preview_url' => $previewUrl]);

            return $previewUrl;
        } catch (\Throwable $e) {
            Log::warning('MediaAi preview step failed', $ctx + [
                'step' => $step,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function buildContext(NewsItemMedia $media): array
    {
        $news = $media->newsItem;
        $plannedEvent = null;
        $eventPlanningContext = null;
        $eventPlanningSource = null;
        $starterReferenceDataUrl = null;
        $starterReferenceStartNumber = null;

        if ($news && $news->planned_event_id) {
            $plannedEvent = $news->relationLoaded('plannedEvent')
                ? $news->plannedEvent
                : PlannedEvent::query()->with('teams')->find($news->planned_event_id);
            if ($plannedEvent) {
                $eventPlanningContext = Str::limit($plannedEvent->formatForAiPrompt(), 12000);
                $eventPlanningSource = 'planned_event';

                $guessedStart = $this->guessStartNumberFromMedia($media);
                if ($guessedStart !== null) {
                    $reference = $this->resolveStarterReferenceImage($plannedEvent, $guessedStart);
                    $starterReferenceDataUrl = $reference['data_url'] ?? null;
                    $starterReferenceStartNumber = $reference['start_number'] ?? null;
                }
            }
        }

        $scheduleSlotLabel = null;
        if ($plannedEvent && $media->capture_time) {
            $slotMatch = app(ScheduleSlotMatcher::class)->matchMediaToSchedule($media, $plannedEvent);
            if (is_array($slotMatch)) {
                $slotTrim = trim((string) ($slotMatch['label'] ?? ''));
                $scheduleSlotLabel = $slotTrim !== '' ? $slotTrim : null;
            }
        }

        return [
            'news_title' => $news?->title,
            'subheadline' => $news?->subheadline,
            'location_label' => $news?->location_label,
            'planned_event_venue_line' => $plannedEvent?->venueAddressLineForPrompt(),
            'caption_venue_kurz' => MediaCaptionLocationDateTail::formatVenueLineOnly($news, $media),
            'caption_ort_datum_schlusszeile' => MediaCaptionLocationDateTail::formatTail($news, $media),
            'capture_date' => $media->capture_time?->format('d.m.Y'),
            'teaser' => $news?->teaser ? Str::limit(strip_tags($news->teaser), 600) : null,
            'body' => $news?->body ? Str::limit(strip_tags($news->body), 1200) : null,
            'author_credit' => $news?->author_credit,
            'event_planning_context' => $eventPlanningContext,
            'event_planning_source' => $eventPlanningSource,
            'event_name' => $plannedEvent?->name,
            'schedule_slot_label' => $scheduleSlotLabel,
            'starter_reference_image_data_url' => $starterReferenceDataUrl,
            'starter_reference_start_number' => $starterReferenceStartNumber,
        ];
    }

    /**
     * @return array{data_url: string, start_number: int}|null
     */
    private function resolveStarterReferenceImage(PlannedEvent $plannedEvent, int $startNumber): ?array
    {
        if ($startNumber <= 0) {
            return null;
        }

        $teams = $plannedEvent->relationLoaded('teams')
            ? $plannedEvent->teams
            : $plannedEvent->teams()->get();

        foreach ($teams as $team) {
            if (! $team instanceof PlannedEventTeam) {
                continue;
            }
            if ($this->extractStartNumberFromTeamName((string) $team->name) !== $startNumber) {
                continue;
            }
            $path = trim((string) ($team->reference_image_path ?? ''));
            if ($path === '') {
                return null;
            }
            $dataUrl = PlannedEventTeamReferenceImageLocator::dataUrl($path);
            if ($dataUrl === null) {
                return null;
            }

            return [
                'data_url' => $dataUrl,
                'start_number' => $startNumber,
            ];
        }

        return null;
    }

    private function extractStartNumberFromTeamName(string $teamName): ?int
    {
        if (preg_match('/Startnr\.?\s*(\d{1,4})/iu', $teamName, $match) !== 1) {
            return null;
        }

        $nr = (int) $match[1];

        return $nr > 0 ? $nr : null;
    }

    private function guessStartNumberFromMedia(NewsItemMedia $media): ?int
    {
        $chunks = [
            (string) ($media->caption ?? ''),
            (string) ($media->image_title ?? ''),
        ];
        if (is_array($media->media_keywords)) {
            $chunks[] = implode(' ', array_map('strval', $media->media_keywords));
        }

        $text = implode("\n", $chunks);
        if ($text === '') {
            return null;
        }

        $sanitizer = app(RaceStartNumberSanitizer::class);

        if (preg_match_all('/\(#\s*(\d{1,4})\)/u', $text, $captionMatches) > 0) {
            foreach ($captionMatches[1] as $raw) {
                $nr = (int) $raw;
                if ($nr > 0 && ! $sanitizer->shouldIgnoreAsMarshalDisplay($nr, $text)) {
                    return $nr;
                }
            }
        }
        if (preg_match_all('/\#\s*(\d{1,4})/u', $text, $hashMatches) > 0) {
            foreach ($hashMatches[1] as $raw) {
                $nr = (int) $raw;
                if ($nr > 0 && ! $sanitizer->shouldIgnoreAsMarshalDisplay($nr, $text)) {
                    return $nr;
                }
            }
        }
        if (preg_match('/(?:Startnummer|Startnr\.?)\s*#?\s*(\d{1,4})/iu', $text, $labelMatch) === 1) {
            $nr = (int) $labelMatch[1];
            if ($nr > 0 && ! $sanitizer->shouldIgnoreAsMarshalDisplay($nr, $text)) {
                return $nr;
            }
        }

        return null;
    }

    protected function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
Du bist eine Bildredaktion für eine deutsche Nachrichtenagentur (ähnlich dpa/WDR).

Aufgabe:
- Analysiere ein Foto im Nachrichtenkontext.
- Liefere ausschließlich ein JSON-Objekt mit den Feldern:
  {
    "image_title": string,
    "photographer": string|null,
    "caption": string,
    "foreground_subject": string|null,
    "background_subject": string|null,
    "keywords": [string],
    "description": string|null,
    "detected_start_number": number|null,
    "detected_car_numbers": [number],
    "number_readability": "high"|"medium"|"low"|null,
    "primary_car_color": string|null,
    "livery_cues": [string],
    "detected_box_number": number|null,
    "pit_lane_or_garage_context": boolean,
    "confidence": { "caption": number, "location": number },
    "needs_review": [string]
  }

Stil- und Inhaltsregeln:
- Neutraler, sachlicher journalistischer Stil.
- Keine Spekulation: Schreib nichts, was nicht mit hoher Sicherheit aus Bild + Kontext hervorgeht.
- Wenn etwas unklar ist (z. B. genauer Ort, Namen, Funktion), trage eine Erklärung in needs_review ein (z. B. "Ort unsicher", "Personen nicht eindeutig identifizierbar").
- Caption-Schema auf Deutsch (Redaktionsüblich: zuerst das Motiv, Schluss mit Ort und Datum):
  "<Beschreibung>. <Schlusszeile>"
  - Zuerst sachliche Bildbeschreibung (Wer/Was/Kontext), dann ein Punkt, danach die Schlusszeile.
  - Wenn im User-Kontext „Vorgeschriebene Schlusszeile für die Bildunterschrift“ steht, muss die Caption exakt damit enden (dieselbe Zeichenfolge, kein zweites Mal im Satz). Das Datum darin entspricht der Aufnahmezeit (capture_date).
  - Ohne diese Schlusszeile (kein capture_date im Kontext): Caption ohne erfundenes Datum; Schlusszeile dann weglassen.
  - Kein Schema „Ort, Datum: …“ am Satzanfang. Keine Dopplung von Ort/Datum (kein „Köln …: Köln …:“).
  - Die vorgegebene Schlusszeile (Ort + Datum) kommt aus der geplanten Veranstaltung (Stadt + Kurz-Ort) und der Aufnahmezeit; ersetze sie nicht durch die IPTC-Stadt am Einzelbild. Ohne Veranstaltungsbezug: Ort/Region aus Meldungskontext, sonst allgemein (z. B. „Region Köln“).
  - Veranstaltungsadresse im Kontext (Straße, PLZ, Bundesland) dient IPTC/Metadaten: diese Zeile nicht wörtlich in die Caption übernehmen. Ortsbezug in der Schlusszeile ausschließlich als Kurz-Ort („Veranstaltungsort für Bildunterschrift“ bzw. vorgeschriebene Schlusszeile ohne Straße/PLZ).
  - WICHTIG: Niemals System-/Upload-/heutiges Datum erfinden oder verwenden.
  - Datum in der Caption ist nur erlaubt, wenn eine Aufnahmezeit (capture_date) im Kontext angegeben ist.
  - Ohne capture_date die Caption ohne Datum formulieren (dann auch keinen Datums-Block am Schluss erfinden). Keine postalische Adresse als Ersatz für eine fehlende Datums-Schlusszeile; bei fehlender Aufnahmezeit ggf. needs_review mit „Aufnahmezeit fehlt“.
- Mehrere Bildebenen (z. B. Künstler mit Effekten, Publikum, zweites Fahrzeug): in der **caption** in natürlichem, konkretem Deutsch beschreiben, was sichtbar ist (z. B. „Ben Zucker mit Flammeneffekten, weiter hinten das Publikum in der Halle“). Keine starren Formulierungen wie „Im Vordergrund:“ oder „Im Hintergrund:“.
- foreground_subject und background_subject: immer null (nicht mehr für die Caption nutzen; alles in caption schreiben).
- image_title: Sehr kurz (max. 90 Zeichen), prägnante Überschrift zum Bild.
- photographer: Falls im Bild oder Kontext erkennbar, sonst null.
- keywords: Liste mit maximal 18 aussagekräftigen Schlagwörtern, keine Stoppwörter oder Füllwörter.
- Bei Motorsport-Motiven: Startnummern in der **caption** ausschließlich als Klammer-Format „(#3)“ am Fahrzeugbezug, nicht als „Startnummer 3“ oder „mit Startnummer 3“. In **keywords** weiterhin z. B. „#3“.
- **Marshal-/LED-Anzeige (WICHTIG):** Ziffern an Windschutzscheibe/Frontscheibe (Streckenposten, z. B. „058“) sind **keine** Renn-Startnummern – **nicht** in detected_start_number oder detected_car_numbers. In caption, image_title und description **nicht erwähnen** (weder Zahl noch „Marshal-Anzeige“, „LED an der Scheibe“ o. Ä.); nur das eigentliche Rennmotiv beschreiben.
- detected_start_number: Nur die **offizielle Renn-Startnummer** am Fahrzeugkörper (Tür, Flanke, Kotflügel, Heck – große Aufkleber/Lackierung). Wenn nur eine Marshal-/LED-Scheibenanzeige sichtbar ist: **null**. Sonst nur Zahl (z. B. 3), nie führende Nullen aus Scheibendisplays. Unterscheide GT3 und GT4 nur, wenn am Karosserie erkennbar – sonst in der caption nicht spekulieren.
- detected_car_numbers: Alle sichtbaren **Renn-**Startnummern am Karosserie (vorn nach hinten), z. B. [911, 3] – **ohne** Marshal-/LED-Scheibenziffern.
- number_readability: "high" nur wenn die Startnummer am Fahrzeugkörper (Tür/Kotflügel/Heck) eindeutig lesbar ist. Bei Marshal-/LED-Scheibe, verdeckter Nummer oder Vermutung: null. "medium"/"low" nur bei tatsächlich sichtbarer Karosserie-Nummer.
- primary_car_color: Dominante Grundfarbe des Hauptfahrzeugs (z. B. "gelb", "schwarz", "gelb-grün"), sonst null.
- livery_cues: 3-8 kurze Merkmale für Fahrzeug-Erkennung auch **ohne** lesbare Startnummer: Farbschema (z. B. orange-schwarz), Stoßstangen-/Frontsplitter-Aufkleber (z. B. PROsport, H&R, MATECRA), Kotflügel-Sponsoren, Fahrzeugklasse (GT3/GT4) nur wenn am Karosserie erkennbar.
- detected_box_number: Sichtbare Box/Garage-Nummer nur dann, wenn tatsächlich Boxen-/Pitlane-Kontext erkennbar ist, sonst null.
- pit_lane_or_garage_context: true nur bei eindeutigem Boxengassen-/Garage-Kontext, sonst false.
- description: Optional, maximal 1 Satz, zusammenfassende Beschreibung (kein Du/Sie, kein Kommentar).

WICHTIG:
- Antworte ausschließlich mit gültigem JSON, ohne zusätzliche Erklärungen, ohne Markdown, ohne Kommentare.
- Verwende bei needs_review nur kurze Stichworte (z. B. "Ort unsicher", "Personen nicht identifizierbar").
PROMPT;
    }

    protected function buildUserPrompt(array $context): string
    {
        $parts = [];
        if (! empty($context['news_title'])) {
            $parts[] = 'Titel der Meldung: '.$context['news_title'];
        }
        if (! empty($context['subheadline'])) {
            $parts[] = 'Dachzeile/Unterzeile: '.$context['subheadline'];
        }
        if (! empty($context['location_label'])) {
            $parts[] = 'Ort/Region: '.$context['location_label'];
        }
        if (! empty($context['planned_event_venue_line'])) {
            $parts[] = 'Veranstaltungsadresse (nur für IPTC/Metadaten, nicht wörtlich in die Caption): '.$context['planned_event_venue_line'];
        }
        if (! empty($context['caption_venue_kurz'])) {
            $parts[] = 'Veranstaltungsort für Bildunterschrift (Kurzform, ohne Straße/PLZ; für Schlusszeile mit Datum): '.$context['caption_venue_kurz'];
        }
        if (! empty($context['capture_date'])) {
            $parts[] = 'Aufnahmezeit (maßgeblich für Caption-Datum): '.$context['capture_date'];
        } elseif (! empty($context['planned_event_venue_line']) || ! empty($context['caption_venue_kurz'])) {
            $parts[] = 'Hinweis: Keine Aufnahmezeit im System – kein Datum in der Caption; keine Straßenadresse als Schlusszeile verwenden.';
        }
        if (! empty($context['caption_ort_datum_schlusszeile'])) {
            $parts[] = 'Vorgeschriebene Schlusszeile für die Bildunterschrift (exakt einmal am Satzende, nach einem Punkt): '.$context['caption_ort_datum_schlusszeile'];
        }
        if (! empty($context['author_credit'])) {
            $parts[] = 'Autor-Credit der Meldung: '.$context['author_credit'];
        }
        if (! empty($context['teaser'])) {
            $parts[] = 'Teasertext: '.$context['teaser'];
        }
        if (! empty($context['body'])) {
            $parts[] = 'Meldungstext (gekürzt): '.$context['body'];
        }
        if (! empty($context['event_planning_context'])) {
            $parts[] = 'Geplante Veranstaltung (Eventplanung; Quelle: '
                .($context['event_planning_source'] ?? 'n/a')."):\n".$context['event_planning_context'];
        }
        if (! empty($context['schedule_slot_label'])) {
            $parts[] = 'Programmpunkt laut hinterlegtem Zeitplan (Aufnahmezeit; in die Bildunterschrift aufnehmen, wenn er zum sichtbaren Motiv passt — mit dem Veranstaltungsnamen sinnvoll verbinden, ohne wörtliche Dopplungen): '
                .$context['schedule_slot_label'];
        }
        if (($context['event_planning_source'] ?? null) === 'planned_event') {
            $parts[] = 'Motorsport (24h/Stint): Marshal-/LED-Ziffern an der Scheibe ignorieren (nicht erkennen, nicht in die Bildunterschrift schreiben). Ohne lesbare Karosserie-Nummer: detected_start_number null, dafür livery_cues mit Lackierung und Stoßstangen-Sponsoren füllen – Zuordnung erfolgt per ADAC-Referenzfoto.';
        }

        $ctxText = implode("\n", $parts);

        $intro = "Nutze folgenden Kontext zur Meldung und das angehängte Bild, um das JSON zu erzeugen.\n".$ctxText;

        $hint = trim((string) ($context['refinement_hint'] ?? ''));
        if ($hint === '') {
            return $intro;
        }

        $exCap = (string) ($context['refinement_existing_caption'] ?? '');
        $exKw = (string) ($context['refinement_existing_keywords'] ?? '');

        return $intro."\n\n---\nSPEZIALMODUS NACHREDAKTION (Redaktion):\n"
            ."Folgende Angaben stammen aus der Meldungs- und Veranstaltungsplanung (nur übernehmen, wenn sie zum sichtbaren Bildinhalt passen; sonst needs_review mit kurzem Grund wie z.B. \"Motivangabe passt nicht sicher zum Bild\"):\n"
            .$hint."\n\n"
            .'Aktuelle Bildunterschrift (überarbeiten: Motivangabe sachlich einbinden, z.B. namentliche Nennung statt generischer Begriffe wenn zutreffend; neutraler Agenturstil. '
            ."Form strikt: zuerst die Bildbeschreibung, dann ein Punkt, dann Ort und Datum genau einmal am Ende wie im Systemprompt – kein „Ort, Datum:“ am Anfang, keine doppelte Orts-/Datumszeile. Vorhandene Dopplungen entfernen.):\n"
            .$exCap."\n\n"
            ."Aktuelle Schlagwörter (kommagetrennt oder leer; dürfen ersetzt oder ergänzt werden, max. 18 sinnvolle Begriffe in keywords im JSON):\n"
            .$exKw."\n\n"
            .'Gib dasselbe JSON-Schema zurück wie im Systemprompt. Setze photographer immer auf null (Urheber wird bei dieser Nachbearbeitung nicht geändert). '
            ."caption und keywords vollständig und redaktionsfähig liefern; image_title knapp anpassen, falls die Unterschrift sich stark ändert.\n";
    }

    private function toNullableInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^\s*\d+\s*$/u', $value) === 1) {
            return (int) trim($value);
        }

        return null;
    }

    private function toNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * @return list<int>
     */
    private function toIntList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            $num = null;
            if (is_int($item)) {
                $num = $item;
            } elseif (is_string($item) && preg_match('/^\s*\d+\s*$/u', $item) === 1) {
                $num = (int) trim($item);
            }
            if ($num !== null && $num > 0 && ! in_array($num, $out, true)) {
                $out[] = $num;
            }
        }

        return $out;
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
            if (! is_string($item)) {
                continue;
            }
            $clean = trim($item);
            if ($clean === '' || in_array($clean, $out, true)) {
                continue;
            }
            $out[] = $clean;
        }

        return $out;
    }
}
