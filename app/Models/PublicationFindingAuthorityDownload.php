<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicationFindingAuthorityDownload extends Model
{
    protected $fillable = [
        'media_publication_finding_id',
        'ip_hash',
        'ua_hash',
        'recipient_label',
    ];

    public function finding(): BelongsTo
    {
        return $this->belongsTo(MediaPublicationFinding::class, 'media_publication_finding_id');
    }
}
