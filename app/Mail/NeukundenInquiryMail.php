<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class NeukundenInquiryMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{medienhaus: string, redaktion: string, name?: string|null, email: string, phone?: string|null, message?: string|null}  $payload
     */
    public function __construct(
        public array $payload,
        public string $requestIp,
        public ?string $userAgent
    ) {}

    public function envelope(): Envelope
    {
        $replyName = trim((string) ($this->payload['name'] ?? ''));
        if ($replyName === '') {
            $replyName = trim($this->payload['medienhaus'].' · '.$this->payload['redaktion']);
        }

        $replyTo = new Address($this->payload['email'], $replyName);

        return new Envelope(
            subject: 'Medienportal: Neukunden-Anfrage – '.$this->payload['medienhaus'].' · '.$this->payload['redaktion'],
            replyTo: [$replyTo],
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            text: [
                'X-EKN-Form' => 'neukunden-inquiry',
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.neukunden-inquiry',
            with: [
                'payload' => $this->payload,
                'requestIp' => $this->requestIp,
                'userAgent' => $this->userAgent,
            ],
        );
    }
}
