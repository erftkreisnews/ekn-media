<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $table = 'settings';

    public const NEWS_WEB_TEXT_AI_SYSTEM_PROMPT = 'news_web_text_ai_system_prompt';

    /** 1/0 – globale KI-Vorgaben für Sport-/Event-Fotos aktiv */
    public const MEDIA_AI_EVENT_PLANNING_ENABLED = 'media_ai_event_planning_enabled';

    /** Freitext: Strecken, Events, Begriffe für Bild-KI */
    public const MEDIA_AI_EVENT_PLANNING_CONTEXT = 'media_ai_event_planning_context';

    public const WDR_NEWSROOM_VIDEO_PRICE_PER_MINUTE = 'wdr_newsroom_video_price_per_minute';

    public const WDR_NEWSROOM_IMAGE_FIRST_PRICE = 'wdr_newsroom_image_first_price';

    public const WDR_NEWSROOM_IMAGE_ADDITIONAL_PRICE = 'wdr_newsroom_image_additional_price';

    public const WDR_NEWSROOM_IMAGE_FROM_FIVE_PRICE = 'wdr_newsroom_image_from_five_price';

    public const WDR_NEWSROOM_AUDIO_PRICE_PER_MINUTE = 'wdr_newsroom_audio_price_per_minute';

    protected $fillable = [
        'key',
        'value',
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        $row = static::query()->where('key', $key)->first();

        return $row?->value ?? $default;
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }
}
