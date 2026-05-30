<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\UsageRecord;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use horstoeko\zugferd\codelists\ZugferdCountryCodes;
use horstoeko\zugferd\codelists\ZugferdCurrencyCodes;
use horstoeko\zugferd\codelists\ZugferdElectronicAddressScheme;
use horstoeko\zugferd\codelists\ZugferdInvoiceType;
use horstoeko\zugferd\codelists\ZugferdUnitCodes;
use horstoeko\zugferd\codelists\ZugferdVatCategoryCodes;
use horstoeko\zugferd\codelists\ZugferdVatTypeCodes;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdDocumentPdfBuilder;
use horstoeko\zugferd\ZugferdProfiles;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mpdf\Mpdf;
use RuntimeException;

class ZugferdInvoiceService
{
    public function generateArchivedDocument(Invoice $invoice): array
    {
        $invoice->loadMissing([
            'organization',
            'product.organization',
            'contact',
            'usageRecords.newsItem',
        ]);

        $invoice->syncTotalsFromUsageRecordsIfEditable();
        $invoice->refresh();

        $usageRecords = $invoice->usageRecords
            ->sortBy([
                ['used_at', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        $this->validateInvoice($invoice, $usageRecords);

        $documentNumber = $this->resolveDocumentNumber($invoice);
        $builder = $this->buildDocument($invoice, $usageRecords, $documentNumber);
        $xmlContent = $builder->getContent();
        $basePdfContent = $this->renderBasePdf($invoice, $usageRecords, $documentNumber);

        $pdfBuilder = ZugferdDocumentPdfBuilder::fromPdfString($builder, $basePdfContent);
        $pdfBuilder->setAdditionalCreatorTool(config('app.name', 'EKN'));
        $pdfBuilder->generateDocument();
        $hybridPdfContent = $pdfBuilder->downloadString();

        return $this->archiveDocument($invoice, $documentNumber, $xmlContent, $hybridPdfContent);
    }

    protected function validateInvoice(Invoice $invoice, $usageRecords): void
    {
        if (! $invoice->organization || ! $invoice->product || ! $invoice->contact) {
            throw new RuntimeException('Für ZUGFeRD fehlen Kunden-, Rechnungs- oder Ansprechpartnerdaten.');
        }

        if ($usageRecords->isEmpty()) {
            throw new RuntimeException('Für ZUGFeRD sind keine Rechnungspositionen vorhanden.');
        }

        if (strtoupper((string) ($invoice->currency ?? 'EUR')) !== 'EUR') {
            throw new RuntimeException('Der ZUGFeRD-MVP unterstützt aktuell nur Rechnungen in EUR.');
        }

        if ($this->mapCountryCode($invoice->product->billing_country) !== ZugferdCountryCodes::GERMANY) {
            throw new RuntimeException('Der ZUGFeRD-MVP unterstützt aktuell nur inländische Rechnungsadressen in Deutschland.');
        }

        $sender = config('invoice.sender', []);
        $bank = config('invoice.bank', []);
        $requiredSenderFields = [
            'name' => 'Absendername',
            'street' => 'Absenderstraße',
            'postal_code' => 'Absender-PLZ',
            'city' => 'Absender-Ort',
            'email' => 'Absender-E-Mail',
            'tax_number' => 'Steuernummer',
        ];

        foreach ($requiredSenderFields as $key => $label) {
            if (trim((string) ($sender[$key] ?? '')) === '') {
                throw new RuntimeException("Für ZUGFeRD fehlt {$label} in den Rechnungseinstellungen.");
            }
        }

        if (trim((string) ($bank['iban'] ?? '')) === '') {
            throw new RuntimeException('Für ZUGFeRD fehlt die IBAN in den Rechnungseinstellungen.');
        }

        if (trim((string) ($invoice->product->billing_company ?: $invoice->organization->name ?: '')) === '') {
            throw new RuntimeException('Für ZUGFeRD fehlt der Firmenname des Rechnungsempfängers.');
        }

        if (trim((string) ($invoice->product->billing_street ?? '')) === '') {
            throw new RuntimeException('Für ZUGFeRD fehlt die Straße des Rechnungsempfängers.');
        }

        if (trim((string) ($invoice->product->billing_postal_code ?? '')) === '' || trim((string) ($invoice->product->billing_city ?? '')) === '') {
            throw new RuntimeException('Für ZUGFeRD fehlen PLZ oder Ort des Rechnungsempfängers.');
        }

        foreach ($usageRecords as $record) {
            $hasImages = (int) $record->images_count > 0;
            $hasVideo = (float) $record->video_minutes > 0;
            $hasRadio = (float) $record->radio_minutes > 0;

            if (! $hasImages && ! $hasVideo && ! $hasRadio) {
                throw new RuntimeException('Eine Rechnungsposition hat weder Bildmenge noch Sendeminuten und ist für den ZUGFeRD-MVP nicht zulässig.');
            }

            if ($hasImages && ($hasVideo || $hasRadio)) {
                throw new RuntimeException('Eine Rechnungsposition kombiniert Bilder und Sendeminuten. Der ZUGFeRD-MVP unterstützt nur eindeutige Standardpositionen.');
            }
        }
    }

    protected function buildDocument(Invoice $invoice, $usageRecords, string $documentNumber): ZugferdDocumentBuilder
    {
        $sender = config('invoice.sender', []);
        $bank = config('invoice.bank', []);
        $payment = config('invoice.payment', []);
        $vatRate = (float) ($payment['vat_rate'] ?? 7);
        $voucherDate = $invoice->voucher_date instanceof CarbonInterface
            ? $invoice->voucher_date->copy()
            : Carbon::parse((string) ($invoice->voucher_date ?: now()->toDateString()));
        $dueDate = $voucherDate->copy()->addDays((int) ($payment['days'] ?? 7));
        $buyerName = trim((string) ($invoice->product->billing_company ?: $invoice->organization->name ?: ''));
        $billingName = trim((string) ($invoice->product->billing_name ?: ''));
        $contactName = trim((string) ($invoice->contact?->name ?: ''));
        $invoice->product->loadMissing('organization');
        $buyerId = $invoice->product->resolvedBuyerReference();
        $serviceDateLabel = $this->buildServiceDateLabel($usageRecords);

        $builder = ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_EN16931);
        $builder->setDocumentInformation(
            $documentNumber,
            ZugferdInvoiceType::INVOICE,
            $voucherDate,
            ZugferdCurrencyCodes::EURO
        );
        $builder->setDocumentSeller(trim((string) $sender['name']));
        $builder->addDocumentSellerTaxNumber(trim((string) $sender['tax_number']));
        if (trim((string) ($sender['vat_id'] ?? '')) !== '') {
            $builder->addDocumentSellerVATRegistrationNumber(trim((string) $sender['vat_id']));
        }

        $builder->setDocumentSellerAddress(
            trim((string) $sender['street']),
            null,
            null,
            trim((string) $sender['postal_code']),
            trim((string) $sender['city']),
            $this->mapCountryCode($sender['country_code'] ?? 'DE')
        );
        $builder->setDocumentSellerContact(
            trim((string) $sender['name']),
            null,
            trim((string) ($sender['phone'] ?? '')) ?: null,
            trim((string) ($sender['fax'] ?? '')) ?: null,
            trim((string) ($sender['email'] ?? '')) ?: null
        );
        $builder->setDocumentSellerCommunication(
            ZugferdElectronicAddressScheme::UNECE3155_EM,
            trim((string) $sender['email'])
        );

        $builder->setDocumentBuyer($buyerName, $buyerId !== '' ? $buyerId : null);
        $builder->setDocumentBuyerAddress(
            trim((string) $invoice->product->billing_street),
            null,
            null,
            trim((string) $invoice->product->billing_postal_code),
            trim((string) $invoice->product->billing_city),
            $this->mapCountryCode($invoice->product->billing_country)
        );
        $builder->setDocumentBuyerContact(
            $contactName !== '' ? $contactName : ($billingName !== '' ? $billingName : null),
            null,
            trim((string) ($invoice->contact?->phone ?? '')) ?: null,
            null,
            trim((string) ($invoice->contact?->email ?? '')) ?: null
        );

        if (trim((string) ($invoice->contact?->email ?? '')) !== '') {
            $builder->setDocumentBuyerCommunication(
                ZugferdElectronicAddressScheme::UNECE3155_EM,
                trim((string) $invoice->contact->email)
            );
        }

        if ($buyerId !== '') {
            $builder->setDocumentBuyerReference($buyerId);
        }

        $periodStart = $usageRecords->first()?->used_at;
        $periodEnd = $usageRecords->last()?->used_at;
        if ($periodStart instanceof CarbonInterface && $periodEnd instanceof CarbonInterface) {
            $builder->setDocumentBillingPeriod($periodStart, $periodEnd, $serviceDateLabel);
            $builder->setDocumentSupplyChainEvent($periodEnd);
        }

        $builder->addDocumentPaymentMeanToCreditTransfer(
            $this->normalizeIban((string) ($bank['iban'] ?? '')),
            trim((string) ($bank['name'] ?? '')) ?: null,
            null,
            trim((string) ($bank['bic'] ?? '')) ?: null,
            $documentNumber
        );
        $builder->addDocumentPaymentTerm(
            'Zahlbar in '.(int) ($payment['days'] ?? 7).' Tagen netto ohne Abzug',
            $dueDate
        );

        $position = 1;
        foreach ($usageRecords as $record) {
            foreach ($this->buildZugferdLineItemsForRecord($record, $invoice) as $lineItem) {
                $builder->addNewPosition((string) $position);
                $builder->setDocumentPositionProductDetails(
                    $lineItem['title'],
                    $lineItem['description']
                );
                $builder->setDocumentPositionNetPrice($lineItem['unit_price']);
                $builder->setDocumentPositionQuantity($lineItem['quantity'], $lineItem['unit_code']);
                $builder->addDocumentPositionTax(
                    ZugferdVatCategoryCodes::STAN_RATE,
                    ZugferdVatTypeCodes::VALUE_ADDED_TAX,
                    $vatRate
                );
                $builder->setDocumentPositionLineSummation($lineItem['line_total']);
                $position++;
            }
        }

        $builder->addDocumentTax(
            ZugferdVatCategoryCodes::STAN_RATE,
            ZugferdVatTypeCodes::VALUE_ADDED_TAX,
            round((float) $invoice->total_net, 2),
            round((float) $invoice->total_vat, 2),
            $vatRate
        );
        $builder->setDocumentSummation(
            round((float) $invoice->total_gross, 2),
            round((float) $invoice->total_gross, 2),
            round((float) $invoice->total_net, 2),
            0.0,
            0.0,
            round((float) $invoice->total_net, 2),
            round((float) $invoice->total_vat, 2)
        );

        return $builder;
    }

    protected function renderBasePdf(Invoice $invoice, $usageRecords, string $documentNumber): string
    {
        $html = view('admin.backoffice.billing.zugferd', $this->buildViewData($invoice, $usageRecords, $documentNumber))->render();
        $tempDir = storage_path('app/tmp/mpdf');

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 16,
            'margin_right' => 16,
            'margin_top' => 24,
            'margin_bottom' => 30,
            'tempDir' => $tempDir,
            'default_font' => 'dejavusans',
        ]);

        $mpdf->SetTitle('ZUGFeRD-Rechnung '.$documentNumber);
        $mpdf->SetAuthor((string) config('invoice.sender.name', config('app.name', 'EKN')));
        $mpdf->WriteHTML($html);

        return $mpdf->OutputBinaryData();
    }

