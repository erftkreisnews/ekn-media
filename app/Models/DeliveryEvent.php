<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'delivery_id',
        'event_type',
        'media_id',
        'download_media_type',
        'download_media_label',
        'download_media_file_name',
        'organization_id',
        'product_id',
        'self_reported_organization_name',
        'self_reported_product_name',
        'ip_hash',
        'ua_hash',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(NewsItemMedia::class, 'media_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
