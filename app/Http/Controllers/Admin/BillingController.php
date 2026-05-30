<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\InvoiceMail;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\InvoiceIncomingDocument;
use App\Models\Organization;
use App\Models\Product;
use App\Models\UsageRecord;
use App\Services\Billing\InvoiceDispatchStatusService;
use App\Services\Billing\WdrNewsroomImageTierLines;
use App\Services\Billing\ZugferdInvoiceService;
use App\Services\Lexware\LexwareInvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
            ->withSum(['usageRecords as open_radio_minutes' => function ($q) {
                $q->whereNull('invoice_id');
            }], 'radio_minutes')
            ->withSum(['usageRecords as open_lizenz_radio_minutes' => function ($q) {
                $q->whereNull('invoice_id')
                    ->where(function ($q2): void {
                        $q2->where('billing_type', 'lizenz')->orWhereNull('billing_type');
                    })
                    ->where('radio_minutes', '>', 0);
            }], 'radio_minutes')
            ->withSum(['usageRecords as open_honorar_radio_minutes' => function ($q) {
                $q->whereNull('invoice_id')
                    ->where('billing_type', 'honorar')
                    ->where('radio_minutes', '>', 0);
            }], 'radio_minutes')
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
            ->when(Schema::hasTable('invoice_incoming_documents'), function ($q): void {
                $q->withCount('incomingDocuments')
                    ->with(['incomingDocuments' => function ($q): void {
                        $q->orderByDesc('id')->limit(8);
                    }]);
            })
            ->whereIn('status', ['draft', 'ready_for_lexware', 'pending', 'lexware_open', 'lexware_voided'])
            ->latest('id')
            ->limit(20)
            ->get();

        $draftInvoices->each(function (Invoice $invoice): void {
            $this->syncInvoiceStatusFromLexwareSnapshot($invoice);
            $invoice->setAttribute('dispatch_status_data', $this->dispatchStatusService->evaluate($invoice));
        });

        return view('admin.backoffice.billing.index', [
            'products' => $products,
            'draftInvoices' => $draftInvoices,
            'hasIncomingDocumentsTable' => Schema::hasTable('invoice_incoming_documents'),
            'hasWdrBillingChecklistColumn' => Schema::hasColumn('invoices', 'wdr_billing_checklist'),
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

        $this->splitMixedOpenUsageRecords((int) $customer->id, (int) $product->id, $product);

        $openRecordsQuery = UsageRecord::where('organization_id', $customer->id)
            ->where('product_id', $product->id)
            ->whereNull('invoice_id')
            ->with('newsItem');
        $this->applyBillingWindowScope($openRecordsQuery, $product);
        $openRecords = $openRecordsQuery->get();

        if ($openRecords->isEmpty()) {
            return redirect()
                ->route('admin.backoffice.billing.index')
                ->with('status', 'Für diese Rechnungseinheit gibt es keine offenen Nachverfolgungs-Einträge mehr.');
        }

        // Fallback: Redaktions-Rechnungsadressen sofort als auswählbare Rechnungsempfänger verfügbar machen.
        $this->bootstrapBillingContactsFromProduct($product, (int) $customer->id);

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
        $openRadio = $openRecords->sum('radio_minutes');
        $openPhotos = $openRecords->sum('images_count');
        $openNet = $openRecords->sum('total_amount');

        $recordsByKind = $openRecords->groupBy(fn (UsageRecord $record) => $this->resolveUsageInvoiceKind($record));
        $bildRecords = $recordsByKind->get('lizenz_bild', collect());
        $videoRecords = $recordsByKind->get('lizenz_video', collect());
        $radioLizenzRecords = $recordsByKind->get('lizenz_radio', collect());
        $honorarRecords = $recordsByKind->get('honorar_audio', collect());

        $invoiceKindStats = [
            'lizenz_bild' => [
                'count' => $bildRecords->count(),
                'net' => (float) $bildRecords->sum('total_amount'),
                'images_sum' => (int) $bildRecords->sum('images_count'),
            ],
            'lizenz_video' => [
                'count' => $videoRecords->count(),
                'net' => (float) $videoRecords->sum('total_amount'),
                'video_minutes_sum' => (float) $videoRecords->sum('video_minutes'),
            ],
            'lizenz_radio' => [
                'count' => $radioLizenzRecords->count(),
                'net' => (float) $radioLizenzRecords->sum('total_amount'),
                'radio_minutes_sum' => (float) $radioLizenzRecords->sum('radio_minutes'),
            ],
            'honorar_audio' => [
                'count' => $honorarRecords->count(),
                'net' => (float) $honorarRecords->sum('total_amount'),
                'radio_minutes_sum' => (float) $honorarRecords->sum('radio_minutes'),
            ],
        ];
        $isWdrBillingProduct = $this->isWdrBillingProduct($product, $customer);
        $invoiceKindBreakdownByNews = [
            'lizenz_bild' => $this->buildUsageBreakdownByNews($bildRecords, 'lizenz_bild'),
            'lizenz_video' => $this->buildUsageBreakdownByNews($videoRecords, 'lizenz_video'),
            'lizenz_radio' => $this->buildUsageBreakdownByNews($radioLizenzRecords, 'lizenz_radio'),
            'honorar_audio' => $this->buildUsageBreakdownByNews($honorarRecords, 'honorar_audio'),
        ];
        $wdrBillingScopes = $isWdrBillingProduct
            ? $this->buildWdrBillingScopeOptions($invoiceKindBreakdownByNews)
            : [];
        $availableInvoiceKinds = array_values(array_filter(
            ['lizenz_bild', 'lizenz_video', 'lizenz_radio', 'honorar_audio'],
            fn (string $kind) => $invoiceKindStats[$kind]['count'] > 0
        ));
        $selectedInvoiceKind = (string) request()->query('invoice_kind', '');
        if (! in_array($selectedInvoiceKind, $availableInvoiceKinds, true)) {
            $selectedInvoiceKind = count($availableInvoiceKinds) === 1 ? $availableInvoiceKinds[0] : '';
        }

        return view('admin.backoffice.billing.create', [
            'customer' => $customer,
            'product' => $product,
            'billingContacts' => $billingContacts,
            'suggestedContactId' => $suggestedContactId,
            'openVideo' => $openVideo,
            'openRadio' => $openRadio,
            'openPhotos' => $openPhotos,
            'openNet' => $openNet,
            'openRecordsCount' => $openRecords->count(),
            'invoiceKindStats' => $invoiceKindStats,
            'invoiceKindBreakdownByNews' => $invoiceKindBreakdownByNews,
            'isWdrBillingProduct' => $isWdrBillingProduct,
            'wdrBillingScopes' => $wdrBillingScopes,
            'availableInvoiceKinds' => $availableInvoiceKinds,
            'selectedInvoiceKind' => $selectedInvoiceKind,
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
            'invoice_kind' => ['nullable', 'in:lizenz_bild,lizenz_video,lizenz_radio,honorar_audio'],
            'billing_scope' => ['nullable', 'string', 'max:255'],
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

        $this->splitMixedOpenUsageRecords((int) $org->id, (int) $product->id, $product);

        $openRecordsQuery = UsageRecord::where('organization_id', $org->id)
            ->where('product_id', $product->id)
            ->whereNull('invoice_id');
        $this->applyBillingWindowScope($openRecordsQuery, $product);
        $openRecords = $openRecordsQuery->get();
        if ($openRecords->isEmpty()) {
            return redirect()
                ->route('admin.backoffice.billing.index')
                ->with('error', 'Für diese Rechnungseinheit gibt es keine offenen Einträge mehr.');
        }

        $isWdrBillingProduct = $this->isWdrBillingProduct($product, $org);
        $wdrNewsItemId = null;
        if ($isWdrBillingProduct) {
            $scopeRaw = trim((string) ($validated['billing_scope'] ?? ''));
            if ($scopeRaw === '') {
                return redirect()
                    ->route('admin.backoffice.billing.create', ['product' => $product->id])
                    ->withInput()
                    ->with('error', 'Bitte eine konkrete Meldung und Rechnungsart auswählen (WDR: eine Rechnung pro Meldung).');
            }
            $decoded = $this->decodeBillingScope($scopeRaw);
            if ($decoded === null) {
                return redirect()
                    ->route('admin.backoffice.billing.create', ['product' => $product->id])
                    ->withInput()
                    ->with('error', 'Ungültige Auswahl der Rechnungsposition.');
            }
            $selectedInvoiceKind = $decoded['kind'];
            $wdrNewsItemId = $decoded['news_item_id'];
        } else {
            $availableInvoiceKinds = $openRecords
                ->map(fn (UsageRecord $record) => $this->resolveUsageInvoiceKind($record))
                ->filter(fn (?string $type) => in_array($type, ['lizenz_bild', 'lizenz_video', 'lizenz_radio', 'honorar_audio'], true))
                ->unique()
                ->values()
                ->all();
            $selectedInvoiceKind = (string) ($validated['invoice_kind'] ?? '');
            if (count($availableInvoiceKinds) > 1 && ! in_array($selectedInvoiceKind, $availableInvoiceKinds, true)) {
                return redirect()
                    ->route('admin.backoffice.billing.create', ['product' => $product->id])
                    ->withInput()
                    ->with('error', 'Bitte die Rechnungsart auswählen (Bilder, Video, Radio oder Honorar Audio).');
            }
            if (count($availableInvoiceKinds) === 1) {
                $selectedInvoiceKind = $availableInvoiceKinds[0];
            }
        }

        if ($selectedInvoiceKind !== '') {
            $openRecords = $openRecords
                ->filter(function (UsageRecord $record) use ($selectedInvoiceKind, $isWdrBillingProduct, $wdrNewsItemId) {
                    if ($this->resolveUsageInvoiceKind($record) !== $selectedInvoiceKind) {
                        return false;
                    }
                    if ($isWdrBillingProduct) {
                        return $this->usageRecordMatchesWdrNewsScope($record, $wdrNewsItemId);
                    }

                    return true;
                })
                ->values();
        }
        if ($openRecords->isEmpty()) {
            return redirect()
                ->route('admin.backoffice.billing.create', ['product' => $product->id])
                ->withInput()
                ->with('error', 'Für die gewählte Rechnungsart bzw. Meldung gibt es keine offenen Einträge.');
        }
        if ($contact->billing_type && $selectedInvoiceKind !== '') {
            $expectedContactType = $selectedInvoiceKind === 'honorar_audio' ? 'honorar' : 'lizenz';
            if ($contact->billing_type !== $expectedContactType) {
                return redirect()
                    ->route('admin.backoffice.billing.create', ['product' => $product->id])
                    ->withInput()
                    ->with('error', 'Der gewählte Rechnungsempfänger ist für „'.$contact->getBillingTypeLabel().'“ hinterlegt und passt nicht zur ausgewählten Rechnungsart.');
            }
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

        $assignQuery = UsageRecord::where('organization_id', $org->id)
            ->where('product_id', $product->id)
            ->whereNull('invoice_id');
        $this->applyBillingWindowScope($assignQuery, $product);
        $assignQuery->get()
            ->filter(function (UsageRecord $record) use ($selectedInvoiceKind, $isWdrBillingProduct, $wdrNewsItemId) {
                if ($selectedInvoiceKind !== '' && $this->resolveUsageInvoiceKind($record) !== $selectedInvoiceKind) {
                    return false;
                }
                if ($isWdrBillingProduct && $selectedInvoiceKind !== '') {
                    return $this->usageRecordMatchesWdrNewsScope($record, $wdrNewsItemId);
                }

                return true;
            })
            ->each(function (UsageRecord $record) use ($invoice): void {
                $record->invoice_id = $invoice->id;
                $record->save();
            });

        return redirect()
            ->route('admin.backoffice.billing.show', $invoice)
            ->with('status', 'Rechnungsentwurf angelegt (Nr. '.$invoice->id.') – lokal in EKN, noch ohne Lexware-Rechnungsnummer. '.$openRecords->count().' Nachverfolgungs-Einträge für '.$product->name.' zugeordnet. Rechnungsempfänger: '.$contact->email.'.');
    }

    private function resolveUsageInvoiceKind(UsageRecord $record): ?string
    {
        if (in_array($record->billing_type, ['lizenz', 'honorar'], true)) {
            if ($record->billing_type === 'honorar') {
                return 'honorar_audio';
            }
        }
        if ((int) $record->images_count > 0) {
            return 'lizenz_bild';
        }
        if ((float) $record->video_minutes > 0) {
            return 'lizenz_video';
        }
        if ((float) $record->radio_minutes > 0) {
            return 'lizenz_radio';
        }

        return null;
    }

    private function isWdrBillingProduct(Product $product, Organization $customer): bool
    {
        $org = mb_strtolower((string) ($customer->name ?? ''));
        $prod = mb_strtolower((string) ($product->name ?? ''));

        return str_contains($org, 'wdr')
            || str_contains($prod, 'wdr')
            || str_contains($prod, 'newsroom');
    }

    /**
     * @return list<array{news_item_id: ?int, label: string, summary: string}>
     */
    private function buildUsageBreakdownByNews(Collection $records, string $invoiceKind): array
    {
        if ($records->isEmpty()) {
            return [];
        }

        $grouped = $records->groupBy(fn (UsageRecord $r) => $r->news_item_id ?? 0);
        $rows = [];
        foreach ($grouped->keys()->sort()->values() as $key) {
            $group = $grouped->get($key);
            if ($group === null || $group->isEmpty()) {
                continue;
            }
            $keyInt = (int) $key;
            $newsItemId = $keyInt === 0 ? null : $keyInt;
            $first = $group->first();
            $label = $newsItemId === null
                ? 'Freies Angebot'
                : 'Meldung #'.$newsItemId;
            if ($first?->relationLoaded('newsItem') && $first->newsItem) {
                $t = trim((string) ($first->newsItem->title ?? ''));
                if ($t !== '') {
                    $label .= ' – '.Str::limit($t, 48);
                }
            }
            if ($invoiceKind === 'lizenz_bild') {
                $n = (int) $group->sum('images_count');
                $summary = $n === 1 ? '1 Bild' : $n.' Bilder';
            } elseif ($invoiceKind === 'lizenz_video') {
                $m = (float) $group->sum('video_minutes');
                $summary = $this->formatUsageMinuteQuantity($m);
            } elseif ($invoiceKind === 'lizenz_radio' || $invoiceKind === 'honorar_audio') {
                $m = (float) $group->sum('radio_minutes');
                $summary = $this->formatUsageMinuteQuantity($m);
            } else {
                $summary = '';
            }
            $rows[] = [
                'news_item_id' => $newsItemId,
                'label' => $label,
                'summary' => $summary,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{scope: string, kind: string, news_item_id: ?int, title: string, line: string}>
     */
    private function buildWdrBillingScopeOptions(array $invoiceKindBreakdownByNews): array
    {
        $titles = [
            'lizenz_bild' => 'Lizenz (Bilder)',
            'lizenz_video' => 'Lizenz (Video)',
            'lizenz_radio' => 'Lizenz (Radio)',
            'honorar_audio' => 'Honorar (Audio)',
        ];
        $order = ['lizenz_bild', 'lizenz_video', 'lizenz_radio', 'honorar_audio'];
        $out = [];
        foreach ($order as $kind) {
            foreach ($invoiceKindBreakdownByNews[$kind] ?? [] as $row) {
                $nid = $row['news_item_id'] ?? null;
                $out[] = [
                    'scope' => $this->encodeBillingScope($kind, $nid),
                    'kind' => $kind,
                    'news_item_id' => $nid,
                    'title' => $titles[$kind] ?? $kind,
                    'line' => $row['label'].': '.$row['summary'],
                ];
            }
        }

        return $out;
    }

    private function encodeBillingScope(string $kind, ?int $newsItemId): string
    {
        return $kind.'|'.($newsItemId === null ? '0' : (string) $newsItemId);
    }

    /**
     * @return array{kind: string, news_item_id: ?int}|null
     */
    private function decodeBillingScope(string $scope): ?array
    {
        $parts = explode('|', $scope, 2);
        if (count($parts) !== 2) {
            return null;
        }
        [$kind, $idRaw] = $parts;
        if (! in_array($kind, ['lizenz_bild', 'lizenz_video', 'lizenz_radio', 'honorar_audio'], true)) {
            return null;
        }
        $id = (int) $idRaw;

        return [
            'kind' => $kind,
            'news_item_id' => $id === 0 ? null : $id,
        ];
    }

    private function usageRecordMatchesWdrNewsScope(UsageRecord $record, ?int $newsItemId): bool
    {
        if ($newsItemId === null) {
            return $record->news_item_id === null;
        }

        return (int) $record->news_item_id === $newsItemId;
    }

    private function formatUsageMinuteQuantity(float $minutes): string
    {
        $s = rtrim(rtrim(number_format($minutes, 1, ',', '.'), '0'), ',');
        $suffix = abs($minutes - 1.0) < 0.05 ? 'Minute' : 'Minuten';

        return $s.' '.$suffix;
    }

    private function splitMixedOpenUsageRecords(int $organizationId, int $productId, ?Product $product = null): void
    {
        $query = UsageRecord::query()
            ->where('organization_id', $organizationId)
            ->where('product_id', $productId)
            ->whereNull('invoice_id')
            ->where('images_count', '>', 0)
            ->where('video_minutes', '>', 0);
        if ($product instanceof Product) {
            $this->applyBillingWindowScope($query, $product);
        }
        $mixedRecords = $query->get();

        foreach ($mixedRecords as $record) {
            $images = (int) $record->images_count;
            $minutes = (float) $record->video_minutes;
            $ppi = (float) ($record->price_per_image ?? 0);
            $ppm = (float) ($record->price_per_minute ?? 0);

            $imageTotal = round($images * $ppi, 2);
            $videoTotal = round($minutes * $ppm, 2);

            $videoRecord = $record->replicate();
            $videoRecord->images_count = 0;
            $videoRecord->price_per_image = 0;
            $videoRecord->video_minutes = $minutes;
            $videoRecord->radio_minutes = 0;
            $videoRecord->price_per_minute = $ppm;
            $videoRecord->total_amount = $videoTotal;
            $videoRecord->billing_type = 'lizenz';
            $videoRecord->line_item_note = trim(((string) ($record->line_item_note ?? '')).' [automatisch getrennt: Lizenz Video]');
            $videoRecord->invoice_id = null;
            $videoRecord->save();

            $record->video_minutes = 0;
            $record->price_per_minute = 0;
            $record->total_amount = $imageTotal;
            $record->billing_type = 'lizenz';
            $record->line_item_note = trim(((string) ($record->line_item_note ?? '')).' [automatisch getrennt: Lizenz Bilder]');
            $record->save();
        }

        $radioMixedQuery = UsageRecord::query()
            ->where('organization_id', $organizationId)
            ->where('product_id', $productId)
            ->whereNull('invoice_id')
            ->where('images_count', '>', 0)
            ->where('radio_minutes', '>', 0);
        if ($product instanceof Product) {
            $this->applyBillingWindowScope($radioMixedQuery, $product);
        }
        foreach ($radioMixedQuery->get() as $record) {
            $images = (int) $record->images_count;
            $minutes = (float) $record->radio_minutes;
            $ppi = (float) ($record->price_per_image ?? 0);
            $ppm = (float) ($record->price_per_minute ?? 0);

            $imageTotal = round($images * $ppi, 2);
            $radioTotal = round($minutes * $ppm, 2);

            $radioRecord = $record->replicate();
            $radioRecord->images_count = 0;
            $radioRecord->price_per_image = 0;
            $radioRecord->radio_minutes = $minutes;
            $radioRecord->video_minutes = 0;
            $radioRecord->price_per_minute = $ppm;
            $radioRecord->total_amount = $radioTotal;
            $radioRecord->billing_type = 'lizenz';
            $radioRecord->line_item_note = trim(((string) ($record->line_item_note ?? '')).' [automatisch getrennt: Lizenz Radio]');
            $radioRecord->invoice_id = null;
            $radioRecord->save();

            $record->radio_minutes = 0;
            $record->price_per_minute = 0;
            $record->total_amount = $imageTotal;
            $record->billing_type = 'lizenz';
            $record->line_item_note = trim(((string) ($record->line_item_note ?? '')).' [automatisch getrennt: Lizenz Bilder]');
            $record->save();
        }

        $videoRadioQuery = UsageRecord::query()
            ->where('organization_id', $organizationId)
            ->where('product_id', $productId)
            ->whereNull('invoice_id')
            ->where('billing_type', 'lizenz')
            ->where('images_count', '=', 0)
            ->where('video_minutes', '>', 0)
            ->where('radio_minutes', '>', 0);
        if ($product instanceof Product) {
            $this->applyBillingWindowScope($videoRadioQuery, $product);
        }
        foreach ($videoRadioQuery->get() as $record) {
            $videoM = (float) $record->video_minutes;
            $radioM = (float) $record->radio_minutes;
            $ppm = (float) ($record->price_per_minute ?? 0);
            $videoTotal = round($videoM * $ppm, 2);
            $radioTotal = round($radioM * $ppm, 2);

            $radioRecord = $record->replicate();
            $radioRecord->video_minutes = 0;
            $radioRecord->images_count = 0;
            $radioRecord->price_per_image = 0;
            $radioRecord->radio_minutes = $radioM;
            $radioRecord->price_per_minute = $ppm;
            $radioRecord->total_amount = $radioTotal;
            $radioRecord->billing_type = 'lizenz';
            $radioRecord->line_item_note = trim(((string) ($record->line_item_note ?? '')).' [automatisch getrennt: Lizenz Radio]');
            $radioRecord->invoice_id = null;
            $radioRecord->save();

            $record->radio_minutes = 0;
            $record->video_minutes = $videoM;
            $record->price_per_minute = $ppm;
            $record->total_amount = $videoTotal;
            $record->billing_type = 'lizenz';
            $record->line_item_note = trim(((string) ($record->line_item_note ?? '')).' [automatisch getrennt: Lizenz Video]');
            $record->save();
        }
    }

    /**
     * TAG24: Abrechnung nur für den letzten vollen Kalendermonat (z. B. 01.03.–31.03.).
     */
    private function applyBillingWindowScope($query, Product $product): void
    {
        if (! $this->isTag24Product($product)) {
            return;
        }

        $start = Carbon::now()->subMonthNoOverflow()->startOfMonth()->startOfDay();
        $end = Carbon::now()->subMonthNoOverflow()->endOfMonth()->endOfDay();
        $query->whereBetween('used_at', [$start, $end]);
    }

    private function isTag24Product(Product $product): bool
    {
        $name = mb_strtolower((string) $product->name);
        $org = mb_strtolower((string) optional($product->organization)->name);

        return str_contains($name, 'tag24') || str_contains($org, 'tag24');
    }

    /**
     * Erstellt/aktiviert Rechnungsempfänger-Kontakte aus den bei der Redaktion hinterlegten Rechnungs-E-Mails.
     */
    private function bootstrapBillingContactsFromProduct(Product $product, int $organizationId): void
    {
        $emails = array_values(array_unique(array_filter([
            $product->billing_email_primary,
            $product->billing_email_secondary,
        ], static fn ($email) => is_string($email) && trim($email) !== ''), SORT_STRING));

        foreach ($emails as $rawEmail) {
            $email = mb_strtolower(trim((string) $rawEmail));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $contact = Contact::query()
                ->where('organization_id', $organizationId)
                ->whereRaw('LOWER(email) = ?', [$email])
                ->where(function ($q) use ($product) {
                    $q->whereNull('product_id')
                        ->orWhere('product_id', $product->id);
                })
                ->orderByRaw('CASE WHEN product_id = ? THEN 0 ELSE 1 END', [$product->id])
                ->first();

            if ($contact) {
                $updates = [];
                if (! $contact->use_for_invoice) {
                    $updates['use_for_invoice'] = true;
                }
                $desiredName = $this->defaultInvoiceRecipientContactName($product);
                $currentName = trim((string) ($contact->name ?? ''));
                if ($currentName === '') {
                    $updates['name'] = $desiredName;
                } elseif ($this->isTag24Product($product) && $this->shouldReplaceTag24InvoiceRecipientName($currentName)) {
                    $updates['name'] = $desiredName;
                }
                if ($updates !== []) {
                    $contact->update($updates);
                }

                continue;
            }

            Contact::create([
                'organization_id' => $organizationId,
                'product_id' => $product->id,
                'name' => $this->defaultInvoiceRecipientContactName($product),
                'email' => $email,
                'use_for_invoice' => true,
                'billing_type' => 'lizenz',
            ]);
        }
    }

    /**
     * Anzeigename für den Rechnungsempfänger-Kontakt (nicht Firmenname, sondern fachliche Zuordnung).
     */
    private function defaultInvoiceRecipientContactName(Product $product): string
    {
        if ($this->isTag24Product($product)) {
            return 'Fotoredaktion';
        }

        return trim((string) $product->name) !== ''
            ? 'Rechnung '.$product->name
            : 'Rechnung';
    }

    private function shouldReplaceTag24InvoiceRecipientName(string $currentName): bool
    {
        if (mb_strtolower($currentName) === 'fotoredaktion') {
            return false;
        }
        if (str_starts_with($currentName, 'Rechnung ')) {
            return true;
        }

        return $currentName === 'Rechnung';
    }

    public function markReadyForLexware(Invoice $invoice): RedirectResponse
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
            $lexwareInvoiceService = app(LexwareInvoiceService::class);
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

    public function sendInvoice(Request $request, Invoice $invoice, ZugferdInvoiceService $zugferdInvoiceService): RedirectResponse
    {
        $hasLexwareFinalization = trim((string) ($invoice->lexware_invoice_id ?? '')) !== ''
            && trim((string) ($invoice->voucher_number ?? '')) !== '';
        if (! $hasLexwareFinalization) {
            return redirect()
                ->route('admin.backoffice.billing.show', ['invoice' => $invoice->getKey()])
                ->with('error', 'Versand ist erst nach finaler Lexware-Rechnung erlaubt. Dadurch wird ein versehentliches Ändern in Lexware ausgeschlossen.');
        }

        $validated = $request->validate([
            'test_recipient' => ['nullable', 'email'],
        ]);

        $dispatchStatus = $this->dispatchStatusService->evaluate($invoice);
        if (! $dispatchStatus['dispatch']['can_send']) {
            $message = $dispatchStatus['dispatch']['messages'][0] ?? 'Diese Rechnung ist aktuell nicht versandbereit.';

            return redirect()
                ->route('admin.backoffice.billing.show', ['invoice' => $invoice->getKey()])
                ->with('error', $message);
        }

        try {
            $document = $zugferdInvoiceService->generateArchivedDocument($invoice);
            $invoice->refresh();
            $customerPdf = $this->buildCustomerPdfDocument($invoice);
            $meta = (array) ($invoice->meta ?? []);
            $dispatchMeta = (array) ($meta['dispatch'] ?? []);
            $isCorrectionResend = ! empty($dispatchMeta['sent_at']);
            $testRecipient = trim((string) ($validated['test_recipient'] ?? ''));
            $recipientEmail = $testRecipient !== '' ? $testRecipient : $invoice->contact->email;

            Mail::to($recipientEmail)->send(new InvoiceMail(
                $invoice->fresh(['organization', 'product', 'contact']),
                $customerPdf['pdf_content'],
                $customerPdf['filename'],
                $isCorrectionResend
            ));

            $invoice->refresh();
            $meta = (array) ($invoice->meta ?? []);
            $dispatchMeta = (array) ($meta['dispatch'] ?? []);
            $dispatchMeta['sent_at'] = now()->toIso8601String();
            $dispatchMeta['sent_to'] = $recipientEmail;
            if ($testRecipient !== '') {
                $dispatchMeta['test_mode'] = true;
                $dispatchMeta['original_recipient'] = $invoice->contact->email;
            } else {
                $dispatchMeta['test_mode'] = false;
                $dispatchMeta['original_recipient'] = null;
            }
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

    /**
     * Zahlungseingang laut Bankmitteilung in EKN festhalten (Rechnungs-meta).
     */
    public function recordPayment(Request $request, Invoice $invoice): RedirectResponse
    {
        $request->merge([
            'payment_amount_gross' => $this->normalizeOptionalEuroAmount($request->input('payment_amount_gross')),
        ]);

        $validated = $request->validate([
            'payment_received_on' => ['nullable', 'date'],
            'payment_amount_gross' => ['nullable', 'numeric', 'min:0'],
            'payment_bank_reference' => ['nullable', 'string', 'max:500'],
            'payment_note' => ['nullable', 'string', 'max:1000'],
            'clear_payment' => ['sometimes', 'boolean'],
        ]);

        $meta = (array) ($invoice->meta ?? []);

        if ($request->boolean('clear_payment')) {
            unset($meta['payment']);
            $invoice->forceFill(['meta' => empty($meta) ? null : $meta])->save();

            return redirect()
                ->to(route('admin.backoffice.billing.show', $invoice).'#zahlungseingang')
                ->with('status', 'Zahlungseintrag wurde entfernt.');
        }

        $dateRaw = $validated['payment_received_on'] ?? null;
        if ($dateRaw === null || $dateRaw === '') {
            return redirect()
                ->to(route('admin.backoffice.billing.show', $invoice).'#zahlungseingang')
                ->with('error', 'Bitte das Datum des Zahlungseingangs angeben (oder den Eintrag entfernen).');
        }

        $amountGross = null;
        if (isset($validated['payment_amount_gross']) && $validated['payment_amount_gross'] !== '' && $validated['payment_amount_gross'] !== null) {
            $amountGross = round((float) $validated['payment_amount_gross'], 2);
        }

        $ref = isset($validated['payment_bank_reference']) ? trim((string) $validated['payment_bank_reference']) : '';
        $note = isset($validated['payment_note']) ? trim((string) $validated['payment_note']) : '';

        $meta['payment'] = [
            'received_on' => Carbon::parse((string) $dateRaw)->toDateString(),
            'amount_gross' => $amountGross,
            'bank_reference' => $ref !== '' ? $ref : null,
            'note' => $note !== '' ? $note : null,
            'recorded_at' => now()->toIso8601String(),
            'recorded_by_user_id' => Auth::id(),
        ];

        $invoice->forceFill(['meta' => $meta])->save();
        $this->refreshLexwarePaymentMeta($invoice->fresh());

        $invoice->refresh();
        $lexwareSyncError = trim((string) (data_get($invoice->meta, 'lexware_payment.sync_error') ?? ''));
        $hasLexwareId = trim((string) ($invoice->lexware_invoice_id ?? '')) !== '';

        $statusText = 'Zahlungseingang wurde gespeichert.';
        if ($hasLexwareId) {
            $statusText .= $lexwareSyncError === ''
                ? ' Lexware-Zahlungsstand wurde abgeglichen.'
                : ' Der automatische Lexware-Abgleich ist fehlgeschlagen (siehe Hinweis).';
        }

        $redirect = redirect()
            ->to(route('admin.backoffice.billing.show', $invoice).'#zahlungseingang')
            ->with('status', $statusText);

        if ($lexwareSyncError !== '') {
            $redirect->with('warning', $lexwareSyncError);
        }

        return $redirect;
    }

    /**
     * Aktuellen Zahlungsstand der Ausgangsrechnung aus Lexware lesen (GET /v1/payments/{id}) und in meta speichern.
     */
    public function syncLexwarePayment(Invoice $invoice): RedirectResponse
    {
        if (trim((string) ($invoice->lexware_invoice_id ?? '')) === '') {
            return redirect()
                ->to(route('admin.backoffice.billing.show', $invoice).'#zahlungseingang')
                ->with('error', 'Für diese Rechnung liegt keine Lexware-ID vor (Rechnung erst finalisieren).');
        }

        $this->refreshLexwarePaymentMeta($invoice->fresh());
        $invoice->refresh();
        $lexwareSyncError = trim((string) (data_get($invoice->meta, 'lexware_payment.sync_error') ?? ''));

        $redirect = redirect()
            ->to(route('admin.backoffice.billing.show', $invoice).'#zahlungseingang')
            ->with('status', 'Lexware-Zahlungsstand wurde abgerufen.');

        if ($lexwareSyncError !== '') {
            $redirect->with('warning', $lexwareSyncError);
        }

        return $redirect;
    }

    /**
     * @see \App\Services\Lexware\LexwareInvoiceService::fetchPaymentInformation()
     */
    private function refreshLexwarePaymentMeta(Invoice $invoice): void
    {
        $id = trim((string) ($invoice->lexware_invoice_id ?? ''));
        if ($id === '') {
            return;
        }

        $token = trim((string) config('lexware.api_token', ''));
        $base = trim((string) config('lexware.base_url', ''));
        if ($token === '' || $base === '') {
            $meta = (array) ($invoice->meta ?? []);
            $meta['lexware_payment'] = [
                'fetched_at' => now()->toIso8601String(),
                'sync_error' => 'Lexware API ist nicht konfiguriert (LEXWARE_BASE_URL / LEXWARE_API_TOKEN).',
            ];
            $invoice->forceFill(['meta' => $meta])->save();

            return;
        }

        try {
            $service = app(LexwareInvoiceService::class);
            $raw = $service->fetchPaymentInformation($id);
            $meta = (array) ($invoice->meta ?? []);
            $meta['lexware_payment'] = $service->summarizePaymentInformation($raw);
            $invoiceStatus = $this->resolveInvoiceStatusFromLexware((string) ($meta['lexware_payment']['voucher_status'] ?? ''), (string) ($invoice->status ?? ''));
            $invoice->forceFill([
                'meta' => $meta,
                'status' => $invoiceStatus,
            ])->save();
        } catch (\Throwable $e) {
            $meta = (array) ($invoice->meta ?? []);
            $meta['lexware_payment'] = [
                'fetched_at' => now()->toIso8601String(),
                'sync_error' => $this->toUserFacingLexwareMessage($e),
            ];
            $invoice->forceFill(['meta' => $meta])->save();
        }
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

    public function storeIncomingDocument(Request $request, Invoice $invoice): RedirectResponse
    {
        if (! Schema::hasTable('invoice_incoming_documents')) {
            return redirect()
                ->route('admin.backoffice.billing.show', $invoice)
                ->with('error', 'Eingangsdokumente sind noch nicht eingerichtet (Migration ausführen).');
        }

        $validated = $request->validate([
            'document_type' => ['required', 'string', 'in:contract,payment_instruction,license_contract,remuneration_notice,other'],
            'note' => ['nullable', 'string', 'max:500'],
            'document' => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,gif,doc,docx,xls,xlsx,txt,zip,eml'],
        ]);

        $file = $request->file('document');
        $original = mb_substr(basename(str_replace('\\', '/', (string) $file->getClientOriginalName())), 0, 255);
        if ($original === '') {
            $original = 'dokument';
        }

        $ext = strtolower((string) $file->getClientOriginalExtension());
        if ($ext === '') {
            $ext = 'bin';
        }
        $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'bin';

        $relativeDir = 'invoice-incoming/'.$invoice->id;
        $storedName = Str::uuid()->toString().'.'.$ext;
        $storagePath = $file->storeAs($relativeDir, $storedName, 'local');

        InvoiceIncomingDocument::create([
            'invoice_id' => $invoice->id,
            'document_type' => $validated['document_type'],
            'original_filename' => $original,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize() ?: null,
            'storage_path' => $storagePath,
            'note' => isset($validated['note']) && trim((string) $validated['note']) !== '' ? trim((string) $validated['note']) : null,
            'uploaded_by' => Auth::id(),
        ]);

        $this->syncWdrChecklistAfterIncomingDocumentUpload($invoice->fresh(), (string) $validated['document_type']);

        return redirect()
            ->to(route('admin.backoffice.billing.show', $invoice).'#eingangsdokumente')
            ->with('status', 'Eingangsdokument wurde hochgeladen.');
    }

    public function downloadIncomingDocument(Invoice $invoice, InvoiceIncomingDocument $document): HttpResponse|RedirectResponse
    {
        if (! Schema::hasTable('invoice_incoming_documents')) {
            abort(404);
        }
        if ((int) $document->invoice_id !== (int) $invoice->id) {
            abort(404);
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($document->storage_path)) {
            return redirect()
                ->to(route('admin.backoffice.billing.show', $invoice).'#eingangsdokumente')
                ->with('error', 'Die Datei wurde auf dem Server nicht gefunden.');
        }

        return $disk->download($document->storage_path, $document->original_filename);
    }

    public function destroyIncomingDocument(Invoice $invoice, InvoiceIncomingDocument $document): RedirectResponse
    {
        if (! Schema::hasTable('invoice_incoming_documents')) {
            return redirect()
                ->route('admin.backoffice.billing.show', $invoice)
                ->with('error', 'Eingangsdokumente sind noch nicht eingerichtet.');
        }
        if ((int) $document->invoice_id !== (int) $invoice->id) {
            abort(404);
        }

        $disk = Storage::disk('local');
        if ($disk->exists($document->storage_path)) {
            $disk->delete($document->storage_path);
        }
        $removedType = (string) $document->document_type;
        $document->delete();

        $this->clearWdrChecklistIfNoMatchingDocuments($invoice->fresh(), $removedType);

        return redirect()
            ->to(route('admin.backoffice.billing.show', $invoice).'#eingangsdokumente')
            ->with('status', 'Eingangsdokument wurde gelöscht.');
    }

    /**
     * WDR: Lizenzvertrag/Vergütungsmitteilung in der Ablage → Prüfpunkt automatisch setzen.
     */
    private function syncWdrChecklistAfterIncomingDocumentUpload(Invoice $invoice, string $documentType): void
    {
        if (! Schema::hasColumn('invoices', 'wdr_billing_checklist')) {
            return;
        }
        $invoice->load(['organization', 'product']);
        if (! $invoice->isWdrBillingContext()) {
            return;
        }

        if ($documentType !== InvoiceIncomingDocument::TYPE_LICENSE_CONTRACT
            && $documentType !== InvoiceIncomingDocument::TYPE_REMUNERATION_NOTICE) {
            return;
        }

        $now = now()->toIso8601String();
        $uid = Auth::id();
        $current = (array) ($invoice->wdr_billing_checklist ?? []);

        if ($documentType === InvoiceIncomingDocument::TYPE_LICENSE_CONTRACT) {
            $current['lizenzvertrag'] = [
                'done' => true,
                'completed_at' => $now,
                'user_id' => $uid,
            ];
        } else {
            $current['verguetungsmitteilung'] = [
                'done' => true,
                'completed_at' => $now,
                'user_id' => $uid,
            ];
        }

        $invoice->forceFill(['wdr_billing_checklist' => $current])->save();
    }

    /**
     * WDR: Letztes Dokument eines Typs entfernt → zugehörigen Prüfpunkt zurücksetzen (wenn nicht mehr durch Ablage abgedeckt).
     */
    private function clearWdrChecklistIfNoMatchingDocuments(Invoice $invoice, string $removedDocumentType): void
    {
        if (! Schema::hasColumn('invoices', 'wdr_billing_checklist')) {
            return;
        }
        $invoice->load(['organization', 'product']);
        if (! $invoice->isWdrBillingContext()) {
            return;
        }

        if ($removedDocumentType !== InvoiceIncomingDocument::TYPE_LICENSE_CONTRACT
            && $removedDocumentType !== InvoiceIncomingDocument::TYPE_REMUNERATION_NOTICE) {
            return;
        }

        $current = (array) ($invoice->wdr_billing_checklist ?? []);

        if ($removedDocumentType === InvoiceIncomingDocument::TYPE_LICENSE_CONTRACT) {
            $still = $invoice->incomingDocuments()
                ->where('document_type', InvoiceIncomingDocument::TYPE_LICENSE_CONTRACT)
                ->exists();
            if (! $still) {
                $current['lizenzvertrag'] = ['done' => false, 'completed_at' => null, 'user_id' => null];
            }
        } else {
            $still = $invoice->incomingDocuments()
                ->where('document_type', InvoiceIncomingDocument::TYPE_REMUNERATION_NOTICE)
                ->exists();
            if (! $still) {
                $current['verguetungsmitteilung'] = ['done' => false, 'completed_at' => null, 'user_id' => null];
            }
        }

        $invoice->forceFill(['wdr_billing_checklist' => $current])->save();
    }

    public function updateWdrInvoiceChecklist(Request $request, Invoice $invoice): RedirectResponse
    {
        if (! Schema::hasColumn('invoices', 'wdr_billing_checklist')) {
            return redirect()
                ->route('admin.backoffice.billing.show', $invoice)
                ->with('error', 'WDR-Prüfpunkte sind noch nicht eingerichtet (Migration ausführen).');
        }

        $invoice->load(['organization', 'product']);
        if (! $invoice->isWdrBillingContext()) {
            abort(403);
        }

        $request->validate([
            'lizenzvertrag' => ['nullable', 'boolean'],
            'verguetungsmitteilung' => ['nullable', 'boolean'],
        ]);

        $now = now()->toIso8601String();
        $uid = Auth::id();
        $current = (array) ($invoice->wdr_billing_checklist ?? []);

        foreach (['lizenzvertrag', 'verguetungsmitteilung'] as $key) {
            if ($request->boolean($key)) {
                $current[$key] = [
                    'done' => true,
                    'completed_at' => $now,
                    'user_id' => $uid,
                ];
            } else {
                $current[$key] = [
                    'done' => false,
                    'completed_at' => null,
                    'user_id' => null,
                ];
            }
        }

        $invoice->wdr_billing_checklist = $current;
        $invoice->save();

        return redirect()
            ->to(route('admin.backoffice.billing.show', $invoice).'#wdr-abrechnung')
            ->with('status', 'WDR-Prüfpunkte wurden gespeichert.');
    }

    public function downloadLexwareFile(Invoice $invoice): HttpResponse|RedirectResponse
    {
        try {
            $lexwareInvoiceService = app(LexwareInvoiceService::class);
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

    /**
     * Formularwerte wie „1.234,56“ oder „1234,56“ in einen normalisierten String für die Validierung bringen.
     */
    private function normalizeOptionalEuroAmount(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }
        $s = preg_replace('/\s+/u', '', $s) ?? $s;
        if (str_contains($s, ',') && ! str_contains($s, '.')) {
            return str_replace(',', '.', $s);
        }
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)$/', $s)) {
            $s = str_replace('.', '', $s);

            return str_replace(',', '.', $s);
        }

        return $s;
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
            $isRadio = (float) $record->radio_minutes > 0;
            $isMinuteAudio = $isRadio && ($record->billing_type === 'honorar');

            $fields = [
                ['label' => 'Anlass', 'value' => $newsItem?->title ?: 'noch nicht gepflegt'],
                ['label' => 'Nutzungsdatum', 'value' => $record->used_at?->format('d.m.Y') ?: 'noch nicht gepflegt'],
                ['label' => 'Credits', 'value' => $newsItem?->author_credit ?: $senderName],
            ];

            if ($isImage) {
                array_splice($fields, 1, 0, [
                    ['label' => 'Format', 'value' => $record->usage_format ?: 'noch nicht gepflegt'],
                    ['label' => 'Link', 'value' => $record->article_url ?: 'noch nicht gepflegt'],
                ]);
            }

            if ($isVideo || $isRadio) {
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

            $sectionTitle = $isImage
                ? 'Materialankauf'
                : ($isMinuteAudio ? 'Audiomaterial (Honorar)' : ($isRadio ? 'Radiomaterial' : 'Videomaterial'));

            return [
                'title' => $sectionTitle,
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

        if ((float) $record->radio_minutes > 0) {
            $customerName = $invoice->organization?->name ?: 'Kunde';
            if (($record->billing_type ?? null) === 'honorar') {
                return 'Honorar (Audio/Radio) '.$customerName;
            }

            return 'Radiomaterial Verkauf '.$customerName;
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
        $load = [
            'organization',
            'product',
            'contact',
            'usageRecords.newsItem',
        ];
        if (Schema::hasTable('invoice_incoming_documents')) {
            $load[] = 'incomingDocuments.uploadedBy';
        }
        $invoice->load($load);
        $this->syncInvoiceStatusFromLexwareSnapshot($invoice);

        if (! $invoice->organization || ! $invoice->product) {
            return null;
        }

        $invoice->syncTotalsFromUsageRecordsIfEditable();
        $invoice->refresh();

        return [
            'invoice' => $invoice,
            'usageRecords' => $invoice->usageRecords->sortBy([
                ['used_at', 'asc'],
                ['id', 'asc'],
            ])->values(),
            'previewSections' => $this->buildPreviewSections($invoice),
            'wdrNewsroomImageTier' => WdrNewsroomImageTierLines::make(),
            'dispatchStatus' => $this->dispatchStatusService->evaluate($invoice),
            'hasInvoiceIncomingDocuments' => Schema::hasTable('invoice_incoming_documents'),
            'showWdrBillingChecklist' => Schema::hasColumn('invoices', 'wdr_billing_checklist') && $invoice->isWdrBillingContext(),
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

        if (str_contains($message, 'Target class [App\\Services\\Lexware\\LexwareClient] does not exist')) {
            return 'Lexware ist im System noch nicht vollständig konfiguriert (Client-Service fehlt). Bitte Lexware-Integration prüfen.';
        }

        return $message;
    }

    private function resolveInvoiceStatusFromLexware(string $voucherStatus, string $currentStatus): string
    {
        $voucherStatus = mb_strtolower(trim($voucherStatus));
        if ($voucherStatus === 'voided') {
            return 'lexware_voided';
        }

        if (trim((string) $currentStatus) === '') {
            return 'lexware_open';
        }

        return $currentStatus;
    }

    private function syncInvoiceStatusFromLexwareSnapshot(Invoice $invoice): void
    {
        $meta = (array) ($invoice->meta ?? []);
        $voucherStatus = (string) (data_get($meta, 'lexware_payment.voucher_status') ?? data_get($meta, 'lexware.voucher_status') ?? '');
        if (trim($voucherStatus) === '') {
            return;
        }

        $resolvedStatus = $this->resolveInvoiceStatusFromLexware($voucherStatus, (string) ($invoice->status ?? ''));
        if ($resolvedStatus === (string) ($invoice->status ?? '')) {
            return;
        }

        $invoice->forceFill(['status' => $resolvedStatus])->save();
        $invoice->refresh();
    }

    /**
     * Kunden-PDF im EKN-Layout erzeugen (auch für Versand), statt Lexware-Standardlayout.
     *
     * @return array{filename: string, pdf_content: string}
     */
    private function buildCustomerPdfDocument(Invoice $invoice): array
    {
        $viewData = $this->getInvoicePreviewData($invoice);
        if ($viewData === null) {
            throw new \RuntimeException('Die Rechnung ist unvollständig und kann nicht als Kunden-PDF erzeugt werden.');
        }

        $pdf = Pdf::loadView('admin.backoffice.billing.pdf', $viewData)
            ->setPaper('a4')
            ->setOption('isPhpEnabled', true)
            ->setOption('isRemoteEnabled', false);

        $filename = 'rechnung-'.($invoice->voucher_number ?: $invoice->id).'.pdf';

        return [
            'filename' => $filename,
            'pdf_content' => $pdf->output(),
        ];
    }
}
