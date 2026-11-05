<?php

return [
    'version' => env('APP_VERSION', '1.0.0'),
    'backup_before_update' => (bool) env('UPDATE_BACKUP_BEFORE_UPDATE', true),
    'package_dir' => env('UPDATE_PACKAGE_DIR', storage_path('app/updates')),
    // Signed update packages: when set, apply() refuses any package whose
    // signature.txt does not verify. Empty = accept anything (self-hosting).
    'release_key' => env('UPDATE_RELEASE_KEY', ''),
];
