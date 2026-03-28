<?php

namespace App\Services\Lexware;

use App\Models\Invoice;
use App\Models\UsageRecord;
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
            'status' => 'lexware_open',
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
            'lineItems' => $usageRecords->map(function (UsageRecord $record) use ($invoice, $vatRate) {
                [$quantity, $unitName, $unitNetAmount] = $this->extractQuantityAndPrice($record);

                return [
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
                ];
            })->all(),
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
        $contactName = trim((string) ($contact?->name ?: ''));

        $supplementParts = array_values(array_filter([
            $billingName !== '' && $billingName !== $companyName ? $billingName : null,
            $contactName !== '' && $contactName !== $billingName ? $contactName : null,
        ]));

        $payload = [
            'contactId' => $product->lexware_contact_id ?: null,
            'name' => $companyName !== '' ? $companyName : ($contactName !== '' ? $contactName : 'Rechnungsempfänger'),
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

        return [
            round((float) $record->video_minutes, 2),
            'Minuten',
            round((float) $record->price_per_minute, 4),
        ];
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
}