    protected function buildViewData(Invoice $invoice, $usageRecords, string $documentNumber): array
    {
        $sender = config('invoice.sender', []);
        $bank = config('invoice.bank', []);
        $payment = config('invoice.payment', []);
        $organization = $invoice->organization;
        $product = $invoice->product;
        $contact = $invoice->contact;
        $headerReferenceLabel = trim((string) ($organization?->external_reference_label ?? ''));
        $headerReferenceLabel = $headerReferenceLabel !== '' ? $headerReferenceLabel : 'PV-Nr.';
        $headerReferenceCode = trim((string) ($usageRecords->first()?->reference_code ?? ''));
        if ($headerReferenceCode === '') {
            $headerReferenceCode = trim((string) ($organization?->external_author_id ?? ''));
        }

        $lineItems = [];
        $position = 1;
        foreach ($usageRecords as $record) {
            foreach ($this->buildZugferdLineItemsForRecord($record, $invoice) as $lineItem) {
                $lineItems[] = [
                    'position' => $position,
                    'title' => $lineItem['title'],
                    'description' => $lineItem['description'],
                    'quantity' => $lineItem['quantity'],
                    'unit_label' => $lineItem['unit_code'] === ZugferdUnitCodes::REC20_PIECE ? 'Stück' : 'Minuten',
                    'unit_price' => $lineItem['unit_price'],
                    'line_total' => $lineItem['line_total'],
                ];
                $position++;
            }
        }

        return [
            'invoice' => $invoice,
            'organization' => $organization,
            'product' => $product,
            'contact' => $contact,
            'usageRecords' => $usageRecords,
            'sender' => $sender,
            'bank' => $bank,
            'payment' => $payment,
            'documentNumber' => $documentNumber,
            'lineItems' => $lineItems,
            'headerReferenceLabel' => $headerReferenceLabel,
            'headerReferenceCode' => $headerReferenceCode,
            'serviceDateLabel' => $this->buildServiceDateLabel($usageRecords),
            'numberSource' => $invoice->voucher_number ? 'lexware' : 'ekn',
        ];
    }

