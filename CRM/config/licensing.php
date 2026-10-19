<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Licensify server licensing (license.shaishad.com).
    | Every install phones this server: activate once, validate on a schedule,
    | bounded offline grace via signed certificates. Fail-closed throughout:
    | no key, no valid certificate, no usable app. There is no open mode.
    |--------------------------------------------------------------------------
    */
    'server_url' => rtrim((string) env('LICENSE_SERVER_URL', 'https://license.shaishad.com'), '/'),
    'product_code' => (string) env('LICENSE_PRODUCT_CODE', 'SSCCRM1000V1'),
    // Customer license key (XXXX-XXXX-...). Env wins; else storage/app/license.key.
    'license_key' => (string) env('LICENSE_KEY', ''),
    // Product API credentials (reseller provisions these per install).
    'api_key' => (string) env('LICENSE_API_KEY', ''),
    'api_secret' => (string) env('LICENSE_API_SECRET', ''),
    'timeout' => (int) env('LICENSE_TIMEOUT', 10),
    'verify_ssl' => (bool) env('LICENSE_VERIFY_SSL', true),
    // At most one server re-validation per N minutes while serving from cache.
    'revalidate_minutes' => (int) env('LICENSE_REVALIDATE_MINUTES', 60),
    // Installation id file (auto-generated, stable across restarts).
    'installation_file' => storage_path('app/.installation_id'),
    // Cached certificate + keyring + last-check state.
    'certificate_file' => storage_path('app/.license_certificate.json'),
    'keyring_file' => storage_path('app/.license_keyring.json'),
    // Local module toggles still work, but only when the license is active.
    'enforce_users_limit' => false,
    'enforce_modules' => false,
];
