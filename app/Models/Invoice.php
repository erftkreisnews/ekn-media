<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class Invoice extends Model
{
    protected $fillable = [
        'brand_id',
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
        'wdr_billing_checklist',
    ];

    protected $casts = [
        'total_net' => 'decimal:2',
        'total_vat' => 'decimal:2',
        'total_gross' => 'decimal:2',
        'voucher_date' => 'date',
        'meta' => 'array',
        'wdr_billing_checklist' => 'array',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Invoice $invoice): void {
            if (! Schema::hasTable('invoice_incoming_documents')) {
                return;
            }
            $disk = Storage::disk('local');
            InvoiceIncomingDocument::query()
                ->where('invoice_id', $invoice->id)
                ->get()
                ->each(function (InvoiceIncomingDocument $doc) use ($disk): void {
                    if ($doc->storage_path !== '' && $disk->exists($doc->storage_path)) {
                        $disk->delete($doc->storage_path);
                    }
                });
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
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

    public function incomingDocuments(): HasMany
    {
        return $this->hasMany(InvoiceIncomingDocument::class);
    }

    /**
     * WDR-typische Abrechnung (Medienhaus oder Redaktion erkennbar an Name).
     */
    public function isWdrBillingContext(): bool
    {
        $this->loadMissing('organization', 'product');
        $org = mb_strtolower((string) ($this->organization?->name ?? ''));
        $prod = mb_strtolower((string) ($this->product?->name ?? ''));

        return str_contains($org, 'wdr')
            || str_contains($prod, 'wdr')
            || str_contains($prod, 'newsroom');
    }

    /**
     * @return array{done: bool, completed_at: ?string, user_id: ?int}
     */
    public function getWdrChecklistEntry(string $key): array
    {
        $raw = (array) ($this->wdr_billing_checklist ?? []);
        $row = isset($raw[$key]) && is_array($raw[$key]) ? $raw[$key] : [];

        return [
            'done' => ! empty($row['done']),
            'completed_at' => isset($row['completed_at']) && is_string($row['completed_at']) ? $row['completed_at'] : null,
            'user_id' => isset($row['user_id']) ? (int) $row['user_id'] : null,
        ];
    }

    /**
     * Erfüllt, wenn Prüfpunkt gesetzt ODER passendes Dokument in der Eingangsablage liegt.
     */
    public function isWdrLizenzvertragSatisfied(): bool
    {
        if ($this->getWdrChecklistEntry('lizenzvertrag')['done']) {
            return true;
        }
        if (! Schema::hasTable('invoice_incoming_documents')) {
            return false;
        }

        return $this->incomingDocuments()
            ->where('document_type', InvoiceIncomingDocument::TYPE_LICENSE_CONTRACT)
            ->exists();
    }

    public function isWdrVerguetungsmitteilungSatisfied(): bool
    {
        if ($this->getWdrChecklistEntry('verguetungsmitteilung')['done']) {
            return true;
        }
        if (! Schema::hasTable('invoice_incoming_documents')) {
            return false;
        }

        return $this->incomingDocuments()
            ->where('document_type', InvoiceIncomingDocument::TYPE_REMUNERATION_NOTICE)
            ->exists();
    }

    /**
     * Stimmt Rechnungs-Netto mit der Summe der Nachverfolgungs-Einträge überein?
     * Nur für lokale Entwürfe ohne Lexware-Finalisierung; überschreibt total_net/total_vat/total_gross bei Abweichung.
     */
    public function syncTotalsFromUsageRecordsIfEditable(): bool
    {
        if ($this->lexware_invoice_id) {
            return false;
        }
        if (! in_array($this->status, ['draft', 'pending', 'ready_for_lexware'], true)) {
            return false;
        }
        $this->loadMissing('usageRecords');
        if ($this->usageRecords->isEmpty()) {
            return false;
        }
        $sumNet = round((float) $this->usageRecords->sum('total_amount'), 2);
        if (abs((float) $this->total_net - $sumNet) < 0.005) {
            return false;
        }
        $vatRate = (float) config('invoice.payment.vat_rate', 7);
        $vat = round($sumNet * $vatRate / 100, 2);
        $gross = round($sumNet + $vat, 2);
        $this->forceFill([
            'total_net' => $sumNet,
            'total_vat' => $vat,
            'total_gross' => $gross,
        ])->save();

        return true;
    }
}
