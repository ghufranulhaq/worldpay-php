# Testing

← [Errors & retries](errors-and-retries.md) · Next: [Security](security.md)

## 1. Testing your application (no network)

`WorldpayFake` gives you a real `WorldpayClient` whose HTTP layer replays responses you queue. Requests still go
through the SDK's own validation, and you can inspect them afterwards.

```php
use AeroTickets\Worldpay\Testing\FakeResponses;
use AeroTickets\Worldpay\Testing\RecordedRequest;
use AeroTickets\Worldpay\Testing\WorldpayFake;

$fake = WorldpayFake::create();                    // Try config with dummy credentials
$fake->queue(FakeResponses::authorized(), FakeResponses::settled());

$service = new BookingPaymentService($fake->client()->payments());
$service->takePayment($booking, $sessionHref);
$service->settle($booking);

$fake->assertSentCount(2);
$fake->assertSent(fn (RecordedRequest $r) => $r->path() === '/api/payments/{linkData}/settlements');
$fake->lastRequest()->get('instruction.value.amount');   // read the body by dot path
```

In Laravel, use `Worldpay::fake()`. It swaps the container binding and returns the `WorldpayFake`. See
[Laravel](laravel.md#testing-with-worldpayfake).

### `FakeResponses`

Each factory returns a response shaped like the ones verified in Try.

| Factory | Simulates |
|---|---|
| `authorized($ref, $overrides)` | 201 authorized, offering settle / cancel / reverse actions. `$overrides` merges into the body, e.g. `['riskFactors' => [...]]` or `['token' => [...]]`. |
| `autoSettled()` / `autoCancelled()` | 202 auto settlement / auto-cancel after a CVC mismatch |
| `refused($code, $description, $advice)` | 201 refused |
| `fraudHighRisk()` | 201 FraudSight stop |
| `settled()`, `partiallySettled()` | 202 sentForSettlement |
| `cancelled()`, `partiallyCancelled()` | 202 sentForCancellation |
| `refunded()`, `partiallyRefunded()` | 202 sentForRefund / sentForPartialRefund |
| `reversed()` | 202 sentForReversal |
| `authorizationIncreased($total)` | 201 authorized with `amounts.totalAuthorized` |
| `query($lastEvent, $actions)` | 200 query |
| `notYetQueryable()` | 404 urlContainsInvalidValue |
| `schemaError($errors)`, `error($status, $name, $msg)` | any error body |
| `duplicateTransactionReference()`, `serverError()`, `accessDenied()` | common errors |
| `connectionFailure()` | network failure (queue the exception) |

## 2. Try test cards and magic values

Use these with **Worldpay Try only**. They're defined in `TestCards` and `MagicValues`.

| Card | Number | CVC (passes) | On the Aero Tickets Try entity |
|---|---|---|---|
| Visa | `TestCards::VISA` = `4000000000002701` | `555` | ✅ |
| Mastercard | `TestCards::MASTERCARD` = `5200000000002235` | `555` | ✅ |
| Amex | `TestCards::AMEX` = `340000000002708` | `6666` | ✅ |
| JCB | `TestCards::JCB` = `3338000000000296` | `555` | ✅ |
| Discover | `TestCards::DISCOVER` = `6011000000002117` | `555` | ❌ `PaymentInstrumentNotSupportedException` |

Any future expiry works. The samples use `05/2035`.

| Controlled by | Value | Result |
|---|---|---|
| `cardHolderName` | `AUTHORISED` or any normal name | authorized |
| | `REFUSED`, `REFUSED51`, `REFUSED33`, `REFUSED43`, `REFUSED55`, … (`MagicValues::refusedWithCode(n)`) | refused with that code |
| | `SOFT_DECLINED` | refused, code 65 |
| | `ERROR` | **HTTP 500** → `ServerException` (outcome unknown) |
| | `REFUSEDRC79MAC01` / `…MAC02` / `REFUSEDRC83MAC03` (Mastercard PAN) | refused with advice 01 / 02 / 03 |
| | `fs-highRisk` / `fs-review` (FraudSight enabled) | fraud stop / review |
| `cvc` | `555` (`6666` Amex) | passes, no risk factor |
| | `444` (`4444` Amex) | cvc `notMatched` |
| | `111` | cvc `notChecked` |
| `billingAddress.postalCode` | `AAAA` | AVS passes |
| | `CCCC` / `FFFF` / `JJJJ` | address / postcode / both `notMatched` |
| | `EEEE` / `HHHH` | not checked / not supplied |

Full lists are in `MagicValues` and the research notes (`MOTO_API_REFERENCE.md` §4–§5).

## 3. The SDK's own tests

```bash
composer test               # Unit + Laravel suites, offline (~0.3 s)
composer test:integration   # real calls to Worldpay Try
```

### Integration suite (Worldpay Try)

```bash
cp .env.testing.example .env.testing     # gitignored
# fill in WORLDPAY_TRY_USERNAME, WORLDPAY_TRY_PASSWORD (and optionally WORLDPAY_TRY_CHECKOUT_ID)
composer test:integration
```

Without credentials, every integration test is **skipped**, not failed. The suite covers:

- authorizations for Visa, Mastercard, Amex and JCB
- a refusal, a CVC mismatch, a duplicate reference and wrong credentials
- the Postman flows: **A** settle → refund, **B** partial settles → partial refunds, **C** cancel → query,
  **D** partial cancel → settle the rest, **E** auto settle → reverse
- auto-cancel on a CVC mismatch
- a **Checkout session** authorization, when `WORLDPAY_TRY_CHECKOUT_ID` is set. `CheckoutSessionFactory` creates
  the session server-side from a test card. That's Try only; in production, sessions come from the browser.

Flow C waits up to 2 minutes for the query (`--exclude-group=slow` skips it).
