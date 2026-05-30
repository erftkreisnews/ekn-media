<?php

namespace App\Services\Lexware;

use App\Models\Product;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class LexwareContactService
{
    public function __construct(
        protected LexwareClient $client
    ) {}

    /**
     * Stellt sicher, dass eine Lexware-Kontakt-ID (UUID) vorliegt: lokal, vom Medienhaus,
     * oder per Lexware-API anhand der Kundennummer (Buyer Reference).
     */
    public function ensureContact(Product $product): void
    {
        $product->loadMissing('organization');
        $product->refresh();

        $productContactId = trim((string) ($product->lexware_contact_id ?? ''));
        if ($productContactId !== '') {
            return;
        }

        $orgContactId = trim((string) ($product->organization?->lexware_contact_id ?? ''));
        if ($orgContactId !== '') {
            $product->lexware_contact_id = $orgContactId;
            $product->save();

            return;
        }

        if (trim((string) config('lexware.api_token', '')) === '') {
            throw new RuntimeException('Für diese Redaktion ist keine Lexware Kontakt-ID hinterlegt. Bitte zuerst Lexware Kontakt-ID in der Redaktion pflegen (oder LEXWARE_API_TOKEN in der Umgebung setzen, damit die Zuordnung über die Kundennummer möglich ist).');
        }

        $resolved = $this->resolveContactIdViaApiByBuyerReference($product);
        if ($resolved !== null && $resolved !== '') {
            $product->lexware_contact_id = $resolved;
            $product->save();
            if ($product->organization && trim((string) ($product->organization->lexware_contact_id ?? '')) === '') {
                $product->organization->lexware_contact_id = $resolved;
                $product->organization->save();
            }

            return;
        }

        $hint = $product->resolvedBuyerReference() !== ''
            ? ' (Kundennummer in EKN: '.$product->resolvedBuyerReference().')'
            : '';

        throw new RuntimeException(
            'Für diese Redaktion ist keine Lexware Kontakt-ID hinterlegt und unter Lexware wurde kein Kontakt mit passender Kundennummer gefunden'.$hint.'. '
            .'Bitte Kundennummer in EKN prüfen oder die Lexware-Kontakt-ID manuell in der Redaktion eintragen.'
        );
    }

    /**
     * Sucht per Lexware Public API einen Kundenkontakt mit passender Kundennummer (roles.customer.number).
     */
    public function resolveContactIdViaApiByBuyerReference(Product $product): ?string
    {
        $buyerRef = $product->resolvedBuyerReference();
        if ($buyerRef === '') {
            return null;
        }

        $page = 0;
        $size = 100;
        $maxPages = 30;

        while ($page < $maxPages) {
            $response = $this->client->get('/v1/contacts?customer=true&page='.$page.'&size='.$size);
            if ($response->failed()) {
                Log::warning('LexwareContactService: Kontaktliste konnte nicht geladen werden.', [
                    'status' => $response->status(),
                    'page' => $page,
                ]);

                return null;
            }

            $data = $response->json();
            if (! is_array($data)) {
                return null;
            }

            $content = $data['content'] ?? [];
            foreach ($content as $contact) {
                if (! is_array($contact)) {
                    continue;
                }
                $num = $this->extractLexwareCustomerNumber($contact);
                if ($num !== null && (string) $num === (string) $buyerRef) {
                    $id = trim((string) ($contact['id'] ?? ''));
                    if ($id !== '') {
                        return $id;
                    }
                }
            }

            $last = (bool) ($data['last'] ?? true);
            if ($last) {
                break;
            }
            $page++;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $contact
     */
    protected function extractLexwareCustomerNumber(array $contact): ?string
    {
        $roles = $contact['roles'] ?? null;
        if (! is_array($roles)) {
            return null;
        }
        $customer = $roles['customer'] ?? null;
        if (! is_array($customer)) {
            return null;
        }
        $number = $customer['number'] ?? null;
        if ($number === null || $number === '') {
            return null;
        }

        return trim((string) $number);
    }
}
