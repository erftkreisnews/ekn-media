<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PresseportalOffice extends Model
{
    protected $fillable = [
        'office_id',
        'name',
        'notes',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'office_id' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
