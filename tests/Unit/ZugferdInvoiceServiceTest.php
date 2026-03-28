<?php

namespace Tests\Unit;

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\NewsItem;
use App\Models\Organization;
use App\Models\Product;
use App\Models\UsageRecord;
use App\Services\Billing\ZugferdInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ZugferdInvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

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
        Config::set('invoice.payment', [
            'days' => 7,
            'vat_rate' => 7,
        ]);
        Config::set('invoice.einvoice', [
            'archive_disk' => 'local',
            'archive_directory' => 'invoices/zugferd-test',
            'document_number_prefix' => 'EKN-',
        ]);
    }

    public function test_it_generates_and_archives_a_zugferd_document_for_standard_domestic_invoice(): void
    {
        [$invoice] = $this->createStandardInvoice();

        $result = app(ZugferdInvoiceService::class)->generateArchivedDocument($invoice);

        Storage::disk('local')->assertExists($result['pdf_path']);
        Storage::disk('local')->assertExists($result['xml_path']);

        $invoice->refresh();
        $zugferdMeta = (array) (($invoice->meta ?? [])['zugferd'] ?? []);

        $this->assertSame('EN16931', $zugferdMeta['profile'] ?? null);
        $this->assertSame($result['document_number'], $zugferdMeta['document_number'] ?? null);
        $this->assertStringContainsString('CrossIndustryInvoice', Storage::disk('local')->get($result['xml_path']));
        $this->assertStringContainsString('Videomaterial Verkauf', Storage::disk('local')->get($result['xml_path']));
        $this->assertNotEmpty($result['pdf_content']);
    }

    public function test_it_rejects_non_domestic_buyer_addresses_in_the_mvp_scope(): void
    {
        [$invoice, $product] = $this->createStandardInvoice();
        $product->update(['billing_country' => 'AT']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Der ZUGFeRD-MVP unterstützt aktuell nur inländische Rechnungsadressen in Deutschland.');

        app(ZugferdInvoiceService::class)->generateArchivedDocument($invoice->fresh());
    }

    protected function createStandardInvoice(): array
    {
        $organization = Organization::create([
            'name' => 'Westdeutscher Rundfunk',
        ]);

        $product = Product::create([
            'organization_id' => $organization->id,
            'name' => 'Studio Köln',
            'buyer_reference' => '10005',
            'billing_name' => 'Studio Köln',
            'billing_company' => 'Westdeutscher Rundfunk',
            'billing_street' => 'Appellhofplatz 1',
            'billing_postal_code' => '50667',
            'billing_city' => 'Köln',
            'billing_country' => 'DE',
        ]);

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
            'slug' => 'beispielbeitrag',
            'status' => 'draft',
            'author_credit' => 'Alexander Franz',
        ]);

        $invoice = Invoice::create([
            'organization_id' => $organization->id,
            'product_id' => $product->id,
            'contact_id' => $contact->id,
            'status' => 'draft',
            'voucher_number' => 'Re-2026030015',
            'total_net' => 150.00,
            'total_vat' => 10.50,
            'total_gross' => 160.50,
            'currency' => 'EUR',
            'voucher_date' => '2026-03-06',
        ]);

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

        return [$invoice, $product, $contact, $organization];
    }
}
