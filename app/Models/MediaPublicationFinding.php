<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaPublicationFinding extends Model
{
    public const KIND_OWN = 'own';

    public const KIND_LICENSED = 'licensed';

    public const KIND_DOCUMENTATION = 'documentation';

    public const KIND_INFRINGEMENT = 'infringement';

    public const KIND_UNKNOWN = 'unknown';

    protected $fillable = [
        'news_item_id',
        'news_item_media_id',
        'organization_id',
        'kind',
        'url',
        'url_hash',
        'page_title',
        'notes',
        'auto_detected',
        'confirmed',
        'scan_source',
        'found_at',
        'created_by',
        'evidence_dossier_status',
        'evidence_dossier_path',
        'evidence_dossier_manifest',
        'evidence_dossier_built_at',
        'evidence_manual_files',
        'authority_access_token',
        'authority_access_expires_at',
        'authority_access_password',
        'authority_access_recipient',
        'authority_access_file_reference',
        'authority_access_created_by',
        'authority_access_created_at',
        'authority_access_revoked_at',
    ];

    protected $hidden = [
        'authority_access_password',
    ];

    protected $casts = [
        'auto_detected' => 'boolean',
        'confirmed' => 'boolean',
        'found_at' => 'datetime',
        'evidence_dossier_manifest' => 'array',
        'evidence_dossier_built_at' => 'datetime',
        'evidence_manual_files' => 'array',
        'authority_access_expires_at' => 'datetime',
        'authority_access_created_at' => 'datetime',
        'authority_access_revoked_at' => 'datetime',
    ];

    /**
     * @return array<string, string>
     */
    public static function kindLabels(): array
    {
        return [
            self::KIND_OWN => 'Eigene Veröffentlichung',
            self::KIND_LICENSED => 'Lizenziert (Kunde)',
            self::KIND_DOCUMENTATION => 'VÖ / Dokumentation',
            self::KIND_INFRINGEMENT => 'Urheberrechtsverstoß',
            self::KIND_UNKNOWN => 'Unklar / prüfen',
        ];
    }

    public function kindLabel(): string
    {
        return self::kindLabels()[$this->kind] ?? $this->kind;
    }

    public function newsItem(): BelongsTo
    {
        return $this->belongsTo(NewsItem::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(NewsItemMedia::class, 'news_item_media_id');
    }

    public function mediaItems(): BelongsToMany
    {
        return $this->belongsToMany(
            NewsItemMedia::class,
            'media_publication_finding_media',
            'media_publication_finding_id',
            'news_item_media_id'
        )->withTimestamps();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function authorityDownloads(): HasMany
    {
        return $this->hasMany(PublicationFindingAuthorityDownload::class, 'media_publication_finding_id');
    }

    public function hasActiveAuthorityAccess(): bool
    {
        if ($this->authority_access_token === null || $this->authority_access_revoked_at !== null) {
            return false;
        }

        if ($this->authority_access_expires_at !== null && $this->authority_access_expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function authorityPortalUrl(): ?string
    {
        if (! $this->hasActiveAuthorityAccess()) {
            return null;
        }

        return route('publication-finding.authority.show', [
            'token' => $this->authority_access_token,
        ], true);
    }

    public function authorityRecipientDisplay(): string
    {
        $parts = array_filter([
            trim((string) ($this->authority_access_recipient ?? '')),
            trim((string) ($this->authority_access_file_reference ?? '')),
        ]);

        if ($parts === []) {
            return 'Polizei / Staatsanwaltschaft (Aktenzeichen noch nicht vergeben)';
        }

        return implode(' · ', $parts);
    }

    public static function hashUrl(string $url): string
    {
        $normalized = strtolower(rtrim(trim($url), '/'));

        return hash('sha256', $normalized);
    }

    /**
     * Checkliste inkl. manuell hochgeladener Beweise (für Anzeige).
     *
     * @return array<string, bool>
     */
    public function evidenceChecklistForDisplay(): array
    {
        $checklist = (array) ($this->evidence_dossier_manifest['checklist'] ?? []);

        $manualKeys = [
            'screenshot_kanal' => 'screenshot_kanal',
            'screenshot_einblendung' => 'screenshot_einblendung',
            'youtube_video' => 'youtube_video_lokal',
        ];

        foreach ($manualKeys as $type => $checkKey) {
            if (is_array(($this->evidence_manual_files ?? [])[$type] ?? null)) {
                $checklist[$checkKey] = true;
            }
        }

        return $checklist;
    }

    /**
     * @return list<string>
     */
    public function evidenceWarningsForDisplay(): array
    {
        $warnings = (array) ($this->evidence_dossier_manifest['warnings'] ?? []);
        $checklist = $this->evidenceChecklistForDisplay();
        $manual = (array) ($this->evidence_manual_files ?? []);

        $filtered = [];
        foreach ($warnings as $warning) {
            if (! is_string($warning) || $warning === '') {
                continue;
            }
            $lower = strtolower($warning);

            if (($checklist['screenshot_kanal'] ?? false) && (str_contains($lower, 'kanal') || str_contains($lower, 'playwright'))) {
                continue;
            }
            if (($checklist['screenshot_einblendung'] ?? false) && (str_contains($lower, 'einblendung') || str_contains($lower, 'playwright'))) {
                continue;
            }
            if (($checklist['youtube_video_lokal'] ?? false) && (str_contains($lower, 'youtube:') || str_contains($lower, 'yt-dlp'))) {
                continue;
            }
            if (isset($manual['youtube_video']) && (str_contains($lower, 'youtube:') || str_contains($lower, 'unavailable') || str_contains($lower, 'vorschaubild'))) {
                continue;
            }
            if (($checklist['screenshot_video'] ?? false) && str_contains($lower, 'vorschaubild')) {
                continue;
            }

            $filtered[] = $warning;
        }

        return array_values(array_unique($filtered));
    }

    /**
     * @return list<string>
     */
    public function evidenceNotesForDisplay(): array
    {
        $notes = (array) ($this->evidence_dossier_manifest['notes'] ?? []);
        $manual = (array) ($this->evidence_manual_files ?? []);

        if (isset($manual['youtube_video'])) {
            $notes[] = 'Manuelles YouTube-Video ist hinterlegt und in der Beweismittelmappe enthalten.';
        }

        return array_values(array_unique(array_filter($notes, static fn ($n): bool => is_string($n) && $n !== '')));
    }

    public function evidenceDossierNeedsRebuild(): bool
    {
        $manual = (array) ($this->evidence_manual_files ?? []);
        if ($manual === []) {
            return false;
        }

        $builtAt = $this->evidence_dossier_built_at;
        if ($builtAt === null) {
            return true;
        }

        foreach ($manual as $meta) {
            if (! is_array($meta) || empty($meta['uploaded_at'])) {
                continue;
            }
            if (\Carbon\Carbon::parse($meta['uploaded_at'])->gt($builtAt)) {
                return true;
            }
        }

        return false;
    }
}
