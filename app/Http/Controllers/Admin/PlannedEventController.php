<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventSuggestion;
use App\Models\NewsItem;
use App\Models\PlannedEvent;
use App\Models\PlannedEventTeam;
use App\Models\User;
use App\Services\AdacStarterListFormatter;
use App\Services\PdfScheduleTextExtractor;
use App\Services\PlannedEvents\SyncPlannedEvent24hParticipants;
use App\Support\PlannedEventScheduleFileLocator;
use App\Support\PlannedEventTeamReferenceImageLocator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Smalot\PdfParser\Parser;

class PlannedEventController extends Controller
{
    public function __construct(
        private PdfScheduleTextExtractor $pdfExtractor,
        private AdacStarterListFormatter $starterListFormatter
    ) {}

    /**
     * Verhindert SQL-Fehler („Unknown column …“) und verschleierte 404-Seiten, wenn Code
     * bereits ausgerollt ist, die Migration auf dem Server aber noch fehlt.
     */
    private function venueColumnsReady(): bool
    {
        return Schema::hasTable('planned_events')
            && Schema::hasColumn('planned_events', 'venue_city');
    }

    private function redirectVenueMigrationMissing(): RedirectResponse
    {
        return redirect()
            ->route('admin.settings.index')
            ->with(
                'error',
                'Ausstehende Datenbank-Migration: Auf diesem Server bitte `php artisan migrate` ausführen '
                .'(neue Felder „Veranstaltungsadresse“ für geplante Veranstaltungen). '
                .'Ohne Migration können Veranstaltungen nicht gespeichert werden.'
            );
    }

    public function index(): View|RedirectResponse
    {
        if (! Schema::hasTable('planned_events')) {
            return redirect()->route('admin.settings.index')
                ->with('error', 'Tabellen für Veranstaltungen fehlen. Bitte Migrationen ausführen.');
        }

        $events = PlannedEvent::query()
            ->withCount('teams')
            ->orderByDesc('starts_at')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.settings.planned-events.index', compact('events'));
    }

    public function create(): View|RedirectResponse
    {
        if (! Schema::hasTable('planned_events')) {
            return redirect()->route('admin.settings.index')
                ->with('error', 'Tabellen für Veranstaltungen fehlen. Bitte Migrationen ausführen.');
        }

        if (! $this->venueColumnsReady()) {
            return redirect()
                ->route('admin.settings.planned-events.index')
                ->with(
                    'error',
                    'Datenbank noch nicht aktualisiert: Bitte auf dem Server `php artisan migrate` ausführen.'
                );
        }

        $assignmentUsers = User::query()->orderBy('name')->orderBy('email')->get(['id', 'name', 'email']);
        $venueAddressPresets = PlannedEvent::venueAddressPresetCandidates(null);
        $draftDefaults = session('planned_event_create_draft', []);

        return view('admin.settings.planned-events.create', compact('assignmentUsers', 'venueAddressPresets', 'draftDefaults'));
    }

    /**
     * Entwurf nur in der Session — kein planned_events-Datensatz (wie gängige Studio-„Create“-Flows).
     */
    public function storeDraft(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('planned_events')) {
            return redirect()->route('admin.settings.index')->with('error', 'Tabelle fehlt.');
        }

        if (! $this->venueColumnsReady()) {
            return $this->redirectVenueMigrationMissing();
        }

        $payload = $this->draftPayloadFromRequest($request);
        session(['planned_event_create_draft' => $payload]);

