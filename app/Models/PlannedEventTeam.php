<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlannedEventTeam extends Model
{
    protected $fillable = [
        'planned_event_id',
        'name',
        'notes',
        'reference_image_path',
        'sort_order',
    ];

    public function plannedEvent(): BelongsTo
    {
        return $this->belongsTo(PlannedEvent::class);
    }

    public function startNumber(): ?int
    {
        if (preg_match('/Startnr\.?\s*(\d{1,4})/iu', (string) $this->name, $match) !== 1) {
            return null;
        }

        $nr = (int) $match[1];

        return $nr > 0 ? $nr : null;
    }

    public function vehicleLabel(): ?string
    {
        $notes = (string) ($this->notes ?? '');
        if (preg_match('/Fahrzeug:\s*(.+)$/imu', $notes, $match) === 1) {
            $label = trim((string) ($match[1] ?? ''));

            return $label !== '' ? $label : null;
        }

        return null;
    }

    public function teamTitleFromName(): ?string
    {
        $name = trim((string) $this->name);
        if ($name === '') {
            return null;
        }

        if (preg_match('/Box\s+\d+\s*\|\s*Startnr\.?\s*\d+\s*\|\s*(.+)$/iu', $name, $match) === 1) {
            $title = trim((string) ($match[1] ?? ''));

            return $title !== '' ? $title : null;
        }

        return $name;
    }

    /** Kurzer Text für die Bildunterschrift (Motiv). */
    public function motivPickerValue(): string
    {
        $title = $this->teamTitleFromName();
        $nr = $this->startNumber();
        if ($title !== null && $title !== '' && $nr !== null) {
            return $title.' (#'.$nr.')';
        }

        return $title ?? trim((string) $this->name);
    }

    /** Anzeige im Dropdown (Startnummer zuerst). */
    public function motivPickerLabel(): string
    {
        $parts = [];
        if ($nr = $this->startNumber()) {
            $parts[] = '#'.$nr;
        }
        if ($title = $this->teamTitleFromName()) {
            $parts[] = $title;
        }
        if ($vehicle = $this->vehicleLabel()) {
            $parts[] = $vehicle;
        }

        $label = implode(' · ', $parts);

        return $label !== '' ? $label : trim((string) $this->name);
    }
}
