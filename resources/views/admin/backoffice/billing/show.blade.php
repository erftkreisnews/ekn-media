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
            'ready_for_lexware' => 'Lokal fuer Lexware vorgemerkt',
            'lexware_open' => 'In Lexware erstellt',
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
        $displayVat = (float) ($invoice->total_vat ?: round((float) $invoice->total_net * $vatRate / 100, 2));
        $displayGross = (float) ($invoice->total_gross ?: round((float) $invoice->total_net + $displayVat, 2));
        $zugferdMeta = (array) (($invoice->meta ?? [])['zugferd'] ?? []);
        $dispatchMeta = (array) (($invoice->meta ?? [])['dispatch'] ?? []);
        $dispatchMessages = array_values(array_unique(array_merge($dispatchStatus['blockers'], $dispatchStatus['reviews'], $dispatchStatus['warnings'])));
        $zugferdMessages = array_values(array_diff($dispatchStatus['zugferd']['messages'], $dispatchMessages));
        $dispatchFlowMessages = $dispatchStatus['dispatch']['messages'] ?? [];
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
                    Die verbindliche Rechnungsnummer kommt aus Lexware; diese Ansicht bleibt die EKN-Vorschau fuer Versand und Kontrolle.
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
                            Rechnung jetzt versenden
                        </button>
                    </form>
                @elseif(($dispatchStatus['dispatch']['status'] ?? null) === 'sent')
                    <span class="ml-2 inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-blue-300 text-blue-900 bg-blue-50">
                        Rechnung bereits versendet
                    </span>
                @endif
            </div>
        </div>

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
                                <p><span class="font-medium">Kundennr.:</span> {{ $product->buyer_reference ?: '–' }}</p>
                                <p><span class="font-medium">Datum:</span> {{ optional($invoice->voucher_date)->format('d.m.Y') ?: '–' }}</p>
                                <p><span class="font-medium">Lieferdatum:</span> {{ optional($usageRecords->first()?->used_at)->format('d.m.Y') ?: '–' }}</p>
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
                                @forelse($usageRecords as $index => $record)
                                    @php
                                        $quantity = (int) $record->images_count > 0 ? (int) $record->images_count : (float) $record->video_minutes;
                                        $unit = (int) $record->images_count > 0 ? 'Stueck' : 'Minuten';
                                        $unitPrice = (int) $record->images_count > 0 ? (float) $record->price_per_image : (float) $record->price_per_minute;
                                        $section = $previewSections[$index] ?? null;
                                    @endphp
                                    <tr class="border-b border-gray-100 align-top">
                                        <td class="py-3 pr-3 text-gray-900">{{ $index + 1 }}</td>
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
                                    <td class="pt-4 text-right text-gray-900">{{ number_format((float) $invoice->total_net, 2, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td colspan="5" class="pt-2 text-right text-gray-700">Umsatzsteuer {{ number_format($vatRate, 0, ',', '.') }} %</td>
                                    <td class="pt-2 text-right text-gray-900">{{ number_format($displayVat, 2, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td colspan="5" class="pt-2 text-right font-semibold text-gray-900">Gesamtbetrag</td>
                                    <td class="pt-2 text-right font-semibold text-gray-900">{{ number_format($displayGross, 2, ',', '.') }}</td>
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
                                    Rechnung jetzt versenden
                                </button>
                            </form>
                        @elseif(($dispatchStatus['dispatch']['status'] ?? null) === 'sent')
                            <span class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium rounded-md border border-blue-300 text-blue-900 bg-blue-50">
                                Rechnung bereits versendet
                            </span>
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
                            <form method="POST" action="{{ route('admin.backoffice.billing.release', $invoice) }}" onsubmit="return confirm('Entwurf aufloesen und Posten wieder freigeben?');">
                                @csrf
                                <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium rounded-md border border-red-300 text-red-700 bg-red-50 hover:bg-red-100">
                                    Entwurf aufloesen
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
                    <h3 class="text-sm font-semibold text-gray-900">Was steht darauf?</h3>
                    <ul class="mt-3 space-y-2 text-sm text-gray-700 list-disc pl-5">
                        <li>Empfaenger-Kontaktperson, Adresse und E-Mail</li>
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
