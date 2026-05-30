<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PlannedEvent extends Model
{
    protected $fillable = [
        'name',
        'date_label',
        'location',
        'venue_street',
        'venue_postal_code',
        'venue_city',
        'venue_state',
        'venue_country',
        'venue_country_code',
        'starts_at',
        'ends_at',
        'ai_context',
        'is_active',
        'sort_order',
        'assigned_user_ids',
        'schedule_pdf_path',
        'schedule_pdf_original_name',
        'schedule_pdf_extracted_text',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'is_active' => 'boolean',
            'assigned_user_ids' => 'array',
        ];
    }

    public function teams(): HasMany
    {
        return $this->hasMany(PlannedEventTeam::class)->orderBy('sort_order')->orderBy('id');
    }

    public function hasSchedulePdf(): bool
    {
        return filled($this->schedule_pdf_path);
    }

    public function hasCompleteVenueAddress(): bool
    {
        return filled($this->venue_street)
            && filled($this->venue_postal_code)
            && filled($this->venue_city)
            && filled($this->venue_state);
    }

    /**
     * Andere Veranstaltungen mit Kurz-„Ort“ und vollständiger Adresse (für Schnellauswahl im Formular).
     *
     * @return Collection<int, static>
     */
    public static function venueAddressPresetCandidates(?int $excludeEventId = null): Collection
    {
        if (! Schema::hasTable('planned_events') || ! Schema::hasColumn('planned_events', 'venue_city')) {
            return collect();
        }

        $q = static::query()
            ->whereNotNull('location')
            ->where('location', '!=', '')
            ->whereNotNull('venue_street')
            ->where('venue_street', '!=', '')
            ->whereNotNull('venue_postal_code')
            ->where('venue_postal_code', '!=', '')
            ->whereNotNull('venue_city')
            ->where('venue_city', '!=', '')
            ->whereNotNull('venue_state')
            ->where('venue_state', '!=', '')
            ->orderByDesc('updated_at')
            ->limit(80);

        if ($excludeEventId !== null && $excludeEventId > 0) {
            $q->whereKeyNot($excludeEventId);
        }

        return $q->get()
            ->unique(static fn (PlannedEvent $e) => mb_strtolower(trim((string) $e->location)))
            ->values();
    }

    /**
     * Eine Zeile für KI-Prompt und interne Hinweise (Stadt/Bundesland für IPTC).
     */
    public function venueAddressLineForPrompt(): ?string
    {
        if (! $this->hasCompleteVenueAddress()) {
            return null;
        }

        $country = trim((string) ($this->venue_country ?? ''));
        if ($country === '') {
            $country = 'Deutschland';
        }

        return trim(sprintf(
            '%s, %s %s, %s, %s',
            trim((string) $this->venue_street),
            trim((string) $this->venue_postal_code),
            trim((string) $this->venue_city),
            trim((string) $this->venue_state),
            $country
        ));
    }

    public function labelWithDateForDisplay(): string
    {
        $date = trim((string) ($this->date_label ?? ''));
        if ($date !== '') {
            return $this->name.' - '.$date;
        }

        if ($this->starts_at) {
            $range = $this->starts_at->format('d.m.Y');
            if ($this->ends_at) {
                $range .= '–'.$this->ends_at->format('d.m.Y');
            }

            return $this->name.' - '.$range;
        }

        return $this->name;
    }

    public function formatForAiPrompt(): string
    {
        $lines = [];
        $lines[] = 'Veranstaltung: '.$this->name;

        $venueLine = $this->venueAddressLineForPrompt();
        if ($venueLine !== null) {
            $lines[] = 'Veranstaltungsadresse (für Bild-Metadaten IPTC Stadt/Bundesland): '.$venueLine;
        }

        $date = trim((string) ($this->date_label ?? ''));
        if ($date !== '') {
            $lines[] = 'Zeitraum: '.$date;
        } elseif ($this->starts_at) {
            $range = $this->starts_at->format('d.m.Y');
            if ($this->ends_at) {
                $range .= '–'.$this->ends_at->format('d.m.Y');
            }
            $lines[] = 'Zeitraum: '.$range;
        }

        $context = trim(strip_tags((string) ($this->ai_context ?? '')));
        if ($context !== '') {
            $lines[] = '';
            $lines[] = 'Zusatz-Kontext:';
            $lines[] = $context;
        }

        if (filled($this->schedule_pdf_extracted_text)) {
            $lines[] = '';
            $lines[] = 'Programm aus PDF (extrahiert):';
            $lines[] = Str::limit(trim((string) $this->schedule_pdf_extracted_text), 12000, "\n…");
        }

        $teams = $this->relationLoaded('teams') ? $this->teams : $this->teams()->get();
        if ($teams->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Teilnehmende / Teams:';
            foreach ($teams as $team) {
                $name = trim((string) $team->name);
                if ($name === '') {
                    continue;
                }
                $notes = trim((string) ($team->notes ?? ''));
                $lines[] = $notes !== '' ? '- '.$name.' ('.$notes.')' : '- '.$name;
            }
        }

        $compact = trim($this->compactStarterListForAiPrompt());
        if ($compact !== '') {
            $lines[] = '';
            $lines[] = $compact;
        }

        return trim(implode("\n", $lines));
    }

    public function compactStarterListForAiPrompt(): string
    {
        $teams = $this->relationLoaded('teams') ? $this->teams : $this->teams()->get();
        $out = [];

        foreach ($teams as $team) {
            $name = trim((string) $team->name);
            if ($name === '') {
                continue;
            }

            if (! preg_match('/Box\s*(\d+)\s*\|\s*Startnr\.?\s*(\d+)\s*\|\s*(.+)$/iu', $name, $m)) {
                continue;
            }

            $box = trim($m[1]);
            $startNr = trim($m[2]);
            $teamLabel = trim($m[3]);
            $note = trim((string) ($team->notes ?? ''));
            $line = '- Startnummer '.$startNr.' => '.$teamLabel.' (Garage/Box '.$box.')';
            if ($note !== '') {
                $line .= ' | '.$note;
            }
            $out[] = $line;
        }

        if ($out === []) {
            return '';
        }

        return "Kompakte Zuordnung (Starterliste):\n".implode("\n", $out);
    }

    public static function queryForNewsSelect(?int $selectedEventId, ?Authenticatable $user): Collection
    {
        $selectedEventId = $selectedEventId && $selectedEventId > 0 ? $selectedEventId : null;

        $query = static::query()
            ->with('teams')
            ->where(function (Builder $q) use ($selectedEventId): void {
                $q->where('is_active', true);
                if ($selectedEventId !== null) {
                    $q->orWhere('id', $selectedEventId);
                }
            });

        if ($user && method_exists($user, 'hasRole') && ! $user->hasRole('admin')) {
            $userId = (int) ($user->id ?? 0);
            $query->where(function (Builder $q) use ($userId, $selectedEventId): void {
                if ($userId > 0) {
                    $q->whereJsonContains('assigned_user_ids', $userId);
                }
                if ($selectedEventId !== null) {
                    $q->orWhere('id', $selectedEventId);
                }
            });
        }

        return $query
            ->orderByDesc('starts_at')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
