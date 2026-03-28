<?php

return [
    'sender' => [
        'name' => env('INVOICE_SENDER_NAME', 'Alexander Franz'),
        'street' => env('INVOICE_SENDER_STREET', 'Konrad-Adenauer-Str. 22'),
        'postal_code' => env('INVOICE_SENDER_POSTAL_CODE', '50389'),
        'city' => env('INVOICE_SENDER_CITY', 'Wesseling'),
        'country_code' => env('INVOICE_SENDER_COUNTRY_CODE', 'DE'),
        'phone' => env('INVOICE_SENDER_PHONE', '+49 2236-4809480'),
        'fax' => env('INVOICE_SENDER_FAX', '+49 2236-4809489'),
        'email' => env('INVOICE_SENDER_EMAIL', 'afranz@erftkreis-news.de'),
        'website' => env('INVOICE_SENDER_WEBSITE', 'www.erftkreis-news.de'),
        'tax_number' => env('INVOICE_SENDER_TAX_NUMBER', '22450803906'),
        'vat_id' => env('INVOICE_SENDER_VAT_ID'),
    ],
    'bank' => [
        'name' => env('INVOICE_BANK_NAME', 'Finom'),
        'iban' => env('INVOICE_BANK_IBAN', 'DE53 1001 8000 0851 3987 47'),
        'bic' => env('INVOICE_BANK_BIC', 'FNOMDEB2XXX'),
    ],
    'payment' => [
        'days' => (int) env('INVOICE_PAYMENT_DAYS', 7),
        'vat_rate' => (float) env('INVOICE_VAT_RATE', 7),
    ],
    'einvoice' => [
        'archive_disk' => env('INVOICE_EINVOICE_DISK', 'local'),
        'archive_directory' => env('INVOICE_EINVOICE_DIRECTORY', 'invoices/zugferd'),
        'document_number_prefix' => env('INVOICE_EINVOICE_PREFIX', 'EKN-'),
    ],
];
