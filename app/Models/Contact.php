<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{
    protected $fillable = [
        'organization_id',
        'product_id',
        'name',
        'email',
        'phone',
        'role',
        'use_for_invoice',
        'billing_department',
        'billing_type',
    ];

    protected $casts = [
        'use_for_invoice' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Bezeichnung für Auswahl bei Rechnungserstellung (z. B. "Newsroom Lizenz – rechnung-lizenz@wdr.de"). */
    public function getBillingLabelAttribute(): string
    {
        $scope = $this->product?->name ?: $this->organization?->name ?: null;
        $parts = array_filter([
            $scope,
            $this->getBillingDepartmentLabel(),
            $this->getBillingTypeLabel(),
        ]);
        $hint = $parts !== [] ? ' ('.implode(', ', $parts).')' : '';

        return $this->name.$hint.' – '.$this->email;
    }

    public function getBillingDepartmentLabel(): ?string
    {
        return match ($this->billing_department) {
            'newsroom' => 'Newsroom',
            'studio_koeln' => 'Studio Köln',
            'studio_bonn' => 'Studio Bonn',
            default => null,
        };
    }

    public function getBillingTypeLabel(): ?string
    {
        return match ($this->billing_type) {
            'lizenz' => 'Lizenz',
            'honorar' => 'Honorar',
            default => null,
        };
    }
}
