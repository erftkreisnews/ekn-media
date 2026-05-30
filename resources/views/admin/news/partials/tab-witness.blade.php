<div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden min-w-0 max-w-full">
    <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
        <h2 class="text-base font-semibold text-gray-900">Zeugen / Upload-Links</h2>
        <p class="mt-1 text-xs text-gray-500 max-w-3xl">
            Nur wer den <strong>geheimen Link</strong> von der Redaktion erhalten hat, kann Material hochladen – kein öffentliches Formular ohne Token.
            Einwilligung und Rechtsbestätigung werden mit Zeitstempel, IP und Textversion protokolliert (bitte mit Ihrer Rechtsabteilung abstimmen).
        </p>
        <p class="mt-2 text-xs text-gray-600">
            Für materialunabhängige Sammel-Links ohne feste Meldung:
            <a href="{{ route('admin.witness.inbox') }}" class="text-[#092E48] underline hover:no-underline">Zeugen-Eingang (ohne Meldung)</a>
        </p>
    </div>
    <div class="px-4 sm:px-6 py-4 sm:py-6 space-y-6 min-w-0 max-w-full">
        @if (! \Illuminate\Support\Facades\Schema::hasTable('news_item_witness_links'))
            <p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                Datenbank-Migration für Zeugen-Uploads noch nicht ausgeführt. Bitte <code class="text-xs">php artisan migrate</code> ausführen.
            </p>
        @elseif (empty($newsItem))
            <p class="text-sm text-gray-600">
                Speichern Sie die Meldung zuerst. Anschließend können Sie im Reiter „Zeugen“ persönliche Upload-Links erzeugen und eingehende Dateien prüfen.
            </p>
        @else
            @if (session('witness_plain_token'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 space-y-2">
                    <p class="text-sm font-semibold text-emerald-900">Link erzeugt – bitte jetzt kopieren (wird nicht erneut angezeigt)</p>
                    <label class="block text-xs font-medium text-emerald-900">Vollständige URL</label>
                    <input type="text" readonly
                        class="w-full min-w-0 text-sm font-mono rounded border border-emerald-300 bg-white px-2 py-2"
                        value="{{ \App\Support\WitnessPortal::uploadUrl(session('witness_plain_token')) }}"
                        onclick="this.select()">
                </div>
            @endif

            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 space-y-3">
                <h3 class="text-sm font-semibold text-gray-900">Neuen Upload-Link erzeugen</h3>
                <form method="post" action="{{ route('admin.news.witness-links.store', $newsItem) }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 sm:items-end">
                    @csrf
                    <div class="sm:col-span-2">
                        <label for="witness-label" class="block text-xs font-medium text-gray-500">Interne Bezeichnung (optional)</label>
                        <input type="text" name="label" id="witness-label" maxlength="191" placeholder="z. B. Zeuge Parkplatz"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div>
                        <label for="witness-expires" class="block text-xs font-medium text-gray-500">Gültigkeit</label>
                        <select name="expires_in_days" id="witness-expires" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option value="">unbegrenzt</option>
                            <option value="7">7 Tage</option>
                            <option value="14" selected>14 Tage</option>
                            <option value="30">30 Tage</option>
                            <option value="90">90 Tage</option>
                        </select>
                    </div>
                    <div>
                        <label for="witness-max" class="block text-xs font-medium text-gray-500">Max. Uploads</label>
                        <input type="number" name="max_uploads" id="witness-max" min="1" max="200" value="20"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div class="sm:col-span-2 lg:col-span-4">
                        <button type="submit" class="inline-flex min-h-[2.75rem] items-center rounded-md bg-[#092E48] px-4 py-2 text-sm font-medium text-white hover:bg-[#0b3858]">
                            Link erzeugen
                        </button>
                    </div>
                </form>
                @if (filled(config('witness.portal_host')))
                    <p class="text-xs text-gray-600">
                        Kurz-URL (Subdomain): <code class="rounded bg-white px-1">{{ config('witness.portal_scheme', 'https') }}://{{ config('witness.portal_host') }}/e/…</code>
                        · funktioniert parallel immer auch: <code class="rounded bg-white px-1">{{ url('/witness/e/…') }}</code>
                        (DNS/Webserver: Subdomain auf dieselbe <code class="text-xs">public/</code> wie die Hauptdomain)
                    </p>
                @else
                    <p class="text-xs text-gray-600">Aktuell: Links unter <code class="rounded bg-white px-1">{{ url('/witness/e/…') }}</code>. Optional <code class="text-xs">WITNESS_PORTAL_HOST</code> in der <code class="text-xs">.env</code> (z. B. zeugen.…).</p>
                @endif
            </div>

            <div>
                <h3 class="text-sm font-semibold text-gray-900 mb-2">Aktive und vergangene Links</h3>
                <div class="overflow-x-auto min-w-0 [scrollbar-width:thin]">
                    <table class="min-w-full text-sm divide-y divide-gray-200">
                        <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                            <tr>
                                <th class="px-2 py-2">Erstellt</th>
                                <th class="px-2 py-2">Label</th>
                                <th class="px-2 py-2">Uploads</th>
                                <th class="px-2 py-2">Status</th>
                                <th class="px-2 py-2 text-right">Aktion</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse ($newsItem->witnessLinks ?? [] as $wl)
                                @php
                                    $wlExpired = $wl->expires_at && $wl->expires_at->isPast();
                                    $wlRevoked = $wl->revoked_at !== null;
                                    $wlFull = ($wl->submissions_count ?? 0) >= max(1, (int) $wl->max_uploads);
                                @endphp
                                <tr>
                                    <td class="px-2 py-2 whitespace-nowrap text-gray-700">{{ $wl->created_at?->format('d.m.Y H:i') }}</td>
                                    <td class="px-2 py-2 text-gray-800 break-words max-w-[12rem]">{{ $wl->label ?: '—' }}</td>
                                    <td class="px-2 py-2">{{ $wl->submissions_count ?? 0 }} / {{ $wl->max_uploads }}</td>
                                    <td class="px-2 py-2">
                                        @if ($wlRevoked)
                                            <span class="text-red-700">zurückgezogen</span>
                                        @elseif ($wlExpired)
                                            <span class="text-amber-800">abgelaufen</span>
                                        @elseif ($wlFull)
                                            <span class="text-amber-800">Limit</span>
                                        @else
                                            <span class="text-emerald-800">aktiv</span>
                                        @endif
                                    </td>
                                    <td class="px-2 py-2 text-right">
                                        @if (! $wlRevoked)
                                            <form method="POST" action="{{ route('admin.news.witness-links.destroy', [$newsItem, $wl]) }}" class="inline"
                                                onsubmit="return window.adminConfirmDelete(this)"
                                                data-delete-prompt="Upload-Link wirklich zurückziehen? Geben Sie zur Bestätigung „ja“ ein.">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-700 hover:underline text-xs font-medium">Zurückziehen</button>
                                            </form>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-2 py-4 text-gray-500">Noch keine Links.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-gray-900 mb-2">Eingereichte Dateien</h3>
                <p class="text-xs text-gray-600 mb-3 max-w-3xl">
                    Vorschau nur für die Redaktion. Nach Prüfung „Übernehmen“ kopiert die App die Datei auf den konfigurierten Medien-Speicher (z. B. S3, siehe <code class="text-xs">media_storage.disk</code>) wie bei einem normalen Upload und legt Bild/Video/Audio in der Meldung an.
                </p>
                <div class="space-y-4 min-w-0 max-w-full">
                    @forelse ($witnessSubmissions ?? [] as $sub)
                        @php
                            $defaultType = \App\Services\Witness\PromoteWitnessSubmissionService::inferMediaTypeFromMime($sub->mime) ?? 'image';
                            $isImageMime = $sub->mime && str_starts_with((string) $sub->mime, 'image/');
                            $isVideoMime = $sub->mime && str_starts_with((string) $sub->mime, 'video/');
                        @endphp
                        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm space-y-3 min-w-0 max-w-full">
                            <div class="flex flex-col sm:flex-row gap-4 min-w-0">
                                <div class="shrink-0 w-full sm:w-44 min-h-[6rem] bg-gray-100 rounded-md border border-gray-200 overflow-hidden flex items-center justify-center">
                                    @if ($sub->stored_path && $isImageMime)
                                        <img src="{{ route('admin.news.witness-submissions.preview', [$newsItem, $sub]) }}" alt="{{ $newsItem->title ?: ($newsItem->subheadline ?: 'Bildmaterial von Erftkreis News Media') }}" class="max-h-40 w-full object-contain">
                                    @elseif ($sub->stored_path && $isVideoMime)
                                        <video src="{{ route('admin.news.witness-submissions.preview', [$newsItem, $sub]) }}" controls class="max-h-40 w-full" preload="metadata"></video>
                                    @elseif ($sub->stored_path)
                                        <span class="text-xs text-gray-500 px-2 text-center">Vorschau: <a href="{{ route('admin.news.witness-submissions.preview', [$newsItem, $sub]) }}" class="text-[#092E48] underline" target="_blank" rel="noopener">öffnen</a></span>
                                    @else
                                        <span class="text-xs text-gray-400">(keine Datei)</span>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1 text-sm space-y-1">
                                    <p class="text-xs text-gray-500">{{ $sub->created_at?->format('d.m.Y H:i') }}
                                        · <span class="font-mono text-[11px]">#{{ $sub->id }}</span>
                                        · Status: <strong>{{ $sub->status }}</strong>
                                    </p>
                                    <p class="font-medium text-gray-900 break-words">{{ $sub->submitter_name }} · {{ $sub->submitter_email }}</p>
                                    @if ($sub->submitter_phone)
                                        <p class="text-xs text-gray-600">{{ $sub->submitter_phone }}</p>
                                    @endif
                                    <p class="break-all text-gray-800"><span class="text-gray-500">Datei:</span> {{ $sub->original_filename }}</p>
                                    @if ($sub->mime)
                                        <p class="text-xs text-gray-600">MIME: {{ $sub->mime }}</p>
                                    @endif
                                    @if ($sub->size_bytes)
                                        <p class="text-xs text-gray-600">{{ number_format($sub->size_bytes / 1024, 0, ',', '.') }} KB</p>
                                    @endif
                                    @if ($sub->credit_anonymous)
                                        <p class="text-xs font-medium text-amber-800 bg-amber-50 border border-amber-100 rounded px-2 py-1 inline-block">Zeuge wünscht keine Namensnennung als Urheber/Fotograf</p>
                                    @endif
                                    @if (filled($sub->witness_suggested_title))
                                        <p class="text-xs text-gray-700"><span class="font-medium">Vorschlag Zeuge:</span> {{ $sub->witness_suggested_title }}</p>
                                    @endif
                                    <p class="text-[11px] text-gray-500">Einwilligung v. {{ $sub->consent_text_version }} · Hash {{ Str::limit($sub->consent_body_hash, 14) }}… @if ($sub->ip_address) · IP {{ $sub->ip_address }} @endif</p>
                                    <div class="flex flex-wrap gap-3 pt-2">
                                        @if ($sub->stored_path)
                                            <a href="{{ route('admin.news.witness-submissions.download', [$newsItem, $sub]) }}" class="text-sm font-medium text-[#092E48] hover:underline">Download (Original)</a>
                                        @endif
                                        @if ($sub->status === \App\Models\NewsItemWitnessSubmission::STATUS_ACCEPTED && $sub->news_item_media_id)
                                            <a href="{{ route('admin.news.media.edit', [$newsItem, $sub->news_item_media_id]) }}" class="text-sm font-medium text-emerald-800 hover:underline">Medien-Eintrag #{{ $sub->news_item_media_id }} bearbeiten</a>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            @if ($sub->status === \App\Models\NewsItemWitnessSubmission::STATUS_PENDING && $sub->stored_path)
                                <form method="post" action="{{ route('admin.news.witness-submissions.promote', [$newsItem, $sub]) }}" class="border-t border-gray-100 pt-3 space-y-3">
                                    @csrf
                                    <p class="text-xs font-semibold text-gray-800">In die Meldung übernehmen (Speicher wie regulärer Upload)</p>
                                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500" for="wt-{{ $sub->id }}-type">Medientyp</label>
                                            <select name="media_type" id="wt-{{ $sub->id }}-type" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                                <option value="image" @selected(old('media_type', $defaultType) === 'image')>Bild</option>
                                                <option value="video" @selected(old('media_type', $defaultType) === 'video')>Video</option>
                                                <option value="audio" @selected(old('media_type', $defaultType) === 'audio')>Audio</option>
                                            </select>
                                            @error('media_type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="block text-xs font-medium text-gray-500" for="wt-{{ $sub->id }}-title">Bildtitel / Titel (optional)</label>
                                            <input type="text" name="image_title" id="wt-{{ $sub->id }}-title" value="{{ old('image_title', $sub->witness_suggested_title) }}" maxlength="255" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                            @error('image_title')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                        </div>
                                        <div class="sm:col-span-2 lg:col-span-4">
                                            <label class="block text-xs font-medium text-gray-500" for="wt-{{ $sub->id }}-cap">Bildunterschrift / Beschreibung (optional)</label>
                                            <textarea name="caption" id="wt-{{ $sub->id }}-cap" rows="2" maxlength="4000" class="mt-1 block w-full rounded-md border-gray-300 text-sm">{{ old('caption') }}</textarea>
                                            @error('caption')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="block text-xs font-medium text-gray-500" for="wt-{{ $sub->id }}-photo">Fotograf / Credit (optional)</label>
                                            <input type="text" name="photographer" id="wt-{{ $sub->id }}-photo" value="{{ old('photographer', $sub->credit_anonymous ? '' : $sub->submitter_name) }}" maxlength="255" placeholder="{{ $sub->credit_anonymous ? 'Leer lassen für: '.config('witness.anonymous_photographer_label') : '' }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                            @error('photographer')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                        </div>
                                        <div class="sm:col-span-2 flex items-end">
                                            <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                                <input type="hidden" name="apply_anonymous_credit" value="0">
                                                <input type="checkbox" name="apply_anonymous_credit" value="1" class="rounded border-gray-300" @checked(old('apply_anonymous_credit', $sub->credit_anonymous))>
                                                <span>Anonymes Credit-Label verwenden (statt Name)</span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <button type="submit" class="inline-flex min-h-[2.75rem] items-center rounded-md bg-[#092E48] px-4 py-2 text-sm font-medium text-white hover:bg-[#0b3858]">
                                            Übernehmen & bearbeiten
                                        </button>
                                    </div>
                                </form>
                                <form method="post" action="{{ route('admin.news.witness-submissions.reject', [$newsItem, $sub]) }}" class="inline" onsubmit="return confirm('Einreichung wirklich ablehnen? Die Datei wird vom Server entfernt.');">
                                    @csrf
                                    <button type="submit" class="text-sm text-red-700 hover:underline font-medium">Ablehnen &amp; Datei löschen</button>
                                </form>
                            @elseif ($sub->status === \App\Models\NewsItemWitnessSubmission::STATUS_REJECTED)
                                <p class="text-xs text-red-800 border-t border-red-100 pt-2">Abgelehnt (Datei entfernt).</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 py-4">Noch keine Einreichungen.</p>
                    @endforelse
                </div>
            </div>
        @endif
    </div>
</div>
