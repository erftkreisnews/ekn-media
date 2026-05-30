<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'brand_id',
        'organization_id',
        'name',
        'active',
        'buyer_reference',
        'lexware_contact_id',
        'billing_name',
        'billing_company',
        'billing_street',
        'billing_postal_code',
        'billing_city',
        'billing_country',
        'billing_email_primary',
        'billing_email_secondary',
        'billing_notes',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function deliveryDestinations(): HasMany
    {
        return $this->hasMany(DeliveryDestination::class, 'product_id');
    }

    public function usageRecords(): HasMany
    {
        return $this->hasMany(UsageRecord::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function getLexwareDisplayNameAttribute(): string
    {
        $name = trim((string) ($this->billing_name ?: $this->name));

        return $name !== '' ? $name : 'Rechnungseinheit';
    }

    public function getBillingAddressSingleLineAttribute(): string
    {
        $parts = array_filter([
            trim((string) ($this->billing_name ?: $this->name)),
            trim((string) ($this->billing_street ?? '')),
            trim(implode(' ', array_filter([
                $this->billing_postal_code,
                $this->billing_city,
            ]))),
            trim((string) ($this->billing_country ?? '')),
        ]);

        return implode(', ', $parts);
    }

    /**
     * Kundennummer / Buyer Reference: zuerst Redaktion, sonst Medienhaus (Lexware-Stamm oft nur auf Organisationsebene gepflegt).
     */
    public function resolvedBuyerReference(): string
    {
        $direct = trim((string) ($this->buyer_reference ?? ''));
        if ($direct !== '') {
            return $direct;
        }

        $this->loadMissing('organization');

        return trim((string) ($this->organization?->buyer_reference ?? ''));
    }
}
