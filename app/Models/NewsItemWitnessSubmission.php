<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class NewsItemWitnessSubmission extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'news_item_witness_link_id',
        'news_item_id',
        'submitter_name',
        'submitter_email',
        'submitter_phone',
        'consent_terms',
        'consent_rights',
        'credit_anonymous',
        'witness_suggested_title',
        'consent_text_version',
        'consent_body_hash',
        'stored_disk',
        'stored_path',
        'original_filename',
        'mime',
        'size_bytes',
        'ip_address',
        'user_agent',
        'status',
        'news_item_media_id',
    ];

    protected $casts = [
        'consent_terms' => 'boolean',
        'consent_rights' => 'boolean',
        'credit_anonymous' => 'boolean',
        'size_bytes' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleting(function (NewsItemWitnessSubmission $submission): void {
            $submission->deleteStoredFile();
        });
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(NewsItemWitnessLink::class, 'news_item_witness_link_id');
    }

    public function newsItem(): BelongsTo
    {
        return $this->belongsTo(NewsItem::class);
    }

    public function importedMedia(): BelongsTo
    {
        return $this->belongsTo(NewsItemMedia::class, 'news_item_media_id');
    }

    public function deleteStoredFile(): void
    {
        $path = $this->stored_path;
        $disk = $this->stored_disk;
        if (is_string($path) && $path !== '' && is_string($disk) && $disk !== '') {
            Storage::disk($disk)->delete($path);
        }
    }
}
