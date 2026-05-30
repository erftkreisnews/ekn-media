<?php

namespace App\Mail;

use App\Models\Delivery;
use App\Models\DeliveryDestination;
use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use App\Services\MediaStorage;
use App\Support\DeliveryMailSubject;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QuickMediaDeliveryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ?NewsItem $newsItem,
        public NewsItemMedia $medium,
        public ?Delivery $delivery,
        public ?string $subjectOverride = null,
        public ?string $editorNote = null,
        public ?DeliveryDestination $destination = null
    ) {}

    public function envelope(): Envelope
    {
        $title = trim((string) ($this->newsItem?->title ?? ''));
        $title = Str::limit($title, 90, '');
        $contact = (string) config('mail.contact_address', '');
        $replyTo = [];
        if ($contact !== '') {
            $replyTo[] = new Address($contact, (string) config('mail.from.name', ''));
        }

        $subject = trim((string) ($this->subjectOverride ?? ''));
        if ($subject === '') {
            $subjectBase = $title !== '' ? $title : 'Mediathek';
            $subject = DeliveryMailSubject::forQuickMedia($subjectBase);
        } else {
            $subject = Str::limit($subject, 180, '');
        }

        return new Envelope(
            subject: $subject,
            replyTo: $replyTo,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.quick-media-delivery',
            with: [
                'newsItem' => $this->newsItem,
                'medium' => $this->medium,
                'editorNote' => $this->editorNote,
                'destination' => $this->destination,
            ],
        );
    }

    public function attachments(): array
    {
        $payload = $this->originalAttachmentPayload();
        if ($payload === null) {
            return [];
        }

        return [
            Attachment::fromData(fn () => $payload['data'], $payload['name'])
                ->withMime($payload['mime']),
        ];
    }

    /**
     * @return array{data: string, name: string, mime: string}|null
     */
    private function originalAttachmentPayload(): ?array
    {
        if (! $this->medium->isImage()) {
            return null;
        }

        $relativePath = $this->medium->resolveDeliveryDownloadRelativePath();
        if (! is_string($relativePath) || $relativePath === '') {
            return null;
        }

        $storage = app(MediaStorage::class);
        $disk = $storage->activeDisk()->exists($relativePath)
            ? $storage->activeDisk()
            : $storage->fallbackDisk();
        if (! $disk->exists($relativePath)) {
            return null;
        }

        try {
            $raw = $disk->get($relativePath);
        } catch (\Throwable $e) {
            Log::warning('quick_media_delivery_mail.attachment_read_failed', [
                'media_id' => $this->medium->id,
                'path' => $relativePath,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $name = trim((string) ($this->medium->original_name ?? ''));
        if ($name === '') {
            $name = basename($relativePath);
            if (! str_contains($name, '.')) {
                $name .= '.jpg';
            }
        }

        $mime = null;
        if (extension_loaded('fileinfo')) {
            $detected = (new \finfo(FILEINFO_MIME_TYPE))->buffer($raw);
            if (is_string($detected) && str_starts_with($detected, 'image/')) {
                $mime = $detected;
            }
        }
        if ($mime === null) {
            $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'png' => 'image/png',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                'jpg', 'jpeg' => 'image/jpeg',
                'tif', 'tiff' => 'image/tiff',
                default => 'application/octet-stream',
            };
        }

        return [
            'data' => $raw,
            'name' => $name,
            'mime' => $mime,
        ];
    }
}
