# Laravel

The package ships a service provider and a facade. Laravel discovers both automatically when you run
`composer require`, so there's nothing to register.

← [Configuration](configuration.md) · Next: [Checkout sessions](checkout-sessions.md)

## `.env`

```dotenv
WORLDPAY_MODE=try                    # try | live
WORLDPAY_ENTITY=PO4098288921
WORLDPAY_USERNAME=
WORLDPAY_PASSWORD=
WORLDPAY_CHECKOUT_ID=                # public; sent to the SPA
WORLDPAY_NARRATIVE=AeroTickets       # statement line 1, max 24 chars
WORLDPAY_LOG_CHANNEL=                # e.g. "worldpay"; empty = no SDK logging
# optional
WORLDPAY_TIMEOUT=30
WORLDPAY_CONNECT_TIMEOUT=10
WORLDPAY_AUTHORIZE_RETRIES=0
WORLDPAY_RETRY_DELAY_MS=1000
WORLDPAY_API_VERSION=2024-06-01
```

Add the same keys, with empty values, to `.env.example`. Never commit real credentials.

## Config file

```bash
php artisan vendor:publish --tag=worldpay-config   # creates config/worldpay.php
```

You only need to publish it to change a default. The package's own copy is merged in otherwise. Each key is
described in [Configuration](configuration.md).

The client is built lazily, the first time you use it. If the config is incomplete, that first use throws
`ConfigurationException` with a message naming the missing key. For example, `username` is required.

## Logging

Set `WORLDPAY_LOG_CHANNEL` to a channel defined in `config/logging.php`, e.g. a daily `worldpay` channel:

```php
'worldpay' => ['driver' => 'daily', 'path' => storage_path('logs/worldpay.log'), 'level' => 'debug', 'days' => 30],
```

The SDK logs each request and response at `debug`, Worldpay error responses at `notice`, and network failures at
`warning`. Card numbers are reduced to their last 4 digits. CVCs, session/token hrefs and credentials are removed.
See [Security](security.md).

## Using it

### Facade

```php
use AeroTickets\Worldpay\Laravel\Facades\Worldpay;

$result = Worldpay::payments()->authorize($request);
$settings = Worldpay::checkoutSettings();   // for the SPA: ['checkoutId' => …, 'mode' => 'try']
```

### Dependency injection (preferred in services)

```php
use AeroTickets\Worldpay\Payments\PaymentsApi;
use AeroTickets\Worldpay\WorldpayClient;

final class BookingPaymentService
{
    public function __construct(private readonly PaymentsApi $payments) {}
    // or: private readonly WorldpayClient $worldpay
}
```

The container binds these:

| Binding | What it is |
|---|---|
| `AeroTickets\Worldpay\WorldpayClient` (alias `worldpay`) | Singleton client |
| `AeroTickets\Worldpay\Payments\PaymentsApi` | `$client->payments()` |
| `AeroTickets\Worldpay\Config\Config` | Singleton config built from `config('worldpay')` |

### Giving the SPA its Checkout settings

```php
// routes/api.php (behind auth:sanctum, like the other admin routes)
Route::get('admin/worldpay/checkout-settings', fn () => response()->json(Worldpay::checkoutSettings()));
```

The SPA passes these values to `@aerotickets/worldpay-checkout`. See [Checkout sessions](checkout-sessions.md).

## Testing with `Worldpay::fake()`

```php
use AeroTickets\Worldpay\Laravel\Facades\Worldpay;
use AeroTickets\Worldpay\Testing\FakeResponses;
use AeroTickets\Worldpay\Testing\RecordedRequest;

it('authorizes a booking payment', function () {
    $fake = Worldpay::fake();                       // swaps the container binding; no network
    $fake->queue(FakeResponses::authorized());

    $this->postJson('/api/admin/files/1/card-payments', [...])->assertCreated();

    $fake->assertSentCount(1);
    $fake->assertSent(fn (RecordedRequest $r) => $r->get('instruction.value.amount') === 25000);
});
```

`Worldpay::fake()` keeps your configured entity and mode, and falls back to dummy values when no config is set.
More in [Testing](testing.md).
