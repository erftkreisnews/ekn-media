<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryRun extends Model
{
    protected $fillable = [
        'delivery_destination_id',
        'status',
        'message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function deliveryDestination(): BelongsTo
    {
        return $this->belongsTo(DeliveryDestination::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryRunItem::class, 'delivery_run_id');
    }
}
