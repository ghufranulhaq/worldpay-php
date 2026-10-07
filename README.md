# aerotickets/worldpay-moto

PHP SDK for taking **Worldpay Access MOTO** (mail order / telephone order) card payments from Aero Tickets.

It wraps the Worldpay Access Payments API (`WP-Api-Version: 2024-06-01`, `channel: "moto"`). With it you don't
handle headers, minor units, HAL links, Worldpay error names or Try-specific behaviour yourself. You call a few
documented methods and get typed results back.

- **Payment methods:** a Checkout session (recommended), a Worldpay token, a plain card, or any other
  `paymentInstrument` passed through.
- **Payment lifecycle:** authorize, settle, partial settle, cancel, partial cancel, refund, partial refund, reverse,
  increase an authorization, and query.
- **Options:** FraudSight, token creation, auto settlement, customer and shipping details, MCC.
- **Safety:** checks requests locally, maps every error to its own exception, refuses to send credentials to any
  host other than Worldpay, and redacts card data from all logs.
- **Laravel:** auto-discovered service provider, `config/worldpay.php`, a `Worldpay` facade and `Worldpay::fake()`.
- **Testing:** `WorldpayFake`, plus the Try test cards and magic values.

> Browser side: the card fields and the Checkout session are handled by the companion package
> [`@aerotickets/worldpay-checkout`](../worldpay-checkout-js/README.md) in aero-app. Card data never reaches this
> SDK when you use Checkout sessions.

## Requirements

- PHP 8.2+ with `ext-json`
- Guzzle 7 (installed automatically)
- Optional: Laravel 11 or 12 for the bridge

## 5-minute quickstart (Laravel / aero-api)

```bash
composer config repositories.worldpay-moto vcs git@github.com:ghufranulhaq/worldpay-php.git
composer require aerotickets/worldpay-moto:^0.1
```

Add to `.env`:

```dotenv
WORLDPAY_MODE=try                 # try | live
WORLDPAY_ENTITY=PO4098288921
WORLDPAY_USERNAME=...
WORLDPAY_PASSWORD=...
WORLDPAY_CHECKOUT_ID=...          # for the browser Checkout SDK
```

Take a payment with the session href that the SPA sent you:

```php
use AeroTickets\Worldpay\Laravel\Facades\Worldpay;
use AeroTickets\Worldpay\Payments\Instrument\CheckoutSession;
use AeroTickets\Worldpay\Payments\PaymentHandle;
use AeroTickets\Worldpay\Payments\Request\Address;
use AeroTickets\Worldpay\Payments\Request\AuthorizeRequest;
use AeroTickets\Worldpay\Support\Money;
use AeroTickets\Worldpay\Support\TransactionReference;

$result = Worldpay::payments()->authorize(
    AuthorizeRequest::moto(TransactionReference::generate('AT'), Money::fromDecimal('250.00', 'GBP'))
        ->paymentInstrument(CheckoutSession::card(
            $request->input('session_href'),
            cardHolderName: 'Jo Smith',
            billingAddress: Address::of('221B Baker Street', 'London', 'GB', 'NW1 6XE'),
        ))
        ->orderReference('BKG-102938')
        ->narrative(line2: 'BKG-102938')
);

if ($result->isAuthorized()) {
    $booking->worldpay_handle = $result->handle()->toJson();   // keep it: you need it to settle/refund
    $booking->card_label = $result->card()?->label();           // "visa •••• 2701"
}

// Later, once the ticket is issued:
$settled = Worldpay::payments()->settle(PaymentHandle::fromJson($booking->worldpay_handle));
$booking->worldpay_handle = $settled->handle()->toJson();       // always replace it with the latest
```

Without Laravel, see [Configuration](docs/configuration.md):

```php
$worldpay = WorldpayClient::create(new Config('try', 'PO4098288921', $username, $password));
```

## Documentation

| Guide | What's in it |
|---|---|
| [Installation & updates](docs/installation.md) | Installing from the git repo, updating, version constraints, local SDK development |
| [Configuration](docs/configuration.md) | Every option, Try vs Live, where to find the entity, credentials and Checkout ID |
| [Laravel](docs/laravel.md) | Service provider, config file, `.env` keys, facade, dependency injection, `Worldpay::fake()` |
| [**Checkout sessions**](docs/checkout-sessions.md) | **The recommended flow**: browser → session → authorize, and PCI scope |
| [Authorizing payments](docs/authorizing-payments.md) | All instruments and all request options |
| [Managing payments](docs/managing-payments.md) | Settle, cancel, refund, reverse, partials, increase, query, and storing handles |
| [Responses](docs/responses.md) | `PaymentResult`, outcomes, risk factors, refusals, and what to store |
| [Errors & retries](docs/errors-and-retries.md) | Exception tree and what to do in each case |
| [Testing](docs/testing.md) | `WorldpayFake`, Try test cards and magic values, the integration suite |
| [Security](docs/security.md) | Redaction guarantees, what to never store, PCI notes |
| [API reference](docs/api-reference.md) | Every public class and method |
| [Releasing](docs/releasing.md) | How to publish a new version |
| [Changelog](CHANGELOG.md) | What changed in each release |
