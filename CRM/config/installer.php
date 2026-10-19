<?php

return [
    'min_php' => '8.2.0',
    'required_extensions' => ['openssl', 'pdo', 'mbstring', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath', 'fileinfo', 'sodium'],
    'required_writable' => ['storage', 'bootstrap/cache', 'public/storage'],
    'lock_file' => storage_path('framework/installed.lock'),
];
