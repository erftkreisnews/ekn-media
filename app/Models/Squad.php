<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Squad extends Model
{
    /** Fester Slug für die Haus-Mannschaft 1. FC Köln Herren (eine Zeile in `squads`). */
    public const FC_KOELN_HERREN_SLUG = '1-fc-koeln-herren';

    protected $fillable = [
        'slug',
        'name',
        'is_active',
        'include_in_media_ai',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'include_in_media_ai' => 'boolean',
        ];
    }

    public function players(): HasMany
    {
        return $this->hasMany(SquadPlayer::class)->orderBy('sort_order')->orderBy('id');
    }

    public static function fcKoelnHerren(): self
    {
        return static::query()->firstOrCreate(
            ['slug' => self::FC_KOELN_HERREN_SLUG],
            [
                'name' => '1. FC Köln – Herren',
                'is_active' => true,
                'include_in_media_ai' => true,
            ]
        );
    }

    /**
     * Textblock für Bild-KI (Vision). Leer wenn deaktiviert oder ohne Spieler.
     */
    public function formatForMediaAiContext(): string
    {
        if (! $this->include_in_media_ai || ! $this->is_active) {
            return '';
        }

        $players = $this->relationLoaded('players')
            ? $this->players->sortBy(['sort_order', 'id'])->values()
            : $this->players()->orderBy('sort_order')->orderBy('id')->get();

        if ($players->isEmpty()) {
            return '';
        }

        $lines = [];
        $lines[] = 'Haus-Mannschaft / Kader: '.$this->name;
        $lines[] = 'Personen (Rückennummer nur bei Spielern; nur nutzen wenn im Bild erkennbar oder eindeutig aus Kontext):';

        foreach ($players as $p) {
            $num = filled($p->shirt_number) ? '#'.trim((string) $p->shirt_number).' ' : '';
            $role = filled($p->position_label) ? ' ('.trim((string) $p->position_label).')' : '';
            $lines[] = '- '.$num.trim((string) $p->full_name).$role;
        }

        return implode("\n", $lines);
    }
}
