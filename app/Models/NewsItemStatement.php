<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsItemStatement extends Model
{
    public const SOURCE_POLICE = 'police';

    public const SOURCE_FIRE_DEPARTMENT = 'fire_department';

    public const SOURCE_CITY_PUBLIC_ORDER = 'city_public_order';

    public const SOURCE_CHURCH = 'church';

    public const SOURCE_RESCUE_SERVICE = 'rescue_service';

    public const SOURCE_PROSECUTOR = 'prosecutor';

    public const SOURCE_PRESS_OFFICE = 'press_office';

    public const SOURCE_OTHER = 'other';

    public const STATEMENT_AUDIO = 'audio';

    public const STATEMENT_VIDEO = 'video';

    public const STATEMENT_TEXT = 'text';

    public const STATEMENT_PHONE_SUMMARY = 'phone_summary';

    protected $fillable = [
        'news_item_id',
        'source_type',
        'source_label',
        'statement_type',
        'transcript',
        'summary',
        'received_at',
        'is_active',
        'is_publishable',
        'show_in_mail',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'is_active' => 'boolean',
        'is_publishable' => 'boolean',
        'show_in_mail' => 'boolean',
    ];

    public static function sourceTypeOptions(): array
    {
        return [
            self::SOURCE_POLICE => 'Polizei',
            self::SOURCE_FIRE_DEPARTMENT => 'Feuerwehr',
            self::SOURCE_CITY_PUBLIC_ORDER => 'Stadt / Ordnungsamt',
            self::SOURCE_CHURCH => 'Kirche',
            self::SOURCE_RESCUE_SERVICE => 'Rettungsdienst',
            self::SOURCE_PROSECUTOR => 'Staatsanwaltschaft',
            self::SOURCE_PRESS_OFFICE => 'Pressestelle',
            self::SOURCE_OTHER => 'Sonstige',
        ];
    }

    public static function statementTypeOptions(): array
    {
        return [
            self::STATEMENT_AUDIO => 'Audio',
            self::STATEMENT_VIDEO => 'Video',
            self::STATEMENT_TEXT => 'Text',
            self::STATEMENT_PHONE_SUMMARY => 'Telefon-Zusammenfassung',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePublishable(Builder $query): Builder
    {
        return $query->where('is_publishable', true);
    }

    public function scopeForMail(Builder $query): Builder
    {
        return $query->where('show_in_mail', true)->where('is_active', true)->where('is_publishable', true);
    }

    public function newsItem(): BelongsTo
    {
        return $this->belongsTo(NewsItem::class, 'news_item_id');
    }

    public function getSourceTypeLabelAttribute(): string
    {
        return self::sourceTypeOptions()[$this->source_type] ?? (string) $this->source_type;
    }

    public function getStatementTypeLabelAttribute(): string
    {
        return self::statementTypeOptions()[$this->statement_type] ?? (string) $this->statement_type;
    }
}
