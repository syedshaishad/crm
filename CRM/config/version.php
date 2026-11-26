<?php

return [
    'version' => env('APP_VERSION', '1.0.0'),
    'backup_before_update' => (bool) env('UPDATE_BACKUP_BEFORE_UPDATE', true),
    'package_dir' => env('UPDATE_PACKAGE_DIR', storage_path('app/updates')),
    // Deprecated HMAC escape hatch for packages built before signed manifests.
    // Requires BOTH switches; the default install never accepts unsigned zips.
    'release_key' => env('UPDATE_RELEASE_KEY', ''),
    'allow_legacy_signed_packages' => (bool) env('UPDATE_ALLOW_LEGACY_SIGNED', false),
];
