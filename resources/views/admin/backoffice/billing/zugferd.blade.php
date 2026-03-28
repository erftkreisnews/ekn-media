<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>ZUGFeRD-Rechnung {{ $documentNumber }}</title>
    <style>
        @page {
            margin: 24mm 16mm 30mm 16mm;
            footer: invoiceFooter;
        }

        body {
            font-family: dejavusans, sans-serif;
            font-size: 10pt;
            color: #1f2937;
            line-height: 1.45;
            margin: 0;
        }

        h1, h2, h3, p, table {
            margin: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-left,
        .header-right {
            vertical-align: top;
        }

        .header-left {
            width: 56%;
            padding-top: 28mm;
        }

        .header-right {
            width: 44%;
            text-align: right;
        }

        .sender-block {
            line-height: 1.5;
        }

        .meta-block {
            margin-top: 8px;
            line-height: 1.55;
        }

        .address-window {
            width: 85mm;
            line-height: 1.45;
        }

        .address-window p {
            margin: 0;
        }

        .eyebrow {
            margin-top: 8px;
            text-align: right;
            color: #6b7280;
            font-size: 8pt;
        }

        .section {
            margin-top: 16px;
        }

        .positions-table {
            margin-top: 14px;
        }

        .positions-table th,
        .positions-table td {
            padding: 6px 5px;
            border-bottom: 1px solid #d1d5db;
            vertical-align: top;
        }

        .positions-table th {
            background: #f3f4f6;
            border-top: 1px solid #9ca3af;
            text-align: left;
            font-size: 8.5pt;
        }

        .number,
        .money {
            text-align: right;
            white-space: nowrap;
        }

        .description {
            color: #4b5563;
            font-size: 8.5pt;
            margin-top: 3px;
            white-space: pre-line;
        }

        .totals {
            width: 260px;
            margin-left: auto;
            margin-top: 12px;
        }

        .totals td {
            padding: 2px 0;
        }

        .totals .label {
            text-align: right;
            padding-right: 10px;
        }

        .totals .value {
            text-align: right;
            white-space: nowrap;
        }

        .signature {
            margin-top: 16px;
        }

        .muted {
            color: #6b7280;
        }
    </style>
</head>
<body>
<htmlpagefooter name="invoiceFooter">
    <table style="font-size: 8pt; color: #6b7280; border-top: 1px solid #d1d5db; padding-top: 4mm;">
        <tr>
            <td style="width: 33%;">
                {{ $sender['name'] ?? 'Alexander Franz' }}<br>
                {{ $sender['street'] ?? '' }}<br>
                {{ trim(($sender['postal_code'] ?? '') . ' ' . ($sender['city'] ?? '')) }}
            </td>
            <td style="width: 34%;">
                {{ $sender['email'] ?? '–' }}<br>
                {{ $sender['website'] ?? '–' }}<br>
                Steuernummer: {{ $sender['tax_number'] ?? '–' }}
            </td>
            <td style="width: 33%; text-align: right;">
                {{ $bank['name'] ?? '–' }}<br>
                IBAN: {{ $bank['iban'] ?? '–' }}<br>
                BIC: {{ $bank['bic'] ?? '–' }}
            </td>
        </tr>
    </table>
</htmlpagefooter>

<table>
    <tr>
        <td class="header-left">
            <div class="address-window">
                @if($product->billing_company)
                    <p>{{ $product->billing_company }}</p>
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
            </div>
        </td>
        <td class="header-right">
            <div class="sender-block">
                <p><strong>{{ $sender['name'] ?? 'Alexander Franz' }}</strong></p>
                <p>{{ $sender['street'] ?? '' }}</p>
                <p>{{ trim(($sender['postal_code'] ?? '') . ' ' . ($sender['city'] ?? '')) }}</p>
                <p style="margin-top: 8px;">Tel.: {{ $sender['phone'] ?? '–' }}</p>
                <p>Fax: {{ $sender['fax'] ?? '–' }}</p>
                <p>{{ $sender['email'] ?? '–' }}</p>
                <p>{{ $sender['website'] ?? '–' }}</p>
            </div>
            <div class="meta-block">
                <p><strong>Rechnungsnr.:</strong> {{ $documentNumber }}</p>
                <p><strong>Kundennr.:</strong> {{ $product->buyer_reference ?: '–' }}</p>
                <p><strong>Datum:</strong> {{ optional($invoice->voucher_date)->format('d.m.Y') ?: '–' }}</p>
                <p><strong>Lieferdatum:</strong> {{ optional($usageRecords->first()?->used_at)->format('d.m.Y') ?: '–' }}</p>
                <p><strong>Kunde:</strong> {{ $organization->name }}</p>
                <p><strong>{{ $headerReferenceLabel }}:</strong> {{ $headerReferenceCode !== '' ? $headerReferenceCode : '–' }}</p>
                <p><strong>Redaktion:</strong> {{ $product->name }}</p>
            </div>
        </td>
    </tr>
</table>

<div class="eyebrow">
    <p>ZUGFeRD / EN16931</p>
    <p>Nummernquelle: {{ $numberSource === 'lexware' ? 'Lexware' : 'EKN-MVP' }}</p>
</div>

<div class="section">
    <p>Sehr geehrte Damen und Herren,</p>
    <p style="margin-top: 5px;">hiermit berechnen wir die nachfolgend dokumentierten journalistischen Nutzungen.</p>
    <p class="muted" style="margin-top: 4px;">{{ $serviceDateLabel }}</p>
</div>

<table class="positions-table">
    <thead>
        <tr>
            <th style="width: 7%;">Pos.</th>
            <th style="width: 43%;">Bezeichnung</th>
            <th style="width: 12%;" class="number">Menge</th>
            <th style="width: 12%;" class="number">Einheit</th>
            <th style="width: 13%;" class="money">Einzel €</th>
            <th style="width: 13%;" class="money">Gesamt €</th>
        </tr>
    </thead>
    <tbody>
        @foreach($lineItems as $item)
            <tr>
                <td>{{ $item['position'] }}</td>
                <td>
                    <div>{{ $item['title'] }}</div>
                    @if($item['description'] !== '')
                        <div class="description">{{ $item['description'] }}</div>
                    @endif
                </td>
                <td class="number">{{ number_format((float) $item['quantity'], fmod((float) $item['quantity'], 1.0) === 0.0 ? 0 : 2, ',', '.') }}</td>
                <td class="number">{{ $item['unit_label'] }}</td>
                <td class="money">{{ number_format((float) $item['unit_price'], 2, ',', '.') }}</td>
                <td class="money">{{ number_format((float) $item['line_total'], 2, ',', '.') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="totals">
    <tr>
        <td class="label">Zwischensumme (netto)</td>
        <td class="value">{{ number_format((float) $invoice->total_net, 2, ',', '.') }}</td>
    </tr>
    <tr>
        <td class="label">Umsatzsteuer {{ number_format((float) ($payment['vat_rate'] ?? 7), 0, ',', '.') }} %</td>
        <td class="value">{{ number_format((float) $invoice->total_vat, 2, ',', '.') }}</td>
    </tr>
    <tr>
        <td class="label"><strong>Gesamtbetrag</strong></td>
        <td class="value"><strong>{{ number_format((float) $invoice->total_gross, 2, ',', '.') }}</strong></td>
    </tr>
</table>

<div class="signature">
    <p>Die Rechnung ist innerhalb von {{ (int) ($payment['days'] ?? 7) }} Tagen netto ohne Abzug zu begleichen.</p>
    <p style="margin-top: 4px;">Verwendungszweck: Bitte geben Sie die Rechnungsnummer {{ $documentNumber }} an.</p>
    <p style="margin-top: 10px;">Mit freundlichen Grüßen</p>
    <p>{{ $sender['name'] ?? 'Alexander Franz' }}</p>
</div>
</body>
</html>
