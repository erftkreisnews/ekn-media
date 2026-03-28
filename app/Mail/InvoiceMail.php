<?php

namespace App\Mail;

use App\Models\Invoice;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public string $pdfContent,
        public string $pdfFilename,
    ) {}

    public function envelope(): Envelope
    {
        $invoiceNumber = trim((string) ($this->invoice->voucher_number ?? ''));
        $mailConfig = config('invoice_mail', []);

        return new Envelope(
            from: new Address(
                (string) ($mailConfig['from_address'] ?? 'rechnung@erftkreis-news.de'),
                (string) ($mailConfig['from_name'] ?? 'Erftkreis News Rechnung')
            ),
            subject: $invoiceNumber !== ''
                ? 'Rechnung '.$invoiceNumber
                : 'Ihre Rechnung von '.config('invoice.sender.name', config('app.name', 'EKN')),
        );
    }

    public function headers(): Headers
    {
        $mailConfig = config('invoice_mail', []);

        return new Headers(
            text: [
                'X-EKN-Mail-Stream' => (string) ($mailConfig['header_stream'] ?? 'invoice'),
                'X-EKN-Mail-Source' => (string) ($mailConfig['header_source'] ?? 'laravel-billing'),
                'X-EKN-Invoice-Id' => (string) $this->invoice->getKey(),
                'X-EKN-Invoice-Number' => (string) ($this->invoice->voucher_number ?? ''),
            ],
        );
    }

    public function content(): Content
    {
        $paymentDays = (int) config('invoice.payment.days', 7);
        $dueDate = $this->invoice->voucher_date instanceof CarbonInterface
            ? $this->invoice->voucher_date->copy()->addDays($paymentDays)
            : null;

        return new Content(
            view: 'emails.invoice',
            with: [
                'invoice' => $this->invoice,
                'senderName' => config('invoice.sender.name', config('app.name', 'EKN')),
                'paymentDays' => $paymentDays,
                'dueDate' => $dueDate,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfContent, $this->pdfFilename)
                ->withMime('application/pdf'),
        ];
    }
}
