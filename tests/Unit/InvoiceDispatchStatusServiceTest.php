<?php

namespace Tests\Unit;

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\NewsItem;
use App\Models\Organization;
use App\Models\Product;
use App\Models\UsageRecord;
use App\Services\Billing\InvoiceDispatchStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class InvoiceDispatchStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('invoice.sender', [
            'name' => 'Alexander Franz',
            'street' => 'Konrad-Adenauer-Str. 22',
            'postal_code' => '50389',
            'city' => 'Wesseling',
            'country_code' => 'DE',
            'phone' => '+49 2236-4809480',
            'fax' => '+49 2236-4809489',
            'email' => 'afranz@erftkreis-news.de',
            'website' => 'www.erftkreis-news.de',
            'tax_number' => '22450803906',
            'vat_id' => null,
        ]);
        Config::set('invoice.bank', [
            'name' => 'Finom',
            'iban' => 'DE53100180000851398747',
            'bic' => 'FNOMDEB2XXX',
        ]);
    }

    public function test_it_marks_complete_domestic_invoices_as_ready(): void
    {
        $invoice = $this->createInvoice();

        $status = app(InvoiceDispatchStatusService::class)->evaluate($invoice->fresh());

        $this->assertSame('ready', $status['status']);
        $this->assertSame('Fachlich bereit', $status['label']);
        $this->assertSame('pending_lexware', $status['dispatch']['status']);
        $this->assertSame('Warte auf Lexware-Nummer', $status['dispatch']['label']);
        $this->assertTrue($status['can_finalize_lexware']);
        $this->assertFalse($status['zugferd']['allowed']);
    }

    public function test_it_marks_missing_buyer_reference_as_warning(): void
    {
        $invoice = $this->createInvoice([
            'buyer_reference' => '',
        ]);

        $status = app(InvoiceDispatchStatusService::class)->evaluate($invoice->fresh());

        $this->assertSame('warning', $status['status']);
        $this->assertContains('Die Kundennummer bzw. Buyer Reference ist noch nicht gepflegt.', $status['warnings']);
        $this->assertTrue($status['can_finalize_lexware']);
        $this->assertSame('pending_lexware', $status['dispatch']['status']);
        $this->assertFalse($status['zugferd']['allowed']);
    }

    public function test_it_marks_foreign_invoices_as_manual_review(): void
    {
        $invoice = $this->createInvoice([
            'billing_country' => 'AT',
        ]);

        $status = app(InvoiceDispatchStatusService::class)->evaluate($invoice->fresh());

        $this->assertSame('review', $status['status']);
        $this->assertContains('Die Rechnungsadresse liegt im Ausland. Steuerlogik und Versandfall bitte manuell prüfen.', $status['reviews']);
        $this->assertFalse($status['zugferd']['allowed']);
    }

    public function test_it_marks_missing_address_fields_as_blocked(): void
    {
        $invoice = $this->createInvoice([
            'billing_street' => '',
        ]);

        $status = app(InvoiceDispatchStatusService::class)->evaluate($invoice->fresh());

        $this->assertSame('blocked', $status['status']);
        $this->assertContains('Für den Rechnungsempfänger fehlt die Straße.', $status['blockers']);
        $this->assertFalse($status['can_finalize_lexware']);
        $this->assertFalse($status['zugferd']['allowed']);
    }

    public function test_it_marks_finalized_lexware_invoices_as_dispatch_ready(): void
    {
        $invoice = $this->createInvoice([], [
            'lexware_invoice_id' => 'lex-123',
            'voucher_number' => 'Re-2026030015',
            'status' => 'lexware_open',
        ]);

        $status = app(InvoiceDispatchStatusService::class)->evaluate($invoice->fresh());

        $this->assertFalse($status['can_finalize_lexware']);
        $this->assertTrue($status['has_lexware_finalization']);
        $this->assertSame('ready', $status['dispatch']['status']);
        $this->assertTrue($status['dispatch']['can_send']);
        $this->assertTrue($status['zugferd']['allowed']);
    }

    protected function createInvoice(array $productOverrides = [], array $invoiceOverrides = []): Invoice
    {
        $organization = Organization::create([
            'name' => 'Westdeutscher Rundfunk',
        ]);

        $product = Product::create(array_merge([
            'organization_id' => $organization->id,
            'name' => 'Studio Köln',
            'buyer_reference' => '10005',
            'billing_name' => 'Studio Köln',
            'billing_company' => 'Westdeutscher Rundfunk',
            'billing_street' => 'Appellhofplatz 1',
            'billing_postal_code' => '50667',
            'billing_city' => 'Köln',
            'billing_country' => 'DE',
        ], $productOverrides));

        $contact = Contact::create([
            'organization_id' => $organization->id,
            'product_id' => $product->id,
            'name' => 'Max Mustermann',
            'email' => 'rechnung@example.test',
            'phone' => '+49 221 123456',
            'use_for_invoice' => true,
            'billing_department' => 'studio_koeln',
            'billing_type' => 'honorar',
        ]);

        $newsItem = NewsItem::create([
            'title' => 'Beispielbeitrag',
            'slug' => 'beispielbeitrag-'.uniqid(),
            'status' => 'draft',
            'author_credit' => 'Alexander Franz',
        ]);

        $invoice = Invoice::create(array_merge([
            'organization_id' => $organization->id,
            'product_id' => $product->id,
            'contact_id' => $contact->id,
            'status' => 'draft',
            'lexware_invoice_id' => null,
            'voucher_number' => null,
            'total_net' => 150.00,
            'total_vat' => 10.50,
            'total_gross' => 160.50,
            'currency' => 'EUR',
            'voucher_date' => '2026-03-06',
        ], $invoiceOverrides));

        UsageRecord::create([
            'news_item_id' => $newsItem->id,
            'organization_id' => $organization->id,
            'billing_department' => 'studio_koeln',
            'billing_type' => 'honorar',
            'product_id' => $product->id,
            'used_at' => '2026-03-05',
            'images_count' => 0,
            'video_minutes' => 3,
            'price_per_image' => 0,
            'price_per_minute' => 50,
            'total_amount' => 150,
            'article_url' => 'https://example.test/beispielbeitrag',
            'usage_format' => 'TV',
            'usage_rights' => 'Einmalig',
            'reference_code' => 'PV-123',
            'line_item_note' => 'Videomaterial',
            'confirmed' => true,
            'invoice_id' => $invoice->id,
        ]);

        return $invoice;
    }
}
