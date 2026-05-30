<?php

namespace App\Mail;

use App\Models\DeliveryDestination;
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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QuickMediaBatchDeliveryMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, NewsItemMedia>  $media
     */
    public function __construct(
        public Collection $media,
        public ?string $subjectOverride = null,
        public ?string $editorNote = null,
        public ?DeliveryDestination $destination = null
    ) {}

    public function envelope(): Envelope
    {
        $contact = (string) config('mail.contact_address', '');
        $replyTo = [];
        if ($contact !== '') {
            $replyTo[] = new Address($contact, (string) config('mail.from.name', ''));
        }

        $subject = trim((string) ($this->subjectOverride ?? ''));
        if ($subject === '') {
            $subject = DeliveryMailSubject::forQuickMedia($this->media->count().' Bilder (Sofortversand)');
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
            view: 'emails.quick-media-batch-delivery',
            with: [
                'media' => $this->media,
                'editorNote' => $this->editorNote,
                'destination' => $this->destination,
            ]
        );
    }

    public function attachments(): array
    {
        $attachments = [];
        foreach ($this->media as $item) {
            if (! $item instanceof NewsItemMedia || ! $item->isImage()) {
                continue;
            }
            $payload = $this->originalAttachmentPayload($item);
            if ($payload === null) {
                continue;
            }

            $attachments[] = Attachment::fromData(fn () => $payload['data'], $payload['name'])
                ->withMime($payload['mime']);
        }

        return $attachments;
    }

    /**
     * @return array{data: string, name: string, mime: string}|null
     */
    private function originalAttachmentPayload(NewsItemMedia $item): ?array
    {
        $relativePath = $item->resolveDeliveryDownloadRelativePath();
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
            Log::warning('quick_media_batch_delivery_mail.attachment_read_failed', [
                'media_id' => $item->id,
                'path' => $relativePath,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $name = trim((string) ($item->original_name ?? ''));
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
