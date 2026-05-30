<?php

namespace App\Services;

use App\Models\NewsItem;
use App\Models\NewsItemStatement;
use App\Models\NewsItemUpdate;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Chronologie für das Medienpaket: Erstmeldung, Updates und O-Töne nach Zeit sortiert.
 */
class DeliveryTimelineBuilder
{
    /**
     * @return Collection<int, array{
     *     kind: string,
     *     label: string,
     *     at: CarbonInterface,
     *     title: ?string,
     *     body: string,
     *     source: ?string,
     *     is_html: bool
     * }>
     */
    public function build(NewsItem $newsItem): Collection
    {
        $entries = collect();
        $latestUpdateId = $this->latestActiveUpdateId($newsItem);

        $erstmeldungAt = $newsItem->published_at
            ?? $newsItem->event_at
            ?? $this->firstDeliveryAt($newsItem)
            ?? $newsItem->created_at;

        if ($erstmeldungAt instanceof CarbonInterface) {
            $body = trim((string) ($newsItem->body ?? ''));
            $title = trim((string) ($newsItem->subheadline ?? ''));
            if ($body !== '' || $title !== '') {
                $entries->push([
                    'kind' => 'first_report',
                    'label' => 'Erstmeldung',
                    'at' => $erstmeldungAt,
                    'title' => $title !== '' ? $title : null,
                    'body' => $body,
                    'source' => null,
                    'is_html' => true,
                ]);
            }
        }

        if (Schema::hasTable('news_item_updates')) {
            foreach ($newsItem->updates->where('is_active', true) as $update) {
                $entry = $this->entryFromUpdate($update, $newsItem, $latestUpdateId);
                if ($entry['at'] instanceof CarbonInterface && $entry['body'] !== '') {
                    $entries->push($entry);
                }
            }
        }

        if (Schema::hasTable('news_item_statements')) {
            foreach ($newsItem->statements
                ->where('is_active', true)
                ->where('is_publishable', true) as $statement) {
                $entry = $this->entryFromStatement($statement);
                if ($entry['at'] instanceof CarbonInterface && $entry['body'] !== '') {
                    $entries->push($entry);
                }
            }
        }

        return $entries
            ->sortByDesc(fn (array $entry) => $entry['at']->timestamp)
            ->values();
    }

    private function firstDeliveryAt(NewsItem $newsItem): ?CarbonInterface
    {
        if (! Schema::hasTable('deliveries')) {
            return null;
        }

        $at = $newsItem->deliveries()->min('created_at');

        return $at instanceof CarbonInterface ? $at : null;
    }

    private function latestActiveUpdateId(NewsItem $newsItem): ?int
    {
        if (! Schema::hasTable('news_item_updates')) {
            return null;
        }

        $latest = $newsItem->updates
            ->where('is_active', true)
            ->sortByDesc(fn (NewsItemUpdate $u) => ($u->happened_at ?? $u->created_at)?->timestamp ?? 0)
            ->first();

        return $latest ? (int) $latest->id : null;
    }

    /**
     * @return array{
     *     kind: string,
     *     label: string,
     *     at: ?CarbonInterface,
     *     title: ?string,
     *     body: string,
     *     source: ?string,
     *     is_html: bool
     * }
     */
    private function entryFromUpdate(NewsItemUpdate $update, NewsItem $newsItem, ?int $latestUpdateId): array
    {
        $label = $update->type_label ?? 'Update zur Lage';
        if ((string) ($newsItem->update_type ?? '') === 'final' && $latestUpdateId !== null && (int) $update->id === $latestUpdateId) {
            $label = 'Abschlussmeldung';
        } elseif ($update->type === NewsItemUpdate::TYPE_CORRECTION) {
            $label = 'Korrektur';
        } elseif ($update->type === NewsItemUpdate::TYPE_SITUATION) {
            $label = 'Update zur Lage';
        }

        return [
            'kind' => 'update',
            'label' => $label,
            'at' => $update->happened_at ?? $update->created_at,
            'title' => filled($update->title) ? (string) $update->title : null,
            'body' => trim((string) $update->body),
            'source' => $update->display_source_line,
            'is_html' => false,
        ];
    }

    /**
     * @return array{
     *     kind: string,
     *     label: string,
     *     at: ?CarbonInterface,
     *     title: ?string,
     *     body: string,
     *     source: ?string,
     *     is_html: bool
     * }
     */
    private function entryFromStatement(NewsItemStatement $statement): array
    {
        $body = trim((string) ($statement->summary ?? ''));
        if ($body === '') {
            $body = trim((string) ($statement->transcript ?? ''));
        }

        $source = trim((string) ($statement->source_label ?? ''));
        if ($source === '' && $statement->source_type_label) {
            $source = (string) $statement->source_type_label;
        }

        return [
            'kind' => 'statement',
            'label' => 'O-Ton',
            'at' => $statement->received_at ?? $statement->created_at,
            'title' => null,
            'body' => $body,
            'source' => $source !== '' ? $source : null,
            'is_html' => false,
        ];
    }
}
