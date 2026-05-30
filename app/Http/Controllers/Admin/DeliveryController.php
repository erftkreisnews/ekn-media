<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryEvent;
use App\Models\DeliveryRun;
use App\Models\NewsItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DeliveryController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $isAdmin = $user && $user->hasRole('admin');

        $newsItemId = $request->integer('news_item_id');
        $newsItem = $newsItemId > 0 ? NewsItem::find($newsItemId) : null;
        if ($newsItem && ! $isAdmin && (int) $newsItem->author_id !== (int) $user?->id) {
            abort(403, 'Sie dürfen nur eigene Beiträge sehen.');
        }

        $scope = $request->string('scope')->lower()->value() === 'archive' ? 'archive' : 'active';

        $query = Delivery::with(['newsItem', 'organization', 'product', 'createdByUser'])
            ->orderByDesc('created_at');

        $selectedBrandId = $this->selectedAdminBrandId($request);
        if ($selectedBrandId !== null && Schema::hasTable('deliveries') && Schema::hasColumn('deliveries', 'brand_id')) {
            $query->where('brand_id', $selectedBrandId);
        }

        if (! $isAdmin) {
            $query->whereHas('newsItem', function ($q) use ($user): void {
                $q->where('author_id', $user?->id);
            });
        }

        if ($newsItem !== null) {
            $query->where('news_item_id', $newsItem->id);
        } else {
            $threshold = now()->subHours(48);
            if ($scope === 'archive') {
                $query->where('expires_at', '<', $threshold);
            } else {
                $query->where('expires_at', '>=', $threshold);
            }
        }

        $deliveries = $query->paginate(20)->withQueryString();

        $deliveryRuns = DeliveryRun::with(['deliveryDestination.organization', 'items.media.newsItem'])
            ->orderByDesc('created_at')
            ->limit($newsItem !== null ? 10 : 20)
            ->get();

        if (! $isAdmin) {
            $deliveryRuns = $deliveryRuns->filter(function (DeliveryRun $run) use ($user) {
                return $run->items->contains(fn ($item) => $item->media && $item->media->newsItem && (int) $item->media->newsItem->author_id === (int) $user?->id);
            })->values();
        }

        if ($newsItem !== null) {
            $deliveryRuns = $deliveryRuns->filter(function (DeliveryRun $run) use ($newsItem) {
                return $run->items->contains(fn ($item) => $item->media && $item->media->news_item_id == $newsItem->id);
            })->values();
        }

        return view('admin.deliveries.index', compact('deliveries', 'deliveryRuns', 'newsItem', 'scope'));
    }

    public function show(Delivery $delivery): View
    {
        $this->abortIfCannotAccessDelivery($delivery);

        $delivery->load([
            'newsItem',
            'organization',
            'product',
            'events' => fn ($q) => $q->with(['media', 'organization', 'product'])->orderBy('created_at'),
        ]);

        $activity = $this->deliveryActivityPresentation($delivery);

        return view('admin.deliveries.show', compact('delivery', 'activity'));
    }

    /**
     * Liefert chronologische Nicht-Download-Ereignisse sowie zusammengefasste Download-Gruppen
     * nach Medientyp (Video, Bild, Audio).
     *
     * @return array{
     *     timeline: Collection<int, DeliveryEvent>,
     *     downloads: array{
     *         video: list<array{count: int, first_at: \Carbon\Carbon, last_at: \Carbon\Carbon, event: DeliveryEvent}>,
     *         image: list<array{count: int, first_at: \Carbon\Carbon, last_at: \Carbon\Carbon, event: DeliveryEvent}>,
     *         audio: list<array{count: int, first_at: \Carbon\Carbon, last_at: \Carbon\Carbon, event: DeliveryEvent}>,
     *         other: list<array{count: int, first_at: \Carbon\Carbon, last_at: \Carbon\Carbon, event: DeliveryEvent}>,
     *     }
     * }
     */
    private function deliveryActivityPresentation(Delivery $delivery): array
    {
        /** @var Collection<int, DeliveryEvent> $events */
        $events = $delivery->events;

        $timeline = $events->filter(fn (DeliveryEvent $e) => $e->event_type !== 'download')->values();

        $downloads = $events->filter(fn (DeliveryEvent $e) => $e->event_type === 'download');

        $groups = $downloads->groupBy(function (DeliveryEvent $e): string {
            if ($e->media_id) {
                return 'm:'.$e->media_id;
            }

            $type = (string) ($e->download_media_type ?? $e->media?->type ?? '');
            $label = (string) ($e->download_media_label ?? '');
            $file = (string) ($e->download_media_file_name ?? '');

            return 's:'.md5($type.'|'.$label.'|'.$file);
        });

        $buckets = [
            'video' => [],
            'image' => [],
            'audio' => [],
            'other' => [],
        ];

        foreach ($groups as $group) {
            /** @var Collection<int, DeliveryEvent> $group */
            $sorted = $group->sortBy(fn (DeliveryEvent $e) => $e->created_at?->timestamp ?? 0);
            $first = $sorted->first();
            $last = $sorted->last();
            if ($first === null || $last === null) {
                continue;
            }

            $representative = $sorted->sortByDesc(fn (DeliveryEvent $e) => $e->created_at?->timestamp ?? 0)->first();
            if ($representative === null) {
                continue;
            }

            $snapType = $representative->download_media_type ?? $representative->media?->type;
            $bucket = match ($snapType) {
                'video' => 'video',
                'image' => 'image',
                'audio' => 'audio',
                default => 'other',
            };

            $buckets[$bucket][] = [
                'count' => $group->count(),
                'first_at' => $first->created_at,
                'last_at' => $last->created_at,
                'event' => $representative,
            ];
        }

        $labelSort = static function (array $a, array $b): int {
            /** @var DeliveryEvent $ea */
            $ea = $a['event'];
            /** @var DeliveryEvent $eb */
            $eb = $b['event'];
            $la = $ea->download_media_label
                ?? ($ea->media ? $ea->media->delivery_activity_label : null)
                ?? '';
            $lb = $eb->download_media_label
                ?? ($eb->media ? $eb->media->delivery_activity_label : null)
                ?? '';

            return strcasecmp((string) $la, (string) $lb);
        };

        foreach (['video', 'image', 'audio', 'other'] as $key) {
            usort($buckets[$key], $labelSort);
        }

        return [
            'timeline' => $timeline,
            'downloads' => $buckets,
        ];
    }

    public function revoke(Delivery $delivery): RedirectResponse
    {
        $this->abortIfCannotAccessDelivery($delivery);

        if ($delivery->revoked_at === null) {
            $delivery->revoked_at = now();
            $delivery->save();

            DeliveryEvent::create([
                'delivery_id' => $delivery->id,
                'event_type' => 'revoked',
                'created_at' => now(),
            ]);
        }

        return redirect()->back()->with('status', 'Versand wurde widerrufen.');
    }

    private function abortIfCannotAccessDelivery(Delivery $delivery): void
    {
        $user = auth()->user();
        if ($user && $user->hasRole('admin')) {
            return;
        }

        $newsItem = $delivery->relationLoaded('newsItem')
            ? $delivery->newsItem
            : $delivery->newsItem()->first();

        if (! $newsItem || (int) $newsItem->author_id !== (int) auth()->id()) {
            abort(403, 'Sie dürfen nur eigenen Versand sehen.');
        }
    }

    private function selectedAdminBrandId(Request $request): ?int
    {
        $raw = $request->session()->get('admin.brand_filter');
        if (is_int($raw)) {
            return $raw;
        }
        if (is_string($raw) && ctype_digit($raw)) {
            return (int) $raw;
        }

        return null;
    }
}
