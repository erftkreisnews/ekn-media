<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Rechnung Entwurf {{ $invoice->voucher_number ?: $invoice->id }}</title>
    <style>
        @page {
            margin: 24mm 16mm 30mm 16mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            line-height: 1.45;
            color: #1f2937;
            margin: 0;
        }

        h1, h2, h3, p {
            margin: 0;
        }

        .muted {
            color: #6b7280;
        }

        .small {
            font-size: 9px;
        }

        .header-table,
        .meta-table,
        .positions-table,
        .footer-table,
        .address-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td,
        .address-table td {
            vertical-align: top;
        }

        .header-left {
            width: 58%;
            padding-top: 28mm;
        }

        .header-right {
            width: 42%;
            text-align: right;
        }

        .sender-block {
            line-height: 1.5;
        }

        .header-meta {
            margin-top: 6px;
            line-height: 1.5;
        }

        .header-meta p {
            margin: 0;
        }

        .address-window {
            width: 85mm;
            line-height: 1.4;
        }

        .address-window p {
            margin: 0;
        }

        .address-notes {
            margin-top: 6px;
            text-align: right;
        }

        .post-address {
            margin-top: 3mm;
        }

        .section {
            margin-top: 16px;
        }

        .section-title {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 6px;
        }

        .section-fields {
            margin-top: 4px;
        }

        .section-fields p {
            margin: 0 0 2px 0;
        }

        .missing {
            color: #b45309;
        }

        .positions-table {
            margin-top: 14px;
        }

        .positions-table th,
        .positions-table td {
            padding: 5px 6px;
            border-bottom: 1px solid #d1d5db;
            vertical-align: top;
        }

        .positions-table thead th {
            border-top: 1px solid #9ca3af;
            background: #f3f4f6;
            text-align: left;
            font-size: 10px;
        }

        .positions-table .number,
        .positions-table .money {
            text-align: right;
            white-space: nowrap;
        }

        .subtitle {
            margin-top: 3px;
            font-size: 9px;
            color: #92400e;
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
            padding-right: 12px;
        }

        .totals .value {
            text-align: right;
            white-space: nowrap;
        }

        .signature {
            margin-top: 16px;
        }

    </style>
</head>
<body>
@php
    $product = $invoice->product;
    $organization = $invoice->organization;
    $contact = $invoice->contact;
    $sender = config('invoice.sender', []);
    $bank = config('invoice.bank', []);
    $payment = config('invoice.payment', []);
    $billingTypeLabels = [
        'lizenz' => 'Lizenz',
        'honorar' => 'Honorar',
    ];
    $vatRate = (float) ($payment['vat_rate'] ?? 7);
    $displayVat = (float) ($invoice->total_vat ?: round((float) $invoice->total_net * $vatRate / 100, 2));
    $displayGross = (float) ($invoice->total_gross ?: round((float) $invoice->total_net + $displayVat, 2));
@endphp

@php
    $headerReferenceLabel = trim((string) ($organization?->external_reference_label ?? ''));
    $headerReferenceLabel = $headerReferenceLabel !== '' ? $headerReferenceLabel : 'PV-Nr.';
    $headerReferenceCode = trim((string) ($usageRecords->first()?->reference_code ?? ''));
    if ($headerReferenceCode === '') {
        $headerReferenceCode = trim((string) ($organization?->external_author_id ?? ''));
    }
@endphp

<table class="header-table">
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
            <div class="header-meta">
                <p><strong>Rechnungsnr.:</strong> {{ $invoice->voucher_number ?: 'Entwurf-' . $invoice->id }}</p>
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

<div class="address-notes">
    <p class="small muted">Lokaler EKN-Rechnungsentwurf</p>
    <p class="small muted">Keine Lexware-Übertragung</p>
    <p class="small muted">Keine finale Lexware-Rechnungsnummer</p>
</div>

<div class="section post-address">
    <p>Sehr geehrte Damen und Herren,</p>
    <p style="margin-top: 6px;">hiermit berechne ich die nachfolgend dokumentierten journalistischen Nutzungen.</p>
</div>

@foreach($previewSections as $section)
    <div class="section">
        <div class="section-title">{{ $section['title'] }}</div>
        <div class="section-fields">
            @foreach($section['fields'] as $field)
                <p @if($field['label'] === 'Credits') style="margin-top: 8px;" @endif>
                    <strong>{{ $field['label'] }}:</strong>
                    @if($field['value'] === 'noch nicht gepflegt')
                        <span class="missing">{{ $field['value'] }}</span>
                    @else
                        {{ $field['value'] }}
                    @endif
                </p>
            @endforeach
        </div>
    </div>
