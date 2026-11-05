<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Web Push (VAPID)
    |--------------------------------------------------------------------------
    |
    | Keys are generated on first use and stored under storage/app so each
    | installation keeps a stable identity. You may pin them with env vars.
    |
    */
    'subject' => env('WEBPUSH_SUBJECT') ?: 'mailto:'.env('MAIL_FROM_ADDRESS', 'noreply@localhost'),
    'public_key' => (string) env('WEBPUSH_PUBLIC_KEY', ''),
    'private_key' => (string) env('WEBPUSH_PRIVATE_KEY', ''),
    'vapid_file' => storage_path('app/webpush_vapid.json'),
    'openssl_conf' => env('WEBPUSH_OPENSSL_CONF'),
    'ca_bundle' => env('WEBPUSH_CA_BUNDLE'),
    'timeout' => (int) env('WEBPUSH_TIMEOUT', 8),
];
