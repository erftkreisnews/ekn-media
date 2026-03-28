<?php

namespace App\Mail;

use App\Models\DeliveryDestination;
use App\Models\DeliveryRun;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DeliveryRunFinishedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public DeliveryRun $run,
        public DeliveryDestination $destination
    ) {}

    public function envelope(): Envelope
    {
        $status = $this->run->status ?? 'unknown';
        $subject = 'FTP-Upload '.strtoupper($status).' – '.($this->destination->label ?? 'Versandziel');

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.delivery-run-finished',
        );
    }
}
