{{-- Nur außerhalb von newsEditForm einbinden: eigene <form> für Löschen ohne Verschachtelung --}}
<div x-show="activeTab === 'ot'" x-cloak class="flex flex-col gap-5 p-4 sm:p-6 min-w-0 max-w-full">
    <div class="order-2 rounded-xl border border-sky-200 bg-sky-50/40 p-4 space-y-4">
        <h3 class="text-sm font-semibold text-sky-900 uppercase tracking-wide">O‑Ton / Statement</h3>
        <p class="text-sm text-gray-600">Volltexte können hier gelesen und bearbeitet werden. Im Medienpaket erscheinen sie unter „O-Töne &amp; Updates“, sofern für Versand freigegeben.</p>

        <div class="space-y-3">
            @forelse(($newsItem->statements ?? collect()) as $s)
                <details class="rounded-lg border border-sky-100 bg-white p-3">
                    <summary class="cursor-pointer text-sm font-medium text-gray-900">
                        {{ $s->source_type_label ?? $s->source_type }}
                        @if(filled($s->source_label)) · {{ $s->source_label }} @endif
                        @if($s->received_at) · {{ $s->received_at->format('d.m.Y H:i') }} @endif
                        · {{ $s->is_active ? 'aktiv' : 'inaktiv' }}
                    </summary>
                    <div class="mt-2 space-y-2 text-sm text-gray-700">
                        @if(filled($s->summary))
                            <p class="text-gray-800 font-medium">Kurz: {{ $s->summary }}</p>
                        @endif
                        <div class="whitespace-pre-wrap break-words">{{ $s->transcript }}</div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2 items-center">
                        <a href="{{ route('admin.news.statements.edit', [$newsItem, $s]) }}" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Bearbeiten</a>
                        <form method="POST" action="{{ route('admin.news.statements.destroy', [$newsItem, $s]) }}" class="inline-flex items-center" onsubmit="return confirm('Dieses Statement wirklich löschen?');">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="confirmation" value="ja">
                            <button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md border border-red-300 bg-white text-red-700 hover:bg-red-50">Löschen</button>
                        </form>
                    </div>
                </details>
            @empty
                <p class="text-sm text-gray-600">Noch keine Statements hinterlegt.</p>
            @endforelse
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-1.5">
                <label for="statement_source_type" class="block text-sm font-medium text-gray-700">Quelle</label>
                <select id="statement_source_type" name="source_type" form="statementCreateForm" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                    @foreach(($statementSourceOptions ?? []) as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="space-y-1.5">
                <label for="statement_source_label" class="block text-sm font-medium text-gray-700">Genaue Bezeichnung</label>
                <input id="statement_source_label" type="text" name="source_label" form="statementCreateForm" maxlength="255" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]" placeholder="z. B. Polizei Bonn">
            </div>
            <div class="space-y-1.5">
                <label for="statement_statement_type" class="block text-sm font-medium text-gray-700">Art des O‑Tons</label>
                <select id="statement_statement_type" name="statement_type" form="statementCreateForm" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                    @foreach(($statementTypeOptions ?? []) as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="space-y-1.5">
                <label for="statement_received_at" class="block text-sm font-medium text-gray-700">Eingegangen am</label>
                <input id="statement_received_at" type="datetime-local" name="received_at" form="statementCreateForm" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
            </div>
        </div>
        <div class="space-y-1.5">
            <label for="statement_summary" class="block text-sm font-medium text-gray-700">Kurz-Zusammenfassung</label>
            <textarea id="statement_summary" name="summary" form="statementCreateForm" rows="2" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"></textarea>
        </div>
        <div class="space-y-1.5">
            <label for="statement_transcript" class="block text-sm font-medium text-gray-700">Transkript / Wortlaut</label>
            <textarea id="statement_transcript" name="transcript" form="statementCreateForm" rows="7" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]" required></textarea>
        </div>
        <div class="flex flex-wrap gap-4 text-sm">
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="show_in_mail" value="1" form="statementCreateForm" class="rounded border-gray-300 text-[#092E48]" checked> In Angebotsmail anzeigen</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_publishable" value="1" form="statementCreateForm" class="rounded border-gray-300 text-[#092E48]" checked> Für Veröffentlichung freigegeben</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_active" value="1" form="statementCreateForm" class="rounded border-gray-300 text-[#092E48]" checked> Aktiv</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="create_update" value="1" form="statementCreateForm" class="rounded border-gray-300 text-[#092E48]" checked> Automatisch als Update anlegen</label>
        </div>
        <div>
            <button type="submit" form="statementCreateForm" class="inline-flex justify-center items-center w-full sm:w-auto min-h-[2.75rem] px-4 py-2 text-sm font-medium rounded-lg bg-[#092E48] text-white hover:bg-[#0b3858]">Statement speichern</button>
        </div>
    </div>

    <div id="news-updates-block" class="order-1 rounded-xl border border-indigo-200 bg-indigo-50/30 p-4 space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h3 class="text-sm font-semibold text-indigo-900 uppercase tracking-wide">Einsatz-Updates</h3>
            <button type="submit" form="updateCreateForm" class="inline-flex justify-center items-center w-full sm:w-auto min-h-[2.75rem] px-3 py-2 text-xs sm:text-sm font-medium rounded-lg border border-indigo-300 text-indigo-900 bg-white hover:bg-indigo-50">Update veröffentlichen</button>
        </div>
        <p class="text-sm text-gray-600">
            Hier nur das <strong>Delta</strong> seit der letzten Mail (PM, Lage, Zahlen) – Medien kommen über „Bilder“/„Videos“.
            Beim Update-Versand wird dieser Text der <strong>Hauptinhalt der Angebotsmail</strong>; die Erstmeldung bleibt als Bezug erhalten.
        </p>
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="activeTab = 'bilder'" class="inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-md border border-indigo-200 bg-white text-indigo-900 hover:bg-indigo-50">
                Neue Bilder hochladen
            </button>
            <button type="button" @click="activeTab = 'videos'" class="inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-md border border-indigo-200 bg-white text-indigo-900 hover:bg-indigo-50">
                Neues Video hochladen
            </button>
        </div>

        <div class="space-y-3">
            @forelse(($newsItem->updates ?? collect()) as $u)
                <details class="rounded-lg border border-indigo-100 bg-white p-3">
                    <summary class="cursor-pointer text-sm font-medium text-gray-900">
                        {{ $u->type_label ?? $u->type }}
                        @if(filled($u->title)) · {{ $u->title }} @endif
                        @if($u->happened_at) · {{ $u->happened_at->format('d.m.Y H:i') }} @endif
                        · {{ $u->is_active ? 'aktiv' : 'inaktiv' }}
                    </summary>
                    <div class="mt-2 space-y-2 text-sm text-gray-700">
                                        @if(filled($u->display_source_line))
                                            <p class="text-xs text-gray-500 mb-2">Quelle: {{ $u->display_source_line }}</p>
                                        @endif
                                        <div class="whitespace-pre-wrap break-words">{{ trim((string) $u->body) }}</div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2 items-center">
                        <a href="{{ route('admin.news.updates.edit', [$newsItem, $u]) }}" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Bearbeiten</a>
                        <form method="POST" action="{{ route('admin.news.updates.destroy', [$newsItem, $u]) }}" class="inline-flex items-center" onsubmit="return confirm('Dieses Update wirklich löschen?');">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="confirmation" value="ja">
                            <button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md border border-red-300 bg-white text-red-700 hover:bg-red-50">Löschen</button>
                        </form>
                    </div>
                </details>
            @empty
                <p class="text-sm text-gray-600">Noch keine Updates hinterlegt.</p>
            @endforelse
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-1.5">
                <label for="update_type" class="block text-sm font-medium text-gray-700">Update-Typ</label>
                <select id="update_type" name="type" form="updateCreateForm" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                    @foreach(($updateTypeOptions ?? []) as $key => $label)
                        <option value="{{ $key }}" @selected(old('type', \App\Models\NewsItemUpdate::TYPE_SITUATION) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="space-y-1.5">
                <label for="update_title" class="block text-sm font-medium text-gray-700">Titel (optional)</label>
                <input id="update_title" type="text" name="title" form="updateCreateForm" maxlength="255" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]" placeholder="z. B. PM der Feuerwehr / Neue Sperrung">
            </div>
            <div class="space-y-1.5">
                <label for="update_source_type" class="block text-sm font-medium text-gray-700">Quelle (optional)</label>
                <select id="update_source_type" name="source_type" form="updateCreateForm" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                    <option value="">—</option>
                    @foreach(($updateSourceOptions ?? []) as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="space-y-1.5">
                <label for="update_happened_at" class="block text-sm font-medium text-gray-700">Zeitpunkt</label>
                <input id="update_happened_at" type="datetime-local" name="happened_at" form="updateCreateForm" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
            </div>
        </div>
        <div class="space-y-1.5">
            <label for="update_source_label" class="block text-sm font-medium text-gray-700">Genaue Quellenbezeichnung</label>
            <input id="update_source_label" type="text" name="source_label" form="updateCreateForm" maxlength="255" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]" placeholder="z. B. Feuerwehr Bergheim, Leitstelle, eigene Beobachtung">
        </div>
        <div class="space-y-1.5">
            <label for="update_presseportal_url" class="block text-sm font-medium text-gray-700">Presseportal-URL (optional)</label>
            <div class="flex flex-col sm:flex-row gap-2 sm:items-stretch">
                <input id="update_presseportal_url" type="url" name="presseportal_url" form="updateCreateForm" maxlength="2000"
                    class="block w-full min-w-0 rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] text-sm"
                    placeholder="https://www.presseportal.de/blaulicht/pm/7304/6245919">
                <button type="button" id="btnPresseportalLoadUpdate"
                    class="inline-flex justify-center items-center shrink-0 px-3 py-2 text-sm font-medium rounded-md border border-indigo-300 text-indigo-900 bg-white hover:bg-indigo-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    @if(empty($presseportalKeySet)) disabled title="PRESSEPORTAL_API_KEY fehlt" @endif>
                    Text laden
                </button>
            </div>
            <input type="hidden" name="presseportal_story_id" id="update_presseportal_story_id" value="" form="updateCreateForm">
            <input type="hidden" name="presseportal_office_id" id="update_presseportal_office_id" value="" form="updateCreateForm">
            @if(empty($presseportalKeySet))
                <p class="text-xs text-amber-800">API-Key fehlt: unter <a href="{{ route('admin.settings.presseportal') }}" class="underline">Einstellungen → Presseportal</a> <code class="text-[11px] bg-amber-50 px-1 rounded">PRESSEPORTAL_API_KEY</code> in der .env setzen.</p>
            @elseif(($presseportalWhitelistCount ?? 0) > 0)
                <p class="text-xs text-gray-500">Whitelist aktiv ({{ $presseportalWhitelistCount }} Dienststelle(n)) – nur importierbare URLs von hinterlegten IDs.</p>
            @else
                <p class="text-xs text-gray-500">Keine Dienststellen-Whitelist – alle gültigen Presseportal-URLs sind erlaubt.</p>
            @endif
        </div>
        <div class="space-y-1.5">
            <label for="update_body" class="block text-sm font-medium text-gray-700">Neuer Stand (nur Änderungen seit letztem Update)</label>
            <textarea id="update_body" name="body" form="updateCreateForm" rows="5" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]" required placeholder="z. B. 15:42 Uhr: Feuerwehr bestätigt Brand in Werkhalle. Drei neue Bilder und ein Video sind hochgeladen."></textarea>
        </div>
        <input type="hidden" name="show_in_mail" value="1" form="updateCreateForm">
        <div class="flex flex-wrap gap-4 text-sm">
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="show_in_article" value="1" form="updateCreateForm" class="rounded border-gray-300 text-[#092E48]"> Im Artikel anzeigen</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_active" value="1" form="updateCreateForm" class="rounded border-gray-300 text-[#092E48]" checked> Aktiv</label>
        </div>
    </div>
</div>
