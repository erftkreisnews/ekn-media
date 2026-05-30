<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'notes',
        'publication_domains',
        'active',
        'buyer_reference',
        'lexware_contact_id',
        'external_reference_label',
        'external_author_id',
        'external_supplier_id',
        'external_vendor_code',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * Externe Identifikatoren (eine Stelle pro Organisation, gelten für alle Versandziele).
     *
     * @return array<int, array{label: string, value: string}>
     */
    public function getExternalIdentifiers(): array
    {
        $label = trim((string) ($this->external_reference_label ?? ''));
        if ($label === '') {
            $label = 'Externe Kennung';
        }
        $out = [];
        foreach (
            [
                'external_author_id' => $this->external_author_id,
                'external_supplier_id' => $this->external_supplier_id,
                'external_vendor_code' => $this->external_vendor_code,
            ] as $value
        ) {
            $v = trim((string) ($value ?? ''));
            if ($v !== '') {
                $out[] = ['label' => $label, 'value' => $v];
            }
        }

        return $out;
    }

    /** Erster nicht-leerer Wert für Dateinamen-Suffix: vendor_code, author_id, supplier_id. */
    public function getFirstExternalIdValue(): ?string
    {
        $raw = $this->external_vendor_code ?? $this->external_author_id ?? $this->external_supplier_id ?? null;
        if ($raw === null || trim((string) $raw) === '') {
            return null;
        }
        $suffix = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $raw);

        return $suffix !== '' ? $suffix : null;
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function deliveryDestinations(): HasMany
    {
        return $this->hasMany(DeliveryDestination::class, 'organization_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function billableEvents(): HasMany
    {
        return $this->hasMany(BillableEvent::class);
    }

    public function usageRecords(): HasMany
    {
        return $this->hasMany(UsageRecord::class);
    }
}
