<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IngestSource extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'config_json',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'config_json' => 'array',
    ];

    public function batches(): HasMany
    {
        return $this->hasMany(IngestBatch::class, 'ingest_source_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(IngestFile::class, 'ingest_source_id');
    }
}
