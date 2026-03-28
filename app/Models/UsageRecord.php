<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageRecord extends Model
{
    protected $fillable = [
        'news_item_id',
        'organization_id',
        'billing_department',
        'billing_type',
        'product_id',
        'used_at',
        'images_count',
        'video_minutes',
        'price_per_image',
        'price_per_minute',
        'total_amount',
        'article_url',
        'usage_format',
        'usage_rights',
        'reference_code',
        'line_item_note',
        'auto_detected',
        'confirmed',
        'created_by',
        'invoice_id',
    ];

    protected $casts = [
        'used_at' => 'date',
        'images_count' => 'integer',
        'video_minutes' => 'decimal:2',
        'price_per_image' => 'decimal:2',
        'price_per_minute' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'auto_detected' => 'boolean',
        'confirmed' => 'boolean',
    ];

    public function newsItem(): BelongsTo
    {
        return $this->belongsTo(NewsItem::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
