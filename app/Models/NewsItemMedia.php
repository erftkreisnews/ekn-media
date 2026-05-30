<?php

namespace App\Models;

use App\Services\ImageExifDetailsReader;
use App\Services\ImageMetadataReader;
use App\Services\MediaStorage;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class NewsItemMedia extends Model
{
    public const REDACTION_PENDING = 'pending';

    public const REDACTION_DONE = 'done';

    public const REDACTION_FAILED = 'failed';

    public const REDACTION_DISABLED = 'disabled';

    protected $fillable = [
        'news_item_id',
        'source_video_media_id',
        'brand_id',
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
        'metadata_location',
        'metadata_recorded_at',
        'city',
        'state',
        'country',
        'country_code',
        'capture_time',
        'credit',
        'copyright',
        'source',
        'sort_order',
        'is_visible',
        'versand',
        'delivery_visible_for_organization_ids',
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
        'delivery_visible_for_organization_ids' => 'array',
        'redaction_boxes' => 'array',
        'redacted_at' => 'datetime',
        'capture_time' => 'datetime',
        'metadata_recorded_at' => 'date',
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
     * Bild aus Video-Standbild-Extraktion (keine Presse-Qualitätsprüfung wie bei Upload-Fotos).
     */
    public function isVideoDerivedStillImage(): bool
    {
        if (! $this->isImage()) {
            return false;
        }

        if (Schema::hasColumn($this->getTable(), 'source_video_media_id')
            && filled($this->source_video_media_id)) {
            return true;
        }

        // Legacy: ältere Standbilder nur über festen Caption-Text erkennbar.
        return trim((string) ($this->caption ?? '')) === 'Standbild aus Video';
    }

    /**
     * Sichtbarkeit im Medienpaket (E-Mail-Link) und für FTP-Ziel: leere Liste = alle Medienhäuser;
     * sonst nur nach Bestätigung mit passender Organisation (nicht bei reiner Freitext-Bestätigung).
     */
    public function isVisibleInDeliveryPackage(Delivery $delivery): bool
    {
        if (! $this->versand) {
            return false;
        }

        $ctx = $delivery->mediaDeliveryViewerContext();
        $ids = $this->delivery_visible_for_organization_ids;
        if (! is_array($ids)) {
            $ids = [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if ($ids === []) {
            return true;
        }

        if ($ctx['unconfirmed']) {
            return false;
        }

        if ($ctx['self_reported_only']) {
            return false;
        }

        $oid = $ctx['organization_id'];
        if ($oid === null) {
            return false;
        }

        return in_array((int) $oid, $ids, true);
    }

    /**
     * Öffentliche Artikel-/Portal-Ansicht: Medien nur sichtbar, wenn keine B2B-/Medienhaus-Einschränkung gesetzt ist.
     */
    public function isVisibleOnPublicArticle(): bool
    {
        $ids = $this->delivery_visible_for_organization_ids;
        if (! is_array($ids)) {
            $ids = [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        return $ids === [];
    }

    /**
     * Für FTP/SFTP: Medium an Ziel ausliefern, wenn Ziel-Organisation passt oder Medium uneingeschränkt ist.
     */
    public function isVisibleForFtpDestination(?int $destinationOrganizationId): bool
    {
        if (! $this->versand) {
            return false;
        }

        $ids = $this->delivery_visible_for_organization_ids;
        if (! is_array($ids)) {
            $ids = [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if ($ids === []) {
            return true;
        }

        if ($destinationOrganizationId === null) {
            return true;
        }

        return in_array((int) $destinationOrganizationId, $ids, true);
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
     * Relativer Pfad für den Bild-Editor: dieselbe Originaldatei wie beim Download/Versand.
     * Kein preview_path, kein Thumb, keine redigierte Ableitung.
     */
    public function resolveEditorSourceRelativePath(): ?string
    {
        return $this->resolveDeliveryDownloadRelativePath();
    }

    /**
     * URLs für den Bild-Editor: bevorzugt Presigned S3 (kein Server-Cache), Proxy als Fallback.
     *
     * @return array{cache_key: string, proxy: string|null, direct: string|null}
     */
    public function editorSourceConfig(): array
    {
        $cacheKey = 'editor-media-'.$this->id;
        $path = $this->resolveEditorSourceRelativePath();
        if ($path === null) {
            return ['cache_key' => $cacheKey, 'proxy' => null, 'direct' => null];
        }

        $proxy = route('admin.images.editor-source', $this->id);
        $direct = null;
        if (config('media_storage.editor_use_presigned_source', true)) {
            $ttl = max(5, (int) config('media_storage.editor_presigned_ttl_minutes', 30));
            $direct = app(MediaStorage::class)->temporaryPlaybackUrlForPath($path, now()->addMinutes($ttl));
        }

        return ['cache_key' => $cacheKey, 'proxy' => $proxy, 'direct' => $direct];
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
     * URL für Admin-Bild-Mediathek: nur Master/Original (JPEG/PNG), keine WebP-/Derived-Vorschau.
     */
    public function getLibraryImageUrlAttribute(): ?string
    {
        if (! $this->isImage()) {
            return null;
        }

        $master = $this->resolveIptcMasterRelativePath();
        if ($master !== null) {
            return app(MediaStorage::class)->url($master);
        }

        $path = trim((string) ($this->path ?? ''));
        if ($path !== '' && $path !== 'news-media/.pending' && ! $this->isDerivedAssetPath($path)) {
            return app(MediaStorage::class)->url($path);
        }

        $originalPath = trim((string) ($this->original_path ?? ''));
        if ($originalPath !== '' && ! $this->isDerivedAssetPath($originalPath)) {
            return app(MediaStorage::class)->url($originalPath);
        }

        return null;
    }

    private function isDerivedAssetPath(string $path): bool
    {
        $normalized = strtolower(str_replace('\\', '/', $path));

        return str_contains($normalized, '/derived/')
            || str_contains($normalized, '/thumb/')
            || str_ends_with($normalized, '.webp');
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

    /**
     * Relativer Pfad zur Master-JPEG-Datei für IPTC-Lesen/Schreiben (Medienpaket, Admin).
     */
    public function resolveIptcMasterRelativePath(): ?string
    {
        if (! $this->isImage()) {
            return null;
        }

        $candidates = array_values(array_filter([
            trim((string) ($this->path ?? '')),
            trim((string) ($this->original_path ?? '')),
        ], fn (string $p) => $p !== '' && $p !== 'news-media/.pending'));

        $storage = app(MediaStorage::class);
        foreach ($candidates as $candidate) {
            $ext = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));
            if (! in_array($ext, ['jpg', 'jpeg'], true)) {
                continue;
            }
            if ($storage->exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function readImageMetadataFromMasterFile(): ?array
    {
        $rel = $this->resolveIptcMasterRelativePath();
        if ($rel === null) {
            return null;
        }

        $storage = app(MediaStorage::class);
        try {
            $resolved = $storage->resolveReadableLocalPath($rel);
            $fullPath = $resolved['path'] ?? null;
            if (! is_string($fullPath) || ! is_file($fullPath)) {
                $storage->cleanupResolvedPath($resolved);

                return null;
            }
            $meta = ImageMetadataReader::read($fullPath);
            $storage->cleanupResolvedPath($resolved);

            return $meta;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Technische EXIF-Zeilen für Admin-Anzeige (Kamera, Belichtung, …).
     *
     * @return list<array{label: string, value: string}>
     */
    public function readExifDetailsFromMasterFile(): array
    {
        $rel = $this->resolveIptcMasterRelativePath();
        if ($rel === null) {
            return [];
        }

        $storage = app(MediaStorage::class);
        try {
            $resolved = $storage->resolveReadableLocalPath($rel);
            $fullPath = $resolved['path'] ?? null;
            if (! is_string($fullPath) || ! is_file($fullPath)) {
                $storage->cleanupResolvedPath($resolved);

                return [];
            }
            $rows = ImageExifDetailsReader::read($fullPath);
            $storage->cleanupResolvedPath($resolved);

            return $rows;
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function syncCaptureTimeFromMetadata(array $metadata): void
    {
        $carbon = ImageMetadataReader::captureTimeCarbonFromMetadata($metadata);
        if ($carbon === null) {
            return;
        }

        $update = [];
        if (Schema::hasColumn($this->getTable(), 'capture_time')) {
            $update['capture_time'] = $carbon;
        }
        if (Schema::hasColumn($this->getTable(), 'metadata_recorded_at')) {
            $update['metadata_recorded_at'] = $carbon->toDateString();
        }

        if ($update !== []) {
            $this->update($update);
        }
    }

    public function resolveCaptureTimeCarbonFromMasterFile(): ?Carbon
    {
        $meta = $this->readImageMetadataFromMasterFile();

        return $meta !== null
            ? ImageMetadataReader::captureTimeCarbonFromMetadata($meta)
            : null;
    }

    /**
     * Setzt fehlende capture_time (EXIF, Quellvideo bei Stills, sonst Upload-Zeit).
     */
    public function backfillCaptureTimeIfMissing(): bool
    {
        if ($this->type !== 'image' || $this->capture_time !== null) {
            return false;
        }

        $carbon = $this->resolveCaptureTimeCarbonFromMasterFile();

        if ($carbon === null && Schema::hasColumn($this->getTable(), 'source_video_media_id')
            && filled($this->source_video_media_id)) {
            $video = self::query()->find($this->source_video_media_id);
            $carbon = $video?->capture_time ?? $video?->created_at;
        }

        $carbon ??= $this->created_at;

        if ($carbon === null) {
            return false;
        }

        $update = ['capture_time' => $carbon];
        if (Schema::hasColumn($this->getTable(), 'metadata_recorded_at')) {
            $update['metadata_recorded_at'] = $carbon->toDateString();
        }

        $this->update($update);

        return true;
    }

    /**
     * IPTC/XMP-Felder für {@see \App\Services\ImageMetadataWriter::write()}.
     *
     * @return array<string, mixed>
     */
    public function resolvedIptcForEmbed(): array
    {
        $keywords = trim((string) ($this->media_keywords ?? ''));

        return [
            'image_title' => $this->image_title,
            'headline' => $this->description,
            'caption' => $this->caption,
            'keywords' => $keywords !== '' ? $keywords : null,
            'photographer' => $this->photographer,
            'credit' => $this->credit,
            'copyright' => $this->copyright,
            'source' => $this->source,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'country_code' => $this->country_code,
            'capture_time' => $this->capture_time,
        ];
    }

    /** Kölnimage-Fotos werden nicht automatisch redigiert; Erftkreis-News-Bilder schon. */
    public function shouldAutoRedact(): bool
    {
        if (! $this->isImage()) {
            return false;
        }

        $newsItem = $this->relationLoaded('newsItem')
            ? $this->newsItem
            : $this->newsItem()->with('brand')->first();

        if ($newsItem === null) {
            return true;
        }

        $brand = $newsItem->relationLoaded('brand')
            ? $newsItem->brand
            : $newsItem->brand()->first();

        return ($brand?->key ?? '') !== 'koelnimage';
    }
}
