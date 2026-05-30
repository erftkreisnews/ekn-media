<?php

namespace App\Services\Billing;

use App\Models\Invoice;

class InvoiceDispatchStatusService
{
    public function evaluate(Invoice $invoice): array
    {
        $invoice->loadMissing([
            'organization',
            'product',
            'contact',
        ]);

        $blockers = [];
        $warnings = [];
        $reviews = [];

        $product = $invoice->product;
        $organization = $invoice->organization;
        $contact = $invoice->contact;
        $usageRecordCount = $this->resolveUsageRecordCount($invoice);
        $hasForeignRecipient = $product ? $this->mapCountryCode($product->billing_country) !== 'DE' : false;
        $hasLexwareFinalization = $this->hasLexwareFinalization($invoice);
        $isLexwareVoided = $this->isLexwareVoided($invoice);

        if (! $organization) {
            $blockers[] = 'Dem Entwurf ist kein Kunde zugeordnet.';
        }

        if (! $product) {
            $blockers[] = 'Dem Entwurf ist keine Rechnungseinheit zugeordnet.';
        }

        if (! $contact) {
            $blockers[] = 'Es ist kein Rechnungsempfänger ausgewählt.';
        }

        if ($usageRecordCount === 0) {
            $blockers[] = 'Die Rechnung enthält noch keine abrechenbaren Positionen.';
        }

        if (! $invoice->voucher_date) {
            $blockers[] = 'Für die Rechnung fehlt das Rechnungsdatum.';
        }

        if ($product) {
            $recipientName = trim((string) ($product->billing_company ?: $organization?->name ?: ''));
            if ($recipientName === '') {
                $blockers[] = 'Für den Rechnungsempfänger fehlt der Firmen- oder Kundenname.';
            }

            if (trim((string) ($product->billing_street ?? '')) === '') {
                $blockers[] = 'Für den Rechnungsempfänger fehlt die Straße.';
            }

            if (trim((string) ($product->billing_postal_code ?? '')) === '' || trim((string) ($product->billing_city ?? '')) === '') {
                $blockers[] = 'Für den Rechnungsempfänger fehlen PLZ oder Ort.';
            }

            if ($hasForeignRecipient) {
                $reviews[] = 'Die Rechnungsadresse liegt im Ausland. Steuerlogik und Versandfall bitte manuell prüfen.';
            }

            if ($product->resolvedBuyerReference() === '') {
                $warnings[] = 'Die Kundennummer bzw. Buyer Reference ist noch nicht gepflegt.';
            }
        }

        if ($contact) {
            if (trim((string) ($contact->name ?? '')) === '') {
                $warnings[] = 'Für den Ansprechpartner fehlt der Name.';
            }

            if (trim((string) ($contact->email ?? '')) === '') {
                $warnings[] = 'Für den Rechnungsempfänger fehlt eine E-Mail-Adresse.';
            }
        }

        if ((float) $invoice->total_net <= 0) {
            $warnings[] = 'Der Netto-Betrag ist 0 oder negativ. Bitte den Beleg fachlich prüfen.';
        }

        if ($isLexwareVoided) {
            $blockers[] = 'Diese Rechnung wurde in Lexware storniert und darf nicht versendet werden.';
        }

        $sender = config('invoice.sender', []);
        if ($hasForeignRecipient && trim((string) ($sender['vat_id'] ?? '')) === '') {
            $reviews[] = 'Für Auslandsfälle ist aktuell keine USt-Id des Absenders hinterlegt.';
        }

        $readinessStatus = $this->resolveReadinessStatus($blockers, $reviews, $warnings);
        $dispatch = $this->evaluateDispatchCapability($invoice, $readinessStatus, $blockers, $reviews, $warnings, $hasLexwareFinalization);

        return [
            'status' => $readinessStatus,
            'label' => $this->labelForReadinessStatus($readinessStatus),
            'badge_classes' => $this->badgeClassesForReadinessStatus($readinessStatus),
            'summary' => $this->summaryForReadinessStatus($readinessStatus),
            'blockers' => $blockers,
            'reviews' => $reviews,
            'warnings' => $warnings,
            'has_lexware_finalization' => $hasLexwareFinalization,
            'can_finalize_lexware' => $readinessStatus !== 'blocked' && ! $hasLexwareFinalization,
            'dispatch' => $dispatch,
            'zugferd' => $this->evaluateZugferdCapability($invoice, $readinessStatus, $blockers, $reviews, $warnings, $hasLexwareFinalization),
        ];
    }

