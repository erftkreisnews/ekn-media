<?php

namespace App\Services\EventDiscovery;

use App\Mail\EventSuggestionsDigestMail;
use App\Models\EventSuggestion;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class EventSuggestionSyncService
{
    public function __construct(
        private TicketmasterDiscoveryFetcher $ticketmaster,
        private RssEventSuggestionFetcher $rss,
        private LanxessArenaJsonFetcher $lanxessArena,
    ) {}

    /**
     * @return array{new_ids: array<int, int>, updated: int, pruned_ticketmaster: int, pruned_lanxess_arena: int}
     */
    public function syncRound(): array
    {
        if (! Schema::hasTable('event_suggestions')) {
            return ['new_ids' => [], 'updated' => 0, 'pruned_ticketmaster' => 0, 'pruned_lanxess_arena' => 0];
        }

        $fetchedAt = now();
        $newIds = [];
        $updated = 0;

        $ticketmasterExternalIdsThisRun = [];

        foreach ($this->ticketmaster->fetchEvents() as $eventRaw) {
            if (! is_array($eventRaw)) {
                continue;
            }

            $normalized = $this->ticketmaster->normalizeEvent($eventRaw);
            $extId = (string) ($normalized['external_id'] ?? '');
            if ($extId !== '') {
                $ticketmasterExternalIdsThisRun[$extId] = true;
            }

            $res = $this->upsert('ticketmaster', $normalized, $fetchedAt);
            if ($res['created']) {
                $newIds[] = $res['id'];
            }
            if ($res['touched']) {
                $updated++;
            }
        }

        $prunedTm = $this->pruneStalePendingSuggestions(
            'ticketmaster',
            array_keys($ticketmasterExternalIdsThisRun)
        );

        $lanxessExternalIdsThisRun = [];

        foreach ($this->lanxessArena->fetchNormalizedRows() as $row) {
            if (! is_array($row) || empty($row['external_id'])) {
                continue;
            }

            $lanxessExternalIdsThisRun[(string) $row['external_id']] = true;

            $res = $this->upsert('lanxess_arena', $row, $fetchedAt);
            if ($res['created']) {
                $newIds[] = $res['id'];
            }
            if ($res['touched']) {
                $updated++;
            }
        }

        $prunedLx = $this->pruneStalePendingSuggestions(
            'lanxess_arena',
            array_keys($lanxessExternalIdsThisRun)
        );

        $rssUrls = (array) config('event_discovery.rss_feed_urls', []);

        foreach ($this->rss->fetchFromFeeds($rssUrls) as $row) {
            if (! is_array($row) || empty($row['external_id'])) {
                continue;
            }

            $res = $this->upsert('rss', $row, $fetchedAt);
            if ($res['created']) {
                $newIds[] = $res['id'];
            }
            if ($res['touched']) {
                $updated++;
            }
        }

        return [
            'new_ids' => array_values(array_unique($newIds)),
            'updated' => $updated,
            'pruned_ticketmaster' => $prunedTm,
            'pruned_lanxess_arena' => $prunedLx,
        ];
    }

    /**
     * Entfernt veraltete pending-Vorschläge dieser Quelle, die beim aktuellen Abruf nicht mehr vorkommen
     * (z. B. nach Umstellung von „ganz DE“ auf Regional-Filter).
     *
     * @param  list<string>  $currentExternalIds
     */
    private function pruneStalePendingSuggestions(string $source, array $currentExternalIds): int
    {
        if ($currentExternalIds === []) {
            return 0;
        }

        return EventSuggestion::query()
            ->where('source', $source)
            ->where('status', EventSuggestion::STATUS_PENDING)
            ->whereNotIn('external_id', $currentExternalIds)
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{created: bool, touched: bool, id: int}
     */
    private function upsert(string $source, array $data, \DateTimeInterface $fetchedAt): array
    {
        $externalId = (string) ($data['external_id'] ?? '');
        if ($externalId === '') {
            return ['created' => false, 'touched' => false, 'id' => 0];
        }

        $existing = EventSuggestion::query()
            ->where('source', $source)
            ->where('external_id', $externalId)
            ->first();

        $payload = [
            'title' => (string) ($data['title'] ?? 'Ohne Titel'),
            'description' => isset($data['description']) ? (string) $data['description'] : null,
            'info_url' => isset($data['info_url']) ? (string) $data['info_url'] : null,
            'image_url' => isset($data['image_url']) ? (string) $data['image_url'] : null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'venue_name' => isset($data['venue_name']) ? (string) $data['venue_name'] : null,
            'venue_street' => isset($data['venue_street']) ? (string) $data['venue_street'] : null,
            'venue_postal_code' => isset($data['venue_postal_code']) ? (string) $data['venue_postal_code'] : null,
            'venue_city' => isset($data['venue_city']) ? (string) $data['venue_city'] : null,
            'venue_state' => isset($data['venue_state']) ? (string) $data['venue_state'] : null,
            'venue_country' => (string) ($data['venue_country'] ?? 'Deutschland'),
            'venue_country_code' => strtoupper(substr((string) ($data['venue_country_code'] ?? 'DE'), 0, 2)),
            'category' => isset($data['category']) ? (string) $data['category'] : null,
            'raw_payload' => is_array($data['raw_payload'] ?? null) ? $data['raw_payload'] : null,
            'fetched_at' => $fetchedAt,
        ];

        if ($existing instanceof EventSuggestion) {
            if ($existing->status !== EventSuggestion::STATUS_PENDING) {
                $existing->forceFill(['fetched_at' => $fetchedAt])->save();

                return ['created' => false, 'touched' => true, 'id' => (int) $existing->id];
            }

            $existing->fill($payload)->save();

            return ['created' => false, 'touched' => true, 'id' => (int) $existing->id];
        }

        $created = EventSuggestion::query()->create(array_merge([
            'source' => $source,
            'external_id' => $externalId,
            'status' => EventSuggestion::STATUS_PENDING,
        ], $payload));

        return ['created' => true, 'touched' => true, 'id' => (int) $created->id];
    }

    /**
     * @param  array<int, int>  $newSuggestionIds
     */
    public function sendDigestMail(array $newSuggestionIds): void
    {
        $ids = array_values(array_filter(array_map('intval', $newSuggestionIds), fn ($id) => $id > 0));
        if ($ids === []) {
            return;
        }

        $recipients = (array) config('event_discovery.mail_recipients', []);
        $recipients = array_values(array_filter($recipients, fn ($mail) => is_string($mail) && filter_var($mail, FILTER_VALIDATE_EMAIL)));

        if ($recipients === []) {
            return;
        }

        $suggestions = EventSuggestion::query()->whereIn('id', $ids)->orderBy('starts_at')->get();
        if ($suggestions->isEmpty()) {
            return;
        }

        $reviewUrl = url(route('admin.event-planning.index', [], false));

        foreach ($recipients as $to) {
            Mail::to($to)->send(new EventSuggestionsDigestMail($suggestions, $reviewUrl));
        }
    }
}
