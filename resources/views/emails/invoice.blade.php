@php
    $organization = $invoice->organization;
    $product = $invoice->product;
    $contact = $invoice->contact;
    $invoiceNumber = $invoice->voucher_number ?: 'Rechnung';
@endphp

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Rechnung {{ $invoiceNumber }}</title>
</head>
<body style="font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background-color: #f3f4f6; padding: 16px;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 640px; background: #ffffff; border-radius: 12px; padding: 24px; border: 1px solid #e5e7eb;">
                <tr>
                    <td>
                        <h1 style="font-size: 18px; margin: 0 0 12px 0; color: #111827;">
                            Ihre Rechnung {{ $invoiceNumber }}
                        </h1>

                        <p style="font-size: 14px; color: #4b5563; margin: 0 0 16px 0;">
                            sehr geehrte Damen und Herren,
                        </p>

                        <p style="font-size: 14px; color: #4b5563; margin: 0 0 16px 0;">
                            anbei erhalten Sie Ihre Rechnung fuer
                            {{ $organization?->name ?? 'den Kunden' }}
                            @if($product?->name)
                                / {{ $product->name }}
                            @endif
                            als PDF-Anhang.
                        </p>

                        <table role="presentation" cellspacing="0" cellpadding="0" style="width: 100%; font-size: 13px; color: #374151; margin-bottom: 16px;">
                            <tr>
                                <td style="padding: 4px 0; width: 180px; color: #6b7280;">Rechnungsnummer</td>
                                <td style="padding: 4px 0;">{{ $invoiceNumber }}</td>
                            </tr>
                            <tr>
                                <td style="padding: 4px 0; color: #6b7280;">Rechnungsdatum</td>
                                <td style="padding: 4px 0;">{{ optional($invoice->voucher_date)->format('d.m.Y') ?: '–' }}</td>
                            </tr>
                            <tr>
                                <td style="padding: 4px 0; color: #6b7280;">Gesamtbetrag</td>
                                <td style="padding: 4px 0;">{{ number_format((float) $invoice->total_gross, 2, ',', '.') }} €</td>
                            </tr>
                            <tr>
                                <td style="padding: 4px 0; color: #6b7280;">Zahlungsziel</td>
                                <td style="padding: 4px 0;">
                                    @if($dueDate)
                                        zahlbar innerhalb von {{ $paymentDays }} Tagen netto, spaetestens bis {{ $dueDate->format('d.m.Y') }}
                                    @else
                                        zahlbar innerhalb von {{ $paymentDays }} Tagen netto
                                    @endif
                                </td>
                            </tr>
                            @if($contact?->name)
                                <tr>
                                    <td style="padding: 4px 0; color: #6b7280;">Ansprechpartner</td>
                                    <td style="padding: 4px 0;">{{ $contact->name }}</td>
                                </tr>
                            @endif
                        </table>

                        <p style="font-size: 14px; color: #4b5563; margin: 0 0 16px 0;">
                            Bitte geben Sie bei der Ueberweisung die Rechnungsnummer als Verwendungszweck an.
                        </p>

                        <p style="font-size: 14px; color: #4b5563; margin: 0;">
                            Mit freundlichen Gruessen<br>
                            {{ $senderName }}
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
