<?php

namespace App\Mail;

use App\Models\DeliveryDestination;
use App\Models\DeliveryRun;
use App\Models\NewsItem;
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
        $wdrTransferNotice = $this->buildWdrTransferNotice();

        return new Content(
            view: 'emails.delivery-run-finished',
            with: [
                'wdrTransferNotice' => $wdrTransferNotice,
            ],
        );
    }

    /**
     * Zusatzhinweis für den Newsroom bei erfolgreichem WDR-Transfer.
     *
     * @return array{
     *   host: string,
     *   news_ids: array<int, int>,
     *   moids: array<int, string>,
     *   paths: array<int, string>
     * }|null
     */
    private function buildWdrTransferNotice(): ?array
    {
        if (($this->run->status ?? '') !== 'success') {
            return null;
        }

        if (! $this->destination->isWdrOrganizationDestination()) {
            return null;
        }

        $host = trim((string) ($this->destination->getHostOrConfig() ?? ''));
        if ($host === '') {
            return null;
        }

        $this->run->loadMissing('items.media.newsItem');

        $successItems = $this->run->items
            ->where('status', 'success')
            ->filter(fn ($item) => $item->media !== null);

        if ($successItems->isEmpty()) {
            return null;
        }

        $newsItems = $successItems
            ->map(fn ($item) => $item->media->newsItem)
            ->filter(fn ($newsItem) => $newsItem instanceof NewsItem)
            ->unique('id')
            ->values();

        if ($newsItems->isEmpty()) {
            return null;
        }

        $remoteBasePath = $this->destination->getRemotePathOrConfig();
        $paths = $newsItems
            ->map(function (NewsItem $newsItem) use ($remoteBasePath): string {
                $subfolder = '';
                if ($this->destination->usesFtpSubfolderPerNewsItem()) {
                    $subfolder = $this->destination->usesEknLiveFolderFormat()
                        ? (string) $newsItem->ftpEknLiveFolderName()
                        : (string) ($newsItem->wdr_subfolder_name ?: ('Meldung_'.$newsItem->id));
                }

                return $this->buildRemotePath($remoteBasePath, $subfolder);
            })
            ->unique()
            ->values()
            ->all();

        return [
            'host' => $host,
            'news_ids' => $newsItems->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'moids' => $newsItems
                ->pluck('moid')
                ->map(fn ($moid) => trim((string) $moid))
                ->filter(fn (string $moid) => $moid !== '')
                ->unique()
                ->values()
                ->all(),
            'paths' => $paths,
        ];
    }

    private function buildRemotePath(string $basePath, string $subfolder): string
    {
        $base = trim($basePath);
        $sub = trim($subfolder);
        $combined = trim($base, '/');

        if ($sub !== '') {
            $combined = $combined === '' ? trim($sub, '/') : $combined.'/'.trim($sub, '/');
        }

        if ($combined === '') {
            return '/';
        }

        return '/'.$combined;
    }
}
