<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IngestBatch extends Model
{
    protected $fillable = [
        'ingest_source_id',
        'reference_label',
        'scanned_at',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(IngestSource::class, 'ingest_source_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(IngestFile::class, 'ingest_batch_id');
    }
}
