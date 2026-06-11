<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Requests from the following domains / hosts will receive stateful API
    | authentication cookies. Include the Vue 3 dev-server origin AND the
    | Laravel API origin so both cookie-based (SPA) and token-based
    | (mobile/IoT) auth flows work correctly.
    |
    */

    'stateful' => explode(',', env(
        'SANCTUM_STATEFUL_DOMAINS',
        'localhost,localhost:5173,localhost:5174,localhost:5175,localhost:8000,127.0.0.1,127.0.0.1:8000,127.0.0.1:5174,127.0.0.1:5175,::1'
    )),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | Authentication guards checked when Sanctum authenticates a request.
    | If none succeed, Sanctum falls back to bearer-token authentication.
    |
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | Minutes until an issued token is considered expired. Null means tokens
    | never expire (revocation-only). Override per-environment via
    | SANCTUM_TOKEN_EXPIRATION in .env.
    |
    */

    'expiration' => env('SANCTUM_TOKEN_EXPIRATION'),

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    |
    | Prefix new tokens to enable secret-scanning by platforms like GitHub.
    | See: https://docs.github.com/en/code-security/secret-scanning
    |
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

];
