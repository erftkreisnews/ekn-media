<?php

namespace App\Services;

use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use App\Models\NewsItemStatement;
use App\Models\NewsItemUpdate;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Ermittelt „Was ist neu?“ für Update-Mails – eine Quelle für Versand-Vorschau, Kurznotiz und Mail-Inhalt.
 */
class NewsDeliveryUpdateSummaryService
{
    public function baselineAt(NewsItem $newsItem): ?CarbonInterface
    {
        if (! Schema::hasTable('deliveries')) {
            return null;
        }

        $at = $newsItem->deliveries()->max('created_at');

        return $at instanceof CarbonInterface ? $at : null;
    }

    public function isUpdateContext(NewsItem $newsItem, ?bool $requestFlag = null, ?string $requestContext = null): bool
    {
        return $newsItem->shouldTreatDispatchAsUpdate($requestFlag, $requestContext);
    }

    /**
     * @return array{images: int, videos: int, audios: int}
     */
    public function newMediaCounts(NewsItem $newsItem, ?CarbonInterface $baselineAt): array
    {
        $newsItem->loadMissing('media');
        $visible = $newsItem->media->filter(fn (NewsItemMedia $m) => (bool) $m->versand);

        if ($baselineAt === null) {
            return [
                'images' => $visible->where('type', 'image')->count(),
                'videos' => $visible->where('type', 'video')->count(),
                'audios' => $visible->where('type', 'audio')->count(),
            ];
        }

        $new = $visible->filter(
            fn (NewsItemMedia $m) => $m->created_at && $m->created_at->gt($baselineAt)
        );

        return [
            'images' => $new->where('type', 'image')->count(),
            'videos' => $new->where('type', 'video')->count(),
            'audios' => $new->where('type', 'audio')->count(),
        ];
    }

    /**
     * @return Collection<int, NewsItemUpdate>
     */
    public function mailUpdates(NewsItem $newsItem, ?CarbonInterface $baselineAt, bool $isUpdateDelivery): Collection
    {
        if (! Schema::hasTable('news_item_updates')) {
            return collect();
        }

        $newsItem->loadMissing([
            'updates' => fn ($q) => $q->latest('happened_at')->latest('id'),
        ]);

        return $newsItem->updates
            ->where('is_active', true)
            ->filter(function (NewsItemUpdate $update) use ($baselineAt, $isUpdateDelivery) {
                if (! $isUpdateDelivery) {
                    return (bool) $update->show_in_mail;
                }

                return $this->isNewSinceBaseline($update->created_at, $baselineAt);
            })
            ->values();
    }

    /**
     * @return Collection<int, NewsItemStatement>
     */
    public function mailStatements(NewsItem $newsItem, ?CarbonInterface $baselineAt, bool $isUpdateDelivery): Collection
    {
        if (! Schema::hasTable('news_item_statements')) {
            return collect();
        }

        $newsItem->loadMissing([
            'statements' => fn ($q) => $q->latest('received_at')->latest('id'),
        ]);

        return $newsItem->statements
            ->where('is_active', true)
            ->where('is_publishable', true)
            ->filter(function (NewsItemStatement $statement) use ($baselineAt, $isUpdateDelivery) {
                if (! $isUpdateDelivery) {
                    return (bool) $statement->show_in_mail;
                }

                return $this->isNewSinceBaseline($statement->received_at ?? $statement->created_at, $baselineAt);
            })
            ->values();
    }

    /**
     * Eine Zeile für Betreff und Mail-Kopf (Newsroom-Scan).
     */
    public function buildScanLine(NewsItem $newsItem, ?CarbonInterface $baselineAt, bool $isUpdateDelivery): string
    {
        if (! $isUpdateDelivery) {
            return '';
        }

        $parts = ['ID '.$newsItem->display_news_id];
        $media = $this->newMediaCounts($newsItem, $baselineAt);
        if ($media['images'] > 0) {
            $parts[] = '+'.$media['images'].' Fotos';
        }
        if ($media['videos'] > 0) {
            $parts[] = '+'.$media['videos'].' Videos';
        }
        if ($media['audios'] > 0) {
            $parts[] = '+'.$media['audios'].' Audio';
        }

        $updates = $this->mailUpdates($newsItem, $baselineAt, true);
        if ($updates->isNotEmpty()) {
            $parts[] = $updates->count() === 1 ? 'neuer Stand' : $updates->count().' Stände';
        }

        $statements = $this->mailStatements($newsItem, $baselineAt, true);
        if ($statements->isNotEmpty()) {
            $parts[] = $statements->count() === 1 ? 'O-Ton' : $statements->count().' O-Töne';
        }

        return implode(' · ', $parts);
    }

