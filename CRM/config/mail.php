<?php

return [
    'default' => env('MAIL_MAILER', 'smtp'),
    'from' => [
        // White-label: sender name/address resolve from Settings > Branding/Company at runtime.
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'CRM')),
    ],
    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'encryption' => env('MAIL_ENCRYPTION'),
        ],
        'log' => ['transport' => 'log'],
        'array' => ['transport' => 'array'],
    ],
];
