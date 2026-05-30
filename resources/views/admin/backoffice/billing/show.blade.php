@extends('layouts.admin')

@section('content')
    @php
        $product = $invoice->product;
        $organization = $invoice->organization;
        $contact = $invoice->contact;
        $sender = config('invoice.sender', []);
        $bank = config('invoice.bank', []);
        $payment = config('invoice.payment', []);
        $statusLabels = [
            'draft' => 'Nur lokal',
            'pending' => 'Altbestand lokal',
            'ready_for_lexware' => 'Lokal für Lexware vorgemerkt',
            'lexware_open' => 'In Lexware erstellt',
            'lexware_voided' => 'In Lexware storniert',
        ];
        $billingTypeLabels = [
            'lizenz' => 'Lizenz',
            'honorar' => 'Honorar',
        ];
        $headerReferenceLabel = trim((string) ($organization?->external_reference_label ?? ''));
        $headerReferenceLabel = $headerReferenceLabel !== '' ? $headerReferenceLabel : 'PV-Nr.';
        $headerReferenceCode = trim((string) ($usageRecords->first()?->reference_code ?? ''));
        if ($headerReferenceCode === '') {
            $headerReferenceCode = trim((string) ($organization?->external_author_id ?? ''));
        }
        $vatRate = (float) ($payment['vat_rate'] ?? 7);
        $displayNet = (float) $invoice->total_net;
        $displayVat = round($displayNet * $vatRate / 100, 2);
        $displayGross = round($displayNet + $displayVat, 2);
        $isTag24Invoice = str_contains(mb_strtolower((string) ($product->name ?? '')), 'tag24')
            || str_contains(mb_strtolower((string) ($organization->name ?? '')), 'tag24');
        $usedDates = $usageRecords->pluck('used_at')->filter()->sort()->values();
        $periodStart = $usedDates->first();
        $periodEnd = $usedDates->last();
        $usageDateLabel = '–';
        if ($periodStart && $periodEnd) {
            $usageDateLabel = $periodStart->isSameDay($periodEnd)
                ? $periodStart->format('d.m.Y')
                : $periodStart->format('d.m.Y').' – '.$periodEnd->format('d.m.Y');
        }
        $zugferdMeta = (array) (($invoice->meta ?? [])['zugferd'] ?? []);
        $dispatchMeta = (array) (($invoice->meta ?? [])['dispatch'] ?? []);
        $dispatchMessages = array_values(array_unique(array_merge($dispatchStatus['blockers'], $dispatchStatus['reviews'], $dispatchStatus['warnings'])));
        $zugferdMessages = array_values(array_diff($dispatchStatus['zugferd']['messages'], $dispatchMessages));
        $dispatchFlowMessages = $dispatchStatus['dispatch']['messages'] ?? [];
        $internalTestRecipient = auth()->user()?->email;
        $paymentMeta = (array) (($invoice->meta ?? [])['payment'] ?? []);
        $lexwarePaymentMeta = (array) (($invoice->meta ?? [])['lexware_payment'] ?? []);
        $lexwareAppBase = rtrim((string) config('lexware.app_base_url', 'https://app.lexware.de'), '/');
        $lexwareInvoiceIdRaw = trim((string) ($invoice->lexware_invoice_id ?? ''));
        $lexwareEditUrl = $lexwareInvoiceIdRaw !== ''
            ? $lexwareAppBase.'/permalink/invoices/edit/'.rawurlencode($lexwareInvoiceIdRaw)
            : null;
    @endphp

    <div class="space-y-6 max-w-7xl mx-auto">
        <div>
            <a href="{{ route('admin.backoffice.billing.index') }}" class="text-sm text-gray-600 hover:text-[#092E48] mb-2 inline-block">← Zur Abrechnungs-Übersicht</a>
            <h1 class="text-2xl font-semibold text-gray-900">Rechnungsentwurf Vorschau</h1>
            <p class="mt-1 text-sm text-gray-600">
                Lokale EKN-Vorschau für Kunde {{ $organization->name }} · Redaktion {{ $product->name }}.
                @if($invoice->lexware_invoice_id && $invoice->voucher_number)
                    Finale Lexware-Rechnung: {{ $invoice->voucher_number }}.
                @else
                    Noch ohne finale Lexware-Rechnungsnummer.
                @endif
            </p>
            @if(!empty($zugferdMeta))
                <p class="mt-1 text-sm text-gray-500">
                    Letzte ZUGFeRD-Erzeugung: {{ \Illuminate\Support\Carbon::parse($zugferdMeta['generated_at'])->format('d.m.Y H:i') }} · Nummer {{ $zugferdMeta['document_number'] ?? '–' }}.
                </p>
            @endif
            @if(!empty($dispatchMeta['sent_at']))
                <p class="mt-1 text-sm text-gray-500">
                    Zuletzt versendet: {{ \Illuminate\Support\Carbon::parse($dispatchMeta['sent_at'])->format('d.m.Y H:i') }} an {{ $dispatchMeta['sent_to'] ?? '–' }}.
                </p>
            @endif
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 p-4 border border-green-200">
                <p class="text-sm text-green-800">{{ session('status') }}</p>
            </div>
        @endif
        @if (session('error'))
            <div class="rounded-md bg-red-50 p-4 border border-red-200">
                <p class="text-sm text-red-800">{{ session('error') }}</p>
            </div>
        @endif
        @if (session('warning'))
            <div class="rounded-md bg-amber-50 p-4 border border-amber-200">
                <p class="text-sm text-amber-950"><span class="font-medium">Lexware:</span> {{ session('warning') }}</p>
            </div>
        @endif

        <div class="rounded-md border p-4 {{ $dispatchStatus['status'] === 'blocked' ? 'bg-red-50 border-red-200' : ($dispatchStatus['status'] === 'review' ? 'bg-amber-50 border-amber-200' : ($dispatchStatus['status'] === 'warning' ? 'bg-yellow-50 border-yellow-200' : 'bg-emerald-50 border-emerald-200')) }}">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-medium {{ $dispatchStatus['badge_classes'] }}">
                            {{ $dispatchStatus['label'] }}
                        </span>
                        <span class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-medium {{ $dispatchStatus['dispatch']['badge_classes'] }}">
                            {{ $dispatchStatus['dispatch']['label'] }}
                        </span>
                        <span class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-medium {{ $dispatchStatus['zugferd']['badge_classes'] }}">
                            {{ $dispatchStatus['zugferd']['label'] }}
                        </span>
                    </div>
                    <p class="mt-2 text-sm text-gray-900">{{ $dispatchStatus['summary'] }}</p>
                    <p class="mt-1 text-sm text-gray-700">{{ $dispatchStatus['dispatch']['summary'] }}</p>
                    <p class="mt-1 text-sm text-gray-700">{{ $dispatchStatus['zugferd']['summary'] }}</p>
                </div>
            </div>
            @if($dispatchMessages !== [])
                <ul class="mt-3 space-y-1 text-sm text-gray-800 list-disc pl-5">
                    @foreach($dispatchMessages as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            @endif
            @if($dispatchFlowMessages !== [])
                <ul class="mt-3 space-y-1 text-sm text-gray-800 list-disc pl-5">
                    @foreach($dispatchFlowMessages as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            @endif
            @if($zugferdMessages !== [] && ! $dispatchStatus['zugferd']['allowed'])
                <ul class="mt-3 space-y-1 text-sm text-gray-800 list-disc pl-5">
                    @foreach($zugferdMessages as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="rounded-md bg-blue-50 border border-blue-200 p-4">
            <p class="text-sm text-blue-900">
                Diese Ansicht zeigt dir, <strong>wie der Rechnungsentwurf inhaltlich aussieht</strong>.
                @if($invoice->lexware_invoice_id)
                    Die verbindliche Rechnungsnummer kommt aus Lexware; diese Ansicht bleibt die EKN-Vorschau für Versand und Kontrolle.
                @else
                    Erst der finale Lexware-Schritt erzeugt die echte Rechnungsnummer. Vorher bleibt alles ein lokaler EKN-Entwurf.
                @endif
            </p>
            <p class="mt-2 text-sm text-blue-900">
                EKN darf finale Versanddokumente erst nach erfolgreicher Lexware-Finalisierung freigeben, damit keine doppelte Rechnungsnummer entstehen kann.
            </p>
            <ul class="mt-3 space-y-1 text-sm text-blue-900 list-disc pl-5">
                <li>Lexware bleibt die einzige Quelle für verbindliche Rechnungsnummern.</li>
                <li>EKN erzeugt ZUGFeRD und spätere Versanddokumente erst mit der finalen Lexware-Nummer.</li>
                <li>Der ZUGFeRD-MVP bleibt bewusst auf Standard-Inlandsrechnungen in EUR ohne Sonderfälle begrenzt.</li>
            </ul>
            <div class="mt-3">
                <a href="{{ route('admin.backoffice.billing.pdf', $invoice) }}" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-blue-300 text-blue-900 bg-white hover:bg-blue-100">
                    Entwurfs-PDF erzeugen
                </a>
                @if($dispatchStatus['zugferd']['allowed'])
                    <a href="{{ route('admin.backoffice.billing.zugferd', $invoice) }}" class="ml-2 inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-violet-300 text-violet-900 bg-violet-50 hover:bg-violet-100">
                        ZUGFeRD laden
                    </a>
                @else
                    <span class="ml-2 inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-gray-300 text-gray-500 bg-gray-100 cursor-not-allowed">
                        ZUGFeRD erst nach Lexware
                    </span>
                @endif
                @if($invoice->lexware_invoice_id)
                    <a href="{{ route('admin.backoffice.billing.lexware-file', $invoice) }}" class="ml-2 inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-emerald-300 text-emerald-900 bg-emerald-50 hover:bg-emerald-100">
                        Lexware-Dokument laden
                    </a>
                @endif
                @if($dispatchStatus['dispatch']['can_send'])
                    <form method="POST" action="{{ route('admin.backoffice.billing.send', $invoice) }}" class="inline">
                        @csrf
                        <button type="submit" class="ml-2 inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-emerald-300 text-emerald-900 bg-emerald-50 hover:bg-emerald-100">
                            {{ ($dispatchStatus['dispatch']['status'] ?? null) === 'sent' ? 'Erneut senden (Korrektur)' : 'Rechnung jetzt versenden' }}
                        </button>
                    </form>
                    @if($internalTestRecipient)
                        <form method="POST" action="{{ route('admin.backoffice.billing.send', $invoice) }}" class="inline">
                            @csrf
                            <input type="hidden" name="test_recipient" value="{{ $internalTestRecipient }}">
                            <button type="submit" class="ml-2 inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-amber-300 text-amber-900 bg-amber-50 hover:bg-amber-100">
                                Testversand an {{ $internalTestRecipient }}
                            </button>
                        </form>
                    @endif
                @endif
            </div>
        </div>

        <div id="zahlungseingang" class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden scroll-mt-6">
            <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
                <h2 class="text-base font-medium text-gray-900">Zahlungseingang (Bank)</h2>
                <p class="mt-0.5 text-sm text-gray-600">
                    Hier trägst du ein, wann der Betrag laut Kontoauszug eingegangen ist – optional mit Verwendungszweck und Notiz.
                </p>
            </div>
            <div class="p-4 space-y-4">
                @if(!empty($paymentMeta['received_on']))
                    <div class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">
                        <span class="font-medium">Erfasst:</span>
                        {{ \Illuminate\Support\Carbon::parse($paymentMeta['received_on'])->format('d.m.Y') }}
                        @if(isset($paymentMeta['amount_gross']) && $paymentMeta['amount_gross'] !== null && $paymentMeta['amount_gross'] !== '')
                            · {{ number_format((float) $paymentMeta['amount_gross'], 2, ',', '.') }} € (brutto)
                        @endif
                        @if(!empty($paymentMeta['bank_reference']))
                            · Verwendungszweck: {{ $paymentMeta['bank_reference'] }}
                        @endif
                        @if(!empty($paymentMeta['recorded_at']))
                            <span class="block text-xs text-emerald-800 mt-1">Gespeichert: {{ \Illuminate\Support\Carbon::parse($paymentMeta['recorded_at'])->format('d.m.Y H:i') }}</span>
                        @endif
                    </div>
                @endif
                <form method="POST" action="{{ route('admin.backoffice.billing.payment', $invoice) }}" class="space-y-3 max-w-xl">
                    @csrf
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="payment_received_on" class="block text-sm font-medium text-gray-700">Eingang am</label>
                            <input type="date" name="payment_received_on" id="payment_received_on" value="{{ old('payment_received_on', $paymentMeta['received_on'] ?? '') }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] text-sm">
                        </div>
                        <div>
                            <label for="payment_amount_gross" class="block text-sm font-medium text-gray-700">Betrag brutto (€, optional)</label>
                            <input type="text" name="payment_amount_gross" id="payment_amount_gross" inputmode="decimal" placeholder="z. B. {{ number_format($displayGross, 2, ',', '') }}"
                                value="{{ old('payment_amount_gross', isset($paymentMeta['amount_gross']) && $paymentMeta['amount_gross'] !== null ? number_format((float) $paymentMeta['amount_gross'], 2, ',', '') : '') }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] text-sm">
                        </div>
                    </div>
                    <div>
                        <label for="payment_bank_reference" class="block text-sm font-medium text-gray-700">Verwendungszweck / Bankreferenz (optional)</label>
                        <input type="text" name="payment_bank_reference" id="payment_bank_reference" value="{{ old('payment_bank_reference', $paymentMeta['bank_reference'] ?? '') }}"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] text-sm">
                    </div>
                    <div>
                        <label for="payment_note" class="block text-sm font-medium text-gray-700">Interne Notiz (optional)</label>
                        <textarea name="payment_note" id="payment_note" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] text-sm">{{ old('payment_note', $paymentMeta['note'] ?? '') }}</textarea>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-900 hover:bg-gray-50">
                            Zahlungseingang speichern
                        </button>
                        @if(!empty($paymentMeta['received_on']))
                            <button type="submit" name="clear_payment" value="1" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-red-200 text-red-800 bg-red-50 hover:bg-red-100"
                                onclick="return confirm('Zahlungseintrag wirklich entfernen?');">
                                Eintrag entfernen
                            </button>
                        @endif
                    </div>
                </form>

                @if($lexwareEditUrl)
                    <div class="mt-6 pt-6 border-t border-gray-200 space-y-3">
                        <h3 class="text-sm font-semibold text-gray-900">Lexware-Abgleich</h3>
                        <p class="text-sm text-gray-600">
                            Die <a href="https://developers.lexware.io/docs/#payments-endpoint-retrieve-payment-information" class="text-[#092E48] font-medium underline underline-offset-2" target="_blank" rel="noopener noreferrer">Lexware Public API</a> erlaubt nur das <strong>Lesen</strong> des Zahlungsstands, kein automatisches Buchen der Bankzahlung. Erfasste Daten oben dienen der Dokumentation in EKN; in Lexware erfolgt die Zuordnung z.&nbsp;B. über Online-Banking oder manuell.
                        </p>
                        <div class="flex flex-wrap items-center gap-2">
                            <form method="POST" action="{{ route('admin.backoffice.billing.lexware-payment-sync', $invoice) }}" class="inline">
                                @csrf
                                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-slate-300 bg-slate-50 text-slate-900 hover:bg-slate-100">
                                    Lexware-Stand jetzt abrufen
                                </button>
                            </form>
                            <a href="{{ $lexwareEditUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-[#092E48] text-[#092E48] bg-white hover:bg-gray-50">
                                Rechnung in Lexware bearbeiten
                            </a>
                        </div>
                        @if(!empty($lexwarePaymentMeta['sync_error']))
                            <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900">
                                Letzter API-Abruf: {{ !empty($lexwarePaymentMeta['fetched_at']) ? \Illuminate\Support\Carbon::parse($lexwarePaymentMeta['fetched_at'])->format('d.m.Y H:i') : '–' }} — {{ $lexwarePaymentMeta['sync_error'] }}
                            </div>
                        @elseif(!empty($lexwarePaymentMeta['fetched_at']))
                            <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-900 space-y-1">
                                <p><span class="font-medium">Stand Lexware</span> ({{ \Illuminate\Support\Carbon::parse($lexwarePaymentMeta['fetched_at'])->format('d.m.Y H:i') }})</p>
                                <p>
                                    Offener Betrag:
                                    @if(isset($lexwarePaymentMeta['open_amount']) && $lexwarePaymentMeta['open_amount'] !== '' && (float) $lexwarePaymentMeta['open_amount'] <= 0.009)
                                        <span class="text-emerald-800 font-medium">0,00 € (ausgeglichen)</span>
                                    @elseif(isset($lexwarePaymentMeta['open_amount']))
                                        {{ number_format((float) $lexwarePaymentMeta['open_amount'], 2, ',', '.') }} {{ $lexwarePaymentMeta['currency'] ?? 'EUR' }}
                                    @else
                                        –
                                    @endif
                                </p>
                                @php
                                    $voucherStatusRaw = (string) ($lexwarePaymentMeta['voucher_status'] ?? '');
                                    $paymentStatusRaw = (string) ($lexwarePaymentMeta['payment_status'] ?? '');
                                    $openAmount = isset($lexwarePaymentMeta['open_amount']) ? (float) $lexwarePaymentMeta['open_amount'] : null;

                                    $voucherStatusLabelMap = [
                                        'open' => 'offen',
                                        'draft' => 'Entwurf',
                                        'paid' => 'bezahlt',
                                        'voided' => 'storniert',
                                        'accepted' => 'angenommen',
                                        'rejected' => 'abgelehnt',
                                    ];
                                    $paymentStatusLabelMap = [
                                        'open' => 'offen',
                                        'partially_paid' => 'teilbezahlt',
                                        'paid' => 'bezahlt',
                                        'balanced' => 'ausgeglichen',
                                        'overpaid' => 'überzahlt',
                                    ];

                                    $voucherStatusLabel = $voucherStatusRaw !== ''
                                        ? ($voucherStatusLabelMap[$voucherStatusRaw] ?? $voucherStatusRaw)
                                        : '–';
                                    $paymentStatusLabel = $paymentStatusRaw !== ''
                                        ? ($paymentStatusLabelMap[$paymentStatusRaw] ?? $paymentStatusRaw)
                                        : null;

                                    if ($paymentStatusLabel === null && $openAmount !== null && $openAmount <= 0.009) {
                                        $paymentStatusLabel = 'ausgeglichen';
                                    }
                                @endphp
                                <p>Belegstatus: <span class="font-medium">{{ $voucherStatusLabel }}</span>
                                    @if(!empty($paymentStatusLabel))
                                        · {{ $paymentStatusLabel }}
                                    @endif
                                </p>
                                @if($voucherStatusRaw === 'voided')
                                    @php
                                        $voidReason = trim((string) ($lexwarePaymentMeta['void_reason'] ?? ''));
                                    @endphp
                                    <p>
                                        Storno-Grund:
                                        @if($voidReason !== '')
                                            <span class="font-medium">{{ $voidReason }}</span>
                                        @else
                                            <span class="text-slate-700">In Lexware storniert (kein Grund über API übermittelt).</span>
                                        @endif
                                    </p>
                                @endif
                                @php
                                    $pItems = $lexwarePaymentMeta['payment_items'] ?? [];
                                @endphp
                                @if(is_array($pItems) && count($pItems) > 0)
                                    <ul class="mt-2 list-disc pl-5 text-xs text-slate-700 space-y-0.5">
                                        @foreach(array_slice($pItems, 0, 8) as $pi)
                                            @if(is_array($pi))
                                                <li>
                                                    {{ $pi['paymentItemType'] ?? '?' }}
                                                    @if(isset($pi['postingDate']))
                                                        · {{ \Illuminate\Support\Carbon::parse($pi['postingDate'])->format('d.m.Y') }}
                                                    @endif
                                                    @if(isset($pi['amount']))
                                                        · {{ number_format((float) $pi['amount'], 2, ',', '.') }} {{ $pi['currency'] ?? 'EUR' }}
                                                    @endif
                                                </li>
                                            @endif
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        @else
                            <p class="text-xs text-gray-500">Noch kein Abruf aus Lexware — nach dem ersten Speichern des Zahlungseingangs oder über „Lexware-Stand jetzt abrufen“.</p>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        @if(!empty($showWdrBillingChecklist))
            @php
                $wdrLiz = $invoice->getWdrChecklistEntry('lizenzvertrag');
                $wdrVerg = $invoice->getWdrChecklistEntry('verguetungsmitteilung');
                $incomingDocs = $invoice->incomingDocuments ?? collect();
                $hasLizenzAblage = $incomingDocs->contains('document_type', 'license_contract');
                $hasVergAblage = $incomingDocs->contains('document_type', 'remuneration_notice');
            @endphp
            <div id="wdr-abrechnung" class="bg-white rounded-lg border border-amber-200 shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-amber-200 bg-amber-50">
                    <h2 class="text-base font-medium text-amber-950">WDR-Abrechnung: Prüfpunkte</h2>
                    <p class="mt-0.5 text-sm text-amber-900">
                        Entweder hier abhaken oder in der <a href="#eingangsdokumente" class="font-medium underline underline-offset-2">Eingangsablage</a> ein Dokument mit der Art „Lizenzvertrag“ bzw. „Vergütungsmitteilung“ hochladen – der passende Prüfpunkt wird dann automatisch gesetzt.
                    </p>
                </div>
                <div class="p-4">
                    <form method="POST" action="{{ route('admin.backoffice.billing.wdr-checklist.update', $invoice) }}" class="space-y-4">
                        @csrf
                        <div class="space-y-3">
                            <label class="flex items-start gap-3 rounded-md border border-gray-200 bg-gray-50 p-3 cursor-pointer hover:bg-gray-100">
                                <input type="checkbox" name="lizenzvertrag" value="1" class="mt-1 h-4 w-4 rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]" @checked($invoice->isWdrLizenzvertragSatisfied())>
                                <span class="text-sm text-gray-800">
                                    <span class="font-medium">Lizenzvertrag</span> – geprüft / liegt vor
                                    @if($wdrLiz['done'] && $wdrLiz['completed_at'])
                                        <span class="block text-xs text-gray-500 mt-0.5">Gesetzt: {{ \Illuminate\Support\Carbon::parse($wdrLiz['completed_at'])->format('d.m.Y H:i') }}</span>
                                    @endif
                                    @if($wdrLiz['done'] && ! $hasLizenzAblage)
                                        <span class="block text-xs text-amber-800 mt-1">Hinweis: In der Eingangsablage fehlt noch ein Dokument mit der Art „Lizenzvertrag“.</span>
                                    @endif
                                </span>
                            </label>
                            <label class="flex items-start gap-3 rounded-md border border-gray-200 bg-gray-50 p-3 cursor-pointer hover:bg-gray-100">
                                <input type="checkbox" name="verguetungsmitteilung" value="1" class="mt-1 h-4 w-4 rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]" @checked($invoice->isWdrVerguetungsmitteilungSatisfied())>
                                <span class="text-sm text-gray-800">
                                    <span class="font-medium">Vergütungsmitteilung</span> – geprüft / liegt vor
                                    @if($wdrVerg['done'] && $wdrVerg['completed_at'])
                                        <span class="block text-xs text-gray-500 mt-0.5">Gesetzt: {{ \Illuminate\Support\Carbon::parse($wdrVerg['completed_at'])->format('d.m.Y H:i') }}</span>
                                    @endif
                                    @if($wdrVerg['done'] && ! $hasVergAblage)
                                        <span class="block text-xs text-amber-800 mt-1">Hinweis: In der Eingangsablage fehlt noch ein Dokument mit der Art „Vergütungsmitteilung“.</span>
                                    @endif
                                </span>
                            </label>
                        </div>
                        <div class="flex items-center justify-end gap-3 pt-2 border-t border-gray-200">
                            <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]">
                                Prüfpunkte speichern
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @if(!empty($hasInvoiceIncomingDocuments))
            <div id="eingangsdokumente" class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
                    <h2 class="text-base font-medium text-gray-900">Eingangsdokumente</h2>
                    <p class="mt-0.5 text-sm text-gray-500">
                        Ablage für Verträge, Zahlungsanweisungen und sonstige Unterlagen vom Kunden (z.&nbsp;B. WDR). Nur intern in EKN, kein automatischer Versand.
                    </p>
                </div>
                <div class="p-4 space-y-4">
                    @if($errors->any())
                        <div class="rounded-md bg-red-50 p-3 border border-red-200 text-sm text-red-800">
                            @foreach ($errors->all() as $err)
                                <p>{{ $err }}</p>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.backoffice.billing.incoming-documents.store', $invoice) }}" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
                        @csrf
                        <div class="lg:col-span-3">
                            <label for="incoming_doc_type" class="block text-xs font-medium text-gray-600">Dokumentart</label>
                            <select id="incoming_doc_type" name="document_type" class="mt-1 block w-full rounded-md border-gray-300 text-sm" required>
                                @if(!empty($showWdrBillingChecklist))
                                    <option value="license_contract">Lizenzvertrag</option>
                                    <option value="remuneration_notice">Vergütungsmitteilung</option>
                                @endif
                                <option value="contract">Vertrag (sonstiges)</option>
                                <option value="payment_instruction">Zahlungsanweisung</option>
                                <option value="other">Sonstiges</option>
                            </select>
                        </div>
                        <div class="lg:col-span-4">
                            <label for="incoming_doc_file" class="block text-xs font-medium text-gray-600">Datei (max. 20&nbsp;MB)</label>
                            <input id="incoming_doc_file" type="file" name="document" required class="mt-1 block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-[#092E48] file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-white">
                        </div>
                        <div class="lg:col-span-4">
                            <label for="incoming_doc_note" class="block text-xs font-medium text-gray-600">Hinweis (optional)</label>
                            <input id="incoming_doc_note" type="text" name="note" maxlength="500" class="mt-1 block w-full rounded-md border-gray-300 text-sm" placeholder="z.&nbsp;B. Aktenzeichen, Gültigkeit">
                        </div>
                        <div class="lg:col-span-1 flex justify-end">
                            <button type="submit" class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858] w-full lg:w-auto">
                                Hochladen
                            </button>
                        </div>
                    </form>

                    @php
                        $incomingList = $invoice->incomingDocuments ?? collect();
                    @endphp
                    @if($incomingList->isEmpty())
                        <p class="text-sm text-gray-500">Noch keine Dokumente abgelegt.</p>
                    @else
                        <div class="overflow-x-auto border border-gray-200 rounded-md">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Art</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Datei</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Größe</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Datum</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Aktion</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    @foreach($incomingList->sortByDesc('created_at') as $doc)
                                        <tr>
                                            <td class="px-3 py-2 text-sm text-gray-800">{{ $doc->getDocumentTypeLabel() }}</td>
                                            <td class="px-3 py-2 text-sm text-gray-800">
                                                <span class="font-medium">{{ $doc->original_filename }}</span>
                                                @if($doc->note)
                                                    <span class="block text-xs text-gray-500">{{ $doc->note }}</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-sm text-right text-gray-600">
                                                @if($doc->size_bytes)
                                                    {{ $doc->size_bytes >= 1048576 ? number_format($doc->size_bytes / 1048576, 1, ',', '.').' MB' : number_format($doc->size_bytes / 1024, 0, ',', '.').' KB' }}
                                                @else
                                                    –
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-sm text-gray-600">{{ optional($doc->created_at)->format('d.m.Y H:i') ?? '–' }}</td>
                                            <td class="px-3 py-2 text-sm text-right whitespace-nowrap">
                                                <a href="{{ route('admin.backoffice.billing.incoming-documents.download', [$invoice, $doc]) }}" class="text-[#092E48] hover:underline mr-3">Herunterladen</a>
                                                <form method="POST" action="{{ route('admin.backoffice.billing.incoming-documents.destroy', [$invoice, $doc]) }}" class="inline" onsubmit="return window.adminConfirmDelete(this)" data-delete-prompt="Dieses Eingangsdokument wirklich löschen? Geben Sie zur Bestätigung „ja“ ein.">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:underline">Löschen</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <div class="space-y-6">
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-14 py-10 lg:px-24 lg:py-14 max-w-6xl mx-auto">
                    <div class="flex items-start justify-between gap-6">
                        <div class="flex-1"></div>
                        <div class="text-right text-sm text-gray-800 leading-6">
                            <span class="inline-flex items-center rounded-md bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-800">
                                {{ $dispatchStatus['dispatch']['label'] }}
                            </span>
                            <div class="mt-4">
                                <p class="font-semibold">{{ $sender['name'] ?? 'Alexander Franz' }}</p>
                                <p>{{ $sender['street'] ?? '' }}</p>
                                <p>{{ trim(($sender['postal_code'] ?? '') . ' ' . ($sender['city'] ?? '')) }}</p>
                                <p class="pt-2">Tel.: {{ $sender['phone'] ?? '–' }}</p>
                                <p>Fax: {{ $sender['fax'] ?? '–' }}</p>
                                <p>{{ $sender['email'] ?? '–' }}</p>
                                <p>{{ $sender['website'] ?? '–' }}</p>
                            </div>
                            <div class="mt-4 space-y-0 text-right">
                                <p><span class="font-medium">Rechnungsnr.:</span> {{ $invoice->voucher_number ?: 'Entwurf-' . $invoice->id }}</p>
                                <p><span class="font-medium">Kundennr.:</span> {{ $product->resolvedBuyerReference() ?: '–' }}</p>
                                <p><span class="font-medium">Datum:</span> {{ optional($invoice->voucher_date)->format('d.m.Y') ?: '–' }}</p>
                                <p><span class="font-medium">Nutzungsdatum:</span> {{ $usageDateLabel }}</p>
                                <p><span class="font-medium">Kunde:</span> {{ $organization->name }}</p>
                                <p><span class="font-medium">{{ $headerReferenceLabel }}:</span> {{ $headerReferenceCode !== '' ? $headerReferenceCode : '–' }}</p>
                                <p><span class="font-medium">Redaktion:</span> {{ $product->name }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-10 grid grid-cols-1 md:grid-cols-2 gap-10">
                        <div class="text-sm text-gray-800 leading-6">
                            <p class="font-semibold">Rechnungsempfänger</p>
                            @if($product->billing_company)
                                <p class="mt-3">{{ $product->billing_company }}</p>
                            @endif
                            @if($product->billing_name && $product->billing_name !== $product->billing_company)
                                <p>{{ $product->billing_name }}</p>
                            @endif
                            @if($contact?->name)
                                <p>{{ $contact->name }}</p>
                            @endif
                            @if($product->billing_street)
                                <p>{{ $product->billing_street }}</p>
                            @endif
                            @if($product->billing_postal_code || $product->billing_city)
                                <p>{{ trim(($product->billing_postal_code ?: '') . ' ' . ($product->billing_city ?: '')) }}</p>
                            @endif
                            @if($contact?->email)
                                <p class="pt-2">{{ $contact->email }}</p>
                            @endif
                        </div>
                        <div class="text-sm text-gray-700 leading-6">
                            @if($invoice->lexware_invoice_id)
                                <p class="text-xs text-gray-500">Finale Rechnung in Lexware erstellt</p>
                                <p class="text-xs text-gray-500">Lexware-ID: {{ $invoice->lexware_invoice_id }}</p>
                                <p class="text-xs text-gray-500">Rechnungsnummer: {{ $invoice->voucher_number }}</p>
                            @else
                                <p class="text-xs text-gray-500">Lokaler EKN-Rechnungsentwurf</p>
                                <p class="text-xs text-gray-500">Keine Lexware-Übertragung</p>
                                <p class="text-xs text-gray-500">Keine finale Lexware-Rechnungsnummer</p>
                            @endif
                        </div>
                    </div>

                    <div class="mt-10 text-sm text-gray-800">
                        <p>Sehr geehrte Damen und Herren,</p>
                        <p class="mt-2">vielen Dank für Ihr Vertrauen.</p>
                    </div>

                    @if($isTag24Invoice)
                        <div class="mt-8 text-sm text-gray-800">
                            <p class="font-semibold">Materialankauf:</p>
                            <div class="mt-3 overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-gray-300">
                                            <th class="py-2 text-left font-medium text-gray-700">#</th>
                                            <th class="py-2 text-left font-medium text-gray-700">Anlass</th>
                                            <th class="py-2 text-left font-medium text-gray-700">Format</th>
                                            <th class="py-2 text-left font-medium text-gray-700">Link</th>
                                            <th class="py-2 text-left font-medium text-gray-700">Nutzungsdatum</th>
                                            <th class="py-2 text-left font-medium text-gray-700">Credits</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($previewSections as $index => $section)
                                            @php
                                                $fieldMap = collect($section['fields'])->pluck('value', 'label');
                                                $link = (string) ($fieldMap['Link'] ?? 'noch nicht gepflegt');
                                            @endphp
                                            <tr class="border-b border-gray-100 align-top">
                                                <td class="py-3 pr-3 text-gray-900">{{ $index + 1 }}</td>
                                                <td class="py-3 pr-3 text-gray-900">{{ $fieldMap['Anlass'] ?? 'noch nicht gepflegt' }}</td>
                                                <td class="py-3 pr-3 text-gray-900">{{ $fieldMap['Format'] ?? 'noch nicht gepflegt' }}</td>
                                                <td class="py-3 pr-3 text-gray-900 break-all">
                                                    @if($link === 'noch nicht gepflegt')
                                                        <span class="text-amber-700">noch nicht gepflegt</span>
                                                    @else
                                                        {{ $link }}
                                                    @endif
                                                </td>
                                                <td class="py-3 pr-3 text-gray-900">{{ $fieldMap['Nutzungsdatum'] ?? 'noch nicht gepflegt' }}</td>
                                                <td class="py-3 text-gray-900">{{ $fieldMap['Credits'] ?? 'noch nicht gepflegt' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @else
                        @foreach($previewSections as $section)
                            <div class="mt-8 text-sm text-gray-800">
                                <p class="font-semibold">{{ $section['title'] }}:</p>
                                <div class="mt-2 space-y-1">
                                    @foreach($section['fields'] as $field)
                                        <p @if($field['label'] === 'Credits') class="mt-4" @endif>
                                            <span class="font-medium">{{ $field['label'] }}:</span>
                                            @if($field['value'] === 'noch nicht gepflegt')
                                                <span class="text-amber-700">{{ $field['value'] }}</span>
                                            @else
                                                {{ $field['value'] }}
                                            @endif
                                        </p>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    @endif

                    <div class="mt-10 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-300">
                                    <th class="py-2 text-left font-medium text-gray-700">Pos.</th>
                                    <th class="py-2 text-left font-medium text-gray-700">Bezeichnung</th>
                                    <th class="py-2 text-right font-medium text-gray-700">Menge</th>
                                    <th class="py-2 text-right font-medium text-gray-700">Einheit</th>
                                    <th class="py-2 text-right font-medium text-gray-700">Einzel €</th>
                                    <th class="py-2 text-right font-medium text-gray-700">Gesamt €</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $position = 1; @endphp
                                @forelse($usageRecords as $index => $record)
                                    @php
                                        $section = $previewSections[$index] ?? null;
                                        $wdrTier = $wdrNewsroomImageTier ?? null;
                                        $isWdrNsTier = $wdrTier && \App\Services\Billing\WdrNewsroomImageTierLines::appliesTo($record, $invoice) && (int) $record->images_count > 0;
                                        $isTag24Tier = $isTag24Invoice && ((int) $record->images_count > 0);
                                    @endphp
                                    @if($isWdrNsTier)
                                        @foreach($wdrTier->linesForImageCount((int) $record->images_count) as $rowIdx => $line)
                                            <tr class="border-b border-gray-100 align-top">
                                                <td class="py-3 pr-3 text-gray-900">{{ $position++ }}</td>
                                                <td class="py-3 pr-3 text-gray-900">
                                                    <div class="space-y-1">
                                                        <p>{{ $line['title'] }}</p>
                                                        @if($rowIdx === 0 && !empty($section['line_item_subtitle']))
                                                            <p class="text-xs text-amber-700">{{ $section['line_item_subtitle'] }}</p>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="py-3 pr-3 text-right text-gray-900">{{ number_format((float) $line['quantity'], (fmod((float) $line['quantity'], 1.0) === 0.0 ? 0 : 1), ',', '.') }}</td>
                                                <td class="py-3 pr-3 text-right text-gray-900">Stueck</td>
                                                <td class="py-3 pr-3 text-right text-gray-900">{{ number_format((float) $line['unit_price'], 2, ',', '.') }}</td>
                                                <td class="py-3 text-right text-gray-900">{{ number_format((float) $line['line_total'], 2, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    @elseif($isTag24Tier)
                                        @php
                                            $additionalImages = max(0, (int) $record->images_count - 1);
                                        @endphp
                                        <tr class="border-b border-gray-100 align-top">
                                            <td class="py-3 pr-3 text-gray-900">{{ $position++ }}</td>
                                            <td class="py-3 pr-3 text-gray-900">
                                                <div class="space-y-1">
                                                    <p>Online-Nutzung Foto - 1. Bild</p>
                                                    @if(!empty($section['line_item_subtitle']))
                                                        <p class="text-xs text-amber-700">{{ $section['line_item_subtitle'] }}</p>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="py-3 pr-3 text-right text-gray-900">1</td>
                                            <td class="py-3 pr-3 text-right text-gray-900">Stueck</td>
                                            <td class="py-3 pr-3 text-right text-gray-900">10,00</td>
                                            <td class="py-3 text-right text-gray-900">10,00</td>
                                        </tr>
                                        @if($additionalImages > 0)
                                            <tr class="border-b border-gray-100 align-top">
                                                <td class="py-3 pr-3 text-gray-900">{{ $position++ }}</td>
                                                <td class="py-3 pr-3 text-gray-900">Online-Nutzung Foto - Bilder ab 2</td>
                                                <td class="py-3 pr-3 text-right text-gray-900">{{ $additionalImages }}</td>
                                                <td class="py-3 pr-3 text-right text-gray-900">Stueck</td>
                                                <td class="py-3 pr-3 text-right text-gray-900">5,00</td>
                                                <td class="py-3 text-right text-gray-900">{{ number_format($additionalImages * 5.0, 2, ',', '.') }}</td>
                                            </tr>
                                        @endif
                                    @else
                                        @php
                                            $quantity = (int) $record->images_count > 0
                                                ? (int) $record->images_count
                                                : ((float) $record->video_minutes > 0 ? (float) $record->video_minutes : (float) $record->radio_minutes);
                                            $unit = (int) $record->images_count > 0 ? 'Stueck' : 'Minuten';
                                            $unitPrice = (int) $record->images_count > 0 ? (float) $record->price_per_image : (float) $record->price_per_minute;
                                        @endphp
                                        <tr class="border-b border-gray-100 align-top">
                                            <td class="py-3 pr-3 text-gray-900">{{ $position++ }}</td>
                                            <td class="py-3 pr-3 text-gray-900">
                                                <div class="space-y-1">
                                                    <p>{{ $section['line_item_title'] ?? ($billingTypeLabels[$record->billing_type] ?? 'Abrechnungsposition') }}</p>
                                                    @if(!empty($section['line_item_subtitle']))
                                                        <p class="text-xs text-amber-700">{{ $section['line_item_subtitle'] }}</p>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="py-3 pr-3 text-right text-gray-900">{{ number_format((float) $quantity, (fmod((float) $quantity, 1.0) === 0.0 ? 0 : 1), ',', '.') }}</td>
                                            <td class="py-3 pr-3 text-right text-gray-900">{{ $unit }}</td>
                                            <td class="py-3 pr-3 text-right text-gray-900">{{ number_format($unitPrice, 2, ',', '.') }}</td>
                                            <td class="py-3 text-right text-gray-900">{{ number_format((float) $record->total_amount, 2, ',', '.') }}</td>
                                        </tr>
                                    @endif
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-6 text-center text-sm text-gray-500">
                                            Diesem Entwurf sind aktuell keine Positionen zugeordnet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="5" class="pt-4 text-right text-gray-700">Zwischensumme (netto)</td>
                                    <td class="pt-4 text-right text-gray-900">{{ number_format($displayNet, 2, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td colspan="5" class="pt-2 text-right text-gray-700">Umsatzsteuer {{ number_format($vatRate, 0, ',', '.') }} %</td>
                                    <td class="pt-2 text-right text-gray-900">{{ number_format($displayVat, 2, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td colspan="5" class="pt-2 text-right font-semibold text-gray-900">
                                        Gesamtbetrag (Brutto)
                                        <span class="block text-xs font-normal text-gray-600">inkl. {{ number_format($vatRate, 0, ',', '.') }}&nbsp;% Umsatzsteuer – Zahlungsbetrag</span>
                                    </td>
                                    <td class="pt-2 text-right font-semibold text-gray-900 align-top">{{ number_format($displayGross, 2, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="mt-10 text-sm text-gray-800 space-y-2">
                        <p>Die Rechnung ist innerhalb von {{ (int) ($payment['days'] ?? 7) }} Tagen netto ohne Abzug zu begleichen.</p>
                        <p>Verwendungszweck: Bitte geben Sie bei der Überweisung die jeweilige Rechnungsnummer an.</p>
                        <p class="pt-2">Mit freundlichen Grüßen</p>
                        <p>{{ $sender['name'] ?? 'Alexander Franz' }}</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
                    <h3 class="text-sm font-semibold text-gray-900">Naechste Schritte</h3>
                    <div class="mt-3 space-y-3">
                        <a href="{{ route('admin.backoffice.billing.pdf', $invoice) }}" class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium rounded-md border border-blue-300 text-blue-900 bg-blue-50 hover:bg-blue-100">
                            Entwurfs-PDF erzeugen
                        </a>
                        @if($dispatchStatus['zugferd']['allowed'])
                            <a href="{{ route('admin.backoffice.billing.zugferd', $invoice) }}" class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium rounded-md border border-violet-300 text-violet-900 bg-violet-50 hover:bg-violet-100">
                                ZUGFeRD / EN16931 laden
                            </a>
                        @else
                            <span class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium rounded-md border border-gray-300 text-gray-500 bg-gray-100 cursor-not-allowed">
                                ZUGFeRD erst nach Lexware
                            </span>
                        @endif
                        @if($invoice->lexware_invoice_id)
                            <a href="{{ route('admin.backoffice.billing.lexware-file', $invoice) }}" class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium rounded-md border border-emerald-300 text-emerald-900 bg-emerald-50 hover:bg-emerald-100">
                                Finale Lexware-Rechnung laden
                            </a>
                        @endif
                        @if($dispatchStatus['dispatch']['can_send'])
                            <form method="POST" action="{{ route('admin.backoffice.billing.send', $invoice) }}">
                                @csrf
                                <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium rounded-md border border-emerald-300 text-emerald-900 bg-emerald-50 hover:bg-emerald-100">
                                    {{ ($dispatchStatus['dispatch']['status'] ?? null) === 'sent' ? 'Erneut senden (Korrektur)' : 'Rechnung jetzt versenden' }}
                                </button>
                            </form>
                            @if($internalTestRecipient)
                                <form method="POST" action="{{ route('admin.backoffice.billing.send', $invoice) }}">
                                    @csrf
                                    <input type="hidden" name="test_recipient" value="{{ $internalTestRecipient }}">
                                    <button type="submit" class="mt-2 w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium rounded-md border border-amber-300 text-amber-900 bg-amber-50 hover:bg-amber-100">
                                        Testversand an {{ $internalTestRecipient }}
                                    </button>
                                </form>
                            @endif
                        @endif
                        @if(in_array($invoice->status, ['draft', 'pending', 'ready_for_lexware'], true) && ! $invoice->lexware_invoice_id && $dispatchStatus['can_finalize_lexware'])
                            <form method="POST" action="{{ route('admin.backoffice.billing.ready-for-lexware', $invoice) }}">
                                @csrf
                                <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium rounded-md border border-amber-300 text-amber-900 bg-amber-50 hover:bg-amber-100">
                                    Finale Lexware-Rechnung erstellen
                                </button>
                            </form>
                        @elseif(in_array($invoice->status, ['draft', 'pending', 'ready_for_lexware'], true) && ! $invoice->lexware_invoice_id)
                            <span class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium rounded-md border border-gray-300 text-gray-500 bg-gray-100 cursor-not-allowed">
                                Lexware-Finalisierung gesperrt
                            </span>
                        @endif
                        @if(! $invoice->lexware_invoice_id)
                            <form method="POST" action="{{ route('admin.backoffice.billing.release', $invoice) }}" onsubmit="return confirm('Entwurf auflösen und Posten wieder freigeben?');">
                                @csrf
                                <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium rounded-md border border-red-300 text-red-700 bg-red-50 hover:bg-red-100">
                                    Entwurf auflösen
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
                    <h3 class="text-sm font-semibold text-gray-900">Was steht darauf?</h3>
                    <ul class="mt-3 space-y-2 text-sm text-gray-700 list-disc pl-5">
                        <li>Empfänger-Kontaktperson, Adresse und E-Mail</li>
                        <li>Rechnungsadresse der Redaktion</li>
                        <li>Kundennummer / Buyer Reference</li>
                        <li>Leistungsblock wie in deinen Musterrechnungen</li>
                        <li>Netto, MwSt. und Gesamtbetrag</li>
                        <li>Lexware-Rechnungsnummer als verbindliche Endnummer</li>
                        <li>ZUGFeRD-Profil EN16931 erst nach erfolgreicher Lexware-Finalisierung</li>
                    </ul>
                </div>

                <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
                    <h3 class="text-sm font-semibold text-gray-900">Footer / Bankdaten</h3>
                    <div class="mt-3 text-sm text-gray-700 space-y-1">
                        <p>{{ $sender['name'] ?? 'Alexander Franz' }}</p>
                        <p>{{ $sender['street'] ?? '' }}</p>
                        <p>{{ trim(($sender['postal_code'] ?? '') . ' ' . ($sender['city'] ?? '')) }}</p>
                        <p>Tel.: {{ $sender['phone'] ?? '–' }}</p>
                        <p>Fax: {{ $sender['fax'] ?? '–' }}</p>
                        <p>{{ $sender['email'] ?? '–' }}</p>
                        <p>{{ $sender['website'] ?? '–' }}</p>
                        <p class="pt-2">Steuernummer: {{ $sender['tax_number'] ?? '–' }}</p>
                        <p>{{ $bank['name'] ?? '–' }}</p>
                        <p>IBAN: {{ $bank['iban'] ?? '–' }}</p>
                        <p>BIC: {{ $bank['bic'] ?? '–' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
