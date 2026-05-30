@php
    if (! isset($errors) || ! ($errors instanceof \Illuminate\Support\ViewErrorBag)) {
        $errors = new \Illuminate\Support\ViewErrorBag();
    }
    $venueAddressPresets = isset($venueAddressPresets) && $venueAddressPresets instanceof \Illuminate\Support\Collection
        ? $venueAddressPresets
        : collect();
    $venuePresetPayload = $venueAddressPresets
        ->map(static fn (\App\Models\PlannedEvent $e) => [
            'location' => (string) $e->location,
            'venue_street' => (string) $e->venue_street,
            'venue_postal_code' => (string) $e->venue_postal_code,
            'venue_city' => (string) $e->venue_city,
            'venue_state' => (string) $e->venue_state,
            'venue_country' => (string) ($e->venue_country ?? 'Deutschland'),
            'venue_country_code' => (string) ($e->venue_country_code ?? 'DE'),
        ])
        ->values()
        ->all();
    $draftDefaults = isset($draftDefaults) && is_array($draftDefaults) ? $draftDefaults : [];
    $isEdit = $plannedEvent instanceof \App\Models\PlannedEvent;
    $hasSchedulePdfExtractedColumn = \Illuminate\Support\Facades\Schema::hasColumn('planned_events', 'schedule_pdf_extracted_text');
    $oActive = old('is_active');
    if ($oActive === null && ! $isEdit && array_key_exists('is_active', $draftDefaults)) {
        $ov = $draftDefaults['is_active'];
        $oActive = ($ov === '1' || $ov === 1 || $ov === true) ? '1' : '0';
    }
    if ($oActive === null) {
        $isActiveVal = $isEdit ? $plannedEvent->is_active : true;
    } else {
        $isActiveVal = $oActive === '1' || $oActive === 1 || $oActive === true;
    }
@endphp