    /**
     * Nur Medien-Neuigkeiten für eine Kontextzeile in der Mail (ohne ID/Stand – das steht separat).
     */
    public function buildUpdateMediaLine(NewsItem $newsItem, ?CarbonInterface $baselineAt): string
    {
        $parts = [];
        $media = $this->newMediaCounts($newsItem, $baselineAt);
        if ($media['images'] > 0) {
            $parts[] = '+'.$media['images'].' '.($media['images'] === 1 ? 'Foto' : 'Fotos');
        }
        if ($media['videos'] > 0) {
            $parts[] = '+'.$media['videos'].' '.($media['videos'] === 1 ? 'Video' : 'Videos');
        }
        if ($media['audios'] > 0) {
            $parts[] = '+'.$media['audios'].' Audio';
        }

        return implode(' · ', $parts);
    }

    public function buildSummaryNote(NewsItem $newsItem, ?CarbonInterface $baselineAt, bool $isUpdateDelivery): string
    {
        if (! $isUpdateDelivery) {
            return '';
        }

        $parts = [];
        $media = $this->newMediaCounts($newsItem, $baselineAt);
        if ($media['images'] > 0) {
            $parts[] = $media['images'] === 1 ? '1 neues Foto' : $media['images'].' neue Fotos';
        }
        if ($media['videos'] > 0) {
            $parts[] = $media['videos'] === 1 ? '1 neues Video' : $media['videos'].' neue Videos';
        }
        if ($media['audios'] > 0) {
            $parts[] = $media['audios'] === 1 ? '1 neues Audio' : $media['audios'].' neue Audios';
        }

        $updates = $this->mailUpdates($newsItem, $baselineAt, true);
        if ($updates->isNotEmpty()) {
            $parts[] = $updates->count() === 1
                ? '1 neuer Einsatz-Stand'
                : $updates->count().' neue Einsatz-Stände';
        }

        $statements = $this->mailStatements($newsItem, $baselineAt, true);
        if ($statements->isNotEmpty()) {
            $parts[] = $statements->count() === 1
                ? '1 neuer O-Ton'
                : $statements->count().' neue O-Töne';
        }

        if ($parts === []) {
            return 'Inhaltliche Ergänzung zur Erstmeldung';
        }

        return implode(' · ', $parts);
    }

    /**
     * @return array{
     *     is_update_delivery: bool,
     *     baseline_at: ?CarbonInterface,
     *     suggested_note: string,
     *     new_media: array{images: int, videos: int, audios: int},
     *     mail_updates: Collection,
     *     mail_statements: Collection,
     *     has_primary_content: bool,
     * }
     */
    public function preview(NewsItem $newsItem, ?bool $requestFlag = null, ?string $requestContext = null): array
    {
        $isUpdateDelivery = $this->isUpdateContext($newsItem, $requestFlag, $requestContext);
        $baselineAt = $isUpdateDelivery ? $this->baselineAt($newsItem) : null;
        $mailUpdates = $this->mailUpdates($newsItem, $baselineAt, $isUpdateDelivery);
        $mailStatements = $this->mailStatements($newsItem, $baselineAt, $isUpdateDelivery);

        return [
            'is_update_delivery' => $isUpdateDelivery,
            'baseline_at' => $baselineAt,
            'suggested_note' => $this->buildSummaryNote($newsItem, $baselineAt, $isUpdateDelivery),
            'new_media' => $this->newMediaCounts($newsItem, $baselineAt),
            'mail_updates' => $mailUpdates,
            'mail_statements' => $mailStatements,
            'has_primary_content' => $isUpdateDelivery
                && ($mailUpdates->isNotEmpty() || $mailStatements->isNotEmpty()),
        ];
    }

    private function isNewSinceBaseline(?CarbonInterface $timestamp, ?CarbonInterface $baselineAt): bool
    {
        if ($baselineAt === null) {
            return true;
        }
        if ($timestamp === null) {
            return false;
        }

        return $timestamp->gt($baselineAt);
    }
}
