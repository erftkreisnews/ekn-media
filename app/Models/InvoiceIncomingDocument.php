<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceIncomingDocument extends Model
{
    public const TYPE_CONTRACT = 'contract';

    public const TYPE_PAYMENT_INSTRUCTION = 'payment_instruction';

    /** Lizenzvertrag (WDR u. a.) */
    public const TYPE_LICENSE_CONTRACT = 'license_contract';

    /** Vergütungsmitteilung (WDR u. a.) */
    public const TYPE_REMUNERATION_NOTICE = 'remuneration_notice';

    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'invoice_id',
        'document_type',
        'original_filename',
        'mime_type',
        'size_bytes',
        'storage_path',
        'note',
        'uploaded_by',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getDocumentTypeLabel(): string
    {
        return match ($this->document_type) {
            self::TYPE_CONTRACT => 'Vertrag',
            self::TYPE_PAYMENT_INSTRUCTION => 'Zahlungsanweisung',
            self::TYPE_LICENSE_CONTRACT => 'Lizenzvertrag',
            self::TYPE_REMUNERATION_NOTICE => 'Vergütungsmitteilung',
            default => 'Sonstiges',
        };
    }
}
