<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsItemFieldAudit extends Model
{
    public const EVENT_CREATED = 'created';

    public const EVENT_UPDATED = 'updated';

    /** @var list<string> */
    public const TRACKED_FIELDS = [
        'status',
        'published_at',
        'embargo_at',
        'title',
        'slug',
    ];

    public $timestamps = false;

    protected $fillable = [
        'news_item_id',
        'user_id',
        'event',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function newsItem(): BelongsTo
    {
        return $this->belongsTo(NewsItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function labelForField(string $field): string
    {
        return match ($field) {
            'status' => 'Status',
            'published_at' => 'Veröffentlicht am',
            'embargo_at' => 'Sperrfrist bis',
            'title' => 'Titel',
            'slug' => 'URL-Slug',
            default => $field,
        };
    }

    public static function formatValueForDisplay(string $field, mixed $value): string
    {
        if ($value === null) {
            return '—';
        }
        if ($field === 'status') {
            $map = [
                'draft' => 'Entwurf',
                'review' => 'Review',
                'published' => 'ready',
                'archived' => 'Archiviert',
            ];

            return $map[(string) $value] ?? (string) $value;
        }
        if (in_array($field, ['published_at', 'embargo_at'], true)) {
            if ($value === '') {
                return '—';
            }
            try {
                return \Carbon\Carbon::parse((string) $value)->timezone(config('app.timezone'))->format('d.m.Y H:i');
            } catch (\Throwable) {
                return (string) $value;
            }
        }

        return (string) $value;
    }
}
