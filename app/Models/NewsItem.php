<?php

namespace App\Models;

use App\Services\MediaStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class NewsItem extends Model
{
    protected $fillable = [
        'parent_news_item_id',
        'update_revision',
        'brand_id',
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
        'latitude',
        'longitude',
        'is_breaking',
        'planned_video_upload',
        'liveu_on_site',
        'is_confidential',
        'status',
        'published_at',
        'event_at',
        'embargo_at',
        'source_type',
        'source_name',
        'verification_status',
        'update_type',
        'author_id',
        'author_credit',
        'media_ai_context',
        'planned_event_id',
        'project_id',
        'channel_type',
        'moid',
        'is_wdr_job',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'event_at' => 'datetime',
        'embargo_at' => 'datetime',
        'is_breaking' => 'boolean',
        'planned_video_upload' => 'boolean',
        'liveu_on_site' => 'boolean',
        'is_confidential' => 'boolean',
        'is_wdr_job' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
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

    public function parentNewsItem(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_news_item_id');
    }

    public function updateChildren(): HasMany
    {
        return $this->hasMany(self::class, 'parent_news_item_id')->orderBy('update_revision');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function plannedEvent(): BelongsTo
    {
        return $this->belongsTo(PlannedEvent::class);
    }

    /**
     * Kurzlabel für Veranstaltungsort-Chips (Portal/Kölnimage), ohne Land allein.
     */
    public function getLocationChipLabelAttribute(): ?string
    {
        if ($this->planned_event_id) {
            $event = $this->relationLoaded('plannedEvent')
                ? $this->plannedEvent
                : $this->plannedEvent()->first();

            if ($event) {
                $location = trim((string) ($event->location ?? ''));
                if ($location !== '') {
                    return $location;
                }

                if ($event->hasCompleteVenueAddress()) {
                    return trim(sprintf(
                        '%s, %s %s, %s',
                        trim((string) $event->venue_street),
                        trim((string) $event->venue_postal_code),
                        trim((string) $event->venue_city),
                        trim((string) $event->venue_state),
                    ));
                }
            }

            $city = trim((string) ($this->city ?? ''));

            return $city !== '' ? $city : null;
        }

        $city = trim((string) ($this->city ?? ''));

        return $city !== '' ? $city : null;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scopeForBrand(Builder $query, ?int $brandId): Builder
    {
        if ($brandId === null) {
            return $query;
        }

        return $query->where('brand_id', $brandId);
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

    public function hasPriorDeliveries(): bool
    {
        return $this->deliveries()->exists();
    }

    /**
     * E-Mail-Versand als Update (Betreff „UPDATE | …“, neue Medien hervorgehoben).
     */
    public function shouldTreatDispatchAsUpdate(?bool $requestFlag = null, ?string $requestContext = null): bool
    {
        if ($this->hasPriorDeliveries()) {
            return true;
        }

        if ($requestFlag === true) {
            return true;
        }

        if ($requestContext === 'update') {
            return true;
        }

        return in_array((string) ($this->update_type ?? ''), ['update', 'correction', 'final'], true);
    }

    public function resolveDeliveryPhase(bool $isUpdateDelivery): string
    {
        return match ((string) ($this->update_type ?? '')) {
            'final' => 'ABSCHLUSSMELDUNG',
            'correction' => 'KORREKTUR',
            'update' => 'UPDATE',
            default => $isUpdateDelivery ? 'UPDATE' : 'ERSTMELDUNG',
        };
    }

    /**
     * Nach dem ersten Versand: Meldungstyp in der Bearbeitung von Erstmeldung auf Update umstellen.
     */
    public function promoteFromFirstReportToUpdateAfterDispatch(): bool
    {
        $currentType = (string) ($this->update_type ?? '');
        if (! in_array($currentType, ['first_report', ''], true)) {
            return false;
        }

        $this->update_type = 'update';
        $this->save();

        return true;
    }

    /** Feldänderungen (Status, Veröffentlichung, Titel, Slug) für Rekonstruktion / Fehlersuche. */
    public function fieldAudits(): HasMany
    {
        return $this->hasMany(NewsItemFieldAudit::class)->orderByDesc('id');
    }

    // PATCH: add statements and updates support for news items
    public function statements(): HasMany
    {
        return $this->hasMany(NewsItemStatement::class)->orderByDesc('received_at')->orderByDesc('id');
    }

    // PATCH: add statements and updates support for news items
    public function activeStatements(): HasMany
    {
        return $this->hasMany(NewsItemStatement::class)
            ->where('is_active', true)
            ->orderByDesc('received_at')
            ->orderByDesc('id');
    }

    // PATCH: add statements and updates support for news items
    public function updates(): HasMany
    {
        return $this->hasMany(NewsItemUpdate::class)->orderByDesc('happened_at')->orderByDesc('id');
    }

    // PATCH: add statements and updates support for news items
    public function activeUpdates(): HasMany
    {
        return $this->hasMany(NewsItemUpdate::class)
            ->where('is_active', true)
            ->orderByDesc('happened_at')
            ->orderByDesc('id');
    }

    public function witnessLinks(): HasMany
    {
        return $this->hasMany(NewsItemWitnessLink::class)->orderByDesc('created_at');
    }

    public function witnessSubmissions(): HasMany
    {
        return $this->hasMany(NewsItemWitnessSubmission::class)->orderByDesc('created_at');
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

    /**
     * Bilder ohne Medienhaus-Einschränkung (für öffentliches Portal / Artikel).
     *
     * @return \Illuminate\Support\Collection<int, NewsItemMedia>
     */
    public function publicPortalImages(): \Illuminate\Support\Collection
    {
        if ($this->relationLoaded('media')) {
            return $this->media
                ->filter(fn (NewsItemMedia $m) => $m->isImage() && $m->isVisibleOnPublicArticle())
                ->values();
        }

        $images = $this->relationLoaded('images') ? $this->images : $this->images()->get();

        return $images->filter(fn (NewsItemMedia $m) => $m->isVisibleOnPublicArticle())->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, NewsItemMedia>
     */
    public function publicPortalVideos(): \Illuminate\Support\Collection
    {
        if ($this->relationLoaded('media')) {
            return $this->media
                ->filter(fn (NewsItemMedia $m) => $m->isVideo() && $m->isVisibleOnPublicArticle())
                ->values();
        }

        $videos = $this->relationLoaded('videos') ? $this->videos : $this->videos()->get();

        return $videos->filter(fn (NewsItemMedia $m) => $m->isVisibleOnPublicArticle())->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, NewsItemMedia>
     */
    public function publicPortalAudios(): \Illuminate\Support\Collection
    {
        if ($this->relationLoaded('media')) {
            return $this->media
                ->filter(fn (NewsItemMedia $m) => $m->isAudio() && $m->isVisibleOnPublicArticle())
                ->values();
        }

        $audios = $this->relationLoaded('audios') ? $this->audios : $this->audios()->get();

        return $audios->filter(fn (NewsItemMedia $m) => $m->isVisibleOnPublicArticle())->values();
    }

    public function hasPublicPortalMedia(): bool
    {
        return $this->publicPortalImages()->isNotEmpty()
            || $this->publicPortalVideos()->isNotEmpty()
            || $this->publicPortalAudios()->isNotEmpty();
    }

    /**
     * Galerie-Reihenfolge nach Aufnahmezeit (EXIF); Einträge ohne Zeit am Ende.
     *
     * @param  Collection<int, NewsItemMedia>|iterable<int, NewsItemMedia>  $images
     * @return Collection<int, NewsItemMedia>
     */
    public function sortGalleryImagesByCaptureTime(iterable $images, bool $newestFirst = true): Collection
    {
        $collection = $images instanceof Collection ? $images : collect($images);

        return $collection
            ->sort(function (NewsItemMedia $a, NewsItemMedia $b) use ($newestFirst): int {
                $aTime = $a->capture_time;
                $bTime = $b->capture_time;

                if ($aTime === null && $bTime === null) {
                    return $newestFirst ? $b->id <=> $a->id : $a->id <=> $b->id;
                }
                if ($aTime === null) {
                    return 1;
                }
                if ($bTime === null) {
                    return -1;
                }

                $timeCompare = $newestFirst
                    ? $bTime <=> $aTime
                    : $aTime <=> $bTime;

                if ($timeCompare !== 0) {
                    return $timeCompare;
                }

                return $newestFirst ? $b->id <=> $a->id : $a->id <=> $b->id;
            })
            ->values();
    }

    /**
     * Teaser nur aus öffentlich sichtbaren Bildern (keine B2B-exklusiven Medien).
     */
    public function getTeaserImageForPublicAttribute(): ?NewsItemMedia
    {
        $public = $this->publicPortalImages();
        if ($public->isEmpty()) {
            return null;
        }

        return $public->where('is_teaser', true)->first()
            ?? $public->first();
    }

    /** Ort/Region für Anzeige (z. B. in Metadaten oder JSON-LD). */
    public function getLocationLabelAttribute(): ?string
    {
        // Straße mit ausgeben (Medienangebot-Mail, Update-Mail, Medienpaket, Portal)
        $parts = array_filter([$this->street, $this->city, $this->region, $this->country]);

        return $parts !== [] ? trim(implode(', ', $parts)) : null;
    }

    /**
     * Überschrift-Zeile für die öffentliche Artikelansicht.
     * Vermeidet „Einsatz in Bahnstraße …“ (unidiomatisch); bei gesetzter Stadt: „Einsatz in {Stadt} – {Rest}“.
     */
    public function getLocationArticleHeadlineAttribute(): string
    {
        $city = trim((string) ($this->city ?? ''));
        $street = trim((string) ($this->street ?? ''));
        $region = trim((string) ($this->region ?? ''));
        $federalState = trim((string) ($this->federal_state ?? ''));
        $country = trim((string) ($this->country ?? ''));

        if ($city !== '') {
            $detailParts = array_filter([
                $street !== '' ? $street : null,
                $region !== '' ? $region : null,
                $federalState !== '' ? $federalState : null,
                $country !== '' ? $country : null,
            ]);
            $detail = $detailParts !== [] ? implode(', ', $detailParts) : '';

            return $detail !== ''
                ? 'Einsatz in '.$city.' – '.$detail
                : 'Einsatz in '.$city;
        }

        $label = $this->location_label;
        if (is_string($label) && trim($label) !== '') {
            return 'Einsatzort: '.trim($label);
        }

        return 'Einsatzort und Ereignis';
    }

    /**
     * Google Maps: mit gespeicherten Koordinaten exakter Pin, sonst Textsuche zur Ortszeile (z. B. Angebotsmail).
     */
    public function getMapsSearchUrlAttribute(): ?string
    {
        if ($this->latitude !== null && $this->longitude !== null) {
            $lat = (float) $this->latitude;
            $lon = (float) $this->longitude;
            if ($lat >= -90.0 && $lat <= 90.0 && $lon >= -180.0 && $lon <= 180.0) {
                return 'https://www.google.com/maps?q='.rawurlencode(sprintf('%.7f,%.7f', $lat, $lon));
            }
        }

        $label = $this->location_label;
        if ($label === null || trim($label) === '') {
            return null;
        }

        return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode(trim($label));
    }

    /**
     * Anzeige-ID für Redaktion und Versand:
     * - Erstmeldung: "64"
     * - Updates: "64.1", "64.2", ...
     */
    public function getDisplayNewsIdAttribute(): string
    {
        $parentId = (int) ($this->parent_news_item_id ?? 0);
        $revision = (int) ($this->update_revision ?? 0);
        if ($parentId > 0 && $revision > 0) {
            return $parentId.'.'.$revision;
        }

        return (string) $this->id;
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

    /**
     * Admin-Versand blockieren, wenn WDR-Job ohne MoID – außer für Marken ohne diese Abrechnungslogik (z. B. Kölnimage).
     */
    public function blocksAdminSendWithoutMoid(): bool
    {
        if (! $this->isWdrJob()) {
            return false;
        }

        if (filled(trim((string) $this->moid))) {
            return false;
        }

        $this->loadMissing('brand');

        if ($this->brand?->key === 'koelnimage') {
            return false;
        }

        return true;
    }

    /**
     * Unterordner für FTP/SFTP (WDR-Format): MoID vorrangig, sonst Jahr_Monat_Tag_Ort_Titel.
     */
    public function getWdrSubfolderNameAttribute(): string
    {
        if (filled($this->moid)) {
            $s = $this->sanitizeFtpFolderSegment((string) $this->moid, false);

            return $s !== '' ? $s : 'moid';
        }

        $date = $this->published_at ?? $this->created_at ?? now();
        $y = $date->format('Y');
        $m = $date->format('m');
        $d = $date->format('d');
        $ort = Str::slug((string) ($this->city ?? $this->region ?? 'ort'));
        if ($ort === '') {
            $ort = 'ort';
        }
        $ti = Str::slug((string) ($this->title ?? 'meldung'));
        if ($ti === '') {
            $ti = 'meldung';
        }

        return "{$y}_{$m}_{$d}_{$ort}_{$ti}";
    }

    /**
     * EKN Live: Ordnername = DD_MM_JJJJ-Titel bis zum: (Datum: Veröffentlichung oder Anlage).
     */
    public function ftpEknLiveFolderName(): string
    {
        $date = $this->published_at ?? $this->created_at ?? now();
        $dd = $date->format('d');
        $mm = $date->format('m');
        $jjjj = $date->format('Y');
        $titlePart = $this->sanitizeEknTitleForFtp((string) ($this->title ?? 'Meldung'));

        return "{$dd}_{$mm}_{$jjjj}-{$titlePart} bis zum:";
    }

    /**
     * EKN Live: Dateiname auf dem Zielserver = exakt dieselbe Logik wie {@see MediaStorage::generateMediaPath}
     * mit Rolle „sendefassung“ (Ingest/Sendefassung). Nur {@see NewsItemMedia::$path} zu verwenden reicht nicht,
     * wenn der Datensatz noch einen Legacy-Pfad oder eine andere Rolle im Dateinamen hat.
     */
    public function ftpEknLiveRemoteVideoFilename(NewsItemMedia $media): string
    {
        $storage = app(MediaStorage::class);

        $fileName = trim((string) ($media->original_name ?? ''));
        if ($fileName === '') {
            $fromPath = basename(str_replace('\\', '/', (string) ($media->path ?? '')));
            if ($fromPath !== '' && $fromPath !== '.pending') {
                $fileName = $fromPath;
            }
        }
        if ($fileName === '' || $fileName === '.pending') {
            $fileName = 'video-'.$media->id.'.mp4';
        }

        $fullPath = $storage->generateMediaPath($this, $media, $fileName, 'sendefassung');
        $base = basename(str_replace('\\', '/', $fullPath));

        return $this->sanitizeEknRemoteBasename($base);
    }

    private function sanitizeEknRemoteBasename(string $basename): string
    {
        $base = preg_replace('/[\x00-\x1F\x7F]/u', '', $basename) ?? '';
        $base = str_replace(['/', '\\'], '', $base);

        return trim($base) !== '' ? trim($base) : 'video.mp4';
    }

    /**
     * Anzeige-/Upload-Unterordner je nach Versandziel-Konfiguration.
     */
    public function ftpSubfolderNameForDestination(DeliveryDestination $destination): string
    {
        if ($destination->usesEknLiveFolderFormat()) {
            return $this->ftpEknLiveFolderName();
        }

        if ($destination->isWdrOrganizationDestination()
            || ! empty($destination->config_json['wdr_subfolder_per_item'] ?? false)) {
            return $this->wdr_subfolder_name;
        }

        return '';
    }

    private function sanitizeEknTitleForFtp(string $title): string
    {
        $t = trim($title);
        $t = preg_replace('/[\x00-\x1F\x7F]/u', '', $t) ?? '';
        foreach (['/', '\\', ':', '*', '?', '"', '<', '>', '|'] as $char) {
            $t = str_replace($char, '', $t);
        }
        $t = trim($t);

        return $t !== '' ? $t : 'Meldung';
    }

    private function sanitizeFtpFolderSegment(string $raw, bool $asSlug): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if ($asSlug) {
            $s = Str::slug($raw);

            return $s !== '' ? $s : 'segment';
        }

        $s = preg_replace('/[^\w\-\.]+/u', '_', $raw) ?? '';

        return trim((string) $s, '._');
    }
}
