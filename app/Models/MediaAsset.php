<?php

namespace App\Models;

/**
 * Schlanker Alias für NewsItemMedia, um den neuen Upload-Workflow
 * unter dem generischen Namen „MediaAsset“ nutzen zu können, ohne
 * das bestehende Modell oder die Datenbankstruktur zu verändern.
 */
class MediaAsset extends NewsItemMedia
{
    /**
     * Tabelle explizit setzen, damit das Modell eindeutig ist,
     * auch falls der Basis-Klassenname irgendwann geändert würde.
     */
    protected $table = 'news_item_media';

    /**
     * Vereinfachter, abgeleiteter Status für die Verarbeitung.
     *
     * Für neue Uploads kann der API-Endpunkt „queued“ direkt zurückgeben.
     * Dieses Feld hilft optional beim späteren Auslesen des Status, ohne
     * die DB-Struktur anzupassen.
     */
    public function getProcessingStatusAttribute(): string
    {
        if ($this->ai_status === 'running') {
            return 'processing';
        }

        if (
            ($this->ai_status === 'done')
            || ($this->isImage() && $this->redaction_status === self::REDACTION_DONE)
        ) {
            return 'done';
        }

        if (
            $this->ai_status === 'error'
            || ($this->isImage() && $this->redaction_status === self::REDACTION_FAILED)
        ) {
            return 'failed';
        }

        return 'queued';
    }
}