    protected function archiveDocument(Invoice $invoice, string $documentNumber, string $xmlContent, string $hybridPdfContent): array
    {
        $disk = (string) config('invoice.einvoice.archive_disk', 'local');
        $directory = trim((string) config('invoice.einvoice.archive_directory', 'invoices/zugferd'), '/');
        $timestamp = now()->format('YmdHis');
        $baseFilename = Str::slug($documentNumber, '-') ?: 'rechnung-'.$invoice->id;
        $relativeBasePath = $directory.'/'.$invoice->id.'/'.$timestamp.'-'.$baseFilename;
        $pdfPath = $relativeBasePath.'.pdf';
        $xmlPath = $relativeBasePath.'.xml';

        Storage::disk($disk)->put($pdfPath, $hybridPdfContent);
        Storage::disk($disk)->put($xmlPath, $xmlContent);

        $meta = (array) ($invoice->meta ?? []);
        $meta['zugferd'] = [
            'profile' => 'EN16931',
            'number_source' => $invoice->voucher_number ? 'lexware' : 'ekn',
            'document_number' => $documentNumber,
            'pdf_disk' => $disk,
            'pdf_path' => $pdfPath,
            'xml_disk' => $disk,
            'xml_path' => $xmlPath,
            'generated_at' => now()->toIso8601String(),
        ];

        $invoice->forceFill(['meta' => $meta])->save();

        return [
            'filename' => $baseFilename.'-zugferd.pdf',
            'pdf_content' => $hybridPdfContent,
            'pdf_path' => $pdfPath,
            'xml_path' => $xmlPath,
            'document_number' => $documentNumber,
        ];
    }

