<?php

namespace App\Services\MediaAi;

use App\Exceptions\MediaAiRateLimitException;
use App\Models\NewsItemMedia;
use App\Services\MediaStorage;
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
     *   keywords:array,
     *   description:?string,
     *   confidence:array,
     *   needs_review:array
     * }
     */
    public function generate(NewsItemMedia $media): array
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

        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt = $this->buildUserPrompt($context);

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
                    'content' => [
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
                    ],
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
            ->timeout(config('media_ai.timeout', 10))
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
            'keywords' => array_values(array_filter($decoded['keywords'] ?? [], fn ($k) => is_string($k) && $k !== '')),
            'description' => $decoded['description'] ?? null,
            'confidence' => [
                'caption' => (float) Arr::get($decoded, 'confidence.caption', 0.0),
                'location' => (float) Arr::get($decoded, 'confidence.location', 0.0),
            ],
            'needs_review' => array_values(array_filter($decoded['needs_review'] ?? [], fn ($k) => is_string($k) && $k !== '')),
        ];

        return $result;
    }

    protected function ensurePreviewUrl(NewsItemMedia $media): string
    {
        $mediaStorage = app(MediaStorage::class);
        $disk = $mediaStorage->activeDisk();

        if ($media->preview_path && $mediaStorage->exists($media->preview_path)) {
            return $mediaStorage->url($media->preview_path);
        }

        $resolved = $mediaStorage->resolveReadableLocalPath($media->path);
        $sourcePath = $resolved['path'] ?? null;
        if (! is_string($sourcePath) || ! is_file($sourcePath) || ! is_readable($sourcePath)) {
            throw new \RuntimeException('Quelldatei für Preview nicht lesbar.');
        }

        $manager = new ImageManager(new Driver);
        $image = $manager->read($sourcePath)->orient();
        $image->scaleDown(1280, 1280);

        $previewRelPath = $mediaStorage->isStructuredNewsMediaPath($media->path)
            ? $mediaStorage->generateDerivedMediaPath(
                $media->newsItem,
                $media,
                pathinfo($media->original_name ?: basename($media->path), PATHINFO_FILENAME).'.jpg',
                'preview-ai'
            )
            : 'news-media/'.$media->news_item_id.'/image/preview_'.$media->id.'.jpg';
        $disk->put($previewRelPath, (string) $image->toJpeg(80), ['visibility' => 'public']);

        $media->preview_path = $previewRelPath;
        $media->save();

        $mediaStorage->cleanupResolvedPath($resolved);

        return $mediaStorage->url($previewRelPath);
    }

    protected function buildContext(NewsItemMedia $media): array
    {
        $news = $media->newsItem;

        return [
            'news_title' => $news?->title,
            'subheadline' => $news?->subheadline,
            'location_label' => $news?->location_label,
            'published_at' => optional($news?->published_at)->format('d.m.Y'),
            'teaser' => $news?->teaser ? Str::limit(strip_tags($news->teaser), 600) : null,
            'body' => $news?->body ? Str::limit(strip_tags($news->body), 1200) : null,
            'author_credit' => $news?->author_credit,
        ];
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
    "keywords": [string],
    "description": string|null,
    "confidence": { "caption": number, "location": number },
    "needs_review": [string]
  }

Stil- und Inhaltsregeln:
- Neutraler, sachlicher journalistischer Stil.
- Keine Spekulation: Schreib nichts, was nicht mit hoher Sicherheit aus Bild + Kontext hervorgeht.
- Wenn etwas unklar ist (z. B. genauer Ort, Namen, Funktion), trage eine Erklärung in needs_review ein (z. B. "Ort unsicher", "Personen nicht eindeutig identifizierbar").
- Caption-Schema auf Deutsch:
  "Ort (Bundesland), DD.MM.YYYY: <Beschreibung>."
  - Ort nach Möglichkeit aus Kontext (Location-Label der Meldung), sonst allgemeiner Ort (z. B. "Region Köln").
  - Datum aus Kontext (published_at), falls vorhanden, sonst geschätztes aktuelles Datum.
- image_title: Sehr kurz (max. 90 Zeichen), prägnante Überschrift zum Bild.
- photographer: Falls im Bild oder Kontext erkennbar, sonst null.
- keywords: Liste mit maximal 18 aussagekräftigen Schlagwörtern, keine Stoppwörter oder Füllwörter.
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
        if (! empty($context['published_at'])) {
            $parts[] = 'Veröffentlicht am: '.$context['published_at'];
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

        $ctxText = implode("\n", $parts);

        return "Nutze folgenden Kontext zur Meldung und das angehängte Bild, um das JSON zu erzeugen.\n".$ctxText;
    }
}
