<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryRunItem extends Model
{
    protected $table = 'delivery_run_items';

    protected $fillable = [
        'delivery_run_id',
        'news_item_media_id',
        'filename',
        'status',
        'message',
    ];

    public function deliveryRun(): BelongsTo
    {
        return $this->belongsTo(DeliveryRun::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(NewsItemMedia::class, 'news_item_media_id');
    }
}
