<?php

namespace App\Services;

use App\Models\Contact;
use Illuminate\Support\Str;

class WdrRecipientGuard
{
    /**
     * Prüft, ob die E-Mail-Adresse ein zulässiger WDR-Empfänger ist
     * (Westdeutscher Rundfunk – für Meldungen mit MoID nur diese erlaubt).
     */
    public function isWdrRecipient(string $email): bool
    {
        $email = Str::lower(trim($email));
        if ($email === '') {
            return false;
        }

        $names = config('newsdesk.wdr_organization_names', ['WDR', 'Westdeutscher Rundfunk']);
        $contact = Contact::whereNotNull('organization_id')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->with('organization')
            ->first();
        if ($contact && $contact->organization) {
            $orgName = $contact->organization->name ?? '';
            foreach ($names as $needle) {
                if (Str::contains(Str::lower($orgName), Str::lower($needle))) {
                    return true;
                }
            }
        }

        $allowed = config('newsdesk.wdr_allowed_emails', []);
        foreach ($allowed as $allowedEntry) {
            $allowedEntry = Str::lower(trim($allowedEntry));
            if ($allowedEntry === $email) {
                return true;
            }
            if (Str::startsWith($allowedEntry, '@') && Str::endsWith($email, $allowedEntry)) {
                return true;
            }
        }

        return false;
    }
}