<form
    method="post"
    action="{{ $isEdit ? route('admin.settings.planned-events.update', $plannedEvent) : route('admin.settings.planned-events.store') }}"
    class="space-y-6"
    enctype="multipart/form-data"
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="space-y-2">
        <label for="name" class="block text-sm font-medium text-gray-700">Veranstaltung <span class="text-red-600">*</span></label>
        <input
            type="text"
            name="name"
            id="name"
            value="{{ old('name', $isEdit ? $plannedEvent?->name : ($draftDefaults['name'] ?? '')) }}"
            required
            maxlength="512"
            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] text-sm"
            placeholder="z. B. ADAC RAVENOL 24h Nürburgring Qualifiers 2026"
        />
        @error('name')
            <p class="text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-2">
            <label for="date_label" class="block text-sm font-medium text-gray-700">Zeitraum (freie Anzeige)</label>
            <input
                type="text"
                name="date_label"
                id="date_label"
                value="{{ old('date_label', $isEdit ? $plannedEvent?->date_label : ($draftDefaults['date_label'] ?? '')) }}"
                maxlength="255"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] text-sm"
                placeholder="z. B. 17.–19.04.2026"
            />
            @error('date_label')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div class="space-y-2">
            <label for="location" class="block text-sm font-medium text-gray-700">Veranstaltungsort</label>
            <input
                type="text"
                name="location"
                id="location"
                value="{{ old('location', $isEdit ? $plannedEvent?->location : ($draftDefaults['location'] ?? '')) }}"
                maxlength="255"
                @if ($venueAddressPresets->isNotEmpty())
                    list="planned_event_location_preset_datalist"
                @endif
                autocomplete="off"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] text-sm"
                placeholder="z. B. Nürburgring, Köln, Lanxess Arena"
            />
            @if ($venueAddressPresets->isNotEmpty())
                <p class="text-xs text-gray-500">
                    Tipp: Bekannte Orte erscheinen als Vorschläge – bei exakter Übereinstimmung werden Straße, PLZ und IPTC-Felder unten automatisch übernommen (anpassbar).
                </p>
            @endif
            @error('location')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    @if ($venueAddressPresets->isNotEmpty())
        <datalist id="planned_event_location_preset_datalist">
            @foreach ($venueAddressPresets as $vp)
                <option value="{{ $vp->location }}"></option>
            @endforeach
        </datalist>
    @endif

    <div class="rounded-lg border border-gray-200 bg-gray-50/80 p-4 space-y-4">
        <div>
            <h3 class="text-sm font-semibold text-gray-900">Veranstaltungsort (vollständige Adresse) <span class="text-red-600">*</span></h3>
            <p class="mt-1 text-xs text-gray-600">
                Pflicht für IPTC-Felder <span class="font-medium text-gray-800">Stadt (2#090)</span> und
                <span class="font-medium text-gray-800">Bundesland/Region (2#095)</span> bei Bildimport und KI-Metadaten.
                Der kurze Anzeige-„Ort“ oben kann z.&nbsp;B. die Arena bleiben; hier die postalische Anschrift.
            </p>
        </div>
        @if ($venueAddressPresets->isNotEmpty())
            <div class="space-y-2 rounded-md border border-dashed border-gray-300 bg-white/80 p-3">
                <label for="venue_address_preset_select" class="block text-sm font-medium text-gray-800">
                    Adresse aus früherer Veranstaltung übernehmen
                </label>
                <select
                    id="venue_address_preset_select"
                    class="block w-full max-w-2xl rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] text-sm"
                >
                    <option value="">— Nicht übernehmen / Felder manuell ausfüllen —</option>
                    @foreach ($venueAddressPresets as $idx => $vp)
                        <option value="{{ $idx }}">{{ $vp->location }} — {{ $vp->venue_city }}, {{ $vp->venue_state }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500">
                    Spart doppelte Eingabe: einmal Arena/Halle mit Adresse angelegt, danach hier wählen – Ort und alle Adresszeilen werden vorausgefüllt (vor dem Speichern änderbar).
                </p>
            </div>
        @endif
        <div class="space-y-2">
            <label for="venue_street" class="block text-sm font-medium text-gray-700">Straße und Hausnummer <span class="text-red-600">*</span></label>
            <input
                type="text"
                name="venue_street"
                id="venue_street"
                value="{{ old('venue_street', $isEdit ? $plannedEvent?->venue_street : ($draftDefaults['venue_street'] ?? '')) }}"
                required
                maxlength="255"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] text-sm"
                placeholder="z. B. Otto-Flimm-Straße 1"
            />
            @error('venue_street')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <label for="venue_postal_code" class="block text-sm font-medium text-gray-700">PLZ <span class="text-red-600">*</span></label>
                <input
                    type="text"
                    name="venue_postal_code"
                    id="venue_postal_code"
                    value="{{ old('venue_postal_code', $isEdit ? $plannedEvent?->venue_postal_code : ($draftDefaults['venue_postal_code'] ?? '')) }}"
                    required
                    maxlength="32"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] text-sm"
                    placeholder="53520"
                />
                @error('venue_postal_code')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="space-y-2">
                <label for="venue_city" class="block text-sm font-medium text-gray-700">Stadt (IPTC City) <span class="text-red-600">*</span></label>
                <input
                    type="text"
                    name="venue_city"
                    id="venue_city"
                    value="{{ old('venue_city', $isEdit ? $plannedEvent?->venue_city : ($draftDefaults['venue_city'] ?? '')) }}"
                    required
                    maxlength="120"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] text-sm"
                    placeholder="Nürburg"
                />
                @error('venue_city')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <label for="venue_state" class="block text-sm font-medium text-gray-700">Bundesland / Region (IPTC Province/State) <span class="text-red-600">*</span></label>
                <input
                    type="text"
                    name="venue_state"
                    id="venue_state"
                    value="{{ old('venue_state', $isEdit ? $plannedEvent?->venue_state : ($draftDefaults['venue_state'] ?? '')) }}"
                    required
                    maxlength="120"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] text-sm"
                    placeholder="Rheinland-Pfalz"
                />
                @error('venue_state')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="space-y-2">
                <label for="venue_country" class="block text-sm font-medium text-gray-700">Land</label>
                <input
                    type="text"
                    name="venue_country"
                    id="venue_country"
                    value="{{ old('venue_country', $isEdit ? ($plannedEvent?->venue_country ?? 'Deutschland') : ($draftDefaults['venue_country'] ?? 'Deutschland')) }}"
                    maxlength="120"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] text-sm"
                    placeholder="Deutschland"
                />
                @error('venue_country')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>
        <div class="space-y-2 max-w-xs">
            <label for="venue_country_code" class="block text-sm font-medium text-gray-700">ISO-Ländercode (2#100)</label>
            <input
                type="text"
                name="venue_country_code"
                id="venue_country_code"
                value="{{ old('venue_country_code', $isEdit ? ($plannedEvent?->venue_country_code ?? 'DE') : ($draftDefaults['venue_country_code'] ?? 'DE')) }}"
                maxlength="2"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] text-sm uppercase"
                placeholder="DE"
            />
            <p class="text-xs text-gray-500">Zwei Buchstaben, z.&nbsp;B. DE. Wird bei leerem Feld als DE gespeichert.</p>
            @error('venue_country_code')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-2">
            <label for="sort_order" class="block text-sm font-medium text-gray-700">Sortierung</label>
            <input
                type="number"
                name="sort_order"
                id="sort_order"
                value="{{ old('sort_order', $isEdit ? ($plannedEvent?->sort_order ?? 0) : ($draftDefaults['sort_order'] ?? 0)) }}"
                min="0"
                max="999999"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] text-sm"
            />
            @error('sort_order')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-2">
            <label for="starts_at" class="block text-sm font-medium text-gray-700">Beginn (Kalender)</label>
            <input
                type="date"
                name="starts_at"
                id="starts_at"
                value="{{ old('starts_at', $isEdit ? $plannedEvent?->starts_at?->format('Y-m-d') : ($draftDefaults['starts_at'] ?? '')) }}"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] text-sm"
            />
            @error('starts_at')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div class="space-y-2">
            <label for="ends_at" class="block text-sm font-medium text-gray-700">Ende (Kalender)</label>
            <input
                type="date"
                name="ends_at"
                id="ends_at"
                value="{{ old('ends_at', $isEdit ? $plannedEvent?->ends_at?->format('Y-m-d') : ($draftDefaults['ends_at'] ?? '')) }}"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] text-sm"
            />
            @error('ends_at')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <label class="flex items-start gap-3 cursor-pointer">
        <input type="hidden" name="is_active" value="0">
        <input
            type="checkbox"
            name="is_active"
            value="1"
            class="mt-1 rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]"
            @checked($isActiveVal)
        >
        <span>
            <span class="block text-sm font-medium text-gray-900">In Auswahllisten anzeigen</span>
            <span class="block text-xs text-gray-500 mt-0.5">Inaktive Events sind nur noch sichtbar, wenn sie einer Meldung zugeordnet sind.</span>
        </span>
    </label>

    @php
        $selectedAssignedUsers = old('assigned_user_ids');
        if (! is_array($selectedAssignedUsers)) {
            $selectedAssignedUsers = $isEdit
                ? (array) ($plannedEvent->assigned_user_ids ?? [])
                : (array) ($draftDefaults['assigned_user_ids'] ?? []);
        }
        $selectedAssignedUsers = array_map('intval', $selectedAssignedUsers);
    @endphp
    <div class="space-y-2">
        <label class="block text-sm font-medium text-gray-700">Zugewiesene Benutzer</label>
        <p class="text-xs text-gray-500">
            Nur diese Benutzer sehen das Event in der Meldungs-Auswahl unter „Geplantes Event“. Leer = nur Admin.
        </p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-56 overflow-y-auto pr-1">
            @forelse(($assignmentUsers ?? collect()) as $assignmentUser)
                <label class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 bg-gray-50 hover:bg-gray-100 cursor-pointer">
                    <input
                        type="checkbox"
                        name="assigned_user_ids[]"
                        value="{{ $assignmentUser->id }}"
                        @checked(in_array((int) $assignmentUser->id, $selectedAssignedUsers, true))
                        class="h-4 w-4 rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]"
                    >
                    <span class="text-sm text-gray-800">
                        {{ $assignmentUser->name }}
                        <span class="text-xs text-gray-500">({{ $assignmentUser->email }})</span>
                    </span>
                </label>
            @empty
                <p class="text-sm text-gray-500">Keine Benutzer vorhanden.</p>
            @endforelse
        </div>
        @error('assigned_user_ids')
            <p class="text-sm text-red-600">{{ $message }}</p>
        @enderror
        @error('assigned_user_ids.*')
            <p class="text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    @php
        $scheduleExtracted = $isEdit && $hasSchedulePdfExtractedColumn && filled($plannedEvent->schedule_pdf_extracted_text ?? null);
        $scheduleExtractLen = 0;
        if ($scheduleExtracted) {
            $scheduleExtractLen = \Illuminate\Support\Str::length(trim((string) $plannedEvent->schedule_pdf_extracted_text));
        }
    @endphp
    <div class="rounded-lg border border-gray-200 bg-gray-50/80 p-4 sm:p-5 space-y-5">
        <div class="space-y-1">
            <h2 class="text-sm font-semibold text-gray-900">Programm / Ablauf (PDF, optional)</h2>
            <p class="text-xs text-gray-600 leading-relaxed">
                Z.&nbsp;B. Running Order, Timetable oder Pressemappe vom Veranstalter – egal ob Gala, Festival, Messe oder Sport.
                Das PDF dient als <span class="text-gray-800">Referenz</span> (Download) und liefert <span class="text-gray-800">lesbaren Ablauf</span> für die Bild-KI sowie die Kurzinfo bei der Meldungs-Zuordnung.
                Die KI sieht nur den <span class="font-medium text-gray-800">extrahierten Text</span>, nicht das PDF selbst.
            </p>
        </div>

        @if (! $isEdit)
            <div class="rounded-md border border-sky-200 bg-sky-50/80 px-3 py-2.5 text-xs text-sky-950">
                <span class="font-medium">Neue Veranstaltung:</span>
                Nach dem ersten Speichern könnt ihr den automatisch eingelesenen Programmtext prüfen, korrigieren oder bei reinen Scan-PDFs manuell einfügen.
            </div>
        @else
            @if (! $hasSchedulePdfExtractedColumn)
                <div class="rounded-md border border-amber-200 bg-amber-50/80 px-3 py-2.5 text-xs text-amber-950">
                    <span class="font-medium">Datenbank nicht vollständig migriert:</span>
                    Die Spalte für den Programmtext fehlt. Auf dem Server bitte <code class="rounded bg-amber-100/80 px-1 py-0.5 font-mono text-[11px]">php artisan migrate</code> ausführen – danach lassen sich PDF-Text speichern, bearbeiten und neu einlesen.
                </div>
            @elseif ($plannedEvent->hasSchedulePdf())
                @if ($scheduleExtracted)
                    <div class="rounded-md border border-emerald-200 bg-emerald-50/70 px-3 py-2.5 text-xs text-emerald-950">
                        <span class="font-medium">Text für KI &amp; Auswahl:</span>
                        vorhanden (ca. {{ number_format($scheduleExtractLen, 0, ',', '.') }} Zeichen). Tabellen und Layout gehen beim Einlesen vereinfacht verloren – bei Bedarf unten nachbearbeiten.
                    </div>
                @else
                    <div class="rounded-md border border-amber-200 bg-amber-50/80 px-3 py-2.5 text-xs text-amber-950">
                        <span class="font-medium">Text für KI &amp; Auswahl:</span>
                        fehlt. Das PDF ist vermutlich nur gescannt oder enthält keinen wählbaren Text. Tragt den Ablauf unten manuell ein oder liefert ein durchsuchbares PDF.
                    </div>
                @endif
            @else
                <div class="rounded-md border border-gray-200 bg-white px-3 py-2.5 text-xs text-gray-700">
                    <span class="font-medium text-gray-900">Noch kein PDF.</span>
                    @if ($hasSchedulePdfExtractedColumn)
                        Ohne Datei und ohne manuellen Text unten kann die KI keinen offiziellen Ablauf aus einem Veranstalter-PDF nutzen.
                    @else
                        Ohne Migration (siehe Hinweis oben) kann kein Programmtext aus dem PDF gespeichert werden.
                    @endif
                </div>
            @endif
        @endif

        <div class="space-y-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">PDF-Datei</p>

            @if ($isEdit && $plannedEvent->hasSchedulePdf())
                <div class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-2 sm:gap-3">
                    <div class="min-w-0 flex-1 text-sm text-gray-700">
                        <span class="text-gray-500 text-xs block sm:inline sm:mr-2">Aktuelle Datei</span>
                        <span class="font-medium text-gray-900 break-all">{{ $plannedEvent->schedule_pdf_original_name ?: 'programm.pdf' }}</span>
                    </div>
                    <a
                        href="{{ route('admin.settings.planned-events.schedule', $plannedEvent) }}"
                        class="inline-flex items-center justify-center shrink-0 rounded-md bg-[#092E48] px-3 py-2 text-sm font-medium text-white hover:bg-[#0b3858]"
                    >
                        PDF herunterladen
                    </a>
                </div>
                <label class="flex items-start gap-2.5 text-sm text-gray-700 cursor-pointer max-w-prose">
                    <input
                        type="checkbox"
                        name="remove_schedule_pdf"
                        value="1"
                        class="mt-0.5 rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]"
                        @checked(old('remove_schedule_pdf'))
                    >
                    <span>
                        <span class="font-medium text-gray-900">PDF und Programmtext entfernen</span>
                        <span class="block text-xs text-gray-500 mt-0.5">Wirksam nach <span class="font-medium text-gray-700">Speichern</span>: Datei und extrahierter Text werden gelöscht. Anschließend zählen nur noch eure manuellen Vorgaben (Teilnehmende unten, KI-Textfeld).</span>
                    </span>
                </label>
            @endif

            <div class="space-y-1.5">
                <label for="schedule_pdf" class="block text-sm font-medium text-gray-700">
                    {{ $isEdit && $plannedEvent->hasSchedulePdf() ? 'Anderes PDF hochladen (ersetzt die gespeicherte Datei)' : 'PDF hochladen' }}
                </label>
                <p class="text-xs text-gray-500">
                    Nur PDF, maximal 20&nbsp;MB. Beim <span class="font-medium text-gray-700">Speichern</span> wird der Text neu aus der dann gültigen Datei gelesen (durchsuchbare PDFs; reine Scans bleiben ohne Extrakt).
                </p>
                <input
                    type="file"
                    name="schedule_pdf"
                    id="schedule_pdf"
                    accept="application/pdf,.pdf"
                    class="block w-full max-w-xl text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-[#092E48] file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-[#0b3858]"
                />
            </div>
            @error('schedule_pdf')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        @if ($isEdit && $hasSchedulePdfExtractedColumn)
            <div class="space-y-2 pt-4 border-t border-gray-200">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Programmtext für KI &amp; Meldung</p>
                <label for="schedule_pdf_extracted_text" class="block text-sm font-medium text-gray-700">Extrahierter oder manueller Ablauf</label>
                <p class="text-xs text-gray-500 leading-relaxed">
                    Bei neuem PDF oder „Text neu einlesen“ wird beim <span class="font-medium text-gray-700">Speichern</span> aus der Datei gelesen, sofern sie durchsuchbaren Text enthält (reine Scans bleiben leer – dann hier einfügen).
                    Speichert ihr ohne neues PDF: Änderungen im Feld werden übernommen; ein leeres Feld löscht den gespeicherten Text nicht.
                    Sehr lange Texte werden für die KI automatisch gekürzt.
                </p>
                <textarea
                    name="schedule_pdf_extracted_text"
                    id="schedule_pdf_extracted_text"
                    rows="14"
                    maxlength="100000"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] text-sm font-mono"
                >{{ old('schedule_pdf_extracted_text', $plannedEvent->schedule_pdf_extracted_text) }}</textarea>
                @error('schedule_pdf_extracted_text')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
                @if ($plannedEvent->hasSchedulePdf())
                    <label class="mt-2 flex items-start gap-2.5 text-sm text-gray-700 cursor-pointer">
                        <input
                            type="checkbox"
                            name="reextract_schedule_pdf"
                            value="1"
                            class="mt-0.5 rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]"
                            @checked(old('reextract_schedule_pdf'))
                        >
                        <span>
                            <span class="font-medium text-gray-900">Text aus gespeichertem PDF neu einlesen</span>
                            <span class="block text-xs text-gray-500 mt-0.5">Nach <span class="font-medium text-gray-700">Speichern</span>: überschreibt das Textfeld mit einer frischen Extraktion (z.&nbsp;B. nach PDF-Tausch außerhalb des Formulars oder zur Wiederholung).</span>
                        </span>
                    </label>
                @endif
            </div>
        @endif
    </div>

    <div class="space-y-2">
        <label for="ai_context" class="block text-sm font-medium text-gray-700">KI-Vorgaben zu diesem Event</label>
        <p class="text-xs text-gray-500">Strecke, Kurvenbezeichnungen, typische Motive, Redaktionshinweise – alles, was für Bildunterschriften hilft.</p>
        <textarea
            name="ai_context"
            id="ai_context"
            rows="10"
            maxlength="60000"
            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] text-sm font-mono"
            placeholder="z. B. caption_edition: 54 (Laufnummer für Agentur-Bildzeile); Nürburgring-Nordschleife; Caracciola-Karussell = Großes Karussell; …"
        >{{ old('ai_context', $isEdit ? $plannedEvent?->ai_context : ($draftDefaults['ai_context'] ?? '')) }}</textarea>
        @error('ai_context')
            <p class="text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="border-t border-gray-200 pt-6">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-2">
            <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wide">Mitwirkende / Acts / Besetzung</h2>
            @if ($isEdit)
                <a
                    href="{{ route('admin.settings.planned-events.teams', $plannedEvent) }}"
                    class="text-sm font-medium text-[#092E48] hover:underline shrink-0"
                >
                    @if ($plannedEvent->teams->isNotEmpty())
                        Alle {{ $plannedEvent->teams->count() }} anzeigen
                    @else
                        Teilnehmerübersicht öffnen
                    @endif
                    (aufklappbar)
                </a>
            @endif
        </div>
        <p class="text-xs text-gray-500 mb-4">Je nach Event: Künstler, Speaker, Vereine, Fahrzeuge oder Personen – Namen und kurze Notizen (z.&nbsp;B. Startnummer, Rolle). Die KI nutzt sie nur, wenn es zum Bild passt.</p>

        <div class="rounded-lg border border-gray-200 bg-white p-4 mb-5 space-y-2">
            <label for="teams_list_pdf" class="block text-sm font-medium text-gray-800">Starterliste als PDF (optional)</label>
            <p class="text-xs text-gray-600 leading-relaxed">
                Z.&nbsp;B. ADAC-Starterliste als durchsuchbare PDF. Beim <span class="font-medium text-gray-800">Speichern</span> wird der Text eingelesen, in Zeilen pro Box/Startnummer zerlegt und die <span class="font-medium text-gray-800">Teilnehmerliste hier unten vollständig ersetzt</span>.
                Das ist <span class="font-medium text-gray-800">nicht</span> dasselbe wie das Programm-/Ablauf-PDF weiter oben – dieses Feld ist nur für Namen/Teams/Fahrer der Starterliste.
                Reine Scan-PDFs ohne Textlayer funktionieren nicht – dann bitte Text kopieren und ins Bulk-Feld darunter.
            </p>
            <input
                type="file"
                name="teams_list_pdf"
                id="teams_list_pdf"
                accept="application/pdf,.pdf"
                class="block w-full max-w-xl text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-[#092E48] file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-[#0b3858]"
            />
            @error('teams_list_pdf')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="rounded-lg border border-dashed border-gray-300 bg-white p-4 mb-5 space-y-2">
            <label for="teams_bulk" class="block text-sm font-medium text-gray-800">Viele Einträge auf einmal (optional)</label>
            <p class="text-xs text-gray-600 leading-relaxed">
                Hier z.&nbsp;B. die formatierte Starterliste einfügen (Blöcke durch <span class="font-medium text-gray-800">Leerzeile</span> getrennt: erste Zeile = Name/Team-Zeile, folgende Zeilen = Notizen/Fahrer).
                Wenn dieses Feld beim <span class="font-medium text-gray-800">Speichern</span> nicht leer ist, werden die <span class="font-medium text-gray-800">unteren Zeilen</span> vollständig durch den importierten Text ersetzt (außer es wurde oben gleichzeitig eine <span class="font-medium text-gray-800">Starterlisten-PDF</span> gewählt – dann hat die PDF Vorrang).
                Zum nachträglichen Bearbeiten einzelner Zeilen das Feld leeren und erneut speichern.
            </p>
            <textarea
                name="teams_bulk"
                id="teams_bulk"
                rows="8"
                maxlength="200000"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] text-xs font-mono"
                placeholder="Box 9 | Startnr. 3 | Mercedes-AMG …&#10;Fahrer / Fahrzeugdetails: …&#10;&#10;Box … | …"
            >{{ old('teams_bulk', $draftDefaults['teams_bulk'] ?? '') }}</textarea>
            @error('teams_bulk')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div id="planned-event-team-rows" class="space-y-3">
            @php
                $teamRows = old('teams');
                if (! is_array($teamRows) && ! $isEdit && isset($draftDefaults['teams']) && is_array($draftDefaults['teams'])) {
                    $teamRows = $draftDefaults['teams'];
                }
                if (! is_array($teamRows)) {
                    $teamRows = $isEdit
                        ? $plannedEvent->teams->map(fn ($t) => ['name' => $t->name, 'notes' => $t->notes ?? ''])->values()->all()
                        : [];
                    if ($teamRows === []) {
                        $teamRows = [['name' => '', 'notes' => '']];
                    }
                }
            @endphp
            @foreach ($teamRows as $i => $row)
                <div class="planned-event-team-row flex flex-wrap gap-2 items-start border border-gray-100 rounded-md p-3 bg-gray-50/50">
                    <input
                        type="text"
                        name="teams[{{ $i }}][name]"
                        value="{{ $row['name'] ?? '' }}"
                        placeholder="Name / Act / Team / Nr."
                        class="block w-full sm:w-52 rounded-md border-gray-300 shadow-sm text-sm"
                    />
                    <input
                        type="text"
                        name="teams[{{ $i }}][notes]"
                        value="{{ $row['notes'] ?? '' }}"
                        placeholder="Notizen (Rolle, Look, …)"
                        class="block flex-1 min-w-[12rem] rounded-md border-gray-300 shadow-sm text-sm"
                    />
                    <button
                        type="button"
                        class="remove-team text-sm text-red-600 hover:underline shrink-0"
                        @if (count($teamRows) <= 1) style="visibility:hidden" @endif
                    >Entfernen</button>
                </div>
            @endforeach
        </div>
        <button type="button" id="add-planned-event-team" class="mt-3 text-sm font-medium text-[#092E48] hover:underline">
            + Eintrag hinzufügen
        </button>
    </div>

    <div class="flex flex-wrap gap-3 pt-2">
        <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-2xl text-white bg-[#092E48] hover:bg-[#0b3858] min-h-[2.75rem]">
            @if ($isEdit)
                Speichern
            @else
                Veranstaltung anlegen
            @endif
        </button>
        @if (! $isEdit)
            <button
                type="submit"
                formaction="{{ route('admin.settings.planned-events.draft') }}"
                formnovalidate
                class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-2xl border border-gray-300 text-gray-800 bg-white hover:bg-gray-50 min-h-[2.75rem]"
            >
                Entwurf speichern
            </button>
            @if (session()->has('planned_event_create_draft'))
                <button
                    type="submit"
                    formaction="{{ route('admin.settings.planned-events.draft.clear') }}"
                    formnovalidate
                    class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-2xl border border-red-200 text-red-800 bg-red-50 hover:bg-red-100 min-h-[2.75rem]"
                    onclick="return confirm('Gespeicherten Entwurf wirklich verwerfen?');"
                >
                    Entwurf verwerfen
                </button>
            @endif
        @endif
        <a href="{{ route('admin.settings.planned-events.index') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-2xl border border-gray-300 text-gray-700 hover:bg-gray-50 min-h-[2.75rem]">
            Abbrechen
        </a>
    </div>
</form>

@if ($venueAddressPresets->isNotEmpty())
    <script>
        (function () {
            var presets = @json($venuePresetPayload);
            if (!presets || !presets.length) {
                return;
            }
            function norm(s) {
                return String(s || '')
                    .trim()
                    .replace(/\s+/g, ' ')
                    .toLowerCase();
            }
            function applyPreset(p) {
                if (!p) {
                    return;
                }
                var loc = document.getElementById('location');
                if (loc) {
                    loc.value = p.location || '';
                }
                ['venue_street', 'venue_postal_code', 'venue_city', 'venue_state', 'venue_country', 'venue_country_code'].forEach(function (id) {
                    var el = document.getElementById(id);
                    if (!el) {
                        return;
                    }
                    var v = p[id] != null ? String(p[id]) : '';
                    el.value = v;
                });
            }
            function findByLocation(text) {
                var n = norm(text);
                if (!n) {
                    return null;
                }
                for (var i = 0; i < presets.length; i++) {
                    if (norm(presets[i].location) === n) {
                        return presets[i];
                    }
                }
                return null;
            }
            document.getElementById('venue_address_preset_select')?.addEventListener('change', function () {
                var v = this.value;
                if (v === '') {
                    return;
                }
                var i = parseInt(v, 10);
                if (!isNaN(i) && presets[i]) {
                    applyPreset(presets[i]);
                }
                this.selectedIndex = 0;
            });
            document.getElementById('location')?.addEventListener('change', function () {
                var p = findByLocation(this.value);
                if (p) {
                    applyPreset(p);
                }
            });
        })();
    </script>
@endif

<script>
(function () {
    const wrap = document.getElementById('planned-event-team-rows');
    if (!wrap) return;
    let idx = wrap.querySelectorAll('.planned-event-team-row').length;
    document.getElementById('add-planned-event-team')?.addEventListener('click', function () {
        const div = document.createElement('div');
        div.className = 'planned-event-team-row flex flex-wrap gap-2 items-start border border-gray-100 rounded-md p-3 bg-gray-50/50';
        div.innerHTML =
            '<input type="text" name="teams[' + idx + '][name]" placeholder="Name / Act / Team / Nr." class="block w-full sm:w-52 rounded-md border-gray-300 shadow-sm text-sm" />' +
            '<input type="text" name="teams[' + idx + '][notes]" placeholder="Notizen (Rolle, Look, …)" class="block flex-1 min-w-[12rem] rounded-md border-gray-300 shadow-sm text-sm" />' +
            '<button type="button" class="remove-team text-sm text-red-600 hover:underline shrink-0">Entfernen</button>';
        wrap.appendChild(div);
        idx++;
        div.querySelector('.remove-team')?.addEventListener('click', function () {
            removeRow(div);
        });
        refreshRemoveVisibility();
    });
    function removeRow(div) {
        if (wrap.querySelectorAll('.planned-event-team-row').length <= 1) return;
        div.remove();
        refreshRemoveVisibility();
    }
    function refreshRemoveVisibility() {
        const rows = wrap.querySelectorAll('.planned-event-team-row');
        const show = rows.length > 1;
        rows.forEach(function (row) {
            const b = row.querySelector('.remove-team');
            if (b) b.style.visibility = show ? 'visible' : 'hidden';
        });
    }
    wrap.querySelectorAll('.remove-team').forEach(function (btn) {
        btn.addEventListener('click', function () {
            removeRow(btn.closest('.planned-event-team-row'));
        });
    });
})();
</script>
