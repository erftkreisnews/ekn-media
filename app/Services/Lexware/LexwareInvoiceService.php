<?php

namespace App\Services\Lexware;

use App\Models\Invoice;
use App\Models\UsageRecord;
use App\Services\Billing\WdrNewsroomImageTierLines;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\Response;
use RuntimeException;

class LexwareInvoiceService
{
    public function __construct(
        protected LexwareClient $client,
        protected LexwareContactService $contactService,
    ) {}

    public function createFinalInvoice(Invoice $invoice): Invoice
    {
        $invoice->loadMissing([
            'organization',
            'product.organization',
            'contact',
            'usageRecords.newsItem',
        ]);

        $invoice->syncTotalsFromUsageRecordsIfEditable();
        $invoice->refresh();

        if ($invoice->lexware_invoice_id || $invoice->voucher_number) {
            throw new RuntimeException('Diese Rechnung wurde bereits an Lexware übergeben.');
        }

        if (! $invoice->organization || ! $invoice->product || ! $invoice->contact) {
            throw new RuntimeException('Für die Lexware-Übergabe fehlen Kunden-, Redaktions- oder Empfängerdaten.');
        }

        if ($invoice->usageRecords->isEmpty()) {
            throw new RuntimeException('Für die Lexware-Übergabe sind keine Rechnungspositionen vorhanden.');
        }

        if (! $invoice->product->lexware_contact_id) {
            $this->contactService->ensureContact($invoice->product);
            $invoice->product->refresh();
        }

        $response = $this->client->post('/v1/invoices?finalize=true', $this->buildPayload($invoice));
        if ($response->failed()) {
            throw new RuntimeException($this->extractErrorMessage($response));
        }

        $lexwareId = trim((string) ($response->json('id') ?? ''));
        if ($lexwareId === '') {
            throw new RuntimeException('Lexware hat keine Rechnungs-ID zurückgegeben.');
        }

        $lexwareInvoice = $this->fetchInvoice($lexwareId);
        $voucherNumber = trim((string) ($lexwareInvoice['voucherNumber'] ?? ''));
        if ($voucherNumber === '') {
            throw new RuntimeException('Lexware hat keine finale Rechnungsnummer zurückgegeben.');
        }

        $meta = (array) ($invoice->meta ?? []);
        $meta['lexware'] = [
            'resource_uri' => $response->json('resourceUri'),
            'voucher_status' => $lexwareInvoice['voucherStatus'] ?? null,
            'electronic_document_profile' => $lexwareInvoice['electronicDocumentProfile'] ?? null,
            'transferred_at' => now()->toIso8601String(),
        ];

        $invoice->update([
            'lexware_invoice_id' => $lexwareId,
            'voucher_number' => $voucherNumber,
            'status' => $this->resolveInvoiceStatusFromVoucherStatus((string) ($lexwareInvoice['voucherStatus'] ?? '')),
            'meta' => $meta,
        ]);

        return $invoice->fresh(['organization', 'product', 'contact', 'usageRecords.newsItem']);
    }

    public function downloadInvoiceFile(Invoice $invoice): Response
    {
        $invoiceId = trim((string) ($invoice->lexware_invoice_id ?? ''));
        if ($invoiceId === '') {
            throw new RuntimeException('Für diese Rechnung ist noch keine Lexware-Rechnung vorhanden.');
        }

        $response = $this->client->getFile("/v1/invoices/{$invoiceId}/file");
        if ($response->failed()) {
            throw new RuntimeException($this->extractErrorMessage($response));
        }

        return $response;
    }

    /**
     * Zahlungsinformationen zur Ausgangsrechnung (nur Lesen; Schreiben unterstützt die Public API nicht).
     *
     * @see https://developers.lexware.io/docs/#payments-endpoint-retrieve-payment-information
     */
    public function fetchPaymentInformation(string $lexwareVoucherId): array
    {
        $id = trim($lexwareVoucherId);
        if ($id === '') {
            throw new RuntimeException('Keine Lexware-Voucher-ID.');
        }

        $response = $this->client->get('/v1/payments/'.rawurlencode($id));
        if ($response->failed()) {
            throw new RuntimeException($this->extractErrorMessage($response));
        }

        $data = $response->json();

        return is_array($data) ? $data : [];
    }

    /**
     * Kompakte Darstellung für invoice.meta (EKN).
     *
     * @param  array<string, mixed>  $apiResponse
     * @return array<string, mixed>
     */
    public function summarizePaymentInformation(array $apiResponse): array
    {
        $voidReason = null;
        foreach ([
            'voucherStatusReason',
            'voidReason',
            'cancellationReason',
            'reason',
            'note',
            'remark',
        ] as $key) {
            $candidate = trim((string) ($apiResponse[$key] ?? ''));
            if ($candidate !== '') {
                $voidReason = $candidate;
                break;
            }
        }

        return [
            'fetched_at' => now()->toIso8601String(),
            'open_amount' => $apiResponse['openAmount'] ?? null,
            'currency' => $apiResponse['currency'] ?? null,
            'voucher_status' => $apiResponse['voucherStatus'] ?? null,
            'payment_status' => $apiResponse['paymentStatus'] ?? null,
            'void_reason' => $voidReason,
            'payment_items' => $apiResponse['paymentItems'] ?? [],
        ];
    }

