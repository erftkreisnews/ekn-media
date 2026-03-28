<?php

namespace App\Mail;

use App\Models\Delivery;
use App\Models\DeliveryDestination;
use App\Models\NewsItem;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class NewsDeliveryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public NewsItem $newsItem,
        public Delivery $delivery,
        public string $deliveryUrl,
        public ?DeliveryDestination $destination = null
    ) {}

    public function envelope(): Envelope
    {
        $title = trim((string) ($this->newsItem->title ?? ''));
        $title = Str::limit($title, 90, ''); // 90 Zeichen, ohne "..."
        $contact = (string) config('mail.contact_address', '');
        $replyTo = [];
        if ($contact !== '') {
            $replyTo[] = new Address($contact, (string) config('mail.from.name', ''));
        }

        return new Envelope(
            // Klarer Absender-Kontext im Betreff (kein Agentur-Feeling).
            subject: 'Alexander Franz | Medienangebot: '.$title.' (NewsID '.$this->newsItem->id.')',
            replyTo: $replyTo,
        );
    }

    public function content(): Content
    {
        $this->newsItem->loadMissing('media');
        $imagesCount = $this->newsItem->media->where('type', 'image')->count();
        $videosCount = $this->newsItem->media->where('type', 'video')->count();
        $audiosCount = $this->newsItem->media->where('type', 'audio')->count();

        $baseUrl = rtrim(config('app.url'), '/');
        $imageMedia = $this->newsItem->media->where('type', 'image')->take(12)->values();
        $teaserImages = $imageMedia->map(function ($media) use ($baseUrl) {
            $url = $media->preview_url ?? $media->url;
            $absoluteUrl = Str::startsWith($url, 'http') ? $url : $baseUrl.(str_starts_with($url, '/') ? '' : '/').$url;
            $w = (int) $media->width;
            $h = (int) $media->height;
            $orientation = 'landscape'; // Fallback
            if ($w > 0 && $h > 0) {
                if ($h > $w) {
                    $orientation = 'portrait';
                } elseif ($w > $h) {
                    $orientation = 'landscape';
                } else {
                    $orientation = 'square';
                }
            }

            $displayName = trim((string) ($media->display_name ?? ''));
            $caption = trim((string) ($media->caption ?? ''));
            $shortCaption = $caption !== '' ? Str::limit($caption, 80, ' …') : Str::limit($displayName, 80, ' …');

            return [
                'url' => $absoluteUrl,
                'alt' => $shortCaption !== '' ? $shortCaption : $displayName,
                'orientation' => $orientation,
                'id' => sprintf('%06d', $media->id),
                'caption' => $shortCaption,
            ];
        })->toArray();

        return new Content(
            view: 'emails.news-delivery',
            with: [
                'imagesCount' => $imagesCount,
                'videosCount' => $videosCount,
                'audiosCount' => $audiosCount,
                'teaserImages' => $teaserImages,
            ],
        );
    }
}
