# Configuration

You create one `Config` object and one `WorldpayClient` when the application boots, and reuse them everywhere.
If a value is missing or invalid, `Config` throws `ConfigurationException` straight away, so a misconfigured app
fails at boot rather than on the first customer's payment.

← [Installation](installation.md) · Next: [Laravel](laravel.md)

## Plain PHP

```php
use AeroTickets\Worldpay\Config\Config;
use AeroTickets\Worldpay\Config\Environment;
use AeroTickets\Worldpay\WorldpayClient;

$worldpay = WorldpayClient::create(new Config(
    environment: Environment::Try,          // or 'try' / 'live'
    entity: 'PO4098288921',
    username: getenv('WORLDPAY_USERNAME'),
    password: getenv('WORLDPAY_PASSWORD'),
    checkoutId: getenv('WORLDPAY_CHECKOUT_ID'),
    defaultNarrative: 'AeroTickets',
    logger: $psrLogger,                     // optional
));

// or, from an array with snake_case keys (what the Laravel bridge does):
$worldpay = WorldpayClient::fromArray([
    'mode' => 'try', 'entity' => 'PO4098288921', 'username' => '…', 'password' => '…',
]);
```

In Laravel you don't write this yourself. The service provider builds the client from `config/worldpay.php`. See
[Laravel](laravel.md).

## Options

| Constructor argument | Array / Laravel key | Default | Description |
|---|---|---|---|
| `environment` | `mode` | — (required) | `Environment::Try` / `Environment::Live`, or the strings `try`/`sandbox`/`test` and `live`/`production`/`prod` |
| `entity` | `entity` | — (required) | Merchant entity reference from **Worldpay Dashboard → Developer Tools**. Aero Tickets Try: `PO4098288921`. `default` is rejected, because it gives `400 entityIsNotConfigured`. |
| `username` | `username` | — (required) | Payments API username for this environment |
| `password` | `password` | — (required) | Payments API password. It's never included in logs or `var_dump`. |
| `defaultNarrative` | `default_narrative` | `AeroTickets` | Statement line 1 (your trading name). Up to 24 characters: letters, digits, space and `, . / -` |
| `checkoutId` | `checkout_id` | `null` | Access Checkout ID for the browser Checkout SDK. It's public, not a secret. |
| `apiVersion` | `api_version` | `2024-06-01` | The `WP-Api-Version` header. Only change it after checking the SDK supports the version. |
| `timeout` | `timeout` | `30` | Total request timeout, in seconds |
| `connectTimeout` | `connect_timeout` | `10` | Connection timeout, in seconds |
| `authorizeRetries` | `authorize_retries` | `0` | Automatic resends (0–5) of an authorization whose outcome is unknown. See [Errors & retries](errors-and-retries.md). |
| `retryDelayMs` | `retry_delay_ms` | `1000` | Delay between those resends |
| `logger` | `log_channel` (Laravel) | `null` | A PSR-3 logger. Card data is always redacted first. See [Security](security.md). |
| `httpClient` | — | Guzzle | Your own `GuzzleHttp\ClientInterface`, e.g. for a proxy |

You can derive a variant with `$config->with(timeout: 5.0)`.

## Try vs Live

| | Try (sandbox) | Live |
|---|---|---|
| Base URL, built into the SDK | `https://try.access.worldpay.com` | `https://access.worldpay.com` |
| Money | Simulated; test cards only ([Testing](testing.md)) | Real |
| Credentials | Try API credentials | Separate Live credentials from your Worldpay Implementation Manager. Try credentials get `401` on Live. |
| Entity / Checkout ID | Try values | Live values (may differ, so check the Dashboard in Live mode) |

To switch, change `WORLDPAY_MODE` and all the other `WORLDPAY_*` values together. Stored payment handles belong to
the mode they were created in. The SDK refuses to use a Try handle while it's configured for Live, and the reverse.

## Where to find the values

- **Entity:** Worldpay Dashboard → Developer Tools (it looks like `PO4098288921`).
- **Username / password:** Worldpay Dashboard → Try mode → generate Try API credentials. For Live, ask your
  Implementation Manager.
- **Checkout ID:** Worldpay Dashboard → Developer Tools → Checkout ID. It's a UUID.
- **MOTO must be enabled** on the entity for both Try and Live. It's enabled on the Aero Tickets Try entity
  (verified 2026-10-07). Confirm it for Live with Worldpay.
- **Network:** if your servers restrict outbound traffic, allow `try.access.worldpay.com` and
  `access.worldpay.com` by DNS name, not by IP.

## The client

```php
$worldpay->payments();          // PaymentsApi: authorize, settle, refund …
$worldpay->config();            // the Config
$worldpay->checkoutSettings();  // ['checkoutId' => '…', 'mode' => 'try'], safe to send to the SPA
```
