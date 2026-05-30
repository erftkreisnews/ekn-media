<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetChannelAssignment extends Model
{
    protected $fillable = [
        'media_asset_id',
        'brand_id',
        'channel_id',
        'project_id',
        'visibility',
        'publish_state',
    ];

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(NewsItemMedia::class, 'media_asset_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
