<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\SiteSetting;
use App\Models\UsageRecord;

/**
 * WDR Newsroom: gestaffelte Bildpreise (1. Bild / 2–4 / ab 5) aus Site-Settings,
 * konsistent mit {@see \App\Http\Controllers\Admin\UsageReportController::calculateNewsroomImageTotal}.
 */
final class WdrNewsroomImageTierLines
{
    public function __construct(
        private readonly float $first,
        private readonly float $additional,
        private readonly float $fromFive,
    ) {}

    public static function make(): self
    {
        return new self(
            self::readDecimal(SiteSetting::WDR_NEWSROOM_IMAGE_FIRST_PRICE, 42.37),
            self::readDecimal(SiteSetting::WDR_NEWSROOM_IMAGE_ADDITIONAL_PRICE, 28.25),
            self::readDecimal(SiteSetting::WDR_NEWSROOM_IMAGE_FROM_FIVE_PRICE, 28.25),
        );
    }

    private static function readDecimal(string $key, float $default): float
    {
        $raw = SiteSetting::get($key);
        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return $default;
        }
        $value = (float) $raw;

        return $value < 0 ? $default : round($value, 2);
    }

    public static function appliesTo(UsageRecord $record, Invoice $invoice): bool
    {
        if ((int) $record->images_count <= 0) {
            return false;
        }
        if (($record->billing_department ?? null) === 'newsroom') {
            return true;
        }

        return str_contains(mb_strtolower((string) ($invoice->product?->name ?? '')), 'newsroom');
    }

    /**
     * @return array{first: float, additional: float, from_five: float}
     */
    public function tariff(): array
    {
        return [
            'first' => $this->first,
            'additional' => $this->additional,
            'from_five' => $this->fromFive,
        ];
    }

    /**
     * Positionszeilen für PDF/Vorschau (Einzelpreise je Staffel).
     *
     * @return list<array{title: string, quantity: float, unit_price: float, line_total: float, tier_label: string}>
     */
    public function linesForImageCount(int $n): array
    {
        if ($n <= 0) {
            return [];
        }

        if ($n === 1) {
            return [[
                'title' => 'Online-Nutzung Foto - 1. Bild',
                'quantity' => 1.0,
                'unit_price' => $this->first,
                'line_total' => round($this->first, 2),
                'tier_label' => 'Staffel: 1. Bild',
            ]];
        }

        $rows = [[
            'title' => 'Online-Nutzung Foto - 1. Bild',
            'quantity' => 1.0,
            'unit_price' => $this->first,
            'line_total' => round($this->first, 2),
            'tier_label' => 'Staffel: 1. Bild',
        ]];

        $additionalCount = min(3, $n - 1);
        $fromFiveCount = max(0, $n - 1 - 3);

        if ($additionalCount > 0) {
            $rows[] = [
                'title' => 'Online-Nutzung Foto - Bilder 2 bis 4',
                'quantity' => (float) $additionalCount,
                'unit_price' => $this->additional,
                'line_total' => round($additionalCount * $this->additional, 2),
                'tier_label' => 'Staffel: Bild 2-4',
            ];
        }

        if ($fromFiveCount > 0) {
            $rows[] = [
                'title' => 'Online-Nutzung Foto - ab Bild 5',
                'quantity' => (float) $fromFiveCount,
                'unit_price' => $this->fromFive,
                'line_total' => round($fromFiveCount * $this->fromFive, 2),
                'tier_label' => 'Staffel: ab Bild 5',
            ];
        }

        return $rows;
    }
}
