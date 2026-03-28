<?php

namespace App\Models;

use App\Services\MediaStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class NewsItem extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'teaser',
        'subheadline',
        'body',
        'keywords',
        'region',
        'country',
        'federal_state',
        'city',
        'street',
        'is_breaking',
        'planned_video_upload',
        'liveu_on_site',
        'status',
        'published_at',
        'embargo_at',
        'author_id',
        'author_credit',
        'moid',
        'is_wdr_job',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'embargo_at' => 'datetime',
        'is_breaking' => 'boolean',
        'planned_video_upload' => 'boolean',
        'liveu_on_site' => 'boolean',
        'is_wdr_job' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (NewsItem $newsItem): void {
            if (filled($newsItem->slug)) {
                return;
            }

            $base = Str::slug((string) $newsItem->title);
            if ($base === '') {
                $base = 'nachricht-'.uniqid();
            }

            $slug = $base;
            $counter = 2;

            while (static::where('slug', $slug)->exists() && $counter < 50) {
                $slug = $base.'-'.$counter;
                $counter++;
            }

            $newsItem->slug = $slug;
        });

        /**
         * Bei DB-CASCADE auf news_item_media werden keine NewsItemMedia::deleting-Events ausgelöst –
         * Medien-Dateien (S3/lokal) müssen hier explizit entfernt werden.
         */
        static::deleting(function (NewsItem $newsItem): void {
            $storage = app(MediaStorage::class);
            foreach ($newsItem->media()->get() as $medium) {
                $medium->purgeStoredFiles($storage);
            }
        });
    }

    /**
     * Scope: nur öffentlich sichtbare Meldungen (veröffentlicht, nicht in der Zukunft, Embargo abgelaufen).
     */
    public function scopePublicVisible(Builder $query): Builder
    {
        return $query
            ->where('status', 'published')
            ->where(function (Builder $q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->where(function (Builder $q) {
                $q->whereNull('embargo_at')
                    ->orWhere('embargo_at', '<=', now());
            });
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(NewsItemMedia::class)->orderBy('sort_order');
    }

    public function images(): HasMany
    {
        return $this->hasMany(NewsItemMedia::class)->where('type', 'image')->orderBy('sort_order');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(NewsItemMedia::class)->where('type', 'video')->orderBy('sort_order');
    }

    public function audios(): HasMany
    {
        return $this->hasMany(NewsItemMedia::class)->where('type', 'audio')->orderBy('sort_order');
    }

    public function usageRecords(): HasMany
    {
        return $this->hasMany(UsageRecord::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    /** Teaser-Bild für Listen und Detail: zuerst das als Teaser markierte, sonst erstes Bild. */
    public function getTeaserImageAttribute(): ?NewsItemMedia
    {
        // Nutzt die bereits lazy/eager geladene `images`-Relation (nicht `media`),
        // damit in Views wie `/admin/news` kein zusätzliches N+1 durch `media` entsteht.
        $images = $this->relationLoaded('images') ? $this->images : $this->images()->get();

        return $images->where('is_teaser', true)->first()
            ?? $images->first();
    }

    /** Ort/Region für Anzeige (z. B. in Metadaten oder JSON-LD). */
    public function getLocationLabelAttribute(): ?string
    {
        $parts = array_filter([$this->city, $this->region, $this->country]);

        return $parts !== [] ? trim(implode(', ', $parts)) : null;
    }

    /**
     * Web-Text für die öffentliche Artikelansicht.
     * Reiner Text aus dem Admin wird in HTML-Absätze überführt (wie im Textarea sichtbar).
     *
     * - &lt;br&gt; wird zu Zeilenumbrüchen normalisiert (sonst blieb „HTML“ erkannt und nichts passierte).
     * - Einfache &lt;p&gt;-Blöcke werden ausgewertet und in gleiche Absatzlogik wie Fließtext überführt.
     * - Nur echte Block-Struktur (Listen, Tabellen, Überschriften, div-Layouts …) bleibt unverändert.
     *
     * Fließtext-Logik:
     * - Gibt es irgendwo eine Leerzeile (doppelter Zeilenumbruch), gelten Absätze als durch Leerzeilen getrennt;
     *   innerhalb eines solchen Absatzes bleiben einzelne Zeilenumbrüche als Zeilen (nl2br).
     * - Gibt es nirgends eine Leerzeile, gilt jeder Zeilenumbruch (Enter) als neuer Absatz (1:1 mit Zeilen im Feld).
     */
    public function bodyHtmlForWeb(): string
    {
        $body = (string) ($this->body ?? '');
        $trimmed = trim($body);
        if ($trimmed === '') {
            return '';
        }

        // <br> zuerst in echte Zeilenumbrüche überführen (häufig aus Copy/Paste; sonst „HTML“-Erkennung)
        $normalized = (string) preg_replace('/<br\s*\/?>/i', "\n", $trimmed);

        if ($this->bodyHasComplexHtmlStructure($normalized)) {
            return $body;
        }

        $blocks = $this->extractWebParagraphBlocks($normalized);
        if ($blocks === []) {
            return '';
        }

        return $this->buildParagraphHtmlFromBlocks($blocks);
    }

    /**
     * Listen, Tabellen, Überschriften, div-Layouts u. Ä.: Ausgabe wie gespeichert (kein Absatz-Umbau).
     */
    private function bodyHasComplexHtmlStructure(string $html): bool
    {
        return (bool) preg_match(
            '/<\s*(div|ul|ol|table|h[1-6]|article|section|figure|blockquote|pre|li)\b/i',
            $html
        );
    }

    /**
     * @return list<string>
     */
    private function extractWebParagraphBlocks(string $html): array
    {
        $trimmed = trim($html);
        $out = [];

        $firstP = stripos($trimmed, '<p');
        if ($firstP !== false && $firstP > 0) {
            $before = trim(strip_tags(substr($trimmed, 0, $firstP)));
            if ($before !== '') {
                $out = array_merge($out, $this->splitPlainTextToParagraphs($before));
            }
        }

        if (preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $trimmed, $matches)) {
            foreach ($matches[1] as $inner) {
                $inner = trim($inner);
                if ($inner === '') {
                    continue;
                }
                $plainInner = trim(strip_tags($inner));
                if ($plainInner === '') {
                    continue;
                }
                $out = array_merge($out, $this->splitPlainTextToParagraphs($plainInner));
            }
        }

        if ($out !== []) {
            $lastClose = strripos($trimmed, '</p>');
            if ($lastClose !== false) {
                $after = substr($trimmed, $lastClose + strlen('</p>'));
                $after = trim(strip_tags($after));
                if ($after !== '') {
                    $out = array_merge($out, $this->splitPlainTextToParagraphs($after));
                }
            }

            return $out;
        }

        $plain = trim(strip_tags($trimmed));
        if ($plain !== '') {
            return $this->splitPlainTextToParagraphs($plain);
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function splitPlainTextToParagraphs(string $text): array
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return [];
        }

        $hasBlankLine = (bool) preg_match('/(?:\r\n|\n|\r)\s*(?:\r\n|\n|\r)/', $trimmed);

        if ($hasBlankLine) {
            $blocks = preg_split('/(?:\r\n|\n|\r)\s*(?:\r\n|\n|\r)/', $trimmed) ?: [];
        } else {
            $blocks = preg_split('/\r\n|\r|\n/', $trimmed) ?: [];
        }

        return array_values(array_filter(array_map('trim', $blocks), fn (string $b): bool => $b !== ''));
    }

    /**
     * @param  list<string>  $blocks
     */
    private function buildParagraphHtmlFromBlocks(array $blocks): string
    {
        $html = [];
        foreach ($blocks as $block) {
            $inner = str_contains($block, "\n")
                ? nl2br(e($block), false)
                : e($block);
            $html[] = '<p>'.$inner.'</p>';
        }

        return implode("\n", $html);
    }

    /** Ob es sich um einen WDR-Job handelt. */
    public function isWdrJob(): bool
    {
        return (bool) $this->is_wdr_job;
    }

    /** Bei WDR-Job mit MoID: nur WDR-Versandziele erlaubt. */
    public function hasMoidRestriction(): bool
    {
        return $this->isWdrJob() && filled($this->moid);
    }
}
