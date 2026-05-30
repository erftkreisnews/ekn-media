<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventSuggestion extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_IMPORTED = 'imported';

    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'source',
        'external_id',
        'title',
        'description',
        'info_url',
        'image_url',
        'starts_at',
        'ends_at',
        'venue_name',
        'venue_street',
        'venue_postal_code',
        'venue_city',
        'venue_state',
        'venue_country',
        'venue_country_code',
        'category',
        'status',
        'planned_event_id',
        'fetched_at',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'fetched_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function plannedEvent(): BelongsTo
    {
        return $this->belongsTo(PlannedEvent::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
