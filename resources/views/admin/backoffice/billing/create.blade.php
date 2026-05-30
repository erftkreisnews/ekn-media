@extends('layouts.admin')

@section('content')
    <div class="space-y-6 max-w-4xl mx-auto">
        <div>
            <a href="{{ route('admin.backoffice.billing.index') }}" class="text-sm text-gray-600 hover:text-[#092E48] mb-2 inline-block">← Abrechnung</a>
            <h1 class="text-2xl font-semibold text-gray-900">Rechnung anlegen</h1>
            <p class="mt-1 text-sm text-gray-600">
                Erzeuge einen Rechnungsentwurf für
                <span class="font-medium">{{ $customer->name }}</span>
                – Redaktion
                <span class="font-medium">{{ $product->name }}</span>.
            </p>
        </div>

        @if (session('error'))
            <div class="rounded-md bg-red-50 p-4 border border-red-200">
                <p class="text-sm text-red-800">{{ session('error') }}</p>
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Offene Videominuten</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">
                    {{ number_format((float) $openVideo, 1, ',', '.') }}
                </p>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Offene Radio-Minuten</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">
                    {{ number_format((float) ($openRadio ?? 0), 1, ',', '.') }}
                </p>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Offene Bilder</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">
                    {{ (int) $openPhotos }}
                </p>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Offener Netto-Betrag</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">
                    {{ number_format((float) $openNet, 2, ',', '.') }} €
                </p>
                <p class="mt-1 text-xs text-gray-500">
                    {{ $openRecordsCount }} Nachverfolgungs-Einträge ohne Rechnung.
                </p>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
            <h2 class="text-base font-medium text-gray-900 mb-4">Rechnungsempfänger wählen</h2>

            @if ($billingContacts->isEmpty())
                <p class="text-sm text-gray-600">
                    Für dieses Medienhaus wurden noch keine Rechnungsempfänger markiert.
                    Du kannst dafür auch reine Funktionsadressen verwenden (z. B. newsroom-honorare@...).
                    Bitte lege im Kundenbereich unter „Kontakte“ einen Eintrag mit aktivierter Option
                    „Als Rechnungsempfänger verwenden“ an.
                </p>
                @if($product->billing_email_primary || $product->billing_email_secondary)
                    <p class="mt-2 text-xs text-gray-500">
                        Bereits bei der Redaktion hinterlegte Rechnungs-E-Mails:
                        {{ $product->billing_email_primary ?: '–' }}@if($product->billing_email_secondary), {{ $product->billing_email_secondary }}@endif.
                        Diese dienen aktuell nur als Stammdaten und müssen einmal als Rechnungsempfänger-Kontakt angelegt werden.
                    </p>
                @endif
            @else
                <form method="POST" action="{{ route('admin.backoffice.billing.store') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">

                    @php
                        $wdrProduct = !empty($isWdrBillingProduct);
                        $scopes = $wdrBillingScopes ?? [];
                        $fmtMin = static function (float $m): string {
                            $s = rtrim(rtrim(number_format($m, 1, ',', '.'), '0'), ',');
                            $suffix = abs($m - 1.0) < 0.05 ? 'Minute' : 'Minuten';
                            return $s.' '.$suffix;
                        };
                    @endphp

                    @if($wdrProduct && count($scopes) > 0)
                        @if(count($scopes) === 1)
                            <input type="hidden" name="billing_scope" value="{{ $scopes[0]['scope'] }}">
                            <div class="rounded-md border border-amber-200 bg-amber-50 p-4 text-xs text-amber-900 space-y-2">
                                <p class="font-medium text-amber-950">WDR – eine offene Rechnungsposition</p>
                                <p><span class="font-medium">{{ $scopes[0]['title'] }}</span><br>{{ $scopes[0]['line'] }}</p>
                                <p class="text-amber-800">Es wird genau diese Meldung und Rechnungsart abgerechnet. Weitere Meldungen bleiben offen, bis du sie in weiteren Schritten anlegst.</p>
                            </div>
                        @else
                            <div class="rounded-md border border-amber-200 bg-amber-50 p-4">
                                <p class="text-sm font-medium text-amber-900">Getrennte Abrechnung erforderlich</p>
                                <p class="mt-1 text-xs text-amber-800">
                                    Beim WDR gehört zu <span class="font-medium">jeder Rechnung genau eine Meldung</span> und eine Rechnungsart (Bilder, Video, Radio-Lizenz oder Honorar Audio).
                                    Wähle unten die Position, die du <span class="font-medium">in diesem Schritt</span> abrechnen möchtest – für jede weitere Meldung legst du einen weiteren Rechnungsentwurf an.
                                </p>
                                <div class="mt-3 space-y-2">
                                    @foreach($scopes as $opt)
                                        <label class="flex items-start gap-2 text-sm text-gray-800 cursor-pointer">
                                            <input type="radio" name="billing_scope" value="{{ $opt['scope'] }}" class="text-[#092E48] focus:ring-[#092E48] mt-0.5" {{ old('billing_scope') === $opt['scope'] ? 'checked' : '' }} @if($loop->first) required @endif>
                                            <span>
                                                <span class="font-medium">{{ $opt['title'] }}</span>
                                                <span class="text-gray-600"> – {{ $opt['line'] }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @elseif(count($availableInvoiceKinds) > 1)
                        <div class="rounded-md border border-amber-200 bg-amber-50 p-4">
                            <p class="text-sm font-medium text-amber-900">Getrennte Abrechnung erforderlich</p>
                            <p class="mt-1 text-xs text-amber-800">Bitte getrennt abrechnen: Bilder, Video, Radio (Lizenz) und Honorar (Audio) werden als eigene Rechnungen geführt.</p>
                            <div class="mt-3 space-y-2">
                                <label class="inline-flex items-start gap-2 text-sm text-gray-800">
                                    <input type="radio" name="invoice_kind" value="lizenz_bild" class="text-[#092E48] focus:ring-[#092E48] mt-0.5" {{ old('invoice_kind', $selectedInvoiceKind) === 'lizenz_bild' ? 'checked' : '' }}>
                                    @php $ib = (int) ($invoiceKindStats['lizenz_bild']['images_sum'] ?? 0); @endphp
                                    Lizenz (Bilder) – {{ $ib === 1 ? '1 Bild' : $ib.' Bilder' }}
                                </label><br>
                                <label class="inline-flex items-start gap-2 text-sm text-gray-800">
                                    <input type="radio" name="invoice_kind" value="lizenz_video" class="text-[#092E48] focus:ring-[#092E48] mt-0.5" {{ old('invoice_kind', $selectedInvoiceKind) === 'lizenz_video' ? 'checked' : '' }}>
                                    @php $vm = (float) ($invoiceKindStats['lizenz_video']['video_minutes_sum'] ?? 0); @endphp
                                    Lizenz (Video) – {{ $fmtMin($vm) }}
                                </label>
                                @if(($invoiceKindStats['lizenz_radio']['count'] ?? 0) > 0)
                                    <br><label class="inline-flex items-start gap-2 text-sm text-gray-800">
                                        <input type="radio" name="invoice_kind" value="lizenz_radio" class="text-[#092E48] focus:ring-[#092E48] mt-0.5" {{ old('invoice_kind', $selectedInvoiceKind) === 'lizenz_radio' ? 'checked' : '' }}>
                                        @php $rm = (float) ($invoiceKindStats['lizenz_radio']['radio_minutes_sum'] ?? 0); @endphp
                                        Lizenz (Radio) – {{ $fmtMin($rm) }}
                                    </label>
                                @endif
                                @if(($invoiceKindStats['honorar_audio']['count'] ?? 0) > 0)
                                    <br><label class="inline-flex items-start gap-2 text-sm text-gray-800">
                                        <input type="radio" name="invoice_kind" value="honorar_audio" class="text-[#092E48] focus:ring-[#092E48] mt-0.5" {{ old('invoice_kind', $selectedInvoiceKind) === 'honorar_audio' ? 'checked' : '' }}>
                                        @php $ha = (float) ($invoiceKindStats['honorar_audio']['radio_minutes_sum'] ?? 0); @endphp
                                        Honorar (Audio) – {{ $fmtMin($ha) }}
                                    </label>
                                @endif
                            </div>
                        </div>
                    @elseif(count($availableInvoiceKinds) === 1 && ! $wdrProduct)
                        <input type="hidden" name="invoice_kind" value="{{ $availableInvoiceKinds[0] }}">
                        <p class="text-xs text-gray-600">
                            Rechnungsart:
                            <span class="font-medium">
                                @if($availableInvoiceKinds[0] === 'lizenz_bild')
                                    Lizenz (Bilder)
                                @elseif($availableInvoiceKinds[0] === 'lizenz_video')
                                    Lizenz (Video)
                                @elseif($availableInvoiceKinds[0] === 'lizenz_radio')
                                    Lizenz (Radio)
                                @else
                                    Honorar (Audio)
                                @endif
                            </span>
                        </p>
                    @endif

                    <div>
                        <label for="contact_id" class="block text-sm font-medium text-gray-700">Rechnungsempfänger (Kontakt)</label>
                        <select id="contact_id" name="contact_id" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                            <option value="">Bitte auswählen …</option>
                            @foreach($billingContacts as $contact)
                                <option value="{{ $contact->id }}" @selected($suggestedContactId === $contact->id)>
                                    {{ $contact->name ?: $contact->email }}
                                    @if($contact->email)
                                        – {{ $contact->email }}
                                    @endif
                                    @if($contact->product)
                                        (Redaktion: {{ $contact->product->name }})
                                    @endif
                                    @if($contact->billing_department || $contact->billing_type)
                                        – {{ $contact->getBillingDepartmentLabel() }}{{ $contact->billing_department && $contact->billing_type ? ' · ' : '' }}{{ $contact->getBillingTypeLabel() }}
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        @error('contact_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <p class="text-xs text-gray-500">
                        Der gewählte Kontakt wird als Rechnungsempfänger verwendet.
                        @if($wdrProduct && count($scopes) > 0)
                            Es werden nur die Nachverfolgungs-Einträge der gewählten Meldung und Rechnungsart diesem Entwurf zugeordnet.
                        @else
                            Alle aktuell offenen Nachverfolgungs-Einträge für diese Redaktion (bzw. nach gewählter Rechnungsart) werden dem neuen Rechnungsentwurf zugeordnet.
                        @endif
                    </p>

                    <div class="pt-4 border-t border-gray-200 flex items-center justify-end gap-3">
                        <a href="{{ route('admin.backoffice.billing.index') }}" class="px-4 py-2 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                            Abbrechen
                        </a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]">
                            Rechnungsentwurf anlegen
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endsection

