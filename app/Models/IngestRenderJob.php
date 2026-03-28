<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngestRenderJob extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RENDERING = 'rendering';

    public const STATUS_UPLOADING = 'uploading';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'news_item_id',
        'ingest_file_ids',
        'status',
        'local_output_path',
        'final_news_item_media_id',
        'error_message',
    ];

    protected $casts = [
        'ingest_file_ids' => 'array',
    ];

    public function newsItem(): BelongsTo
    {
        return $this->belongsTo(NewsItem::class, 'news_item_id');
    }

    public function finalMedia(): BelongsTo
    {
        return $this->belongsTo(NewsItemMedia::class, 'final_news_item_media_id');
    }
}
