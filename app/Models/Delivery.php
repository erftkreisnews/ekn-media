<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Delivery extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'news_item_id',
        'recipient_email',
        'token',
        'expires_at',
        'revoked_at',
        'first_opened_at',
        'last_access_at',
        'confirmed_at',
        'organization_id',
        'product_id',
        'allowed_organization_id',
        'self_reported_organization_name',
        'self_reported_product_name',
        'ip_hash',
        'ua_hash',
        'created_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'first_opened_at' => 'datetime',
        'last_access_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Delivery $delivery): void {
            if (empty($delivery->id)) {
                $delivery->id = (string) Str::uuid();
            }
            if (empty($delivery->token)) {
                $delivery->token = (string) Str::uuid();
            }
        });
    }

    public function newsItem(): BelongsTo
    {
        return $this->belongsTo(NewsItem::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function allowedOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'allowed_organization_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(DeliveryEvent::class)->orderBy('created_at');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isValid(): bool
    {
        return ! $this->isExpired() && ! $this->isRevoked();
    }
}