    protected function resolveDocumentNumber(Invoice $invoice): string
    {
        $voucherNumber = trim((string) ($invoice->voucher_number ?? ''));
        if ($voucherNumber !== '') {
            return $voucherNumber;
        }

        $prefix = trim((string) config('invoice.einvoice.document_number_prefix', 'EKN-'));
        $voucherDate = $invoice->voucher_date instanceof CarbonInterface
            ? $invoice->voucher_date->copy()
            : Carbon::parse((string) ($invoice->voucher_date ?: now()->toDateString()));

        return $prefix.$voucherDate->format('Ym').str_pad((string) $invoice->id, 4, '0', STR_PAD_LEFT);
    }

    protected function buildServiceDateLabel($usageRecords): string
    {
        $start = $usageRecords->first()?->used_at;
        $end = $usageRecords->last()?->used_at;

        if (! $start instanceof CarbonInterface || ! $end instanceof CarbonInterface) {
            return 'Leistungsdatum laut Rechnungspositionen';
        }

        if ($start->isSameDay($end)) {
            return 'Leistungsdatum '.$start->format('d.m.Y');
        }

        return 'Leistungszeitraum '.$start->format('d.m.Y').' bis '.$end->format('d.m.Y');
    }

    protected function extractQuantityPriceAndUnit(UsageRecord $record): array
    {
        if ((int) $record->images_count > 0) {
            return [
                (float) (int) $record->images_count,
                ZugferdUnitCodes::REC20_PIECE,
                round((float) $record->price_per_image, 4),
            ];
        }

        if ((float) $record->video_minutes > 0) {
            return [
                round((float) $record->video_minutes, 2),
                ZugferdUnitCodes::REC20_MINUTE_UNIT_OF_TIME,
                round((float) $record->price_per_minute, 4),
            ];
        }

        return [
            round((float) $record->radio_minutes, 2),
            ZugferdUnitCodes::REC20_MINUTE_UNIT_OF_TIME,
            round((float) $record->price_per_minute, 4),
        ];
    }

    protected function buildZugferdLineItemsForRecord(UsageRecord $record, Invoice $invoice): array
    {
        if (WdrNewsroomImageTierLines::appliesTo($record, $invoice)) {
            $tier = WdrNewsroomImageTierLines::make();
            $baseDescription = $this->buildLineItemDescription($record);
            $items = [];
            foreach ($tier->linesForImageCount((int) $record->images_count) as $line) {
                $items[] = [
                    'title' => $line['title'],
                    'description' => trim($baseDescription."\n".$line['tier_label']),
                    'quantity' => (float) $line['quantity'],
                    'unit_code' => ZugferdUnitCodes::REC20_PIECE,
                    'unit_price' => round((float) $line['unit_price'], 2),
                    'line_total' => (float) $line['line_total'],
                ];
            }

            if ($items !== []) {
                return $items;
            }
        }

        [$quantity, $unitCode, $unitNetAmount] = $this->extractQuantityPriceAndUnit($record);

        return [[
            'title' => $this->buildLineItemTitle($record, $invoice),
            'description' => $this->buildLineItemDescription($record),
            'quantity' => (float) $quantity,
            'unit_code' => $unitCode,
            'unit_price' => (float) $unitNetAmount,
            'line_total' => round((float) $record->total_amount, 2),
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

    protected function mapCountryCode(?string $country): string
    {
        $value = strtoupper(trim((string) ($country ?? '')));

        return match ($value) {
            '', 'DEUTSCHLAND', 'GERMANY', 'DE' => ZugferdCountryCodes::GERMANY,
            default => strlen($value) === 2 ? $value : ZugferdCountryCodes::GERMANY,
        };
    }

    protected function normalizeIban(string $iban): string
    {
        return preg_replace('/\s+/', '', trim($iban)) ?: '';
    }
}
