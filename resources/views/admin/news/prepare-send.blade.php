@extends('layouts.admin')

@section('content')
<div class="py-6 max-w-2xl mx-auto px-4">
    <h1 class="text-2xl font-semibold text-gray-900">Versand vorbereiten</h1>
    <p class="mt-1 text-sm text-gray-600">{{ $newsItem->title }}</p>
    @if($newsItem->moid)
        <p class="mt-2 text-sm text-green-700">MoID: <strong>{{ $newsItem->moid }}</strong></p>
    @endif
    @if(!empty($onlyWdrAllowed))
        <div class="mt-3 p-3 rounded-lg bg-amber-50 border border-amber-200">
            <p class="text-sm font-medium text-amber-800">Nur WDR: Diese Meldung hat eine MoID und darf nur an den Westdeutschen Rundfunk (WDR) versendet werden.</p>
            @if($destinations->isEmpty())
                <p class="text-sm text-amber-700 mt-1">Es ist kein WDR-Versandziel (Typ E-Mail) angelegt. Bitte unter Kunden → Organisation WDR ein Versandziel vom Typ E-Mail anlegen.</p>
            @endif
        </div>
    @endif

    @if (session('error'))
        <div class="mt-4 rounded-md bg-red-50 p-4 border border-red-200">
            <p class="text-sm text-red-800">{{ session('error') }}</p>
        </div>
    @endif
    @if (session('status'))
        <div class="mt-4 rounded-md bg-emerald-50 p-4 border border-emerald-200">
            <p class="text-sm text-emerald-800">{{ session('status') }}</p>
        </div>
    @endif

    @php
        $hasFtpDestinations = isset($ftpDestinations) && $ftpDestinations->isNotEmpty();
    @endphp

    @php
        // PATCH: add statements and updates support for news items
        $hasPriorDeliveries = (bool) ($hasPriorDeliveries ?? $newsItem->hasPriorDeliveries());
        $isUpdateContext = $hasPriorDeliveries
            || request()->query('context') === 'update'
            || in_array((string) ($newsItem->update_type ?? ''), ['update', 'correction', 'final'], true);
        $hasDispatchMedia = $newsItem->media()->where('versand', true)->exists();
        $allowsMediaMissingFirstReport = (bool) ($newsItem->planned_video_upload ?? false);
    @endphp
    <div class="mt-6" x-data="{ tab: 'email' }">
        @if($hasFtpDestinations)
            <div class="border-b border-gray-200 mb-4">
                <nav class="-mb-px flex space-x-4 text-sm font-medium">
                    <button type="button"
                            @click="tab = 'email'"
                            :class="tab === 'email' ? 'border-[#092E48] text-[#092E48]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap border-b-2 px-3 py-2">
                        E-Mail-Versand
                    </button>
                    <button type="button"
                            @click="tab = 'ftp'"
                            :class="tab === 'ftp' ? 'border-[#092E48] text-[#092E48]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap border-b-2 px-3 py-2">
                        FTP / SFTP
                    </button>
                </nav>
            </div>
        @endif

        {{-- Tab: E-Mail-Versand --}}
        <div x-show="tab === 'email'">
            <form method="POST" action="{{ route('admin.news.send', $newsItem) }}" class="bg-white rounded-lg border border-gray-200 p-6 space-y-4" id="send_form">
                @csrf
                @if($isUpdateContext)
                    <input type="hidden" name="is_update_delivery" value="1">
                @endif

                @if($isUpdateContext)
                    @php
                        $updatePreview = $updatePreview ?? [];
                        $suggestedNote = old('update_note', (string) ($updatePreview['suggested_note'] ?? ''));
                        $previewUpdates = collect($updatePreview['mail_updates'] ?? []);
                        $previewStatements = collect($updatePreview['mail_statements'] ?? []);
                        $previewMedia = $updatePreview['new_media'] ?? ['images' => 0, 'videos' => 0, 'audios' => 0];
                        $hasPrimaryContent = (bool) ($updatePreview['has_primary_content'] ?? false);
                    @endphp
                    <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-3 space-y-2">
                        <p class="text-sm font-medium text-indigo-900">Update-Versand – Folgemeldung zur Erstmeldung</p>
                        <p class="text-xs text-indigo-800">
                            Die Redaktion sieht sofort: Erstmeldung liegt vor, dies ist der <strong>neue Stand</strong>.
                            Betreff: <strong>UPDATE</strong> · Bezug zur NewsID {{ $newsItem->display_news_id }}.
                        </p>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 space-y-2">
                        <p class="text-sm font-medium text-gray-900">Vorschau: Das steht in der Mail</p>
                        <ul class="text-sm text-gray-700 space-y-1 list-disc list-inside">
                            @if($hasPrimaryContent)
                                @foreach($previewUpdates as $u)
                                    <li>
                                        <span class="font-medium">{{ $u->type_label ?? $u->type }}</span>
                                        @if(filled($u->title)) – {{ $u->title }} @endif
                                        <span class="text-gray-500">({{ \Illuminate\Support\Str::limit((string) $u->body, 90) }})</span>
                                    </li>
                                @endforeach
                                @foreach($previewStatements as $s)
                                    <li>
                                        <span class="font-medium">O-Ton</span>
                                        @if(filled($s->source_label)) – {{ $s->source_label }} @endif
                                    </li>
                                @endforeach
                            @else
                                <li class="text-amber-800">Noch kein Einsatz-Update im Reiter „O-Töne &amp; Updates“ – nur Medien/Hinweise.</li>
                            @endif
                            @if(($previewMedia['images'] ?? 0) + ($previewMedia['videos'] ?? 0) + ($previewMedia['audios'] ?? 0) > 0)
                                <li>
                                    Neue Medien:
                                    {{ (int) ($previewMedia['images'] ?? 0) }} Fotos,
                                    {{ (int) ($previewMedia['videos'] ?? 0) }} Videos,
                                    {{ (int) ($previewMedia['audios'] ?? 0) }} Audios
                                </li>
                            @endif
                        </ul>
                        @if(! $hasPrimaryContent)
                            <a href="{{ route('admin.news.edit', ['newsItem' => $newsItem, 'tab' => 'ot']) }}" class="inline-flex text-xs font-medium text-indigo-800 underline">
                                Einsatz-Update jetzt erfassen
                            </a>
                        @endif
                    </div>

                    <div>
                        <label for="update_note" class="block text-sm font-medium text-gray-700">Kurzzeile für die Redaktion (optional)</label>
                        <textarea
                            name="update_note"
                            id="update_note"
                            rows="2"
                            maxlength="500"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                            placeholder="Wird automatisch befüllt, wenn du das Feld leer lässt."
                        >{{ $suggestedNote }}</textarea>
                        <p class="mt-1 text-xs text-gray-500">Vorschlag aus Systemdaten – du kannst anpassen oder leer lassen (dann wird der Vorschlag automatisch versendet).</p>
                    </div>
                @endif
                @if(!$isUpdateContext && !$hasDispatchMedia && !$allowsMediaMissingFirstReport)
                    <div class="rounded-lg border border-red-200 bg-red-50 p-3">
                        <p class="text-sm font-medium text-red-900">Erstmeldung aktuell gesperrt</p>
                        <p class="text-xs text-red-800 mt-1">Es ist noch kein Medium mit <strong>Versand = Ja</strong> freigegeben. Erstmeldungen ohne Medien können nicht versendet werden.</p>
                    </div>
                @elseif(!$isUpdateContext && !$hasDispatchMedia && $allowsMediaMissingFirstReport)
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                        <p class="text-sm font-medium text-amber-900">Ausnahme aktiv: Video-Upload geplant</p>
                        <p class="text-xs text-amber-800 mt-1">Erstmeldung ohne Medien ist erlaubt, weil <strong>Video-Upload geplant</strong> gesetzt ist. Bitte Update mit Medien nachreichen.</p>
                    </div>
                @endif

                <p class="text-sm font-medium text-gray-700">Wohin soll das Medienpaket per E-Mail versendet werden?</p>

                @if($destinations->isNotEmpty())
                    <div class="mb-4">
                        <p class="text-sm font-medium text-gray-700 mb-2">Versandziele (E-Mail) der Organisationen – anklicken</p>
                        <div class="border border-gray-200 rounded-lg divide-y divide-gray-200 max-h-80 overflow-y-auto bg-gray-50/50" id="destinations_list">
                            @foreach($destinationsByOrg as $orgId => $dests)
                                <div class="bg-white first:rounded-t-lg last:rounded-b-lg">
                                    @php
                                        $org = $orgId ? $dests->first()->organization : null;
                                    @endphp
                                    @if($org)
                                        <p class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide bg-gray-100">{{ $org->name }}</p>
                                    @else
                                        <p class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide bg-gray-100">Ohne Organisation</p>
                                    @endif
                                    @foreach($dests as $d)
                                        @php
                                            $toAddresses = $d->getEmailToAddresses();
                                            $toPreview = count($toAddresses) > 0 ? implode(', ', array_slice($toAddresses, 0, 2)) : '(keine To-Adressen)';
                                            if (count($toAddresses) > 2) {
                                                $toPreview .= ' …';
                                            }
                                        @endphp
                                        <button
                                            type="button"
                                            data-destination-id="{{ $d->id }}"
                                            class="destination-item w-full text-left px-4 py-3 hover:bg-[#092E48]/5 focus:bg-[#092E48]/10 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-[#092E48] transition-colors"
                                        >
                                            <div class="flex items-start gap-3">
                                                <span class="mt-1 inline-flex h-4 w-4 rounded-full border border-gray-400 bg-white destination-indicator"></span>
                                                <div>
                                                    <span class="font-medium text-gray-900">{{ $d->label }}</span>
                                                    <span class="text-sm text-gray-600 block mt-0.5">{{ $toPreview }}</span>
                                                </div>
                                            </div>
                                        </button>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                        <p class="mt-2 text-xs text-gray-500">Vor dem Titel (z. B. „BILD NRW“) zeigt der Kreis die Auswahl an. Ein Klick wählt das Versandziel. E-Mail geht an alle beim Ziel hinterlegten Adressen (To, CC, BCC).</p>
                    </div>

                    <p class="text-sm text-gray-600">oder</p>
                @endif

                @if(empty($restrictByUserOrganizations))
                    <div>
                        <label for="recipient_email" class="block text-sm font-medium text-gray-700">E-Mail-Adresse(n) (wenn kein Versandziel gewählt)</label>
                        <input
                            type="text"
                            name="recipient_email"
                            id="recipient_email"
                            value="{{ old('recipient_email', $defaultEmail) }}"
                            @if($destinations->isEmpty()) required @endif
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                            placeholder="empfaenger1@example.com, empfaenger2@example.com"
                        >
                        @error('recipient_email')
                            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <p class="text-sm text-gray-500">Mehrere Adressen sind möglich (durch Komma getrennt). Die Empfänger wählen auf dem Versand-Link selbst Medienhaus und Format und können sich optional als Empfänger anlegen lassen.</p>
                @else
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                        <p class="text-sm text-amber-800">
                            Direkter Versand an freie Einzel-E-Mail-Adressen ist für deinen Benutzer deaktiviert.
                            Bitte nutze nur freigegebene Versandziele.
                        </p>
                    </div>
                @endif

                <div class="flex gap-3 pt-4">
                    <button type="submit" class="px-4 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]">
                        @if($isUpdateContext)
                            Update per E-Mail versenden
                        @else
                            Erstmeldung per E-Mail versenden
                        @endif
                    </button>
                    <a href="{{ route('admin.news.edit', $newsItem) }}" class="px-4 py-2 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                        Abbrechen
                    </a>
                </div>
            </form>
        </div>

        {{-- Tab: FTP-/SFTP-Versand --}}
        @if($hasFtpDestinations)
            <div x-show="tab === 'ftp'">
                <form method="POST" action="{{ route('admin.news.send-ftp', $newsItem) }}" class="bg-white rounded-lg border border-gray-200 p-6 space-y-4 mt-4" id="ftp_form">
                    @csrf

                    <input type="hidden" name="ftp_destination_id" id="ftp_destination_id" value="{{ old('ftp_destination_id') }}">

                    <p class="text-sm font-medium text-gray-700">Wohin sollen die Medien per FTP / SFTP hochgeladen werden?</p>

                    <div class="mb-2">
                        <p class="text-sm font-medium text-gray-700 mb-2">FTP-/SFTP-Versandziele – anklicken</p>
                        <div class="border border-gray-200 rounded-lg divide-y divide-gray-200 max-h-80 overflow-y-auto bg-gray-50/50" id="ftp_destinations_list">
                            @foreach($ftpDestinationsByOrg as $orgId => $dests)
                                <div class="bg-white first:rounded-t-lg last:rounded-b-lg">
                                    @php
                                        $org = $orgId ? $dests->first()->organization : null;
                                    @endphp
                                    @if($org)
                                        <p class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide bg-gray-100">{{ $org->name }}</p>
                                    @else
                                        <p class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide bg-gray-100">Ohne Organisation</p>
                                    @endif
                                    @foreach($dests as $d)
                                        <button
                                            type="button"
                                            data-ftp-destination-id="{{ $d->id }}"
                                            class="ftp-destination-item w-full text-left px-4 py-3 hover:bg-[#092E48]/5 focus:bg-[#092E48]/10 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-[#092E48] transition-colors"
                                        >
                                            <span class="font-medium text-gray-900">{{ $d->label }}</span>
                                            <span class="text-xs text-gray-600 block mt-0.5">{{ strtoupper($d->type) }} · {{ $d->getHostOrConfig() }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                        <p class="mt-2 text-xs text-gray-500">
                            Upload umfasst alle Medien dieser Nachricht, die mit „Versand = Ja“ markiert sind. Der Upload läuft im Hintergrund (Queue).
                            Bereits erfolgreich an dieses Ziel übertragene Dateien werden beim nächsten Upload automatisch übersprungen.
                        </p>
                    </div>

                    @php
                        $oldFtpMediaIds = collect((array) old('ftp_media_ids', []))->map(fn ($id) => (int) $id)->all();
                        $hasOldFtpMedia = count($oldFtpMediaIds) > 0;
                        $preselectedFtpMediaId = isset($preselectedFtpMediaId) ? (int) $preselectedFtpMediaId : 0;
                        $ftpMediaSingular = $ftpMediaTypeLabels['singular'] ?? 'Medium';
                        $ftpMediaPlural = $ftpMediaTypeLabels['plural'] ?? 'Medien';
                    @endphp
                    @if(isset($ftpSelectableMedia) && $ftpSelectableMedia->isNotEmpty())
                        <div class="mb-2">
                            <p class="text-sm font-medium text-gray-700 mb-2">Welche {{ $ftpMediaPlural }} sollen hochgeladen werden?</p>
                            <div class="border border-gray-200 rounded-lg max-h-72 overflow-y-auto bg-white divide-y divide-gray-100">
                                @foreach($ftpSelectableMedia as $media)
                                    @php
                                        $mediaName = trim((string) ($media->delivery_activity_label ?? '')) ?: ('Medium #'.$media->id);
                                        $mediaChecked = $hasOldFtpMedia
                                            ? in_array((int) $media->id, $oldFtpMediaIds, true)
                                            : ($preselectedFtpMediaId > 0 ? ((int) $media->id === $preselectedFtpMediaId) : true);
                                    @endphp
                                    <label class="flex items-start gap-3 px-3 py-2 hover:bg-gray-50 ftp-media-row">
                                        <input
                                            type="checkbox"
                                            name="ftp_media_ids[]"
                                            value="{{ $media->id }}"
                                            class="mt-1 rounded border-gray-300 text-[#092E48] focus:ring-[#092E48] ftp-media-checkbox"
                                            @if($mediaChecked) checked @endif
                                        >
                                        <span>
                                            <span class="text-sm font-medium text-gray-900">{{ $mediaName }}</span>
                                            <span class="text-xs text-gray-600 block">{{ $ftpMediaSingular }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            <p class="mt-2 text-xs text-gray-500" id="ftp_media_hint">
                                Angezeigt werden nur {{ $ftpMediaPlural }} mit „Versand = Ja“ aus dieser Meldung.
                            </p>
                            @if($preselectedFtpMediaId > 0 && !$hasOldFtpMedia)
                                <p class="mt-1 text-xs text-emerald-700">
                                    Vorauswahl aktiv: nur das zuvor gewählte Medium (ID {{ $preselectedFtpMediaId }}) ist markiert.
                                </p>
                            @endif
                        </div>
                    @else
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                            <p class="text-sm text-amber-800">Für diese Meldung sind keine {{ $ftpMediaPlural }} mit „Versand = Ja“ markiert.</p>
                        </div>
                    @endif

                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                        <label class="inline-flex items-start gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                name="force_reupload"
                                value="1"
                                class="mt-1 rounded border-amber-300 text-amber-700 focus:ring-amber-600"
                                @checked((bool) old('force_reupload'))
                            >
                            <span class="text-sm text-amber-900">
                                Bereits erfolgreich hochgeladene Dateien trotzdem erneut übertragen (Re-Upload erzwingen)
                            </span>
                        </label>
                        <p class="mt-1 text-xs text-amber-800">
                            Standard: bereits erfolgreiche Uploads werden übersprungen. Aktivieren nur bei bewusstem Nachversand.
                        </p>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button
                            type="submit"
                            id="ftp_submit_button"
                            class="px-4 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858] disabled:opacity-60 disabled:cursor-not-allowed"
                        >
                            FTP-Upload starten
                        </button>
                        <a href="{{ route('admin.news.edit', $newsItem) }}" class="px-4 py-2 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                            Abbrechen
                        </a>
                    </div>
                    <p id="ftp_upload_status" class="hidden text-sm text-[#092E48]">
                        Upload läuft im Hintergrund. Du kannst weiterarbeiten ...
                    </p>
                </form>
            </div>
        @endif
    </div>

    <p class="mt-4 text-sm text-gray-500">
        <a href="{{ route('admin.news.edit', $newsItem) }}" class="text-[#092E48] hover:underline">← Zurück zur Nachricht</a>
    </p>
</div>

@if($destinations->isNotEmpty())
<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('send_form');
    var emailInput = document.getElementById('recipient_email');
    var selectedDestinations = new Set();

    // Vorbelegung aus alter Eingabe (z.B. nach Validierungsfehler)
    @php
        $oldDestinations = (array) old('delivery_destination_ids', []);
    @endphp
    var preselected = @json(array_map('strval', $oldDestinations));
    preselected.forEach(function(id) {
        if (id) {
            selectedDestinations.add(String(id));
        }
    });

    function syncHiddenInputs() {
        // Bestehende Hidden-Felder entfernen
        form.querySelectorAll('input.destination-hidden').forEach(function(input) {
            input.remove();
        });
        // Für jede Auswahl ein Hidden-Feld anlegen
        selectedDestinations.forEach(function(id) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'delivery_destination_ids[]';
            input.value = id;
            input.className = 'destination-hidden';
            form.appendChild(input);
        });
    }

    function updateEmailRequiredFlag() {
        if (!emailInput) return;
        if (selectedDestinations.size > 0) {
            emailInput.removeAttribute('required');
        } else if (!emailInput.value.trim()) {
            emailInput.setAttribute('required', 'required');
        }
    }

    function toggleDestination(id) {
        if (selectedDestinations.has(id)) {
            selectedDestinations.delete(id);
        } else {
            selectedDestinations.add(id);
        }
        syncHiddenInputs();
        setSelectedInList();
        updateEmailRequiredFlag();
    }

    function setSelectedInList() {
        document.querySelectorAll('.destination-item').forEach(function(btn) {
            var bid = btn.getAttribute('data-destination-id');
            var isSelected = selectedDestinations.has(String(bid));

            if (isSelected) {
                btn.classList.add('bg-[#092E48]/10', 'ring-2', 'ring-[#092E48]');
                btn.classList.remove('hover:bg-[#092E48]/5');
            } else {
                btn.classList.remove('bg-[#092E48]/10', 'ring-2', 'ring-[#092E48]');
                btn.classList.add('hover:bg-[#092E48]/5');
            }

            var indicator = btn.querySelector('.destination-indicator');
            if (indicator) {
                if (isSelected) {
                    indicator.classList.remove('border-gray-400');
                    indicator.classList.add('border-[#092E48]', 'bg-[#092E48]');
                } else {
                    indicator.classList.add('border-gray-400');
                    indicator.classList.remove('border-[#092E48]', 'bg-[#092E48]');
                }
            }
        });
    }

    document.querySelectorAll('.destination-item').forEach(function(btn) {
        btn.addEventListener('click', function() {
            toggleDestination(this.getAttribute('data-destination-id'));
        });
    });

    if (emailInput) {
        emailInput.addEventListener('input', function() {
            updateEmailRequiredFlag();
        });
    }

    form.addEventListener('submit', function(e) {
        if (selectedDestinations.size === 0 && emailInput && !emailInput.value.trim()) {
            e.preventDefault();
            alert('Bitte ein Versandziel anklicken oder eine E-Mail-Adresse eingeben.');
            return;
        }
    });

    // Initiale Synchronisierung (z.B. nach „zurück“-Navigation)
    if (selectedDestinations.size > 0) {
        syncHiddenInputs();
        setSelectedInList();
    }
    updateEmailRequiredFlag();
});
</script>
@endif

