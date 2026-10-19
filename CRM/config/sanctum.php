<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Str;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Requests from these origins may use session cookies to authenticate
    | against the API (first-party SPAs/dashboard). Everyone else must send
    | a bearer token. We trust the installed APP_URL plus loopback hosts.
    |
    */

    'stateful' => array_filter(array_unique(array_map(
        fn ($host) => $host,
        collect([
            config('app.url'),
            'localhost',
            'localhost:8000',
            '127.0.0.1',
            '127.0.0.1:8000',
        ])->map(fn ($u) => Str::of($u)->after('://')->before('/')->toString())->all()
    ))),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | Personal access tokens never expire unless the UI sets a date; leave null.
    |
    */

    'expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    */

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'verify_csrf_token' => VerifyCsrfToken::class,
    ],
];
