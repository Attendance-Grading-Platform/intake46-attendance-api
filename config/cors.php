<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Configured for a Vue 3 SPA on http://localhost:5173 communicating
    | with Sanctum's token/cookie-based authentication.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

'allowed_origins' => [
    'http://localhost:5173',
    'http://localhost:5174',
    'http://localhost:5175',
    'http://127.0.0.1:5173',
    'http://127.0.0.1:5174',
    'http://127.0.0.1:5175',
    'https://intake45-attendance-client.vercel.app',
],

    'allowed_origins_patterns' => [
        '#^https://.*-[\w-]+-.*\.vercel\.app$#', // Allows Vercel Preview Deployments
    ],

    'allowed_headers' => [
        'Content-Type',
        'X-Requested-With',
        'Authorization',
        'Accept',
        'Origin',
        'X-XSRF-TOKEN',
    ],

    'exposed_headers' => [],

    'max_age' => 0,

    /*
    |--------------------------------------------------------------------------
    | Credentials Support
    |--------------------------------------------------------------------------
    |
    | Must be true for Sanctum SPA cookie-based authentication.
    | The browser will include cookies/authorization headers in cross-origin
    | requests only when this is enabled AND the SPA sets
    | `withCredentials: true` on its HTTP client (Axios, fetch, etc.).
    |
    */
    'supports_credentials' => true,

];
