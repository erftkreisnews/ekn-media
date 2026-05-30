<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\UsageRecordCreatedMail;
use App\Models\NewsItem;
use App\Models\Organization;
use App\Models\Product;
use App\Models\SiteSetting;
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
        $monthInput = trim((string) $request->query('month', ''));
        $anchor = Carbon::now();
        if (preg_match('/^\d{4}-\d{2}$/', $monthInput) === 1) {
            try {
                $anchor = Carbon::createFromFormat('Y-m', $monthInput)->startOfMonth();
            } catch (\Throwable) {
                $anchor = Carbon::now();
            }
        }

        [$start, $end, $label] = $this->resolvePeriod($period, $anchor);

        $query = UsageRecord::with(['newsItem', 'organization', 'product', 'invoice'])
            ->whereBetween('used_at', [$start, $end])
            ->orderByDesc('used_at')
            ->orderByDesc('id');

        $user = Auth::user();
        $isAdmin = $user?->hasRole('admin') ?? false;
        if (! $isAdmin) {
            $query->where('created_by', (int) Auth::id());
        }

        $records = $query->limit(100)->get();
        $focusRecordId = (int) $request->query('focus_record', 0);
        $focusRecord = $focusRecordId > 0
            ? $records->firstWhere('id', $focusRecordId)
            : null;

        $net = (float) $records->sum('total_amount');
        $vatRate = 0.07;
        $vat = round($net * $vatRate, 2);
        $gross = $net + $vat;

        $totals = [
            'images' => $records->sum('images_count'),
            'video_minutes' => (float) $records->sum('video_minutes'),
            'radio_minutes' => (float) $records->sum('radio_minutes'),
            'amount' => $net,
            'vat' => $vat,
            'gross' => $gross,
        ];

        return view('admin.backoffice.usage.index', [
            'records' => $records,
            'focusRecord' => $focusRecord,
            'isAdmin' => $isAdmin,
            'totals' => $totals,
            'period' => $period,
            'periodLabel' => $label,
            'start' => $start,
            'end' => $end,
            'month' => $anchor->format('Y-m'),
            'prevMonth' => $anchor->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $anchor->copy()->addMonth()->format('Y-m'),
        ]);
    }

    public function create(): View
    {
        return view('admin.backoffice.usage.create', [
            ...$this->getFormOptions(),
            ...$this->getWdrTariffViewData(),
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
            ...$this->getWdrTariffViewData(),
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
            ->route('admin.backoffice.usage.index', [
                'period' => 'month',
                'month' => optional($record->used_at)->format('Y-m') ?: now()->format('Y-m'),
                'focus_record' => $record->id,
            ])
            ->with('status', 'Nachverfolgungs-Eintrag wurde gespeichert und im Zeitraum hervorgehoben ('.$this->usageRecordNewsLabel($record).').');
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
            ->with('status', 'Nachverfolgungs-Eintrag wurde aktualisiert ('.$this->usageRecordNewsLabel($record).').');
    }

    public function destroy(UsageRecord $usageRecord): RedirectResponse
    {
        if ($usageRecord->invoice_id) {
            return redirect()
                ->route('admin.backoffice.usage.index', ['period' => 'month'])
                ->with('error', 'Dieser Nachverfolgungs-Eintrag ist aktuell gesperrt und kann hier nicht direkt gelöscht werden.');
        }

        $newsLabel = $this->usageRecordNewsLabel($usageRecord);
        $usageRecord->delete();

        return redirect()
            ->route('admin.backoffice.usage.index', ['period' => 'month'])
            ->with('status', 'Nachverfolgungs-Eintrag wurde gelöscht ('.$newsLabel.').');
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
    private function resolvePeriod(string $period, Carbon $anchor): array
    {
        $now = $anchor->copy();

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
            'news_item_id' => ['nullable', 'integer', 'exists:news_items,id'],
            'organization_id' => ['required', 'exists:organizations,id'],
            'product_id' => ['required', 'exists:products,id'],
            'used_at' => ['required', 'date'],
            'images_count' => ['nullable', 'integer', 'min:0'],
            'video_minutes' => ['nullable', 'numeric', 'min:0'],
            'radio_minutes' => ['nullable', 'numeric', 'min:0'],
            'print_copies' => ['nullable', 'integer', 'min:0'],
            'price_per_image' => ['nullable', 'numeric', 'min:0'],
            'price_per_minute' => ['nullable', 'numeric', 'min:0'],
            'article_url' => ['nullable', 'url'],
            'text_taken_over' => ['nullable', 'boolean'],
            'text_taken_over_excerpt' => ['nullable', 'string', 'max:5000'],
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
        $videoMinutes = (float) ($validated['video_minutes'] ?? 0);
        $radioMinutes = (float) ($validated['radio_minutes'] ?? 0);
        $printCopies = (int) ($validated['print_copies'] ?? 0);
        $textTakenOver = (bool) $request->boolean('text_taken_over');
        $ppi = (float) ($validated['price_per_image'] ?? 0);
        $ppmInput = (float) ($validated['price_per_minute'] ?? 0);
        $ppm = $ppmInput;

        if ($videoMinutes > 0 && $radioMinutes > 0) {
            return throw \Illuminate\Validation\ValidationException::withMessages([
                'video_minutes' => 'Video- und Radio-Sendeminuten bitte in getrennten Eintraegen erfassen.',
            ]);
        }
        if ($images <= 0 && $videoMinutes <= 0 && $radioMinutes <= 0 && $printCopies <= 0 && ! $textTakenOver) {
            return throw \Illuminate\Validation\ValidationException::withMessages([
                'images_count' => 'Bitte mindestens Bilder, Videominuten, Radio-Sendeminuten, Print-Auflage oder "Text übernommen" angeben.',
            ]);
        }
        if (
            $images > 0
            && $this->isOnlineUsageFormat((string) $validated['usage_format'])
            && $printCopies <= 0
            && empty($validated['article_url'])
        ) {
            return throw \Illuminate\Validation\ValidationException::withMessages([
                'article_url' => 'Fuer Online-Bilder ist die Beitrags-URL als Nachweis erforderlich.',
            ]);
        }

        if ($this->usesNewsroomTariff($validated['billing_department'] ?? null, $product)) {
            $ppmVideo = $this->getWdrNewsroomVideoPricePerMinute();
            $ppmAudio = $this->getWdrNewsroomAudioPricePerMinute();
            $imageTotal = $this->calculateNewsroomImageTotal($images);
            $videoTotal = round($videoMinutes * $ppmVideo, 2);
            $radioTotal = round($radioMinutes * $ppmAudio, 2);
            $total = round($imageTotal + $videoTotal + $radioTotal, 2);
            // In der Datenbank bleibt ein Einzelwert erhalten; für die gestaffelte Bildlogik
            // speichern wir den effektiven Durchschnittspreis pro Bild.
            $ppi = $images > 0 ? round($imageTotal / $images, 2) : 0.0;
            if ($videoMinutes > 0) {
                $ppm = $ppmVideo;
            } elseif ($radioMinutes > 0) {
                $ppm = $ppmAudio;
            } else {
                $ppm = $ppmVideo;
            }
        } elseif ($this->usesTag24Tariff($product)) {
            $imageTotal = $this->calculateTag24ImageTotal($images);
            $videoTotal = round($videoMinutes * $ppm, 2);
            $radioTotal = round($radioMinutes * $ppm, 2);
            $total = round($imageTotal + $videoTotal + $radioTotal, 2);
            // Effektiver Durchschnittspreis pro Bild für korrekte Summenabbildung in bestehenden Feldern.
            $ppi = $images > 0 ? round($imageTotal / $images, 2) : 0.0;
        } else {
            $total = ($images * $ppi) + ($videoMinutes * $ppm) + ($radioMinutes * $ppm);
        }
        $billingTypeInput = $validated['billing_type'] ?? null;
        $billingType = in_array($billingTypeInput, ['lizenz', 'honorar'], true)
            ? $billingTypeInput
            : 'lizenz';
        if ($billingType === 'honorar' && $images > 0) {
            return throw \Illuminate\Validation\ValidationException::withMessages([
                'billing_type' => 'Honorar ist für Audio vorgesehen. Bei Bildern oder Video bitte Lizenz verwenden.',
            ]);
        }
        if ($billingType === 'honorar' && $videoMinutes <= 0 && $radioMinutes <= 0) {
            return throw \Illuminate\Validation\ValidationException::withMessages([
                'radio_minutes' => 'Für Honorar (Audio) bitte Radio-Sendeminuten angeben (oder Legacy: Videominuten-Feld).',
            ]);
        }

        $newsItemId = isset($validated['news_item_id']) && (int) $validated['news_item_id'] > 0
            ? (int) $validated['news_item_id']
            : null;

        $record->fill([
            'news_item_id' => $newsItemId,
            'organization_id' => $validated['organization_id'],
            'product_id' => $validated['product_id'],
            'used_at' => $validated['used_at'],
            'images_count' => $images,
            'video_minutes' => $videoMinutes,
            'radio_minutes' => $radioMinutes,
            'print_copies' => $printCopies,
            'price_per_image' => $ppi,
            'price_per_minute' => $ppm,
            'total_amount' => $total,
            'article_url' => $validated['article_url'] ?? null,
            'text_taken_over' => $textTakenOver,
            'text_taken_over_excerpt' => isset($validated['text_taken_over_excerpt']) && trim((string) $validated['text_taken_over_excerpt']) !== ''
                ? trim((string) $validated['text_taken_over_excerpt'])
                : null,
            'usage_format' => trim((string) $validated['usage_format']),
            'usage_rights' => isset($validated['usage_rights']) && trim((string) $validated['usage_rights']) !== '' ? trim((string) $validated['usage_rights']) : null,
            'reference_code' => isset($validated['reference_code']) && trim((string) $validated['reference_code']) !== '' ? trim((string) $validated['reference_code']) : null,
            'line_item_note' => isset($validated['line_item_note']) && trim((string) $validated['line_item_note']) !== '' ? trim((string) $validated['line_item_note']) : null,
            'auto_detected' => false,
            'confirmed' => true,
            'billing_department' => $validated['billing_department'] ?? null,
            'billing_type' => $billingType,
        ]);

        if ($isNew) {
            $record->created_by = Auth::id();
        }

        $record->save();

        return $record->fresh(['newsItem', 'organization', 'product']);
    }

    private function usesNewsroomTariff(?string $billingDepartment, Product $product): bool
    {
        if ($billingDepartment === 'newsroom') {
            return true;
        }

        return str_contains(mb_strtolower((string) $product->name), 'newsroom');
    }

    private function calculateNewsroomImageTotal(int $images): float
    {
        $first = $this->getWdrNewsroomImageFirstPrice();
        $additionalUntilFour = $this->getWdrNewsroomImageAdditionalPrice();
        $fromFive = $this->getWdrNewsroomImageFromFivePrice();

        if ($images <= 0) {
            return 0.0;
        }
        if ($images === 1) {
            return $first;
        }
        if ($images <= 4) {
            return round($first + (($images - 1) * $additionalUntilFour), 2);
        }

        return round(
            $first + (3 * $additionalUntilFour) + (($images - 4) * $fromFive),
            2
        );
    }

    private function getWdrNewsroomVideoPricePerMinute(): float
    {
        return $this->getPositiveDecimalSetting(
            SiteSetting::WDR_NEWSROOM_VIDEO_PRICE_PER_MINUTE,
            448.60
        );
    }

    private function getWdrNewsroomImageFirstPrice(): float
    {
        return $this->getPositiveDecimalSetting(
            SiteSetting::WDR_NEWSROOM_IMAGE_FIRST_PRICE,
            42.37
        );
    }

    private function getWdrNewsroomImageAdditionalPrice(): float
    {
        return $this->getPositiveDecimalSetting(
            SiteSetting::WDR_NEWSROOM_IMAGE_ADDITIONAL_PRICE,
            28.25
        );
    }

    private function getWdrNewsroomImageFromFivePrice(): float
    {
        return $this->getPositiveDecimalSetting(
            SiteSetting::WDR_NEWSROOM_IMAGE_FROM_FIVE_PRICE,
            28.25
        );
    }

    private function getWdrNewsroomAudioPricePerMinute(): float
    {
        return $this->getPositiveDecimalSetting(
            SiteSetting::WDR_NEWSROOM_AUDIO_PRICE_PER_MINUTE,
            0.00
        );
    }

    private function getPositiveDecimalSetting(string $key, float $default): float
    {
        $raw = SiteSetting::get($key);
        if ($raw === null || $raw === '') {
            return $default;
        }

        if (! is_numeric($raw)) {
            return $default;
        }

        $value = (float) $raw;
        if ($value < 0) {
            return $default;
        }

        return round($value, 2);
    }

    private function getWdrTariffViewData(): array
    {
        $video = $this->getWdrNewsroomVideoPricePerMinute();
        $audio = $this->getWdrNewsroomAudioPricePerMinute();
        $first = $this->getWdrNewsroomImageFirstPrice();
        $additional = $this->getWdrNewsroomImageAdditionalPrice();
        $fromFive = $this->getWdrNewsroomImageFromFivePrice();

        return [
            'wdrNewsroomTariff' => [
                'video_per_minute' => $video,
                'audio_per_minute' => $audio,
                'image_first' => $first,
                'image_additional' => $additional,
                'image_from_five' => $fromFive,
            ],
            'wdrNewsroomTariffJs' => [
                'video_per_minute' => number_format($video, 2, '.', ''),
                'audio_per_minute' => number_format($audio, 2, '.', ''),
                'image_first' => number_format($first, 2, '.', ''),
                'image_additional' => number_format($additional, 2, '.', ''),
                'image_from_five' => number_format($fromFive, 2, '.', ''),
            ],
        ];
    }

    private function usesTag24Tariff(Product $product): bool
    {
        $name = mb_strtolower((string) $product->name);
        $org = mb_strtolower((string) optional($product->organization)->name);

        return str_contains($name, 'tag24') || str_contains($org, 'tag24');
    }

    /**
     * TAG24-Staffel: 1. Bild 10,00 EUR, jedes weitere Bild 5,00 EUR.
     */
    private function calculateTag24ImageTotal(int $images): float
    {
        if ($images <= 0) {
            return 0.0;
        }
        if ($images === 1) {
            return 10.00;
        }

        return round(10.00 + (($images - 1) * 5.00), 2);
    }

    private function usageRecordNewsLabel(UsageRecord $record): string
    {
        return $record->news_item_id
            ? 'News '.$record->news_item_id
            : 'Freies Angebot';
    }

    private function isOnlineUsageFormat(string $usageFormat): bool
    {
        $value = mb_strtolower(trim($usageFormat));
        if ($value === '') {
            return false;
        }

        $onlineMarkers = ['online', 'web', 'app', 'digital', 'social', 'portal'];
        foreach ($onlineMarkers as $marker) {
            if (str_contains($value, $marker)) {
                return true;
            }
        }

        return false;
    }
}
