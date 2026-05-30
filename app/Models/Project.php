<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'brand_id',
        'project_type_id',
        'title',
        'slug',
        'description',
        'starts_at',
        'ends_at',
        'status',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function scopeForBrand(Builder $query, ?int $brandId): Builder
    {
        if ($brandId === null) {
            return $query;
        }

        return $query->where('brand_id', $brandId);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function projectType(): BelongsTo
    {
        return $this->belongsTo(ProjectType::class);
    }

    public function assetAssignments(): HasMany
    {
        return $this->hasMany(AssetChannelAssignment::class);
    }
}
