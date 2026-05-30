<?php

namespace App\Observers;

use App\Models\NewsItem;
use App\Models\NewsItemFieldAudit;
use Illuminate\Support\Facades\Schema;

class NewsItemObserver
{
    public function created(NewsItem $newsItem): void
    {
        if (! $this->auditingEnabled()) {
            return;
        }

        $newValues = $this->snapshotTracked($newsItem);
        if ($newValues === []) {
            return;
        }

        $this->storeAudit($newsItem, NewsItemFieldAudit::EVENT_CREATED, null, $newValues);
    }

    public function updated(NewsItem $newsItem): void
    {
        if (! $this->auditingEnabled()) {
            return;
        }

        $oldValues = [];
        $newValues = [];

        foreach (NewsItemFieldAudit::TRACKED_FIELDS as $field) {
            if (! $newsItem->wasChanged($field)) {
                continue;
            }
            $oldValues[$field] = $this->serializeValue($field, $newsItem->getOriginal($field));
            $newValues[$field] = $this->serializeValue($field, $newsItem->getAttribute($field));
        }

        if ($oldValues === []) {
            return;
        }

        $this->storeAudit($newsItem, NewsItemFieldAudit::EVENT_UPDATED, $oldValues, $newValues);
    }

    private function auditingEnabled(): bool
    {
        return Schema::hasTable('news_item_field_audits');
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotTracked(NewsItem $newsItem): array
    {
        $out = [];
        foreach (NewsItemFieldAudit::TRACKED_FIELDS as $field) {
            $out[$field] = $this->serializeValue($field, $newsItem->getAttribute($field));
        }

        return $out;
    }

    private function serializeValue(string $field, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (in_array($field, ['published_at', 'embargo_at'], true)) {
            if ($value instanceof \Carbon\CarbonInterface) {
                return $value->toIso8601String();
            }
            try {
                return \Carbon\Carbon::parse((string) $value)->toIso8601String();
            } catch (\Throwable) {
                return (string) $value;
            }
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function storeAudit(NewsItem $newsItem, string $event, ?array $oldValues, ?array $newValues): void
    {
        $req = request();

        NewsItemFieldAudit::create([
            'news_item_id' => $newsItem->id,
            'user_id' => auth()->id(),
            'event' => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $req?->ip(),
            'user_agent' => $req ? (string) $req->userAgent() : null,
            'created_at' => now(),
        ]);
    }
}