@if(isset($ftpDestinations) && $ftpDestinations->isNotEmpty())
<script>
document.addEventListener('DOMContentLoaded', function() {
    var ftpForm = document.getElementById('ftp_form');
    if (!ftpForm) return;

    var ftpInput = document.getElementById('ftp_destination_id');
    var ftpSubmitButton = document.getElementById('ftp_submit_button');
    var ftpUploadStatus = document.getElementById('ftp_upload_status');
    var ftpSubmitting = false;
    var ftpMediaHint = document.getElementById('ftp_media_hint');

    function setFtpDestination(id) {
        if (ftpInput) ftpInput.value = id || '';
        var selectedButton = null;
        document.querySelectorAll('.ftp-destination-item').forEach(function(btn) {
            var bid = btn.getAttribute('data-ftp-destination-id');
            if (bid === String(id)) {
                selectedButton = btn;
                btn.classList.add('bg-[#092E48]/10', 'ring-2', 'ring-[#092E48]');
                btn.classList.remove('hover:bg-[#092E48]/5');
            } else {
                btn.classList.remove('bg-[#092E48]/10', 'ring-2', 'ring-[#092E48]');
                btn.classList.add('hover:bg-[#092E48]/5');
            }
        });
    }

    document.querySelectorAll('.ftp-destination-item').forEach(function(btn) {
        btn.addEventListener('click', function() {
            setFtpDestination(this.getAttribute('data-ftp-destination-id'));
        });
    });

    ftpForm.addEventListener('submit', function(e) {
        if (ftpSubmitting) {
            e.preventDefault();
            return;
        }

        if (!ftpInput.value) {
            e.preventDefault();
            alert('Bitte ein FTP-/SFTP-Versandziel anklicken.');
            return;
        }

        var selectedMediaCount = ftpForm.querySelectorAll('.ftp-media-checkbox:checked').length;
        if (ftpForm.querySelectorAll('.ftp-media-checkbox').length > 0 && selectedMediaCount === 0) {
            e.preventDefault();
            alert('Bitte mindestens ein Medium zum Upload auswählen.');
            return;
        }

        ftpSubmitting = true;
        if (ftpSubmitButton) {
            ftpSubmitButton.disabled = true;
            ftpSubmitButton.textContent = 'Upload läuft...';
        }
        if (ftpUploadStatus) {
            ftpUploadStatus.classList.remove('hidden');
        }
    });

    if (ftpInput.value) {
        setFtpDestination(ftpInput.value);
    }
});
</script>
@endif
@endsection