    protected function evaluateDispatchCapability(
        Invoice $invoice,
        string $readinessStatus,
        array $blockers,
        array $reviews,
        array $warnings,
        bool $hasLexwareFinalization
    ): array {
        $sentAt = $this->resolveSentAt($invoice);
        $dispatchMessages = [];

        if ($blockers !== []) {
            $status = 'blocked';
            $dispatchMessages = $blockers;
        } elseif (! $hasLexwareFinalization) {
            $status = 'pending_lexware';
            $dispatchMessages[] = 'Vor dem Versand muss Lexware die finale Rechnungsnummer vergeben.';
        } elseif ($reviews !== []) {
            $status = 'review';
            $dispatchMessages = $reviews;
        } elseif ($sentAt !== null) {
            $status = 'sent';
            $dispatchMessages[] = 'Die Rechnung wurde bereits aus EKN versendet.';
        } elseif ($warnings !== []) {
            $status = 'warning';
            $dispatchMessages = $warnings;
        } else {
            $status = 'ready';
            $dispatchMessages[] = 'Die Rechnung ist final nummeriert und kann jetzt aus EKN versendet werden.';
        }

        if (trim((string) ($invoice->contact?->email ?? '')) === '') {
            $status = 'blocked';
            $dispatchMessages[] = 'Für den Versand fehlt eine Empfänger-E-Mail-Adresse.';
        }

        $dispatchMessages = array_values(array_unique($dispatchMessages));

        return [
            'status' => $status,
            'label' => $this->labelForDispatchStatus($status),
            'badge_classes' => $this->badgeClassesForDispatchStatus($status),
            'summary' => $this->summaryForDispatchStatus($status, $readinessStatus),
            'messages' => $dispatchMessages,
            'can_send' => in_array($status, ['ready', 'warning', 'sent'], true),
            'sent_at' => $sentAt,
        ];
    }

    protected function evaluateZugferdCapability(
        Invoice $invoice,
        string $readinessStatus,
        array $blockers,
        array $reviews,
        array $warnings,
        bool $hasLexwareFinalization
    ): array {
        $zugferdBlockers = [];
        $sender = config('invoice.sender', []);
        $bank = config('invoice.bank', []);

        if ($readinessStatus === 'blocked') {
            $zugferdBlockers = array_merge($zugferdBlockers, $blockers);
        }

        if (! $hasLexwareFinalization) {
            $zugferdBlockers[] = 'ZUGFeRD wird erst nach erfolgreicher Lexware-Finalisierung freigegeben.';
        }

        if (strtoupper((string) ($invoice->currency ?? 'EUR')) !== 'EUR') {
            $zugferdBlockers[] = 'Der aktuelle ZUGFeRD-MVP unterstützt nur Rechnungen in EUR.';
        }

        if ($invoice->product && $this->mapCountryCode($invoice->product->billing_country) !== 'DE') {
            $zugferdBlockers[] = 'Der aktuelle ZUGFeRD-MVP ist nur für deutsche Standardfälle freigegeben.';
        }

        foreach ([
            'name' => 'Absendername',
            'street' => 'Absenderstraße',
            'postal_code' => 'Absender-PLZ',
            'city' => 'Absender-Ort',
            'email' => 'Absender-E-Mail',
            'tax_number' => 'Steuernummer',
        ] as $key => $label) {
            if (trim((string) ($sender[$key] ?? '')) === '') {
                $zugferdBlockers[] = "Für ZUGFeRD fehlt {$label}.";
            }
        }

        if (trim((string) ($bank['iban'] ?? '')) === '') {
            $zugferdBlockers[] = 'Für ZUGFeRD fehlt die IBAN.';
        }

        $allowed = $zugferdBlockers === [];
        $messages = $allowed ? $warnings : array_values(array_unique(array_merge($zugferdBlockers, $reviews)));

        return [
            'allowed' => $allowed,
            'status' => $allowed ? ($warnings !== [] ? 'warning' : 'ready') : 'blocked',
            'label' => $allowed ? 'ZUGFeRD freigegeben' : 'ZUGFeRD gesperrt',
            'badge_classes' => $allowed ? 'bg-violet-100 text-violet-900' : 'bg-gray-100 text-gray-700',
            'summary' => $allowed
                ? 'Die ZUGFeRD-Datei kann jetzt mit der finalen Lexware-Rechnungsnummer erzeugt werden.'
                : 'Die ZUGFeRD-Datei bleibt bis zur Lexware-Finalisierung oder bei Sonderfällen gesperrt.',
            'messages' => $messages,
        ];
    }

    protected function resolveUsageRecordCount(Invoice $invoice): int
    {
        if ($invoice->relationLoaded('usageRecords')) {
            return $invoice->usageRecords->count();
        }

        if (array_key_exists('usage_records_count', $invoice->getAttributes())) {
            return (int) ($invoice->usage_records_count ?? 0);
        }

        return $invoice->usageRecords()->count();
    }

