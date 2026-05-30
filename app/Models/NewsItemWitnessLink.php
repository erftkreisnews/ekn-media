<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsItemWitnessLink extends Model
{
    protected $fillable = [
        'news_item_id',
        'created_by_user_id',
        'token_hash',
        'label',
        'expires_at',
        'revoked_at',
        'max_uploads',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'max_uploads' => 'integer',
    ];

    public function newsItem(): BelongsTo
    {
        return $this->belongsTo(NewsItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(NewsItemWitnessSubmission::class, 'news_item_witness_link_id');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function acceptsUploads(): bool
    {
        if ($this->isRevoked() || $this->isExpired()) {
            return false;
        }

        return $this->submissions()->count() < max(1, (int) $this->max_uploads);
    }

    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }
}
