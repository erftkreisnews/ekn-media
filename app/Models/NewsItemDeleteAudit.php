<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsItemDeleteAudit extends Model
{
    protected $fillable = [
        'news_item_id',
        'user_id',
        'title',
        'slug',
        'status',
        'published_at',
        'deleted_at',
        'request_ip',
        'user_agent',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
