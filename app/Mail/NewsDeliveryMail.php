<?php

namespace App\Mail;

use App\Models\Delivery;
use App\Models\DeliveryDestination;
use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use App\Services\MediaStorage;
use App\Services\NewsDeliveryUpdateSummaryService;
use App\Support\DeliveryMailSubject;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

class NewsDeliveryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public NewsItem $newsItem,
        public Delivery $delivery,
        public string $deliveryUrl,
        public ?DeliveryDestination $destination = null,
        public bool $isUpdateDelivery = false,
        public ?string $deliveryPhase = null,
        public ?string $updateNote = null
    ) {}

    public function envelope(): Envelope
    {
        $title = trim((string) ($this->newsItem->title ?? ''));
        $contact = (string) config('mail.contact_address', '');
        $replyTo = [];
        if ($contact !== '') {
            $replyTo[] = new Address($contact, (string) config('mail.from.name', ''));
        }
        $phase = filled($this->deliveryPhase)
            ? (string) $this->deliveryPhase
            : ($this->isUpdateDelivery ? 'UPDATE' : 'ERSTMELDUNG');
        if ($this->newsItem->is_breaking) {
            $phase .= ' | BREAKING-NEWS';
        }
        $updateScanLine = null;
        if ($this->isUpdateDelivery) {
            $summary = app(NewsDeliveryUpdateSummaryService::class);
            $baselineAt = $this->delivery->update_baseline_delivery_at ?? $summary->baselineAt($this->newsItem);
            $updateScanLine = $summary->buildScanLine($this->newsItem, $baselineAt, true);
        }

        return new Envelope(
            subject: DeliveryMailSubject::forNewsDelivery($phase, $title, null, $updateScanLine),
            replyTo: $replyTo,
        );
    }

    public function content(): Content
    {
        $summary = app(NewsDeliveryUpdateSummaryService::class);
        $this->newsItem->loadMissing(['media', 'parentNewsItem']);
        $visibleMedia = $this->newsItem->media->filter(fn (NewsItemMedia $m) => $m->isVisibleInDeliveryPackage($this->delivery));
        $imagesCount = $visibleMedia->where('type', 'image')->count();
        $videosCount = $visibleMedia->where('type', 'video')->count();
        $audiosCount = $visibleMedia->where('type', 'audio')->count();
        $newVisibleMedia = $visibleMedia;
        $previousVisibleMedia = collect();
        if ($this->isUpdateDelivery && $this->delivery->update_baseline_delivery_at) {
            $baseline = $this->delivery->update_baseline_delivery_at;
            $newVisibleMedia = $visibleMedia
                ->filter(fn (NewsItemMedia $m) => $m->created_at && $m->created_at->gt($baseline))
                ->values();
            $previousVisibleMedia = $visibleMedia
                ->reject(fn (NewsItemMedia $m) => $m->created_at && $m->created_at->gt($baseline))
                ->values();
        }
        $newImagesCount = $newVisibleMedia->where('type', 'image')->count();
        $newVideosCount = $newVisibleMedia->where('type', 'video')->count();
        $newAudiosCount = $newVisibleMedia->where('type', 'audio')->count();
        $baselineAt = $this->isUpdateDelivery
            ? ($this->delivery->update_baseline_delivery_at ?? $summary->baselineAt($this->newsItem))
            : null;
        $mailStatements = $summary->mailStatements($this->newsItem, $baselineAt, $this->isUpdateDelivery);
        $mailUpdates = $summary->mailUpdates($this->newsItem, $baselineAt, $this->isUpdateDelivery);
        $resolvedUpdateNote = filled($this->updateNote)
            ? (string) $this->updateNote
            : ($this->isUpdateDelivery
                ? $summary->buildSummaryNote($this->newsItem, $baselineAt, true)
                : '');
        $updateMailIsPrimary = $this->isUpdateDelivery
            && ($mailUpdates->isNotEmpty() || $mailStatements->isNotEmpty());
        $updateScanLine = $this->isUpdateDelivery
            ? $summary->buildScanLine($this->newsItem, $baselineAt, true)
            : '';
        $updateMediaLine = $this->isUpdateDelivery
            ? $summary->buildUpdateMediaLine($this->newsItem, $baselineAt)
            : '';
        $updateHints = [];
        if ($this->isUpdateDelivery && ! $updateMailIsPrimary) {
            if ($newImagesCount > 0) {
                $updateHints[] = $newImagesCount === 1 ? '1 neues Foto' : $newImagesCount.' neue Fotos';
            }
            if ($newVideosCount > 0) {
                $updateHints[] = $newVideosCount === 1 ? '1 neues Video' : $newVideosCount.' neue Videos';
            }
            if ($newAudiosCount > 0) {
                $updateHints[] = $newAudiosCount === 1 ? '1 neues Audio' : $newAudiosCount.' neue Audios';
            }
            if ($updateHints === []) {
                $updateHints[] = 'Material-Update';
            }
        }
        if ($updateMailIsPrimary) {
            $resolvedUpdateNote = '';
        }
        $updateReferenceNewsId = $this->newsItem->parentNewsItem?->display_news_id ?? $this->newsItem->display_news_id;

        $baseUrl = rtrim(config('app.url'), '/');
        $teaserSourceMedia = $this->isUpdateDelivery ? $newVisibleMedia : $visibleMedia;
        $teaserLimit = $updateMailIsPrimary ? 4 : 12;
        $imageMedia = $teaserSourceMedia->where('type', 'image')->take($teaserLimit)->values();
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

            $thumbW = 128;
            $thumbH = null;
            if ($w > 0 && $h > 0) {
                $thumbH = max(1, (int) round($h * ($thumbW / $w)));
            }

            return [
                'url' => $absoluteUrl,
                'alt' => $shortCaption !== '' ? $shortCaption : $displayName,
                'orientation' => $orientation,
                'id' => sprintf('%06d', $media->id),
                'caption' => $shortCaption,
                'embed' => self::readMailTeaserEmbedPayload($media),
                'thumb_width' => $thumbW,
                'thumb_height' => $thumbH,
            ];
        })->toArray();

        return new Content(
            view: 'emails.news-delivery',
            with: [
                'imagesCount' => $imagesCount,
                'videosCount' => $videosCount,
                'audiosCount' => $audiosCount,
                'newImagesCount' => $newImagesCount,
                'newVideosCount' => $newVideosCount,
                'newAudiosCount' => $newAudiosCount,
                'hasPreviousVisibleMedia' => $previousVisibleMedia->isNotEmpty(),
                'teaserImages' => $teaserImages,
                'mailStatements' => $mailStatements,
                'mailUpdates' => $mailUpdates,
                'isUpdateDelivery' => $this->isUpdateDelivery,
                'updateHints' => $updateHints,
                'deliveryPhase' => $this->deliveryPhase,
                'updateNote' => $resolvedUpdateNote,
                'updateReferenceNewsId' => $updateReferenceNewsId,
                'updateMailIsPrimary' => $updateMailIsPrimary,
                'updateScanLine' => $updateScanLine,
                'updateMediaLine' => $updateMediaLine,
            ],
        );
    }

    /**
     * Kleines JPEG per CID einbetten: Outlook zeigt eingebettete Teile ohne „externe Bilder laden“.
     * Vorschau-Datei bevorzugt; anschließend Skalierung (max. Breite) für kleine, zuverlässige Anhänge.
     *
     * @return array{data: string, name: string, mime: string}|null
     */
    private static function readMailTeaserEmbedPayload(NewsItemMedia $media): ?array
    {
        if (! $media->isImage()) {
            return null;
        }

        $storage = app(MediaStorage::class);
        $previewPath = is_string($media->preview_path) ? trim($media->preview_path) : '';
        $mainPath = is_string($media->path) ? trim($media->path) : '';

        $path = null;
        if ($previewPath !== '' && $storage->exists($previewPath)) {
            $path = $previewPath;
        } elseif ($mainPath !== '' && $storage->exists($mainPath)) {
            $path = $mainPath;
        }

        if ($path === null) {
            return null;
        }

        $disk = $storage->activeDisk()->exists($path)
            ? $storage->activeDisk()
            : $storage->fallbackDisk();

        if (! $disk->exists($path)) {
            return null;
        }

        $maxReadBytes = 15 * 1024 * 1024;
        try {
            $size = $disk->size($path);
            if (! is_int($size) || $size <= 0 || $size > $maxReadBytes) {
                return null;
            }
            $raw = $disk->get($path);
        } catch (\Throwable $e) {
            Log::warning('news_delivery_mail.embed_read_failed', [
                'media_id' => $media->id,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $baseName = 'teaser-'.$media->id.'.jpg';

        try {
            $manager = ImageManager::gd();
            $image = $manager->read($raw);
            $image->scaleDown(width: 320);
            $jpeg = (string) $image->toJpeg(quality: 82);
            if ($jpeg === '') {
                return null;
            }

            return [
                'data' => $jpeg,
                'name' => $baseName,
                'mime' => 'image/jpeg',
            ];
        } catch (\Throwable $e) {
            Log::info('news_delivery_mail.embed_resize_fallback', [
                'media_id' => $media->id,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }

        $maxFallback = 4 * 1024 * 1024;
        if (strlen($raw) > $maxFallback) {
            return null;
        }

        $mime = null;
        if (extension_loaded('fileinfo')) {
            $detected = (new \finfo(FILEINFO_MIME_TYPE))->buffer($raw);
            if (is_string($detected) && str_starts_with($detected, 'image/')) {
                $mime = $detected;
            }
        }
        if ($mime === null) {
            $mime = match (strtolower((string) pathinfo($path, PATHINFO_EXTENSION))) {
                'png' => 'image/png',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                'jpg', 'jpeg' => 'image/jpeg',
                default => null,
            };
        }
        if ($mime === null) {
            return null;
        }

        $ext = match (true) {
            str_contains($mime, 'png') => 'png',
            str_contains($mime, 'webp') => 'webp',
            str_contains($mime, 'gif') => 'gif',
            default => 'jpg',
        };

        return [
            'data' => $raw,
            'name' => 'teaser-'.$media->id.'.'.$ext,
            'mime' => $mime,
        ];
    }
}
