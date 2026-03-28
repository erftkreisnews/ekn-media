<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\UsageRecordCreatedMail;
use App\Models\NewsItem;
use App\Models\Organization;
use App\Models\Product;
use App\Models\UsageRecord;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class UsageReportController extends Controller
{
    public function index(Request $request): View
    {
        $period = $request->query('period', 'month');

        [$start, $end, $label] = $this->resolvePeriod($period);

        $query = UsageRecord::with(['newsItem', 'organization', 'product', 'invoice'])
            ->whereBetween('used_at', [$start, $end])
            ->orderByDesc('used_at')
            ->orderByDesc('id');

        $records = $query->limit(100)->get();

        $net = (float) $records->sum('total_amount');
        $vatRate = 0.07;
        $vat = round($net * $vatRate, 2);
        $gross = $net + $vat;

        $totals = [
            'images' => $records->sum('images_count'),
            'video_minutes' => (float) $records->sum('video_minutes'),
            'amount' => $net,
            'vat' => $vat,
            'gross' => $gross,
        ];

        return view('admin.backoffice.usage.index', [
            'records' => $records,
            'totals' => $totals,
            'period' => $period,
            'periodLabel' => $label,
            'start' => $start,
            'end' => $end,
        ]);
    }

    public function create(): View
    {
        return view('admin.backoffice.usage.create', [
            ...$this->getFormOptions(),
        ]);
    }

    public function edit(UsageRecord $usageRecord): View|RedirectResponse
    {
        if ($usageRecord->invoice_id) {
            return redirect()
                ->route('admin.backoffice.usage.index', ['period' => 'month'])
                ->with('error', 'Dieser Nachverfolgungs-Eintrag ist aktuell gesperrt und kann hier nicht direkt bearbeitet werden.');
        }

        return view('admin.backoffice.usage.edit', [
            'record' => $usageRecord->load(['newsItem', 'organization', 'product']),
            ...$this->getFormOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $record = $this->saveUsageRecord(new UsageRecord, $request, true);

        $user = Auth::user();
        if ($user && $user->email) {
            $record->loadMissing(['newsItem', 'organization', 'product', 'creator']);
            Mail::to($user->email)->send(new UsageRecordCreatedMail($record));
        }

        return redirect()
            ->route('admin.backoffice.usage.index', ['period' => 'month'])
            ->with('status', 'Nutzungs-Eintrag wurde gespeichert (News '.$record->newsItem->id.').');
    }

    public function update(Request $request, UsageRecord $usageRecord): RedirectResponse
    {
        if ($usageRecord->invoice_id) {
            return redirect()
                ->route('admin.backoffice.usage.index', ['period' => 'month'])
                ->with('error', 'Dieser Nachverfolgungs-Eintrag ist aktuell gesperrt und kann hier nicht direkt bearbeitet werden.');
        }

        $record = $this->saveUsageRecord($usageRecord, $request, false);

        return redirect()
            ->route('admin.backoffice.usage.index', ['period' => 'month'])
            ->with('status', 'Nachverfolgungs-Eintrag wurde aktualisiert (News '.$record->newsItem->id.').');
    }

    public function destroy(UsageRecord $usageRecord): RedirectResponse
    {
        if ($usageRecord->invoice_id) {
            return redirect()
                ->route('admin.backoffice.usage.index', ['period' => 'month'])
                ->with('error', 'Dieser Nachverfolgungs-Eintrag ist aktuell gesperrt und kann hier nicht direkt gelöscht werden.');
        }

        $newsItemId = $usageRecord->news_item_id;
        $usageRecord->delete();

        return redirect()
            ->route('admin.backoffice.usage.index', ['period' => 'month'])
            ->with('status', 'Nachverfolgungs-Eintrag wurde gelöscht (News '.$newsItemId.').');
    }

    public function releaseInvoiceAndEdit(UsageRecord $usageRecord): RedirectResponse
    {
        $usageRecord->load('invoice');

        if (! $usageRecord->invoice) {
            return redirect()
                ->route('admin.backoffice.usage.edit', $usageRecord)
                ->with('status', 'Der Eintrag ist aktuell keinem Rechnungsentwurf zugeordnet und kann direkt bearbeitet werden.');
        }

        $invoice = $usageRecord->invoice;
        if (! in_array($invoice->status, ['draft', 'pending', 'ready_for_lexware'], true)) {
            return redirect()
                ->route('admin.backoffice.usage.index', ['period' => 'month'])
                ->with('error', 'Nur lokale Rechnungsentwürfe können aufgelöst werden. Bereits weiterverarbeitete Rechnungen bleiben gesperrt.');
        }

        if ($invoice->lexware_invoice_id || $invoice->voucher_number) {
            return redirect()
                ->route('admin.backoffice.usage.index', ['period' => 'month'])
                ->with('error', 'Dieser Entwurf hat bereits eine externe Rechnungsreferenz und kann nicht automatisch aufgelöst werden.');
        }

        $usageCount = $invoice->usageRecords()->count();
        $invoice->usageRecords()->update(['invoice_id' => null]);
        $invoice->delete();

        return redirect()
            ->route('admin.backoffice.usage.edit', $usageRecord->fresh())
            ->with('status', 'Rechnungsentwurf wurde aufgelöst. '.$usageCount.' zugeordnete Nachverfolgungs-Einträge sind wieder offen und dieser Eintrag kann jetzt bearbeitet werden.');
    }

    /**
     * Ermittelt Start/Ende für Zeitraum (Monat, Quartal, Halbjahr, Jahr).
     *
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon, 2: string}
     */
    private function resolvePeriod(string $period): array
    {
        $now = Carbon::now();

        return match ($period) {
            'quarter' => [
                $now->copy()->firstOfQuarter()->startOfDay(),
                $now->copy()->lastOfQuarter()->endOfDay(),
                'laufendes Quartal',
            ],
            'halfyear' => [
                $now->month <= 6
                    ? $now->copy()->startOfYear()->startOfDay()
                    : $now->copy()->startOfYear()->addMonths(6)->startOfDay(),
                $now->month <= 6
                    ? $now->copy()->startOfYear()->addMonths(6)->subDay()->endOfDay()
                    : $now->copy()->endOfYear()->endOfDay(),
                'laufendes Halbjahr',
            ],
            'year' => [
                $now->copy()->startOfYear()->startOfDay(),
                $now->copy()->endOfYear()->endOfDay(),
                'laufendes Jahr',
            ],
            default => [
                $now->copy()->startOfMonth()->startOfDay(),
                $now->copy()->endOfMonth()->endOfDay(),
                'laufender Monat',
            ],
        };
    }

    private function getFormOptions(): array
    {
        $newsItems = NewsItem::query()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $organizations = Organization::where('active', true)
            ->orderBy('name')
            ->get();

        $products = Product::where('active', true)
            ->with('organization')
            ->orderBy('name')
            ->get();

        return [
            'newsItems' => $newsItems,
            'organizations' => $organizations,
            'products' => $products,
        ];
    }

    private function saveUsageRecord(UsageRecord $record, Request $request, bool $isNew): UsageRecord
    {
        $validated = $request->validate([
            'news_item_id' => ['required', 'exists:news_items,id'],
            'organization_id' => ['required', 'exists:organizations,id'],
            'product_id' => ['required', 'exists:products,id'],
            'used_at' => ['required', 'date'],
            'images_count' => ['nullable', 'integer', 'min:0'],
            'video_minutes' => ['nullable', 'numeric', 'min:0'],
            'price_per_image' => ['nullable', 'numeric', 'min:0'],
            'price_per_minute' => ['nullable', 'numeric', 'min:0'],
            'article_url' => ['nullable', 'url'],
            'usage_format' => ['required', 'string', 'max:255'],
            'usage_rights' => ['nullable', 'string', 'max:255'],
            'reference_code' => ['nullable', 'string', 'max:255'],
            'line_item_note' => ['nullable', 'string', 'max:255'],
            'billing_department' => ['nullable', 'string', 'in:newsroom,studio_koeln,studio_bonn'],
            'billing_type' => ['nullable', 'string', 'in:lizenz,honorar'],
        ]);

        $product = Product::findOrFail($validated['product_id']);
        if ((int) $product->organization_id !== (int) $validated['organization_id']) {
            return throw \Illuminate\Validation\ValidationException::withMessages([
                'product_id' => 'Die gewählte Rechnungseinheit gehört nicht zum ausgewählten Medienhaus.',
            ]);
        }

        $images = (int) ($validated['images_count'] ?? 0);
        $minutes = (float) ($validated['video_minutes'] ?? 0);
        $ppi = (float) ($validated['price_per_image'] ?? 0);
        $ppm = (float) ($validated['price_per_minute'] ?? 0);

        if ($images <= 0 && $minutes <= 0) {
            return throw \Illuminate\Validation\ValidationException::withMessages([
                'images_count' => 'Bitte mindestens Bilder oder Videominuten fuer die Nutzung angeben.',
            ]);
        }

        if ($images > 0 && empty($validated['article_url'])) {
            return throw \Illuminate\Validation\ValidationException::withMessages([
                'article_url' => 'Fuer Online-Bilder ist die Beitrags-URL als Nachweis erforderlich.',
            ]);
        }

        $total = ($images * $ppi) + ($minutes * $ppm);

        $record->fill([
            'news_item_id' => $validated['news_item_id'],
            'organization_id' => $validated['organization_id'],
            'product_id' => $validated['product_id'],
            'used_at' => $validated['used_at'],
            'images_count' => $images,
            'video_minutes' => $minutes,
            'price_per_image' => $ppi,
            'price_per_minute' => $ppm,
            'total_amount' => $total,
            'article_url' => $validated['article_url'] ?? null,
            'usage_format' => trim((string) $validated['usage_format']),
            'usage_rights' => isset($validated['usage_rights']) && trim((string) $validated['usage_rights']) !== '' ? trim((string) $validated['usage_rights']) : null,
            'reference_code' => isset($validated['reference_code']) && trim((string) $validated['reference_code']) !== '' ? trim((string) $validated['reference_code']) : null,
            'line_item_note' => isset($validated['line_item_note']) && trim((string) $validated['line_item_note']) !== '' ? trim((string) $validated['line_item_note']) : null,
            'auto_detected' => false,
            'confirmed' => true,
            'billing_department' => $validated['billing_department'] ?? null,
            'billing_type' => $validated['billing_type'] ?? null,
        ]);

        if ($isNew) {
            $record->created_by = Auth::id();
        }

        $record->save();

        return $record->fresh(['newsItem', 'organization', 'product']);
    }
}