@endforeach

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
    @forelse($usageRecords as $index => $record)
        @php
            $quantity = (int) $record->images_count > 0 ? (int) $record->images_count : (float) $record->video_minutes;
            $unit = (int) $record->images_count > 0 ? 'Stueck' : 'Minuten';
            $unitPrice = (int) $record->images_count > 0 ? (float) $record->price_per_image : (float) $record->price_per_minute;
            $section = $previewSections[$index] ?? null;
        @endphp
        <tr>
            <td>{{ $index + 1 }}</td>
            <td>
                <div>{{ $section['line_item_title'] ?? ($billingTypeLabels[$record->billing_type] ?? 'Abrechnungsposition') }}</div>
                @if(!empty($section['line_item_subtitle']))
                    <div class="subtitle">{{ $section['line_item_subtitle'] }}</div>
                @endif
            </td>
            <td class="number">{{ number_format((float) $quantity, (fmod((float) $quantity, 1.0) === 0.0 ? 0 : 1), ',', '.') }}</td>
            <td class="number">{{ $unit }}</td>
            <td class="money">{{ number_format($unitPrice, 2, ',', '.') }}</td>
            <td class="money">{{ number_format((float) $record->total_amount, 2, ',', '.') }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="6" style="text-align: center; padding: 16px 8px;">Diesem Entwurf sind aktuell keine Positionen zugeordnet.</td>
        </tr>
    @endforelse
    </tbody>
</table>

<table class="totals">
    <tr>
        <td class="label">Zwischensumme (netto)</td>
        <td class="value">{{ number_format((float) $invoice->total_net, 2, ',', '.') }}</td>
    </tr>
    <tr>
        <td class="label">Umsatzsteuer {{ number_format($vatRate, 0, ',', '.') }} %</td>
        <td class="value">{{ number_format($displayVat, 2, ',', '.') }}</td>
    </tr>
    <tr>
        <td class="label"><strong>Gesamtbetrag</strong></td>
        <td class="value"><strong>{{ number_format($displayGross, 2, ',', '.') }}</strong></td>
    </tr>
</table>

<div class="signature">
    <p>Die Rechnung ist innerhalb von {{ (int) ($payment['days'] ?? 7) }} Tagen netto ohne Abzug zu begleichen.</p>
    <p style="margin-top: 4px;">Verwendungszweck: Bitte geben Sie bei der Überweisung die jeweilige Rechnungsnummer an.</p>
    <p style="margin-top: 16px;">Mit freundlichen Grüßen</p>
    <p style="margin-top: 4px;">{{ $sender['name'] ?? 'Alexander Franz' }}</p>
</div>

<script type="text/php">
    if (isset($pdf) && isset($fontMetrics)) {
        $fontRegular = $fontMetrics->getFont('DejaVu Sans', 'normal');
        $fontSize = 8;
        $lineHeight = 10;
        $pageWidth = $pdf->get_width();
        $pageHeight = $pdf->get_height();
        $left = 45;
        $right = $pageWidth - 45;
        $footerTop = $pageHeight - 72;
        $colWidth = ($right - $left) / 3;

        $columnOne = [
            @json($sender['name'] ?? 'Alexander Franz'),
            @json($sender['street'] ?? ''),
            @json(trim(($sender['postal_code'] ?? '') . ' ' . ($sender['city'] ?? ''))),
            @json('Tel.: ' . ($sender['phone'] ?? '–')),
            @json('Fax: ' . ($sender['fax'] ?? '–')),
        ];

        $columnTwo = [
            @json($sender['email'] ?? '–'),
            @json($sender['website'] ?? '–'),
            @json('Steuernummer: ' . ($sender['tax_number'] ?? '–')),
        ];

        $columnThree = [
            @json($bank['name'] ?? '–'),
            @json('IBAN: ' . ($bank['iban'] ?? '–')),
            @json('BIC: ' . ($bank['bic'] ?? '–')),
        ];

        $pdf->page_script(function ($pageNumber, $pageCount, $canvas) use (
            $fontRegular,
            $fontSize,
            $lineHeight,
            $left,
            $right,
            $footerTop,
            $colWidth,
            $columnOne,
            $columnTwo,
            $columnThree
        ) {
            $canvas->line($left, $footerTop, $right, $footerTop, [0.61, 0.64, 0.69], 0.5);

            $x1 = $left;
            $x2 = $left + $colWidth;
            $x3 = $left + ($colWidth * 2);
            $y = $footerTop + 10;

            foreach ($columnOne as $index => $line) {
                $canvas->text($x1, $y + ($index * $lineHeight), $line, $fontRegular, $fontSize);
            }

            foreach ($columnTwo as $index => $line) {
                $canvas->text($x2, $y + ($index * $lineHeight), $line, $fontRegular, $fontSize);
            }

            foreach ($columnThree as $index => $line) {
                $canvas->text($x3, $y + ($index * $lineHeight), $line, $fontRegular, $fontSize);
            }
        });
    }
</script>
</body>
</html>
