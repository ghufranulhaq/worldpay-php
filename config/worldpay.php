<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Worldpay Access MOTO (aerotickets/worldpay-moto)
|--------------------------------------------------------------------------
|
| Publish with: php artisan vendor:publish --tag=worldpay-config
| Every value comes from .env so Try and Live differ only in environment variables.
|
*/

return [
    // "try" (sandbox) or "live". The SDK knows both base URLs.
    'mode' => env('WORLDPAY_MODE', 'try'),

    // Merchant entity from Worldpay Dashboard → Developer Tools (e.g. PO4098288921 for Aero Tickets Try).
    'entity' => env('WORLDPAY_ENTITY'),

    // Payments API credentials for the chosen mode. Try and Live credentials are different.
    'username' => env('WORLDPAY_USERNAME'),
    'password' => env('WORLDPAY_PASSWORD'),

    // Access Checkout ID for the browser Checkout SDK. Public (safe to send to the SPA).
    'checkout_id' => env('WORLDPAY_CHECKOUT_ID'),

    // Statement line 1 (trading name): max 24 chars of letters, digits, space and , . / -
    'default_narrative' => env('WORLDPAY_NARRATIVE', 'AeroTickets'),

    'api_version' => env('WORLDPAY_API_VERSION', '2024-06-01'),

    // Seconds.
    'timeout' => env('WORLDPAY_TIMEOUT', 30),
    'connect_timeout' => env('WORLDPAY_CONNECT_TIMEOUT', 10),

    // Automatic resends (same transactionReference) of an authorization whose outcome is unknown.
    'authorize_retries' => env('WORLDPAY_AUTHORIZE_RETRIES', 0),
    'retry_delay_ms' => env('WORLDPAY_RETRY_DELAY_MS', 1000),

    // Laravel log channel for SDK request/response logs (card data is always redacted).
    // null = no logging.
    'log_channel' => env('WORLDPAY_LOG_CHANNEL'),
];
