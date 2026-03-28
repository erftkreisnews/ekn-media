<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\InvoiceMail;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\UsageRecord;
use App\Services\Billing\InvoiceDispatchStatusService;
use App\Services\Billing\ZugferdInvoiceService;
use App\Services\Lexware\LexwareInvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class BillingController extends Controller
{
    public function __construct(
        private readonly InvoiceDispatchStatusService $dispatchStatusService,
    ) {}

    /**
     * Übersicht: Offene Mengen pro Rechnungseinheit aus der Nachverfolgung (usage_records).
     */
    public function index(): View
    {
        $products = Product::query()
            ->with('organization')
            ->where('active', true)
            ->whereHas('usageRecords', fn ($q) => $q->whereNull('invoice_id'))
            ->withSum(['usageRecords as open_video_minutes' => function ($q) {
                $q->whereNull('invoice_id');
            }], 'video_minutes')
            ->withSum(['usageRecords as open_photos' => function ($q) {
                $q->whereNull('invoice_id');
            }], 'images_count')
            ->withSum(['usageRecords as open_net' => function ($q) {
                $q->whereNull('invoice_id');
            }], 'total_amount')
            ->orderBy('name')
            ->paginate(20);

        $draftInvoices = Invoice::query()
            ->with(['organization', 'product', 'contact'])
            ->withCount('usageRecords')
            ->whereIn('status', ['draft', 'ready_for_lexware', 'pending', 'lexware_open'])
            ->latest('id')
            ->limit(20)
            ->get();

        $draftInvoices->each(function (Invoice $invoice): void {
            $invoice->setAttribute('dispatch_status_data', $this->dispatchStatusService->evaluate($invoice));
        });

        return view('admin.backoffice.billing.index', [
            'products' => $products,
            'draftInvoices' => $draftInvoices,
        ]);
    }

    /**
     * Formular: Rechnung für eine Rechnungseinheit erzeugen – Auswahl Rechnungsempfänger (Kontakt).
     */
    public function create(Product $product): View|RedirectResponse
    {
        $product->load('organization');
        $customer = $product->organization;
        if (! $customer) {
            return redirect()
                ->route('admin.backoffice.billing.index')
                ->with('error', 'Zur gewählten Rechnungseinheit wurde kein Medienhaus gefunden.');
        }

        $openRecords = UsageRecord::where('organization_id', $customer->id)
            ->where('product_id', $product->id)
            ->whereNull('invoice_id')
            ->get();

        if ($openRecords->isEmpty()) {
            return redirect()
                ->route('admin.backoffice.billing.index')
                ->with('status', 'Für diese Rechnungseinheit gibt es keine offenen Nachverfolgungs-Einträge mehr.');
        }

        $billingContacts = $customer->contacts()
            ->where('use_for_invoice', true)
            ->where(function ($query) use ($product) {
                $query->whereNull('product_id')->orWhere('product_id', $product->id);
            })
            ->orderByRaw('CASE WHEN product_id = ? THEN 0 ELSE 1 END', [$product->id])
            ->orderBy('billing_department')
            ->orderBy('billing_type')
            ->orderBy('name')
            ->get();

        $suggestedContactId = null;
        $first = $openRecords->first();
        if ($first && $billingContacts->isNotEmpty()) {
            foreach ($billingContacts as $c) {
                if (($c->product_id === null || $c->product_id === $product->id) && $c->billing_type === $first->billing_type) {
                    $suggestedContactId = $c->id;
                    break;
                }
            }
        }
        if ($suggestedContactId === null && $billingContacts->isNotEmpty()) {
            $suggestedContactId = $billingContacts->first()->id;
        }

        $openVideo = $openRecords->sum('video_minutes');
        $openPhotos = $openRecords->sum('images_count');
        $openNet = $openRecords->sum('total_amount');

        return view('admin.backoffice.billing.create', [
            'customer' => $customer,
            'product' => $product,
            'billingContacts' => $billingContacts,
            'suggestedContactId' => $suggestedContactId,
            'openVideo' => $openVideo,
            'openPhotos' => $openPhotos,
            'openNet' => $openNet,
            'openRecordsCount' => $openRecords->count(),
        ]);
    }

    public function show(Invoice $invoice): View|RedirectResponse
    {
        $viewData = $this->getInvoicePreviewData($invoice);
        if ($viewData === null) {
            return redirect()
                ->route('admin.backoffice.billing.index')
                ->with('error', 'Die gewählte Rechnung ist unvollständig und kann nicht angezeigt werden.');
        }

        return view('admin.backoffice.billing.show', $viewData);
    }

    public function downloadPdf(Invoice $invoice): HttpResponse|RedirectResponse
    {
        $viewData = $this->getInvoicePreviewData($invoice);
        if ($viewData === null) {
            return redirect()
                ->route('admin.backoffice.billing.index')
                ->with('error', 'Die gewählte Rechnung ist unvollständig und kann nicht als PDF erzeugt werden.');
        }

        $pdf = Pdf::loadView('admin.backoffice.billing.pdf', $viewData)
            ->setPaper('a4')
            ->setOption('isPhpEnabled', true)
            ->setOption('isRemoteEnabled', false);

        $filename = 'rechnung-entwurf-'.($invoice->voucher_number ?: $invoice->id).'.pdf';

        return $pdf->download($filename);
    }

    public function downloadZugferd(Invoice $invoice, ZugferdInvoiceService $zugferdInvoiceService): HttpResponse|RedirectResponse
    {
        $dispatchStatus = $this->dispatchStatusService->evaluate($invoice);
        if (! $dispatchStatus['zugferd']['allowed']) {
            $message = $dispatchStatus['zugferd']['messages'][0] ?? $dispatchStatus['zugferd']['summary'];

            return redirect()
                ->route('admin.backoffice.billing.show', ['invoice' => $invoice->getKey()])
                ->with('error', $message);
        }

        try {
            $document = $zugferdInvoiceService->generateArchivedDocument($invoice);
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.backoffice.billing.show', $invoice)
                ->with('error', $e->getMessage() !== '' ? $e->getMessage() : 'Die ZUGFeRD-Erzeugung ist fehlgeschlagen.');
        }

        return response($document['pdf_content'], 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="'.$document['filename'].'"');
    }

    /**
     * Rechnung anlegen und offene Usage-Records des Kunden zuordnen.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'contact_id' => ['required', 'integer', 'exists:contacts,id'],
        ]);

        $product = Product::with('organization')->findOrFail($validated['product_id']);
        $org = $product->organization;
        if (! $org) {
            return redirect()
                ->route('admin.backoffice.billing.index')
                ->with('error', 'Zur gewählten Rechnungseinheit wurde kein Medienhaus gefunden.');
        }
        $contact = Contact::findOrFail($validated['contact_id']);
        if ($contact->organization_id !== $org->id) {
            return redirect()
                ->route('admin.backoffice.billing.index')
                ->with('error', 'Der gewählte Kontakt gehört nicht zu diesem Medienhaus.');
        }
        if ($contact->product_id !== null && (int) $contact->product_id !== (int) $product->id) {
            return redirect()
                ->route('admin.backoffice.billing.index')
                ->with('error', 'Der gewählte Kontakt gehört zu einer anderen Rechnungseinheit.');
        }

        $openRecords = UsageRecord::where('organization_id', $org->id)
            ->where('product_id', $product->id)
            ->whereNull('invoice_id')
            ->get();
        if ($openRecords->isEmpty()) {
            return redirect()
                ->route('admin.backoffice.billing.index')
                ->with('error', 'Für diese Rechnungseinheit gibt es keine offenen Einträge mehr.');
        }

        $totalNet = $openRecords->sum('total_amount');
        $vatRate = (float) config('invoice.payment.vat_rate', 7);
        $totalVat = round((float) $totalNet * $vatRate / 100, 2);
        $totalGross = round((float) $totalNet + $totalVat, 2);

        $invoice = Invoice::create([
            'organization_id' => $org->id,
            'product_id' => $product->id,
            'contact_id' => $contact->id,
            'status' => 'draft',
            'total_net' => $totalNet,
            'total_vat' => $totalVat,
            'total_gross' => $totalGross,
            'currency' => 'EUR',
            'voucher_date' => now()->toDateString(),
        ]);

        UsageRecord::where('organization_id', $org->id)
            ->where('product_id', $product->id)
            ->whereNull('invoice_id')
            ->update(['invoice_id' => $invoice->id]);

        return redirect()
            ->route('admin.backoffice.billing.show', $invoice)
            ->with('status', 'Rechnungsentwurf angelegt (Nr. '.$invoice->id.') – lokal in EKN, noch ohne Lexware-Rechnungsnummer. '.$openRecords->count().' Nachverfolgungs-Einträge für '.$product->name.' zugeordnet. Rechnungsempfänger: '.$contact->email.'.');
    }

    public function markReadyForLexware(Invoice $invoice, LexwareInvoiceService $lexwareInvoiceService): RedirectResponse
    {
        if (! in_array($invoice->status, ['draft', 'ready_for_lexware', 'pending'], true)) {
            return redirect()
                ->route('admin.backoffice.billing.index')
                ->with('error', 'Nur lokale Rechnungsentwürfe können an Lexware übergeben werden.');
        }

        if ($invoice->lexware_invoice_id || $invoice->voucher_number) {
            return redirect()
                ->route('admin.backoffice.billing.show', $invoice)
                ->with('status', 'Diese Rechnung wurde bereits an Lexware übergeben.');
        }

        try {
            $invoice = $lexwareInvoiceService->createFinalInvoice($invoice);
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.backoffice.billing.show', $invoice)
                ->with('error', $this->toUserFacingLexwareMessage($e));
        }

        return redirect()
            ->route('admin.backoffice.billing.show', $invoice)
            ->with('status', 'Lexware-Rechnung '.$invoice->voucher_number.' wurde erfolgreich erzeugt. Der Beleg ist damit in EKN final nummeriert und versandbereit, sofern keine Prüfhinweise mehr offen sind.');
    }

    public function sendInvoice(Invoice $invoice, ZugferdInvoiceService $zugferdInvoiceService): RedirectResponse
    {
        $dispatchStatus = $this->dispatchStatusService->evaluate($invoice);
        if (! $dispatchStatus['dispatch']['can_send']) {
            $message = $dispatchStatus['dispatch']['messages'][0] ?? 'Diese Rechnung ist aktuell nicht versandbereit.';

            return redirect()
                ->route('admin.backoffice.billing.show', ['invoice' => $invoice->getKey()])
                ->with('error', $message);
        }

        try {
            $document = $zugferdInvoiceService->generateArchivedDocument($invoice);

            Mail::to($invoice->contact->email)->send(new InvoiceMail(
                $invoice->fresh(['organization', 'product', 'contact']),
                $document['pdf_content'],
                $document['filename']
            ));

            $invoice->refresh();
            $meta = (array) ($invoice->meta ?? []);
            $dispatchMeta = (array) ($meta['dispatch'] ?? []);
            $dispatchMeta['sent_at'] = now()->toIso8601String();
            $dispatchMeta['sent_to'] = $invoice->contact->email;
            $dispatchMeta['sent_by_user_id'] = auth()->id();
            $dispatchMeta['mail_from'] = (string) config('invoice.mail.from_address', 'rechnung@erftkreis-news.de');
            $dispatchMeta['mail_header_stream'] = (string) config('invoice.mail.header_stream', 'invoice');
            $dispatchMeta['mail_header_source'] = (string) config('invoice.mail.header_source', 'laravel-billing');
            $dispatchMeta['mail_subject'] = 'Rechnung '.($invoice->voucher_number ?: $document['document_number']);
            $dispatchMeta['document_number'] = $document['document_number'];
            $dispatchMeta['pdf_path'] = $document['pdf_path'];
            $dispatchMeta['xml_path'] = $document['xml_path'];
            $dispatchMeta['channel'] = 'email';
            $meta['dispatch'] = $dispatchMeta;

            $invoice->forceFill(['meta' => $meta])->save();
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.backoffice.billing.show', ['invoice' => $invoice->getKey()])
                ->with('error', $e->getMessage() !== '' ? $e->getMessage() : 'Der Rechnungsversand ist fehlgeschlagen.');
        }

        return redirect()
            ->route('admin.backoffice.billing.show', ['invoice' => $invoice->getKey()])
            ->with('status', 'Die Rechnung '.$invoice->voucher_number.' wurde an '.$invoice->contact->email.' versendet.');
    }

    public function releaseDraft(Invoice $invoice): RedirectResponse
    {
        if (! in_array($invoice->status, ['draft', 'ready_for_lexware', 'pending'], true)) {
            return redirect()
                ->route('admin.backoffice.billing.index')
                ->with('error', 'Nur lokale Rechnungsentwürfe können aufgelöst werden.');
        }

        $usageCount = $invoice->usageRecords()->count();

        $invoice->usageRecords()->update(['invoice_id' => null]);
        $invoice->delete();

        return redirect()
            ->route('admin.backoffice.billing.index')
            ->with('status', 'Rechnungsentwurf wurde aufgelöst. '.$usageCount.' Nachverfolgungs-Einträge sind wieder offen zur Abrechnung.');
    }

    public function downloadLexwareFile(Invoice $invoice, LexwareInvoiceService $lexwareInvoiceService): HttpResponse|RedirectResponse
    {
        try {
            $fileResponse = $lexwareInvoiceService->downloadInvoiceFile($invoice);
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.backoffice.billing.show', $invoice)
                ->with('error', $this->toUserFacingLexwareMessage($e));
        }

        $contentType = $fileResponse->header('Content-Type', 'application/octet-stream');
        $contentDisposition = $fileResponse->header('Content-Disposition', 'attachment; filename="lexware-rechnung-'.($invoice->voucher_number ?: $invoice->id).'"');

        return response($fileResponse->body(), $fileResponse->status())
            ->header('Content-Type', $contentType)
            ->header('Content-Disposition', $contentDisposition);
    }

    private function buildPreviewSections(Invoice $invoice): array
    {
        $records = $invoice->usageRecords->loadMissing('newsItem');
        $product = $invoice->product;
        $senderName = config('invoice.sender.name', 'Alexander Franz');

        return $records->map(function (UsageRecord $record) use ($product, $senderName, $invoice) {
            $newsItem = $record->newsItem;
            $isImage = (int) $record->images_count > 0;
            $isVideo = (float) $record->video_minutes > 0;

            $fields = [
                ['label' => 'Anlass', 'value' => $newsItem?->title ?: 'noch nicht gepflegt'],
                ['label' => 'Datum', 'value' => $record->used_at?->format('d.m.Y') ?: 'noch nicht gepflegt'],
                ['label' => 'Credits', 'value' => $newsItem?->author_credit ?: $senderName],
            ];

            if ($isImage) {
                array_splice($fields, 1, 0, [
                    ['label' => 'Format', 'value' => $record->usage_format ?: 'noch nicht gepflegt'],
                    ['label' => 'Link', 'value' => $record->article_url ?: 'noch nicht gepflegt'],
                ]);
            }

            if ($isVideo) {
                array_splice($fields, 1, 0, [
                    ['label' => 'Format', 'value' => $record->usage_format ?: 'noch nicht gepflegt'],
                    ['label' => 'Nutzungsrecht', 'value' => $record->usage_rights ?: 'noch nicht gepflegt'],
                    ['label' => 'Beauftragt', 'value' => $product?->billing_name ?: $product?->name ?: 'noch nicht gepflegt'],
                ]);

                if ($record->article_url) {
                    array_splice($fields, 2, 0, [
                        ['label' => 'Link', 'value' => $record->article_url],
                    ]);
                }
            }

            return [
                'title' => $isImage ? 'Materialankauf' : 'Videomaterial',
                'fields' => $fields,
                'line_item_title' => $this->buildLineItemTitle($record, $invoice),
                'line_item_subtitle' => $record->line_item_note,
            ];
        })->all();
    }

    private function buildLineItemTitle(UsageRecord $record, Invoice $invoice): string
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

    private function resolveReferenceCode(UsageRecord $record, Invoice $invoice): ?string
    {
        $referenceCode = trim((string) ($record->reference_code ?? ''));
        if ($referenceCode !== '') {
            return $referenceCode;
        }

        $organizationAuthorId = trim((string) ($invoice->organization?->external_author_id ?? ''));
        if ($organizationAuthorId !== '') {
            return $organizationAuthorId;
        }

        return null;
    }

    private function resolveReferenceLabel(Invoice $invoice): string
    {
        $label = trim((string) ($invoice->organization?->external_reference_label ?? ''));

        return $label !== '' ? $label : 'PVNr.';
    }

    private function getInvoicePreviewData(Invoice $invoice): ?array
    {
        $invoice->load([
            'organization',
            'product',
            'contact',
            'usageRecords.newsItem',
        ]);

        if (! $invoice->organization || ! $invoice->product) {
            return null;
        }

        return [
            'invoice' => $invoice,
            'usageRecords' => $invoice->usageRecords->sortBy([
                ['used_at', 'asc'],
                ['id', 'asc'],
            ])->values(),
            'previewSections' => $this->buildPreviewSections($invoice),
            'dispatchStatus' => $this->dispatchStatusService->evaluate($invoice),
        ];
    }

    private function toUserFacingLexwareMessage(\Throwable $e): string
    {
        $message = trim($e->getMessage());

        if ($message === '') {
            return 'Die Lexware-Übergabe ist fehlgeschlagen.';
        }

        if (str_contains(mb_strtolower($message), 'rate limit')) {
            return 'Lexware ist gerade ausgelastet. Bitte in wenigen Sekunden erneut versuchen.';
        }

        return $message;
    }
}
