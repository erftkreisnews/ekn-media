<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'organization_id',
        'product_id',
        'contact_id',
        'lexware_invoice_id',
        'voucher_number',
        'status',
        'total_net',
        'total_vat',
        'total_gross',
        'currency',
        'voucher_date',
        'meta',
    ];

    protected $casts = [
        'total_net' => 'decimal:2',
        'total_vat' => 'decimal:2',
        'total_gross' => 'decimal:2',
        'voucher_date' => 'date',
        'meta' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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