    protected function resolveReadinessStatus(array $blockers, array $reviews, array $warnings): string
    {
        if ($blockers !== []) {
            return 'blocked';
        }

        if ($reviews !== []) {
            return 'review';
        }

        if ($warnings !== []) {
            return 'warning';
        }

        return 'ready';
    }

    protected function labelForReadinessStatus(string $status): string
    {
        return match ($status) {
            'blocked' => 'Nicht versendbar',
            'review' => 'Manuell prüfen',
            'warning' => 'Mit Warnung',
            default => 'Fachlich bereit',
        };
    }

    protected function summaryForReadinessStatus(string $status): string
    {
        return match ($status) {
            'blocked' => 'Vor dem Versand fehlen noch echte Pflichtdaten.',
            'review' => 'Die Rechnung ist grundsätzlich möglich, sollte fachlich aber manuell geprüft werden.',
            'warning' => 'Die Rechnung ist fachlich nutzbar, enthält aber Hinweise zur Datenqualität.',
            default => 'Die Rechnungsdaten sind vollständig genug für die Lexware-Finalisierung.',
        };
    }

    protected function badgeClassesForReadinessStatus(string $status): string
    {
        return match ($status) {
            'blocked' => 'bg-red-100 text-red-800',
            'review' => 'bg-amber-100 text-amber-900',
            'warning' => 'bg-yellow-100 text-yellow-900',
            default => 'bg-emerald-100 text-emerald-900',
        };
    }

    protected function labelForDispatchStatus(string $status): string
    {
        return match ($status) {
            'blocked' => 'Nicht versendbar',
            'pending_lexware' => 'Warte auf Lexware-Nummer',
            'review' => 'Manuell prüfen',
            'warning' => 'Versandbereit mit Warnung',
            'sent' => 'Versendet',
            default => 'Versandbereit',
        };
    }

    protected function summaryForDispatchStatus(string $status, string $readinessStatus): string
    {
        return match ($status) {
            'blocked' => 'Vor dem Versand müssen noch Pflichtdaten oder Versandangaben ergänzt werden.',
            'pending_lexware' => $readinessStatus === 'ready'
                ? 'Der Entwurf ist fachlich bereit, wartet aber noch auf die finale Lexware-Rechnungsnummer.'
                : 'Vor dem Versand muss zuerst die Lexware-Finalisierung erfolgen.',
            'review' => 'Die Rechnung ist final nummeriert, sollte vor dem Versand aber noch manuell geprüft werden.',
            'warning' => 'Die Rechnung ist final nummeriert und versandbereit, enthält aber Hinweise.',
            'sent' => 'Die Rechnung wurde bereits aus EKN versendet.',
            default => 'Die Rechnung ist final nummeriert und kann jetzt aus EKN versendet werden.',
        };
    }

    protected function badgeClassesForDispatchStatus(string $status): string
    {
        return match ($status) {
            'blocked' => 'bg-red-100 text-red-800',
            'pending_lexware' => 'bg-slate-100 text-slate-800',
            'review' => 'bg-amber-100 text-amber-900',
            'warning' => 'bg-yellow-100 text-yellow-900',
            'sent' => 'bg-blue-100 text-blue-900',
            default => 'bg-emerald-100 text-emerald-900',
        };
    }

    protected function hasLexwareFinalization(Invoice $invoice): bool
    {
        return trim((string) ($invoice->lexware_invoice_id ?? '')) !== ''
            && trim((string) ($invoice->voucher_number ?? '')) !== '';
    }

    protected function isLexwareVoided(Invoice $invoice): bool
    {
        if (trim((string) ($invoice->status ?? '')) === 'lexware_voided') {
            return true;
        }

        $meta = (array) ($invoice->meta ?? []);
        $voucherStatus = mb_strtolower(trim((string) (data_get($meta, 'lexware_payment.voucher_status') ?? data_get($meta, 'lexware.voucher_status') ?? '')));

        return $voucherStatus === 'voided';
    }

    protected function resolveSentAt(Invoice $invoice): ?string
    {
        $meta = (array) ($invoice->meta ?? []);
        $dispatch = (array) ($meta['dispatch'] ?? []);
        $sentAt = trim((string) ($dispatch['sent_at'] ?? ''));

        return $sentAt !== '' ? $sentAt : null;
    }

    protected function mapCountryCode(?string $country): string
    {
        $value = strtoupper(trim((string) ($country ?? '')));

        return match ($value) {
            '', 'DEUTSCHLAND', 'GERMANY', 'DE' => 'DE',
            default => strlen($value) === 2 ? $value : $value,
        };
    }
}