    protected function fetchInvoice(string $id): array
    {
        $response = $this->client->get("/v1/invoices/{$id}");
        if ($response->failed()) {
            throw new RuntimeException($this->extractErrorMessage($response));
        }

        $data = $response->json();

        return is_array($data) ? $data : [];
    }

    protected function buildPayload(Invoice $invoice): array
    {
        $usageRecords = $invoice->usageRecords
            ->sortBy([
                ['used_at', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        $product = $invoice->product;
        $contact = $invoice->contact;
        $paymentDays = (int) config('invoice.payment.days', 7);
        $vatRate = (float) config('invoice.payment.vat_rate', 7);

        return [
            'archived' => false,
            'voucherDate' => $this->formatTimestamp($invoice->voucher_date),
            'address' => $this->buildAddressPayload($invoice),
            'lineItems' => $usageRecords->flatMap(function (UsageRecord $record) use ($invoice, $vatRate) {
                return collect($this->buildLexwareLineItemsForRecord($record, $invoice, $vatRate));
            })->values()->all(),
            'totalPrice' => [
                'currency' => 'EUR',
            ],
            'taxConditions' => [
                'taxType' => 'net',
            ],
            'paymentConditions' => [
                'paymentTermLabel' => 'Zahlbar in '.$paymentDays.' Tagen netto ohne Abzug',
                'paymentTermDuration' => $paymentDays,
            ],
            'shippingConditions' => $this->buildShippingConditions($usageRecords),
            'title' => 'Rechnung',
            'introduction' => 'Hiermit berechnen wir die nachfolgend dokumentierten journalistischen Nutzungen.',
            'remark' => 'Bitte geben Sie bei der Überweisung die Rechnungsnummer als Verwendungszweck an.',
        ];
    }

    protected function buildAddressPayload(Invoice $invoice): array
    {
        $product = $invoice->product;
        $contact = $invoice->contact;

        $companyName = trim((string) ($product->billing_company ?: $invoice->organization?->name ?: ''));
        $billingName = trim((string) ($product->billing_name ?: ''));
        $supplementParts = array_values(array_filter([
            $billingName !== '' && $billingName !== $companyName ? $billingName : null,
        ]));

        $payload = [
            'contactId' => $product->lexware_contact_id ?: null,
            'name' => $companyName !== '' ? $companyName : ($billingName !== '' ? $billingName : 'Rechnungsempfänger'),
        ];

        if ($supplementParts !== []) {
            $payload['supplement'] = implode(' | ', $supplementParts);
        }

        $street = trim((string) ($product->billing_street ?? ''));
        $zip = trim((string) ($product->billing_postal_code ?? ''));
        $city = trim((string) ($product->billing_city ?? ''));
        $countryCode = $this->mapCountryCode($product->billing_country);

        if ($street !== '') {
            $payload['street'] = $street;
        }
        if ($zip !== '') {
            $payload['zip'] = $zip;
        }
        if ($city !== '') {
            $payload['city'] = $city;
        }
        if ($countryCode !== '') {
            $payload['countryCode'] = $countryCode;
        }

        return array_filter($payload, static fn ($value) => $value !== null);
    }

    protected function buildShippingConditions($usageRecords): array
    {
        $dates = $usageRecords
            ->pluck('used_at')
            ->filter()
            ->map(fn ($date) => $date instanceof CarbonInterface ? $date : null)
            ->filter()
            ->sort()
            ->values();

        if ($dates->isEmpty()) {
            return ['shippingType' => 'none'];
        }

        $start = $dates->first();
        $end = $dates->last();

        if ($start && $end && $start->isSameDay($end)) {
            return [
                'shippingType' => 'service',
                'shippingDate' => $this->formatTimestamp($start),
            ];
        }

        return [
            'shippingType' => 'serviceperiod',
            'shippingDate' => $this->formatTimestamp($start),
            'shippingEndDate' => $this->formatTimestamp($end),
        ];
    }

    protected function extractQuantityAndPrice(UsageRecord $record): array
    {
        if ((int) $record->images_count > 0) {
            return [
                (int) $record->images_count,
                'Stück',
                round((float) $record->price_per_image, 4),
            ];
        }

        if ((float) $record->video_minutes > 0) {
            return [
                round((float) $record->video_minutes, 2),
                'Minuten',
                round((float) $record->price_per_minute, 4),
            ];
        }

        return [
            round((float) $record->radio_minutes, 2),
            'Minuten',
            round((float) $record->price_per_minute, 4),
        ];
    }

    protected function buildLexwareLineItemsForRecord(UsageRecord $record, Invoice $invoice, float $vatRate): array
    {
        if (WdrNewsroomImageTierLines::appliesTo($record, $invoice)) {
            $tier = WdrNewsroomImageTierLines::make();
            $baseDescription = $this->buildLineItemDescription($record);
            $items = [];
            foreach ($tier->linesForImageCount((int) $record->images_count) as $line) {
                $items[] = [
                    'type' => 'custom',
                    'name' => $line['title'],
                    'description' => trim($baseDescription."\n".$line['tier_label']),
                    'quantity' => (int) round($line['quantity']),
                    'unitName' => 'Stück',
                    'unitPrice' => [
                        'currency' => 'EUR',
                        'netAmount' => round((float) $line['unit_price'], 2),
                        'taxRatePercentage' => $vatRate,
                    ],
                    'discountPercentage' => 0,
                ];
            }

            if ($items !== []) {
                return $items;
            }
        }

        [$quantity, $unitName, $unitNetAmount] = $this->extractQuantityAndPrice($record);

        return [[
            'type' => 'custom',
            'name' => $this->buildLineItemTitle($record, $invoice),
            'description' => $this->buildLineItemDescription($record),
            'quantity' => $quantity,
            'unitName' => $unitName,
            'unitPrice' => [
                'currency' => 'EUR',
                'netAmount' => $unitNetAmount,
                'taxRatePercentage' => $vatRate,
            ],
            'discountPercentage' => 0,
        ]];
    }

    protected function buildLineItemTitle(UsageRecord $record, Invoice $invoice): string
    {
        if ((int) $record->images_count > 0) {
            return (int) $record->images_count === 1
                ? 'Online-Nutzung Foto - erstes Bild'
                : 'Online-Nutzung Foto - '.(int) $record->images_count.' Bilder';
        }

        if ((float) $record->video_minutes > 0) {
            $customerName = $invoice->organization?->name ?: 'Kunde';

            return 'Videomaterial Verkauf '.$customerName;
        }

        if ((float) $record->radio_minutes > 0) {
            $customerName = $invoice->organization?->name ?: 'Kunde';
            if (($record->billing_type ?? null) === 'honorar') {
                return 'Honorar (Audio/Radio) '.$customerName;
            }

            return 'Radiomaterial Verkauf '.$customerName;
        }

        return 'Abrechnungsposition';
    }

    protected function buildLineItemDescription(UsageRecord $record): string
    {
        $lines = array_filter([
            $record->line_item_note ? 'Hinweis: '.trim((string) $record->line_item_note) : null,
            $record->newsItem?->title ? 'Anlass: '.trim((string) $record->newsItem->title) : null,
            $record->used_at ? 'Datum: '.$record->used_at->format('d.m.Y') : null,
            $record->usage_format ? 'Format: '.trim((string) $record->usage_format) : null,
            $record->usage_rights ? 'Nutzungsrecht: '.trim((string) $record->usage_rights) : null,
            $record->article_url ? 'Link: '.trim((string) $record->article_url) : null,
            $record->reference_code ? 'Referenz: '.trim((string) $record->reference_code) : null,
            $record->newsItem?->author_credit ? 'Credits: '.trim((string) $record->newsItem->author_credit) : null,
        ]);

        return implode("\n", $lines);
    }

    protected function formatTimestamp(CarbonInterface|string|null $value): string
    {
        $date = $value instanceof CarbonInterface
            ? $value->copy()
            : Carbon::parse((string) $value, config('app.timezone', 'Europe/Berlin'));

        return $date
            ->setTimezone(config('app.timezone', 'Europe/Berlin'))
            ->startOfDay()
            ->format('Y-m-d\TH:i:s.000P');
    }

    protected function mapCountryCode(?string $country): string
    {
        $value = strtoupper(trim((string) ($country ?? '')));

        return match ($value) {
            '', 'DEUTSCHLAND', 'GERMANY', 'DE' => 'DE',
            default => strlen($value) === 2 ? $value : 'DE',
        };
    }

    protected function extractErrorMessage(Response $response): string
    {
        if ($response->status() === 429) {
            return 'Lexware ist gerade ausgelastet. Bitte in wenigen Sekunden erneut versuchen.';
        }

        $json = $response->json();

        if (is_array($json)) {
            $detailMessage = $json['details'][0]['message'] ?? null;
            if (is_string($detailMessage) && trim($detailMessage) !== '') {
                $field = $json['details'][0]['field'] ?? null;

                return $field
                    ? 'Lexware-Validierung fehlgeschlagen: '.$field.' '.$detailMessage
                    : 'Lexware-Validierung fehlgeschlagen: '.$detailMessage;
            }

            $legacyMessage = $json['IssueList'][0]['i18nKey'] ?? null;
            if (is_string($legacyMessage) && trim($legacyMessage) !== '') {
                return 'Lexware hat die Anfrage abgelehnt: '.$legacyMessage;
            }

            $message = $json['message'] ?? null;
            if (is_string($message) && trim($message) !== '') {
                return 'Lexware-Antwort: '.trim($message);
            }
        }

        return 'Lexware-Antwort mit HTTP '.$response->status().'.';
    }

    protected function resolveInvoiceStatusFromVoucherStatus(string $voucherStatus): string
    {
        return mb_strtolower(trim($voucherStatus)) === 'voided'
            ? 'lexware_voided'
            : 'lexware_open';
    }
}
