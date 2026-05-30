<?php

namespace App\Services\MediaAi;

use App\Exceptions\MediaAiRateLimitException;
use App\Models\SiteSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class NewsWebTextPolisher
{
    public function polish(string $rawText): string
    {
        $apiKey = config('media_ai.api_key');
        if (empty($apiKey)) {
            throw new \RuntimeException('OPENAI_API_KEY ist nicht gesetzt.');
        }

        $system = $this->resolveSystemPrompt();
        $model = config('news_ai.text_model', 'gpt-4o-mini');
        $timeout = (int) config('news_ai.timeout', 60);
        $maxTokens = (int) config('news_ai.max_output_tokens', 4096);
        $temperature = (float) config('news_ai.temperature', 0.35);

        $headers = [
            'Authorization' => 'Bearer '.$apiKey,
        ];
        if ($org = config('media_ai.organization')) {
            $headers['OpenAI-Organization'] = $org;
        }

        $payload = [
            'model' => $model,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                [
                    'role' => 'user',
                    'content' => "Rohtext der Meldung (nur überarbeiten, Inhalt nicht erfinden):\n\n".$rawText,
                ],
            ],
        ];

        $response = Http::withHeaders($headers)
            ->timeout($timeout)
            ->post('https://api.openai.com/v1/chat/completions', $payload);

        if (! $response->successful()) {
            $status = $response->status();
            $body = Str::limit($response->body(), 500);
            Log::warning('NewsWebTextPolisher: OpenAI call failed', [
                'status' => $status,
                'body' => $body,
            ]);
            if ($status === 429) {
                throw new MediaAiRateLimitException('OpenAI Rate Limit (429). Bitte später erneut versuchen.');
            }
            throw new \RuntimeException('OpenAI API Fehler: '.$status.'. '.$body);
        }

        $data = $response->json();
        $content = Arr::get($data, 'choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new \RuntimeException('OpenAI Antwort ohne gültigen Text.');
        }

        return trim($content);
    }

    protected function resolveSystemPrompt(): string
    {
        $stored = '';
        if (Schema::hasTable('settings')) {
            $stored = trim((string) (SiteSetting::get(SiteSetting::NEWS_WEB_TEXT_AI_SYSTEM_PROMPT) ?? ''));
        }

        if ($stored !== '') {
            return $stored;
        }

        return (string) config('news_ai.default_system_prompt');
    }
}
