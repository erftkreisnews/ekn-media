<?php

namespace App\Mail;

use App\Models\Delivery;
use App\Models\NewsItemMedia;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class DeliveryDownloadNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Delivery $delivery,
        public NewsItemMedia $media,
    ) {}

    public function envelope(): Envelope
    {
        $newsItem = $this->delivery->newsItem;
        $title = Str::limit(trim((string) ($newsItem->title ?? '—')), 60, '…');
        $fileHint = $this->media->delivery_activity_stored_file_name
            ?? $this->media->delivery_activity_label;

        return new Envelope(
            subject: 'EKN | Download: '.$title.' · '.Str::limit($fileHint, 80, '…'),
        );
    }

    public function content(): Content
    {
        $this->delivery->loadMissing('newsItem');

        return new Content(
            view: 'emails.delivery-download-notification',
            with: [
                'delivery' => $this->delivery,
                'media' => $this->media,
                'newsItem' => $this->delivery->newsItem,
                'label' => $this->media->delivery_activity_label,
                'fileName' => $this->media->delivery_activity_stored_file_name,
                'adminActivityUrl' => route('admin.deliveries.show', $this->delivery),
            ],
        );
    }
}
