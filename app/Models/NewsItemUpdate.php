<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsItemUpdate extends Model
{
    public const TYPE_STATEMENT = 'statement';

    public const TYPE_SITUATION = 'situation';

    public const TYPE_IMAGE_UPDATE = 'image_update';

    public const TYPE_VIDEO_UPDATE = 'video_update';

    public const TYPE_DATA_UPDATE = 'data_update';

    public const TYPE_CORRECTION = 'correction';

    public const TYPE_PRESS_RELEASE = 'press_release';

    public const TYPE_ARTICLE_UPDATE = 'article_update';

    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'news_item_id',
        'type',
        'title',
        'body',
        'presseportal_url',
        'presseportal_story_id',
        'presseportal_office_id',
        'source_type',
        'source_label',
        'happened_at',
        'show_in_mail',
        'show_in_article',
        'is_active',
        'statement_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'happened_at' => 'datetime',
        'show_in_mail' => 'boolean',
        'show_in_article' => 'boolean',
        'is_active' => 'boolean',
    ];

    public static function updateTypeOptions(): array
    {
        return [
            self::TYPE_SITUATION => 'Lage-Update',
            self::TYPE_IMAGE_UPDATE => 'Bilder-Update',
            self::TYPE_VIDEO_UPDATE => 'Video-Update',
            self::TYPE_DATA_UPDATE => 'Daten-Update',
            self::TYPE_CORRECTION => 'Korrektur',
            self::TYPE_PRESS_RELEASE => 'PM / Pressemitteilung',
            self::TYPE_ARTICLE_UPDATE => 'Text-Update',
            self::TYPE_STATEMENT => 'Statement',
            self::TYPE_OTHER => 'Sonstige',
        ];
    }

    public static function sourceTypeOptions(): array
    {
        return NewsItemStatement::sourceTypeOptions();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForMail(Builder $query): Builder
    {
        return $query->where('show_in_mail', true)->where('is_active', true);
    }

    public function newsItem(): BelongsTo
    {
        return $this->belongsTo(NewsItem::class, 'news_item_id');
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(NewsItemStatement::class, 'statement_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::updateTypeOptions()[$this->type] ?? (string) $this->type;
    }

    public function getSourceTypeLabelAttribute(): ?string
    {
        if (! $this->source_type) {
            return null;
        }

        return self::sourceTypeOptions()[$this->source_type] ?? (string) $this->source_type;
    }

    /**
     * Eine Zeile für „Quelle:“ in Medienpaket/Mail – vermeidet Redundanz wie „Polizei (Polizei Bonn)“.
     */
    public function getDisplaySourceLineAttribute(): ?string
    {
        $typeLabel = $this->source_type_label ?? $this->source_type;
        $label = $this->source_label;

        if (! filled($label) && ! filled($typeLabel)) {
            return null;
        }

        if (filled($label) && ! filled($typeLabel)) {
            return $label;
        }

        if (filled($typeLabel) && ! filled($label)) {
            return (string) $typeLabel;
        }

        $t = mb_strtolower(trim((string) $typeLabel));
        $l = mb_strtolower(trim($label));

        if ($l === $t) {
            return $this->source_label;
        }

        if (mb_strpos($l, $t) !== false) {
            return $this->source_label;
        }

        return trim($typeLabel.' · '.$this->source_label);
    }
}
