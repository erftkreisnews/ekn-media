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

    @php
        $hasFtpDestinations = isset($ftpDestinations) && $ftpDestinations->isNotEmpty();
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

                <div>
                    <label for="recipient_email" class="block text-sm font-medium text-gray-700">Einzelne E-Mail-Adresse (wenn kein Versandziel gewählt)</label>
                    <input
                        type="email"
                        name="recipient_email"
                        id="recipient_email"
                        value="{{ old('recipient_email', $defaultEmail) }}"
                        @if($destinations->isEmpty()) required @endif
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
                        placeholder="empfaenger@example.com"
                    >
                    @error('recipient_email')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <p class="text-sm text-gray-500">Der Empfänger wählt auf dem Versand-Link selbst Medienhaus und Format und kann sich optional als Empfänger anlegen lassen.</p>

                <div class="flex gap-3 pt-4">
                    <button type="submit" class="px-4 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]">
                        Jetzt per E-Mail versenden
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
                        </p>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="submit" class="px-4 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]">
                            FTP-Upload starten
                        </button>
                        <a href="{{ route('admin.news.edit', $newsItem) }}" class="px-4 py-2 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                            Abbrechen
                        </a>
                    </div>
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

    function setFtpDestination(id) {
        if (ftpInput) ftpInput.value = id || '';
        document.querySelectorAll('.ftp-destination-item').forEach(function(btn) {
            var bid = btn.getAttribute('data-ftp-destination-id');
            if (bid === String(id)) {
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
        if (!ftpInput.value) {
            e.preventDefault();
            alert('Bitte ein FTP-/SFTP-Versandziel anklicken.');
        }
    });

    if (ftpInput.value) {
        setFtpDestination(ftpInput.value);
    }
});
</script>
@endif
@endsection
