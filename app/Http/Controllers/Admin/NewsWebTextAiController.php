<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\MediaAiRateLimitException;
use App\Http\Controllers\Controller;
use App\Http\Requests\PolishNewsWebTextRequest;
use App\Services\MediaAi\NewsWebTextPolisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class NewsWebTextAiController extends Controller
{
    public function polish(PolishNewsWebTextRequest $request, NewsWebTextPolisher $polisher): JsonResponse
    {
        if (empty(config('media_ai.api_key'))) {
            return response()->json([
                'message' => 'OPENAI_API_KEY ist nicht konfiguriert.',
            ], 503);
        }

        $userId = auth()->id();
        $limit = (int) config('news_ai.rate_limit_per_minute', 20);
        if ($limit > 0 && $userId) {
            $key = 'news_ai:web_text:user:'.$userId;
            $count = cache()->add($key, 1, 60) ? 1 : cache()->increment($key);
            if ($count > $limit) {
                return response()->json([
                    'message' => 'Zu viele Anfragen. Bitte kurz warten.',
                ], 429);
            }
        }

        try {
            $text = $polisher->polish($request->validated()['text']);

            return response()->json(['text' => $text]);
        } catch (MediaAiRateLimitException $e) {
            return response()->json(['message' => $e->getMessage()], 429);
        } catch (\Throwable $e) {
            Log::warning('NewsWebTextAi: polish failed', [
                'message' => $e->getMessage(),
                'user_id' => $userId,
            ]);

            return response()->json([
                'message' => 'KI-Anfrage fehlgeschlagen: '.$e->getMessage(),
            ], 502);
        }
    }
}
