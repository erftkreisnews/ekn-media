<?php

namespace App\Http\Controllers\KoelnImage;

use App\Http\Controllers\Controller;
use App\Models\PlannedEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $range = (string) $request->query('range', 'season');
        $status = (string) $request->query('status', 'active');
        $sort = (string) $request->query('sort', 'date_desc');

        if (! in_array($range, ['all', 'today', 'week', 'season'], true)) {
            $range = 'season';
        }
        if (! in_array($status, ['active', 'all'], true)) {
            $status = 'active';
        }
        if (! in_array($sort, ['date_desc', 'date_asc', 'name_asc', 'name_desc'], true)) {
            $sort = 'date_desc';
        }

        $today = Carbon::today();
        $weekStart = $today->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $today->copy()->endOfWeek(Carbon::SUNDAY);
        $seasonStart = $today->copy()->startOfYear();

        $applyRange = function ($query, string $selectedRange) use ($today, $weekStart, $weekEnd, $seasonStart) {
            if ($selectedRange === 'all') {
                return;
            }

            if ($selectedRange === 'today') {
                $query->where(function ($q) use ($today) {
                    $q->whereDate('starts_at', '<=', $today)
                        ->where(function ($dateQ) use ($today) {
                            $dateQ->whereNull('ends_at')
                                ->orWhereDate('ends_at', '>=', $today);
                        });
                });

                return;
            }

            if ($selectedRange === 'week') {
                $query->where(function ($q) use ($weekStart, $weekEnd) {
                    $q->whereDate('starts_at', '<=', $weekEnd)
                        ->where(function ($dateQ) use ($weekStart) {
                            $dateQ->whereNull('ends_at')
                                ->orWhereDate('ends_at', '>=', $weekStart);
                        });
                });

                return;
            }

            $query->where(function ($q) use ($seasonStart) {
                $q->whereNull('starts_at')
                    ->orWhereDate('starts_at', '>=', $seasonStart)
                    ->orWhereDate('ends_at', '>=', $seasonStart);
            });
        };

        if (! Schema::hasTable('planned_events')) {
            return view('koelnimage.events.index', [
                'events' => collect(),
                'filters' => compact('search', 'range', 'status', 'sort'),
                'counts' => ['today' => 0, 'week' => 0, 'season' => 0],
            ]);
        }

        $query = PlannedEvent::query()->withCount('teams');

        // Oeffentliches Event-Listing zeigt nur aktuelle/kommende Termine.
        // Vergangene Events (Ende/Datum vor heute) bleiben in News/Galerien sichtbar.
        $query->where(function ($q) use ($today) {
            $q->whereNull('starts_at')
                ->orWhereDate('ends_at', '>=', $today)
                ->orWhere(function ($dateQ) use ($today) {
                    $dateQ->whereNull('ends_at')
                        ->whereDate('starts_at', '>=', $today);
                });
        });

        if ($status === 'active') {
            $query->where('is_active', true);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('date_label', 'like', '%'.$search.'%');
            });
        }

        $applyRange($query, $range);

        if ($sort === 'date_asc') {
            $query->orderBy('starts_at')->orderBy('sort_order')->orderBy('name');
        } elseif ($sort === 'name_asc') {
            $query->orderBy('name')->orderByDesc('starts_at')->orderBy('sort_order');
        } elseif ($sort === 'name_desc') {
            $query->orderByDesc('name')->orderByDesc('starts_at')->orderBy('sort_order');
        } else {
            $query->orderByDesc('starts_at')->orderBy('sort_order')->orderBy('name');
        }

        $events = $query->paginate(12)->withQueryString();

        $countBase = PlannedEvent::query()->where('is_active', true);
        $todayCountQuery = clone $countBase;
        $weekCountQuery = clone $countBase;
        $seasonCountQuery = clone $countBase;
        $applyRange($todayCountQuery, 'today');
        $applyRange($weekCountQuery, 'week');
        $applyRange($seasonCountQuery, 'season');

        return view('koelnimage.events.index', [
            'events' => $events,
            'filters' => compact('search', 'range', 'status', 'sort'),
            'counts' => [
                'today' => $todayCountQuery->count(),
                'week' => $weekCountQuery->count(),
                'season' => $seasonCountQuery->count(),
            ],
        ]);
    }

    public function motorsport(): View
    {
        return view('koelnimage.events.motorsport');
    }
}
