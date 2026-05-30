<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventSuggestion;
use App\Models\PlannedEvent;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class EventPlanningController extends Controller
{
    public function index(): View
    {
        $plannedEventsCount = 0;
        if (Schema::hasTable('planned_events')) {
            $plannedEventsCount = PlannedEvent::query()->count();
        }

        $pendingSuggestionsCount = 0;
        if (Schema::hasTable('event_suggestions')) {
            $pendingSuggestionsCount = EventSuggestion::query()
                ->where('status', EventSuggestion::STATUS_PENDING)
                ->count();
        }

        return view('admin.event-planning.index', compact('plannedEventsCount', 'pendingSuggestionsCount'));
    }
}
