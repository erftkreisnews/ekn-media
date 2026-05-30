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

    public const STATUS_PREVIEW_GENERATING = 'preview_generating';

    public const STATUS_PREVIEW_READY = 'preview_ready';

    public const STATUS_RENDERING = 'rendering';

    public const STATUS_USED = 'used_in_render';

    public const STATUS_FAILED = 'failed';

    public const PREVIEW_STATUS_GENERATING = 'generating';

    public const PREVIEW_STATUS_READY = 'ready';

    protected $fillable = [
        'ingest_source_id',
        'ingest_batch_id',
        'original_name',
        'relative_path',
        'absolute_path',
        'thumb_path',
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
        'is_selected',
        'trim_in_seconds',
        'trim_out_seconds',
        'final_news_item_media_id',
        'error_message',
        'ffprobe_json',
        'preview_path',
        'preview_status',
        'preview_error_message',
    ];

    protected $casts = [
        'duration_s' => 'decimal:4',
        'fps' => 'decimal:4',
        'is_selected' => 'boolean',
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

    /** JPEG aus dem Ingest-Upload (Dateiname). */
    public function isIngestImageFile(): bool
    {
        $ext = strtolower((string) pathinfo((string) $this->original_name, PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'jpe'], true);
    }

    /**
     * Video-Rohclip (alle Ingest-Video-Endungen, nicht nur MP4).
     */
    public function isIngestVideoFile(): bool
    {
        if ($this->isIngestImageFile()) {
            return false;
        }

        $mime = strtolower((string) ($this->mime ?? ''));
        if (str_starts_with($mime, 'video/')) {
            return true;
        }

        $ext = strtolower((string) pathinfo((string) $this->original_name, PATHINFO_EXTENSION));

        return in_array($ext, ['mp4', 'mov', 'mxf', 'mkv', 'avi', 'mts', 'm2ts', 'webm'], true);
    }

    /**
     * Originaldatei direkt im Browser abspielbar (ohne FFmpeg-Kurzvorschau).
     */
    public function isBrowserPlayableOriginal(): bool
    {
        if (! $this->isIngestVideoFile()) {
            return false;
        }

        $mime = strtolower((string) ($this->mime ?? ''));
        if (str_starts_with($mime, 'video/mp4') || str_starts_with($mime, 'video/webm')) {
            return true;
        }

        $ext = strtolower((string) pathinfo((string) $this->original_name, PATHINFO_EXTENSION));

        return in_array($ext, ['mp4', 'webm'], true);
    }

    /**
     * Clips, für die eine FFmpeg-Kurzvorschau (MP4) erzeugt wird.
     */
    public function isIngestVideoCandidate(): bool
    {
        return $this->isIngestVideoFile();
    }

    /**
     * Lokales Standbild (Poster) für die Sichtung – unabhängig von preview_path.
     */
    public function hasIngestPoster(): bool
    {
        $p = $this->thumb_path ?? null;

        return is_string($p) && trim($p) !== '';
    }

    /**
     * FFmpeg-Kurzvorschau auf dem konfigurierten Preview-Disk (lokal) – nicht nur preview_path in DB.
     */
    public function hasIngestPreviewVideo(): bool
    {
        if (! $this->isIngestVideoCandidate()) {
            return false;
        }

        $p = $this->preview_path ?? null;
        if (! is_string($p) || $p === '') {
            return false;
        }

        try {
            return app(\App\Services\MediaStorage::class)->ingestPreviewDisk()->exists($p);
        } catch (\Throwable) {
            return false;
        }
    }
}
