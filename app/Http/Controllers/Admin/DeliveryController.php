<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryEvent;
use App\Models\DeliveryRun;
use App\Models\NewsItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeliveryController extends Controller
{
    public function index(Request $request): View
    {
        $newsItemId = $request->integer('news_item_id');
        $newsItem = $newsItemId > 0 ? NewsItem::find($newsItemId) : null;

        $scope = $request->string('scope')->lower()->value() === 'archive' ? 'archive' : 'active';

        $query = Delivery::with(['newsItem', 'organization', 'product', 'createdByUser'])
            ->orderByDesc('created_at');

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

        if ($newsItem !== null) {
            $deliveryRuns = $deliveryRuns->filter(function (DeliveryRun $run) use ($newsItem) {
                return $run->items->contains(fn ($item) => $item->media && $item->media->news_item_id == $newsItem->id);
            })->values();
        }

        return view('admin.deliveries.index', compact('deliveries', 'deliveryRuns', 'newsItem', 'scope'));
    }

    public function show(Delivery $delivery): View
    {
        $delivery->load([
            'newsItem',
            'organization',
            'product',
            'events' => fn ($q) => $q->with(['media', 'organization', 'product'])->orderBy('created_at'),
        ]);

        return view('admin.deliveries.show', compact('delivery'));
    }

    public function revoke(Delivery $delivery): RedirectResponse
    {
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
}
