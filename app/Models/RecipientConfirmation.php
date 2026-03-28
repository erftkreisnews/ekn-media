<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipientConfirmation extends Model
{
    protected $fillable = [
        'email',
        'organization_id',
        'product_id',
        'self_reported_organization_name',
        'self_reported_product_name',
        'last_confirmed_at',
        'confirmed_until',
    ];

    protected $casts = [
        'last_confirmed_at' => 'datetime',
        'confirmed_until' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
