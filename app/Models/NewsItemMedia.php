<?php

namespace App\Models;

use App\Services\MediaStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class NewsItemMedia extends Model
{
    public const REDACTION_PENDING = 'pending';

    public const REDACTION_DONE = 'done';

    public const REDACTION_FAILED = 'failed';

    public const REDACTION_DISABLED = 'disabled';

    protected $fillable = [
        'news_item_id',
        'type',
        'path',
        'preview_path',
        'original_path',
        'redacted_path',
        'original_name',
        'caption',
        'image_title',
        'photographer',
        'media_keywords',
        'description',
        'sort_order',
        'is_visible',
        'versand',
        'is_teaser',
        'is_unkentlich',
        'quality_status',
        'quality_notes',
        'width',
        'height',
        'fps',
        'bitrate_bps',
        'codec',
        'field_order',
        'duration_s',
        'ai_status',
        'ai_started_at',
        'ai_finished_at',
        'ai_suggested_at',
        'ai_model',
        'ai_payload',
        'ai_error',
        'ai_attempts',
        'ai_last_error',
        'redaction_status',
        'redaction_method',
        'redaction_boxes',
        'redaction_error',
        'redacted_at',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
        'versand' => 'boolean',
        'is_teaser' => 'boolean',
        'is_unkentlich' => 'boolean',
        'fps' => 'decimal:4',
        'duration_s' => 'decimal:4',
        'ai_started_at' => 'datetime',
        'ai_finished_at' => 'datetime',
        'ai_suggested_at' => 'datetime',
        'ai_payload' => 'array',
        'redaction_boxes' => 'array',
        'redacted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (NewsItemMedia $media): void {
            $media->purgeStoredFiles(app(MediaStorage::class));
        });
    }

    /**
     * Löscht alle zu diesem Datensatz gehörenden Dateien auf der aktiven Disk inkl. Fallback (S3 + lokal).
     * Wird bei Einzellöschung per Eloquent ausgeführt; bei Löschung der gesamten Meldung zusätzlich
     * in NewsItem::deleting (DB-CASCADE feuert hier kein Model-Event).
     */
    public function purgeStoredFiles(MediaStorage $mediaStorage): void
    {
        $paths = array_filter([
            $this->path,
            $this->preview_path,
            $this->original_path,
            $this->redacted_path,
        ], fn ($p) => is_string($p) && $p !== '');

        if ($this->path && $this->isImage()) {
            $paths[] = dirname($this->path).'/thumb/'.pathinfo($this->path, PATHINFO_FILENAME).'.webp';
        }

        if ($this->isImage() && $this->news_item_id) {
            $paths[] = 'news-media/'.$this->news_item_id.'/image/preview_'.$this->id.'.jpg';
        }

        foreach (array_unique($paths) as $p) {
            if ($p !== '') {
                $mediaStorage->delete($p);
            }
        }
    }

    public function newsItem(): BelongsTo
    {
        return $this->belongsTo(NewsItem::class);
    }

    public function isImage(): bool
    {
        return $this->type === 'image';
    }

    public function isAudio(): bool
    {
        return $this->type === 'audio';
    }

    public function isVideo(): bool
    {
        return $this->type === 'video';
    }

    /**
     * Relativer Speicherpfad für den echten Medienpaket-Download (signierte Delivery-URL).
     * Verbindlich die Originaldatei (Spalte path), niemals Preview/Thumb/Poster/redacted.
     * Fallback nur, wenn original_path existiert (Backup der Redaction-Pipeline) und path fehlt.
     */
    public function resolveDeliveryDownloadRelativePath(): ?string
    {
        $storage = app(MediaStorage::class);

        if ($this->path && $storage->exists($this->path)) {
            return $this->path;
        }

        if ($this->original_path && $storage->exists($this->original_path)) {
            Log::info('delivery.download.fallback_original_path_column', [
                'media_id' => $this->id,
            ]);

            return $this->original_path;
        }

        return null;
    }

    /**
     * Pfad für öffentliche Auslieferung (bei Bildern: redigierte Version falls vorhanden, sonst null bei pending/failed).
     */
    public function getPublicPathAttribute(): ?string
    {
        if ($this->type !== 'image') {
            return $this->path;
        }

        if ($this->redaction_status === null) {
            return $this->path;
        }

        if ($this->redaction_status === self::REDACTION_DONE && $this->redacted_path) {
            if (app(MediaStorage::class)->exists($this->redacted_path)) {
                return $this->redacted_path;
            }
        }

        if ($this->redaction_status === self::REDACTION_PENDING || $this->redaction_status === self::REDACTION_FAILED) {
            return null;
        }

        if ($this->redaction_status === self::REDACTION_DISABLED) {
            return $this->path;
        }

        return $this->path;
    }

    /**
     * Öffentliche URL (für public_path).
     */
    public function getPublicUrlAttribute(): ?string
    {
        $path = $this->public_path;
        if ($path === null) {
            return null;
        }

        return app(MediaStorage::class)->url($path);
    }

    /**
     * Ob das Medium unbedenklich öffentlich ausgeliefert werden darf.
     */
    public function isSafeForPublic(): bool
    {
        return $this->public_path !== null;
    }

    /**
     * URL der Hauptdatei (path).
     */
    public function getUrlAttribute(): string
    {
        return app(MediaStorage::class)->url($this->path);
    }

    /**
     * URL der Vorschau (preview_path).
     */
    public function getPreviewUrlAttribute(): ?string
    {
        if (! $this->preview_path) {
            return null;
        }

        return app(MediaStorage::class)->url($this->preview_path);
    }

    /** Thumbnail-URL (für Teaser/Listen); nutzt Vorschau oder Hauptdatei. */
    public function getThumbUrlAttribute(): ?string
    {
        return $this->preview_url ?? ($this->path ? app(MediaStorage::class)->url($this->path) : null);
    }

    /**
     * Dateigröße in KB für die Admin-Listen (Bilder).
     *
     * Nach S3-Umstellung existiert keine DB-Spalte mehr zuverlässig; daher zur Laufzeit via Storage.size().
     */
    public function getFileSizeKbAttribute(): int
    {
        $storage = app(MediaStorage::class);

        $bytes = 0;
        $path = is_string($this->path) ? $this->path : '';
        $originalPath = is_string($this->original_path) ? $this->original_path : '';

        if ($path !== '') {
            $bytes = $storage->size($path);
        }

        if ($bytes <= 0 && $originalPath !== '') {
            $bytes = $storage->size($originalPath);
        }

        return (int) round($bytes / 1024);
    }

    /**
     * Anzeigename (z. B. für E-Mail-Teaser).
     */
    public function getDisplayNameAttribute(): string
    {
        $title = trim((string) ($this->image_title ?? ''));
        if ($title !== '') {
            return $title;
        }

        return trim((string) ($this->original_name ?? '')) ?: '';
    }

    /**
     * Lesbarer Name für Versand-Aktivität (Download), inkl. Fallback auf Dateinamen aus dem Speicherpfad.
     *
     * Ohne Fallback wäre die Zeile leer, wenn weder image_title noch original_name gesetzt sind.
     */
    public function getDeliveryActivityLabelAttribute(): string
    {
        $dn = trim($this->display_name);
        if ($dn !== '') {
            return $dn;
        }

        $orig = trim((string) ($this->original_name ?? ''));
        if ($orig !== '') {
            return $orig;
        }

        $path = (string) ($this->path ?? '');
        if ($path !== '') {
            $base = basename($path);
            if ($base !== '' && $base !== '.') {
                return $base;
            }
        }

        $typeLabel = match ($this->type) {
            'image' => 'Bild',
            'video' => 'Video',
            'audio' => 'Audio',
            default => 'Medium',
        };

        return $typeLabel.' #'.$this->id;
    }

    /**
     * Technischer Dateiname (Originalname oder gespeicherte Datei), für Aktivitäts-/Download-Anzeige.
     * Kann von der Titelzeile (image_title) abweichen.
     */
    public function getDeliveryActivityStoredFileNameAttribute(): ?string
    {
        $orig = trim((string) ($this->original_name ?? ''));
        if ($orig !== '') {
            return $orig;
        }

        $path = (string) ($this->path ?? '');
        if ($path === '') {
            return null;
        }

        $base = basename($path);

        return ($base !== '' && $base !== '.') ? $base : null;
    }

    /**
     * Kurzes Präfix für Aktivitätslisten (Medientyp).
     */
    public function getDeliveryActivityTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'image' => 'Bild',
            'video' => 'Video',
            'audio' => 'Audio',
            default => 'Medium',
        };
    }

    /**
     * Kurzbeschreibung der Video-Metadaten, z. B. "1920x1080p | 5.2 Mbit/s | 25 fps".
     *
     * Gibt nur für Videos und nur bei vorhandenen Metadaten einen String zurück, sonst null.
     */
    public function getVideoMetazeileAttribute(): ?string
    {
        if (! $this->isVideo()) {
            return null;
        }

        $parts = [];

        if ($this->width && $this->height) {
            $scan = null;
            if ($this->field_order) {
                $fo = mb_strtolower($this->field_order);
                if (str_contains($fo, 'progressive')) {
                    $scan = 'p';
                } elseif (str_contains($fo, 'interlaced') || str_contains($fo, 'tt') || str_contains($fo, 'bb')) {
                    $scan = 'i';
                }
            }
            $parts[] = $this->width.'x'.$this->height.($scan ? $scan : '');
        }

        if ($this->bitrate_bps) {
            $mbps = $this->bitrate_bps / 1_000_000;
            $parts[] = rtrim(rtrim(number_format($mbps, 1, ',', ''), '0'), ',').' Mbit/s';
        }

        if ($this->fps) {
            $parts[] = rtrim(rtrim(number_format((float) $this->fps, 2, ',', ''), '0'), ',').' fps';
        }

        if ($parts === []) {
            return null;
        }

        return implode(' | ', $parts);
    }

    /**
     * Kurzbeschreibung der Audio-Metadaten, z. B. "AAC | 128 kbit/s | 00:30 min".
     *
     * Gibt nur für Audios und nur bei vorhandenen Metadaten einen String zurück, sonst null.
     */
    public function getAudioMetazeileAttribute(): ?string
    {
        if (! $this->isAudio()) {
            return null;
        }

        $parts = [];

        if ($this->codec) {
            $parts[] = (string) $this->codec;
        }

        if ($this->bitrate_bps) {
            $kbit = $this->bitrate_bps / 1000;
            $parts[] = rtrim(rtrim(number_format($kbit, 0, ',', ''), '0'), ',').' kbit/s';
        }

        if ($this->duration_s) {
            $totalSeconds = (int) round((float) $this->duration_s);
            $minutes = intdiv($totalSeconds, 60);
            $seconds = $totalSeconds % 60;
            $parts[] = sprintf('%02d:%02d min', $minutes, $seconds);
        }

        if ($parts === []) {
            return null;
        }

        return implode(' | ', $parts);
    }

    public function markAiRunning(): void
    {
        $this->update([
            'ai_status' => 'running',
            'ai_started_at' => now(),
        ]);
    }

    public function markAiQueued(): void
    {
        $this->update([
            'ai_status' => 'queued',
            'ai_started_at' => null,
            'ai_finished_at' => null,
            'ai_error' => null,
            'ai_last_error' => null,
            'ai_attempts' => 0,
        ]);
    }

    /**
     * @param  array{ai_payload?: mixed, ai_model?: string, ai_suggested_at?: \DateTimeInterface}  $data
     */
    public function markAiDone(array $data): void
    {
        $this->update(array_merge([
            'ai_status' => 'done',
            'ai_finished_at' => now(),
            'ai_error' => null,
            'ai_last_error' => null,
        ], $data));
    }

    public function markAiError(string $message): void
    {
        $this->update([
            'ai_status' => 'error',
            'ai_finished_at' => now(),
            'ai_error' => $message,
            'ai_last_error' => $message,
            'ai_attempts' => ($this->ai_attempts ?? 0) + 1,
        ]);
    }
}
