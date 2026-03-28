<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngestFile extends Model
{
    public const STATUS_IMPORTED = 'imported';

    public const STATUS_VALIDATING = 'validating';

    public const STATUS_VALIDATED = 'validated';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_USED = 'used_in_render';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'ingest_source_id',
        'ingest_batch_id',
        'original_name',
        'relative_path',
        'absolute_path',
        'file_size',
        'content_hash',
        'mime',
        'duration_s',
        'width',
        'height',
        'fps',
        'codec',
        'status',
        'news_item_id',
        'selection_order',
        'final_news_item_media_id',
        'error_message',
        'ffprobe_json',
    ];

    protected $casts = [
        'duration_s' => 'decimal:4',
        'fps' => 'decimal:4',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(IngestSource::class, 'ingest_source_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(IngestBatch::class, 'ingest_batch_id');
    }

    public function newsItem(): BelongsTo
    {
        return $this->belongsTo(NewsItem::class, 'news_item_id');
    }

    public function finalMedia(): BelongsTo
    {
        return $this->belongsTo(NewsItemMedia::class, 'final_news_item_media_id');
    }
}
