<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventSuggestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EventSuggestionController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (! Schema::hasTable('event_suggestions')) {
            return redirect()
                ->route('admin.settings.index')
                ->with('error', 'Tabelle für Event-Vorschläge fehlt — bitte `php artisan migrate` ausführen.');
        }

        $suggestions = EventSuggestion::query()
            ->where('status', EventSuggestion::STATUS_PENDING)
            ->orderBy('starts_at')
            ->orderByDesc('fetched_at')
            ->paginate(30);

        return view('admin.settings.event-suggestions.index', compact('suggestions'));
    }

    public function import(EventSuggestion $eventSuggestion): RedirectResponse
    {
        if (! $eventSuggestion->isPending()) {
            abort(404);
        }

        session()->forget('planned_event_create_draft');
        session(['pending_event_suggestion_id' => $eventSuggestion->id]);

        return redirect()
            ->route('admin.settings.planned-events.create')
            ->withInput($this->mapToPlannedEventInput($eventSuggestion))
            ->with(
                'status',
                'Felder aus dem Vorschlag übernommen — bitte vollständige Veranstaltungsadresse und Details prüfen, dann speichern.'
            );
    }

    public function dismiss(EventSuggestion $eventSuggestion): RedirectResponse
    {
        if (! $eventSuggestion->isPending()) {
            abort(404);
        }

        $eventSuggestion->forceFill([
            'status' => EventSuggestion::STATUS_DISMISSED,
            'planned_event_id' => null,
        ])->save();

        return back()->with('status', 'Der Vorschlag wurde verworfen.');
    }

    /**
     * @return array<string, mixed>
     */
    private function mapToPlannedEventInput(EventSuggestion $suggestion): array
    {
        $dateLabel = '';
        if ($suggestion->starts_at) {
            $dateLabel = $suggestion->starts_at->format('d.m.Y');
            if ($suggestion->ends_at && $suggestion->ends_at->gt($suggestion->starts_at)) {
                $dateLabel .= ' – '.$suggestion->ends_at->format('d.m.Y');
            }
        }

        $ai = trim(implode("\n\n", array_filter([
            $suggestion->description,
            $suggestion->info_url ? 'Quelle: '.$suggestion->info_url : null,
            $suggestion->category ? 'Kategorie (Vorschlag): '.$suggestion->category : null,
        ])));

        $cc = Str::upper(trim((string) ($suggestion->venue_country_code ?: 'DE')));
        if (strlen($cc) !== 2 || ! ctype_alpha($cc)) {
            $cc = 'DE';
        }

        return [
            'name' => $suggestion->title,
            'date_label' => $dateLabel !== '' ? $dateLabel : null,
            'location' => $suggestion->venue_name ?: $suggestion->venue_city,
            'venue_street' => $suggestion->venue_street ?: 'Bitte Straße ergänzen',
            'venue_postal_code' => $suggestion->venue_postal_code ?: '00000',
            'venue_city' => $suggestion->venue_city ?: 'Bitte Stadt ergänzen',
            'venue_state' => $suggestion->venue_state ?: 'Bitte Bundesland ergänzen',
            'venue_country' => $suggestion->venue_country ?: 'Deutschland',
            'venue_country_code' => $cc,
            'starts_at' => $suggestion->starts_at?->format('Y-m-d'),
            'ends_at' => $suggestion->ends_at?->format('Y-m-d'),
            'ai_context' => $ai !== '' ? Str::limit($ai, 60000, '') : null,
            'sort_order' => 0,
        ];
    }
}
