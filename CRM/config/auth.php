<?php

use App\Models\ClientUser;
use App\Models\User;

return [
    'defaults' => ['guard' => 'web', 'passwords' => 'users'],
    'guards' => [
        'web' => ['driver' => 'session', 'provider' => 'users'],
        'sanctum' => ['driver' => 'sanctum', 'provider' => 'users'],
        'client' => ['driver' => 'session', 'provider' => 'client_users'],
    ],
    'providers' => [
        'users' => ['driver' => 'eloquent', 'model' => User::class],
        'client_users' => ['driver' => 'eloquent', 'model' => ClientUser::class],
    ],
];
