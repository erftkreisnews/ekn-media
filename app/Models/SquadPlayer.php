<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SquadPlayer extends Model
{
    protected $fillable = [
        'squad_id',
        'shirt_number',
        'full_name',
        'position_label',
        'sort_order',
    ];

    public function squad(): BelongsTo
    {
        return $this->belongsTo(Squad::class);
    }
}