        return redirect()
            ->route('admin.settings.planned-events.create')
            ->withInput($payload)
            ->with('status', 'Entwurf gespeichert (noch nicht als Veranstaltung angelegt).');
    }

    public function clearDraft(): RedirectResponse
    {
        session()->forget('planned_event_create_draft');

        return redirect()
            ->route('admin.settings.planned-events.create')
            ->with('status', 'Entwurf wurde verworfen.');
    }

    public function store(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('planned_events')) {
            return redirect()->route('admin.settings.index')->with('error', 'Tabelle fehlt.');
        }

        if (! $this->venueColumnsReady()) {
            return $this->redirectVenueMigrationMissing();
        }

        $data = $this->validatePayload($request);
        $event = PlannedEvent::query()->create($this->eventAttributes($data, $request));

        $this->syncSchedulePdf($request, $event, false);
        $this->applyScheduleExtractedTextFromForm($request, $event, false);
        $this->syncTeams($request, $event);

        $this->finalizePendingEventSuggestion((int) $event->id);

        session()->forget('planned_event_create_draft');

        return redirect()
            ->route('admin.settings.planned-events.edit', $event)
            ->with('status', 'Veranstaltung wurde angelegt.');
    }

    public function edit(PlannedEvent $plannedEvent): View|RedirectResponse
    {
        if (! Schema::hasTable('planned_events')) {
            return redirect()->route('admin.settings.index')->with('error', 'Tabelle fehlt.');
        }

        if (! $this->venueColumnsReady()) {
            return redirect()
                ->route('admin.settings.planned-events.index')
                ->with(
                    'error',
                    'Datenbank noch nicht aktualisiert: Bitte auf dem Server `php artisan migrate` ausführen, '
                    .'damit die Veranstaltungsadresse gespeichert werden kann.'
                );
        }

        $plannedEvent->load(['teams' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')]);
        $assignmentUsers = User::query()->orderBy('name')->orderBy('email')->get(['id', 'name', 'email']);
        $venueAddressPresets = PlannedEvent::venueAddressPresetCandidates((int) $plannedEvent->id);

        return view('admin.settings.planned-events.edit', [
            'plannedEvent' => $plannedEvent,
            'assignmentUsers' => $assignmentUsers,
            'venueAddressPresets' => $venueAddressPresets,
            'draftDefaults' => [],
        ]);
    }

    public function update(Request $request, PlannedEvent $plannedEvent): RedirectResponse
    {
        if (! $this->venueColumnsReady()) {
            return $this->redirectVenueMigrationMissing();
        }

        $data = $this->validatePayload($request, true);
        $plannedEvent->fill($this->eventAttributes($data, $request));
        $plannedEvent->save();

        $extractionMessage = $this->syncSchedulePdf($request, $plannedEvent, true);
        $this->applyScheduleExtractedTextFromForm($request, $plannedEvent, true);
        $this->syncTeams($request, $plannedEvent);

        $redirect = redirect()
            ->route('admin.settings.planned-events.edit', $plannedEvent)
            ->with('status', 'Veranstaltung wurde gespeichert.');

        if ($extractionMessage !== null) {
            $redirect->with('schedule_pdf_extraction', $extractionMessage);
        }

        return $redirect;
    }

    public function destroy(Request $request, PlannedEvent $plannedEvent): RedirectResponse
    {
        if ($request->input('confirmation') !== 'ja') {
            return redirect()->route('admin.settings.planned-events.index')
                ->with('error', 'Bitte Löschung bestätigen.');
        }

        $hasAssignedNews = NewsItem::query()->where('planned_event_id', $plannedEvent->id)->exists();
        if ($hasAssignedNews) {
            return redirect()->route('admin.settings.planned-events.index')
                ->with('error', 'Veranstaltung kann nicht gelöscht werden: es sind Meldungen zugeordnet.');
        }

        if ($plannedEvent->hasSchedulePdf()) {
            PlannedEventScheduleFileLocator::delete((string) $plannedEvent->schedule_pdf_path);
        }

        $plannedEvent->delete();

        return redirect()->route('admin.settings.planned-events.index')
            ->with('status', 'Veranstaltung wurde gelöscht.');
    }

    public function teamsOverview(PlannedEvent $plannedEvent): View
    {
        $plannedEvent->load(['teams' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')]);
        $teamsSorted = $plannedEvent->teams
            ->sortBy(fn (PlannedEventTeam $team): int => $team->startNumber() ?? 99999)
            ->values();
        $plannedEvent->setRelation('teams', $teamsSorted);

        return view('admin.settings.planned-events.teams', compact('plannedEvent'));
    }

    public function downloadSchedule(PlannedEvent $plannedEvent)
    {
        if (! $plannedEvent->hasSchedulePdf()) {
            abort(404);
        }

        $downloadName = trim((string) ($plannedEvent->schedule_pdf_original_name ?? ''));
        if ($downloadName === '') {
            $downloadName = Str::slug($plannedEvent->name) ?: 'programm';
            $downloadName .= '.pdf';
        }

        $response = PlannedEventScheduleFileLocator::download((string) $plannedEvent->schedule_pdf_path, $downloadName);
        if ($response === null) {
            abort(404);
        }

        return $response;
    }

    public function downloadTeamReference(PlannedEvent $plannedEvent, PlannedEventTeam $team)
    {
        if ((int) $team->planned_event_id !== (int) $plannedEvent->id) {
            abort(404);
        }

        $path = (string) ($team->reference_image_path ?? '');
        if ($path === '') {
            abort(404);
        }

        if (preg_match('/Startnr\.?\s*(\d{1,4})/iu', (string) $team->name, $match) === 1) {
            $downloadName = 'referenz-startnr-'.$match[1].'.jpg';
        } else {
            $downloadName = 'referenz-team-'.$team->id.'.jpg';
        }

        $response = PlannedEventTeamReferenceImageLocator::download($path, $downloadName);
        if ($response === null) {
            abort(404);
        }

        return $response;
    }

    public function sync24hParticipants(PlannedEvent $plannedEvent, SyncPlannedEvent24hParticipants $sync): RedirectResponse
    {
        try {
            $stats = $sync->sync($plannedEvent, downloadImages: true, dryRun: false);
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.settings.planned-events.teams', $plannedEvent)
                ->with('error', 'Abgleich fehlgeschlagen: '.$e->getMessage());
        }

        $message = sprintf(
            'Starterliste abgeglichen: %d Einträge, %d aktualisiert, %d neu, %d entfernt, %d Referenzfotos geladen.',
            $stats['fetched'],
            $stats['updated'],
            $stats['created'],
            $stats['removed'],
            $stats['images_downloaded']
        );

        $redirect = redirect()
            ->route('admin.settings.planned-events.teams', $plannedEvent)
            ->with('status', $message);

        if ($stats['errors'] !== []) {
            $redirect->with('error', implode(' ', array_slice($stats['errors'], 0, 3)));
        }

        return $redirect;
    }

    private function validatePayload(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:512'],
            'date_label' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'venue_street' => ['required', 'string', 'max:255'],
            'venue_postal_code' => ['required', 'string', 'max:32'],
            'venue_city' => ['required', 'string', 'max:120'],
            'venue_state' => ['required', 'string', 'max:120'],
            'venue_country' => ['nullable', 'string', 'max:120'],
            'venue_country_code' => ['nullable', 'string', 'max:2', 'regex:/^[A-Za-z]*$/'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'ai_context' => ['nullable', 'string', 'max:60000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['nullable', 'boolean'],
            'assigned_user_ids' => ['nullable', 'array'],
            'assigned_user_ids.*' => ['integer', 'exists:users,id'],
            'teams' => ['nullable', 'array'],
            'teams.*.name' => ['nullable', 'string', 'max:255'],
            'teams.*.notes' => ['nullable', 'string', 'max:5000'],
            'teams_bulk' => ['nullable', 'string', 'max:200000'],
            'teams_list_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'schedule_pdf' => [$isUpdate ? 'nullable' : 'sometimes', 'file', 'mimes:pdf', 'max:20480'],
            'remove_schedule_pdf' => ['nullable', 'boolean'],
            'schedule_pdf_extracted_text' => ['nullable', 'string', 'max:100000'],
            'reextract_schedule_pdf' => ['nullable', 'boolean'],
        ]);
    }

    private function eventAttributes(array $validated, Request $request): array
    {
        $assigned = collect((array) ($validated['assigned_user_ids'] ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $country = $this->nullableTrimmed($validated['venue_country'] ?? null) ?? 'Deutschland';
        $ccRaw = strtoupper((string) ($this->nullableTrimmed($validated['venue_country_code'] ?? null) ?? ''));
        $countryCode = strlen($ccRaw) === 2 && ctype_alpha($ccRaw) ? $ccRaw : 'DE';

        return [
            'name' => trim((string) $validated['name']),
            'date_label' => $this->nullableTrimmed($validated['date_label'] ?? null),
            'location' => $this->nullableTrimmed($validated['location'] ?? null),
            'venue_street' => trim((string) $validated['venue_street']),
            'venue_postal_code' => trim((string) $validated['venue_postal_code']),
            'venue_city' => trim((string) $validated['venue_city']),
            'venue_state' => trim((string) $validated['venue_state']),
            'venue_country' => $country,
            'venue_country_code' => $countryCode,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'ai_context' => $this->nullableTrimmed($validated['ai_context'] ?? null),
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'assigned_user_ids' => $assigned,
        ];
    }

    /**
     * Programmtext nicht in eventAttributes/create speichern: ein leeres „Extrahierter Ablauf“-Feld im POST
     * hätte bei jedem Speichern ohne neuen Upload den gespeicherten PDF-Extrakt mit null überschrieben.
     * Läuft nach syncSchedulePdf: bei neuem PDF oder Re-Extraktion allein der Extrakt aus der Datei
     * (Formularfeld wird ignoriert, damit kein alter Text aus der Vor-Ansicht den neuen Extrakt verdrängt).
     * Ohne PDF-Aktion: nicht-leeres Feld = manuelle Bearbeitung/Korrektur; leeres Feld = DB-Wert unverändert.
     */
    private function applyScheduleExtractedTextFromForm(Request $request, PlannedEvent $event, bool $isUpdate): void
    {
        $hadUpload = $request->hasFile('schedule_pdf');
        $hadReextract = $isUpdate && $request->boolean('reextract_schedule_pdf');

        if ($hadUpload || $hadReextract) {
            return;
        }

        $manual = trim((string) $request->input('schedule_pdf_extracted_text', ''));
        if ($manual === '') {
            return;
        }

        $event->forceFill([
            'schedule_pdf_extracted_text' => Str::limit($manual, 100000, ''),
        ])->save();
    }

    private function syncSchedulePdf(Request $request, PlannedEvent $event, bool $isUpdate): ?string
    {
        $extractionMessage = null;
        $removeSchedule = $request->boolean('remove_schedule_pdf');
        $uploadSchedule = $request->file('schedule_pdf');

        if ($removeSchedule && $event->hasSchedulePdf()) {
            PlannedEventScheduleFileLocator::delete((string) $event->schedule_pdf_path);
            $event->forceFill([
                'schedule_pdf_path' => null,
                'schedule_pdf_original_name' => null,
                'schedule_pdf_extracted_text' => null,
            ])->save();
        }

        if ($uploadSchedule) {
            if ($event->hasSchedulePdf()) {
                PlannedEventScheduleFileLocator::delete((string) $event->schedule_pdf_path);
            }

            $originalName = (string) ($uploadSchedule->getClientOriginalName() ?: 'programm.pdf');
            $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $originalName) ?: 'programm.pdf';
            $path = 'planned-event-schedules/'.$event->id.'/'.Str::uuid().'-'.$safeName;

            Storage::disk('local')->put($path, file_get_contents($uploadSchedule->getRealPath()));

            $event->forceFill([
                'schedule_pdf_path' => $path,
                'schedule_pdf_original_name' => $originalName,
            ])->save();

            $extract = $this->pdfExtractor->extractFromLocalDisk($path);
            $event->forceFill([
                'schedule_pdf_extracted_text' => $extract['ok'] ? Str::limit((string) $extract['text'], 100000, '') : null,
            ])->save();

            if (! $extract['ok'] && filled($extract['message'])) {
                $extractionMessage = (string) $extract['message'];
            }
        } elseif ($isUpdate && $request->boolean('reextract_schedule_pdf') && $event->hasSchedulePdf()) {
            $extract = $this->pdfExtractor->extractFromLocalDisk((string) $event->schedule_pdf_path);
            $event->forceFill([
                'schedule_pdf_extracted_text' => $extract['ok'] ? Str::limit((string) $extract['text'], 100000, '') : null,
            ])->save();

            if (! $extract['ok'] && filled($extract['message'])) {
                $extractionMessage = (string) $extract['message'];
            }
        }

        return $extractionMessage;
    }

    private function syncTeams(Request $request, PlannedEvent $event): void
    {
        $bulk = trim((string) $request->input('teams_bulk', ''));
        $teamsFromPdf = $this->parseTeamsPdfToBulkText($request);
        if ($teamsFromPdf !== null) {
            $bulk = $teamsFromPdf;
        }

        if ($bulk !== '') {
            $rows = $this->parseTeamRowsFromBulk($bulk);
        } else {
            $rows = collect((array) $request->input('teams', []))
                ->map(function ($row): ?array {
                    if (! is_array($row)) {
                        return null;
                    }
                    $name = trim((string) ($row['name'] ?? ''));
                    $notes = trim((string) ($row['notes'] ?? ''));
                    if ($name === '') {
                        return null;
                    }

                    return ['name' => $name, 'notes' => $notes !== '' ? $notes : null];
                })
                ->filter()
                ->values()
                ->all();
        }

        $event->teams()->delete();

        foreach ($rows as $index => $row) {
            $event->teams()->create([
                'name' => $row['name'],
                'notes' => $row['notes'],
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @return array<int, array{name: string, notes: string|null}>
     */
    private function parseTeamRowsFromBulk(string $bulk): array
    {
        $normalized = preg_replace("/\r\n|\r/u", "\n", $bulk) ?? $bulk;
        $blocks = preg_split("/\n\s*\n/u", $normalized) ?: [];
        $rows = [];

        foreach ($blocks as $block) {
            $lines = array_values(array_filter(array_map('trim', explode("\n", $block)), fn ($line) => $line !== ''));
            if ($lines === []) {
                continue;
            }

            $name = array_shift($lines);
            $notes = trim(implode("\n", $lines));
            $rows[] = [
                'name' => (string) $name,
                'notes' => $notes !== '' ? $notes : null,
            ];
        }

        return $rows;
    }

    private function parseTeamsPdfToBulkText(Request $request): ?string
    {
        $pdf = $request->file('teams_list_pdf');
        if (! $pdf) {
            return null;
        }

        try {
            $parser = new Parser;
            $text = trim((string) $parser->parseFile($pdf->getRealPath())->getText());
            if ($text === '') {
                return null;
            }

            [$preamble, $body] = $this->starterListFormatter->splitPreambleAndBody($text);

            return trim($this->starterListFormatter->format($body, $preamble));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Formularfelder für einen Session-Entwurf (ohne Datei-Uploads).
     *
     * @return array<string, mixed>
     */
    private function draftPayloadFromRequest(Request $request): array
    {
        return $request->except([
            '_token',
            '_method',
            'schedule_pdf',
            'teams_list_pdf',
        ]);
    }

    private function finalizePendingEventSuggestion(int $plannedEventId): void
    {
        $pendingId = (int) session()->pull('pending_event_suggestion_id', 0);
        if ($pendingId <= 0 || ! Schema::hasTable('event_suggestions')) {
            return;
        }

        EventSuggestion::query()
            ->whereKey($pendingId)
            ->where('status', EventSuggestion::STATUS_PENDING)
            ->update([
                'status' => EventSuggestion::STATUS_IMPORTED,
                'planned_event_id' => $plannedEventId,
            ]);
    }

    private function nullableTrimmed(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed !== '' ? $trimmed : null;
    }
}
