<?php

return [
    'from_address' => env('INVOICE_MAIL_FROM_ADDRESS', 'rechnung@erftkreis-news.de'),
    'from_name' => env('INVOICE_MAIL_FROM_NAME', 'Erftkreis News Rechnung'),
    'header_stream' => env('INVOICE_MAIL_HEADER_STREAM', 'invoice'),
    'header_source' => env('INVOICE_MAIL_HEADER_SOURCE', 'laravel-billing'),
    'test_recipient' => env('INVOICE_MAIL_TEST_RECIPIENT'),
];
