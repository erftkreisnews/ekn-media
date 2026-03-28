<?php

namespace App\Mail;

use App\Models\UsageRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UsageRecordCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public UsageRecord $record,
    ) {}

    public function envelope(): Envelope
    {
        $newsId = null;
        try {
            $newsId = $this->record->newsItem?->id;
        } catch (\Throwable) {
            // Ignorieren: Beziehungen werden im content() geladen.
        }

        $subject = 'EKN | Neuer Nutzungs-Eintrag';
        if ($newsId) {
            $subject .= ' (NewsID '.$newsId.')';
        }

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $this->record->loadMissing(['newsItem', 'organization', 'product', 'creator']);

        return new Content(
            view: 'emails.usage-record-created',
            with: [
                'record' => $this->record,
                'newsItem' => $this->record->newsItem,
                'organization' => $this->record->organization,
                'product' => $this->record->product,
                'creator' => $this->record->creator,
                'adminEditUrl' => route('admin.backoffice.usage.edit', $this->record),
            ],
        );
    }
}
