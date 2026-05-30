<?php

namespace Tests\Feature\Admin;

use App\Mail\InvoiceMail;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\NewsItem;
use App\Models\Organization;
use App\Models\Product;
use App\Models\UsageRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Tests\TestCase;

class BillingSendInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Mail::fake();

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
        Config::set('invoice_mail', [
            'from_address' => 'rechnung@erftkreis-news.de',
            'from_name' => 'Erftkreis News Rechnung',
            'header_stream' => 'invoice',
            'header_source' => 'laravel-billing',
        ]);
    }

    public function test_it_sends_a_finalized_invoice_by_mail_and_stores_dispatch_meta(): void
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'password',
        ]);

        $invoice = $this->createInvoice([
            'lexware_invoice_id' => 'lex-123',
            'voucher_number' => 'Re-2026030015',
            'status' => 'lexware_open',
        ]);

        $this->withoutMiddleware(PermissionMiddleware::class)
            ->actingAs($user)
            ->post(route('admin.backoffice.billing.send', $invoice))
            ->assertRedirect(route('admin.backoffice.billing.show', ['invoice' => $invoice->id]))
            ->assertSessionHas('status');

        Mail::assertSent(InvoiceMail::class, function (InvoiceMail $mail) use ($invoice) {
            $headers = $mail->headers();
            $envelope = $mail->envelope();

            return $mail->invoice->is($invoice->fresh())
                && $mail->hasTo('rechnung@example.test')
                && $envelope->from?->address === 'rechnung@erftkreis-news.de'
                && $headers->text['X-EKN-Mail-Stream'] === 'invoice'
                && $headers->text['X-EKN-Mail-Source'] === 'laravel-billing'
                && $headers->text['X-EKN-Invoice-Id'] === (string) $invoice->id
                && $headers->text['X-EKN-Invoice-Number'] === 'Re-2026030015';
        });

        $invoice->refresh();
        $dispatchMeta = (array) (($invoice->meta ?? [])['dispatch'] ?? []);

        $this->assertSame('rechnung@example.test', $dispatchMeta['sent_to'] ?? null);
        $this->assertSame($user->id, $dispatchMeta['sent_by_user_id'] ?? null);
        $this->assertSame('email', $dispatchMeta['channel'] ?? null);
        $this->assertSame('rechnung@erftkreis-news.de', $dispatchMeta['mail_from'] ?? null);
        $this->assertSame('invoice', $dispatchMeta['mail_header_stream'] ?? null);
        $this->assertSame('laravel-billing', $dispatchMeta['mail_header_source'] ?? null);
        $this->assertNotEmpty($dispatchMeta['sent_at'] ?? null);
        $this->assertNotEmpty($dispatchMeta['pdf_path'] ?? null);
        $this->assertNotEmpty($dispatchMeta['xml_path'] ?? null);

        Storage::disk('local')->assertExists($dispatchMeta['pdf_path']);
        Storage::disk('local')->assertExists($dispatchMeta['xml_path']);
    }

    public function test_it_rejects_sending_without_lexware_finalization(): void
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'password',
        ]);

        $invoice = $this->createInvoice();

        $this->withoutMiddleware(PermissionMiddleware::class)
            ->actingAs($user)
            ->post(route('admin.backoffice.billing.send', $invoice))
            ->assertRedirect(route('admin.backoffice.billing.show', ['invoice' => $invoice->id]))
            ->assertSessionHas('error');

        Mail::assertNothingSent();
        $this->assertArrayNotHasKey('dispatch', (array) ($invoice->fresh()->meta ?? []));
    }

    public function test_it_records_bank_payment_in_invoice_meta(): void
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'password',
        ]);

        $invoice = $this->createInvoice();

        $this->withoutMiddleware(PermissionMiddleware::class)
            ->actingAs($user)
            ->post(route('admin.backoffice.billing.payment', $invoice), [
                'payment_received_on' => '2026-04-09',
                'payment_amount_gross' => '1.234,56',
                'payment_bank_reference' => 'RE 2026 123',
                'payment_note' => 'Kontoauszug April',
            ])
            ->assertRedirect(route('admin.backoffice.billing.show', ['invoice' => $invoice->id]).'#zahlungseingang')
            ->assertSessionHas('status');

        $invoice->refresh();
        $payment = (array) (($invoice->meta ?? [])['payment'] ?? []);
        $this->assertSame('2026-04-09', $payment['received_on'] ?? null);
        $this->assertSame(1234.56, $payment['amount_gross'] ?? null);
        $this->assertSame('RE 2026 123', $payment['bank_reference'] ?? null);
        $this->assertSame('Kontoauszug April', $payment['note'] ?? null);
        $this->assertSame($user->id, $payment['recorded_by_user_id'] ?? null);
    }

    public function test_it_syncs_lexware_payment_snapshot_when_lexware_invoice_exists(): void
    {
        Config::set('lexware.base_url', 'https://api.lexware.io');
        Config::set('lexware.api_token', 'test-token');

        Http::fake([
            'https://api.lexware.io/v1/payments/*' => Http::response([
                'openAmount' => '0.00',
                'currency' => 'EUR',
                'paymentStatus' => 'balanced',
                'voucherStatus' => 'paid',
                'voucherType' => 'salesinvoice',
                'paymentItems' => [],
            ], 200),
        ]);

        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'password',
        ]);

        $invoice = $this->createInvoice([
            'lexware_invoice_id' => 'ebb48e10-e20a-11ee-9cde-7789c0d1fa1c',
            'voucher_number' => 'R-1',
            'status' => 'lexware_open',
        ]);

        $this->withoutMiddleware(PermissionMiddleware::class)
            ->actingAs($user)
            ->post(route('admin.backoffice.billing.payment', $invoice), [
                'payment_received_on' => '2026-04-09',
            ])
            ->assertRedirect(route('admin.backoffice.billing.show', ['invoice' => $invoice->id]).'#zahlungseingang');

        $invoice->refresh();
        $lw = (array) (($invoice->meta ?? [])['lexware_payment'] ?? []);
        $this->assertSame('paid', $lw['voucher_status'] ?? null);
        $this->assertSame('lexware_open', $invoice->status);
        $this->assertArrayNotHasKey('sync_error', $lw);
        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return str_starts_with($request->url(), 'https://api.lexware.io/v1/payments/')
                && $request->method() === 'GET';
        });
    }

    public function test_it_marks_invoice_as_voided_when_lexware_reports_voided(): void
    {
        Config::set('lexware.base_url', 'https://api.lexware.io');
        Config::set('lexware.api_token', 'test-token');

        Http::fake([
            'https://api.lexware.io/v1/payments/*' => Http::response([
                'openAmount' => '0.00',
                'currency' => 'EUR',
                'paymentStatus' => 'balanced',
                'voucherStatus' => 'voided',
                'voucherStatusReason' => 'Beleg wurde in Lexware storniert (Dublettenkorrektur).',
                'voucherType' => 'salesinvoice',
                'paymentItems' => [],
            ], 200),
        ]);

        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'password',
        ]);

        $invoice = $this->createInvoice([
            'lexware_invoice_id' => 'ebb48e10-e20a-11ee-9cde-7789c0d1fa1c',
            'voucher_number' => 'R-1',
            'status' => 'lexware_open',
        ]);

        $this->withoutMiddleware(PermissionMiddleware::class)
            ->actingAs($user)
            ->post(route('admin.backoffice.billing.lexware-payment-sync', $invoice))
            ->assertRedirect(route('admin.backoffice.billing.show', ['invoice' => $invoice->id]).'#zahlungseingang');

        $invoice->refresh();
        $lw = (array) (($invoice->meta ?? [])['lexware_payment'] ?? []);
        $this->assertSame('voided', $lw['voucher_status'] ?? null);
        $this->assertSame('Beleg wurde in Lexware storniert (Dublettenkorrektur).', $lw['void_reason'] ?? null);
        $this->assertSame('lexware_voided', $invoice->status);
    }

    protected function createInvoice(array $invoiceOverrides = []): Invoice
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
