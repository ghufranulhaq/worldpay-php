# API reference

Every public class in `aerotickets/worldpay-moto` v0.1. All of them live under the `AeroTickets\Worldpay\` namespace.
Anything marked `@internal` in the source, such as `Http\Transport`, `Http\Redactor` and `Support\Validate`, isn't
part of the public API and may change in any release.

← [Security](security.md) · [README](../README.md)

**Contents:** [Client & config](#client--config) · [Payments API](#payments-api) · [Requests](#requests) ·
[Instruments](#instruments) · [Responses](#responses) · [Support](#support) · [Exceptions](#exceptions) ·
[Laravel](#laravel) · [Testing](#testing)

---

## Client & config

### `WorldpayClient`

| Method | Returns | |
|---|---|---|
| `__construct(Config $config)` / `static create(Config $config)` | `WorldpayClient` | |
| `static fromArray(array $config)` | `WorldpayClient` | Uses `Config::fromArray()` |
| `payments()` | `Payments\PaymentsApi` | |
| `config()` | `Config\Config` | |
| `checkoutSettings()` | `array{checkoutId: ?string, mode: string}` | Safe to send to the browser |

### `Config\Config`

```php
new Config(
    Environment|string $environment,      // Environment::Try|Live, or 'try' / 'live'
    string $entity,
    string $username,
    string $password,
    string $defaultNarrative = 'AeroTickets',
    ?string $checkoutId = null,
    string $apiVersion = '2024-06-01',
    float $timeout = 30.0,
    float $connectTimeout = 10.0,
    int $authorizeRetries = 0,            // 0–5
    int $retryDelayMs = 1000,
    ?Psr\Log\LoggerInterface $logger = null,
    ?GuzzleHttp\ClientInterface $httpClient = null,
)
```

- Every constructor argument is also a public `readonly` property, e.g. `$config->environment`.
- `static fromArray(array $values)` takes snake_case keys, e.g. `mode`, `entity`, `checkout_id`, `default_narrative`.
- `with(mixed ...$changes)` returns a modified copy.
- `baseUrl()` returns the current environment's base URL.
- `DEFAULT_API_VERSION = '2024-06-01'`.
- Throws `ConfigurationException`.

### `Config\Environment` (enum)

| Member | Description |
|---|---|
| `Try = 'try'`, `Live = 'live'` | The two environments |
| `baseUrl()` | `https://try.access.worldpay.com` or `https://access.worldpay.com` |
| `host()` | The base URL's host |
| `static fromString(string $mode)` | Accepts `try`/`sandbox`/`test` and `live`/`production`/`prod`, case-insensitive |

---

## Payments API

### `Payments\PaymentsApi`

Get it from `$client->payments()`. Every method throws a `WorldpayException` subclass on failure.

| Method | Returns | Worldpay call |
|---|---|---|
| `authorize(AuthorizeRequest $request)` | `PaymentResult` | `POST /api/payments` (resends with the same reference on `OutcomeUnknown` if `authorizeRetries > 0`) |
| `query(PaymentHandle $handle, int $waitSeconds = 0, int $pollIntervalSeconds = 5)` | `QueryResult` | `GET self` (polls through 404 while waiting) |
| `settle(PaymentHandle $handle)` | `PaymentResult` | `settlePayment` |
| `partialSettle(PaymentHandle $handle, Money $amount, string $reference, ?Sequence $sequence = null)` | `PaymentResult` | `partiallySettlePayment` |
| `cancel(PaymentHandle $handle, ?string $reference = null)` | `PaymentResult` | `cancelPayment` |
| `partialCancel(PaymentHandle $handle, Money $amount, string $reference)` | `PaymentResult` | `partiallyCancelPayment` |
| `refund(PaymentHandle $handle)` | `PaymentResult` | `refundPayment` |
| `partialRefund(PaymentHandle $handle, Money $amount, string $reference)` | `PaymentResult` | `partiallyRefundPayment` |
| `reverse(PaymentHandle $handle)` | `PaymentResult` | `reversePayment` |
| `increaseAuthorization(PaymentHandle $handle, Money $amount)` | `PaymentResult` | `increaseAuthorizedAmount` |

The manage methods throw `ActionNotAvailableException` without sending anything when the handle doesn't offer the
action. They throw `InvalidRequestException` for a bad reference, a zero amount, or an amount in a different
currency from the authorization.

### `Payments\PaymentHandle`

| Member | Description |
|---|---|
| `readonly ?string $paymentId, $transactionReference, $selfHref, $currency` | |
| `can(Action $action): bool` | Whether the action is allowed now |
| `availableActions(): list<Action>` | |
| `link(Action $action): array{href, method}` | Throws `ActionNotAvailableException` |
| `isFinal(): bool` | No actions left |
| `toArray()`, `jsonSerialize()`, `toJson()` | For storage |
| `static fromArray(array)`, `static fromJson(string)` | Restore; throws `InvalidRequestException` on bad input |
| `SCHEMA_VERSION = 1` | |

### `Payments\Action` (enum)

`SettlePayment`, `PartiallySettlePayment`, `CancelPayment`, `PartiallyCancelPayment`, `RefundPayment`,
`PartiallyRefundPayment`, `ReversePayment`, `IncreaseAuthorizedAmount`. Each value is Worldpay's `_actions` key,
e.g. `'settlePayment'`.

---

## Requests

### `Payments\Request\AuthorizeRequest`

This is an immutable builder: each method returns a copy.

| Method | Description |
|---|---|
| `static moto(string $transactionReference, Money $value)` | Validates the reference (1–64 chars) and that the amount is above 0 |
| `paymentInstrument(PaymentInstrument $instrument)` | **Required** |
| `orderReference(string $orderReference)` | 1–64 chars |
| `narrative(?string $line1 = null, ?string $line2 = null)` | 24 chars each, `[a-zA-Z0-9 ,./-]` |
| `autoSettle(bool $cancelOnCvcNotMatched = true, bool $cancelOnAvsNotMatched = true)` | |
| `manualSettle()` | |
| `estimated(bool $estimated = true)` | Can't be combined with auto settlement |
| `acceptPartialAmount(bool $accept = true)` | |
| `createToken(?TokenCreation $tokenCreation = null)` | |
| `fraud(?FraudSight $fraud = null)` | |
| `customer(Customer $customer)` | |
| `shipping(Shipping $shipping)` | |
| `mcc(string $mcc)` | 4 digits |
| `entity(string $entity)` | Per-payment entity override |
| `withExtra(string $path, mixed $value)` | Any other field by dot path; forbidden paths are refused |
| `instrument(): ?PaymentInstrument`, `isAutoSettle(): bool` | |
| `toPayload(string $defaultEntity, string $defaultNarrative): array` | The JSON body. `PaymentsApi` calls it for you. |
| `readonly string $transactionReference`, `readonly Money $value` | |

### `Payments\Request\Address`

- `static of(string $address1, string $city, string $countryCode, ?string $postalCode = null, ?string $address2 = null, ?string $address3 = null, ?string $state = null)`
- `toArray(string $field = 'billingAddress', bool $postalCodeRequired = false)`
- Postcode is required unless `countryCode` is `IE`.

### `Payments\Request\Customer`

`new Customer(?string $customerId, ?string $firstName, ?string $lastName, ?string $email, ?string $phone, ?DateTimeInterface $dateOfBirth, ?string $ipAddress, ?string $documentReference)`.
Every argument is optional and named, and `toArray()` builds the request object.

### `Payments\Request\Shipping`

`new Shipping(?ShippingMethod $method, ?ShippingTimeFrame $timeFrame, ?string $email, ?string $phone, ?string $firstName, ?string $lastName, ?Address $address)`

- **`ShippingMethod`:** `BillingAddress`, `VerifiedAddress`, `OtherAddress`, `Store`, `Digital`, `UnshippedTickets`,
  `Other`.
- **`ShippingTimeFrame`:** `Electronic`, `SameDay`, `NextDay`, `TwoDaysPlus`.

### `Payments\Request\FraudSight`

| Method | Description |
|---|---|
| `static assess()` | Start a FraudSight request |
| `silentMode(bool $enabled = true)` | Score only, never stop the payment |
| `custom(int\|string ...$fields)` | Named `number1`–`number9` (int) and `string1`–`string9` (1–100 chars) |
| `ravelinDeviceData(string $collectionReference)` | Ravelin device data reference |
| `toArray()` | The request object |

### `Payments\Request\TokenCreation`

`static worldpay(?string $namespace = null)`, `toArray()`.

### `Payments\Request\Sequence`

`new Sequence(int $number, int $total)` or `static of(int $number, int $total)`, where 1 ≤ number ≤ total.
`toArray()` builds the request object.

---

## Instruments

All four implement `Payments\Instrument\PaymentInstrument`, which has `type(): string` and `toArray(): array`
(`toArray()` validates first). All except `RawInstrument` also have
`preferredCardBrand(CardBrand $brand): static`.

| Class | Factory | Extras |
|---|---|---|
| `CheckoutSession` | `static card(string $sessionHref, ?string $cardHolderName = null, ?Address $billingAddress = null)` | |
| `WorldpayToken` | `static href(string $href)`, `static id(string $tokenId, ?string $namespace = null)` | `withCvc(string $cvc)`, `withCvcSession(string $cvcSessionHref)` |
| `PlainCard` | `static of(string $cardNumber, int $expiryMonth, int $expiryYear, ?string $cvc = null, ?string $cardHolderName = null, ?Address $billingAddress = null)` | `lastFour()` |
| `RawInstrument` | `static fromArray(array $instrument)` | |

`CardBrand` (enum): `Visa`, `Mastercard`, `Maestro`, `Amex`, `CartesBancaires`, `Diners`, `Dankort`, `Jcb`,
`Discover`, `Elo`, `EftposAU`.

---

## Responses

### `Payments\Response\PaymentResult`

| Member | Type |
|---|---|
| `readonly Outcome $outcome` | |
| `readonly int $httpStatus` | |
| `handle()` | `PaymentHandle` |
| `paymentId()`, `transactionReference()`, `commandId()`, `schemeReference()`, `authorizationCode()` | `?string` |
| `isAuthorized()`, `isRefused()`, `isSuccessful()`, `needsReview()` | `bool` |
| `refusal()` | `?Refusal` |
| `riskFactors()` | `RiskFactors` |
| `card()` | `?CardSummary` |
| `fraud()` | `?FraudAssessment` |
| `token()` | `?CreatedToken` |
| `totalAuthorized()` | `?Money` |
| `raw()`, `toArray()` | `array` |

### Other response types

| Type | Members |
|---|---|
| `Outcome` (enum) | `Authorized`, `Refused`, `SentForSettlement`, `SentForCancellation`, `SentForRefund`, `SentForPartialRefund`, `SentForReversal`, `FraudHighRisk`, `Unknown`; `isSuccessful()`; `static fromApi(?string)` |
| `QueryResult` | `readonly LastEvent $lastEvent`, `handle()`, `raw()` |
| `LastEvent` (enum) | `Authorized`, `Refused`, `SentForSettlement`, `Settled`, `SettlementFailed`, `SentForRefund`, `Refunded`, `RefundFailed`, `SentForCancellation`, `Error`, `Expired`, `Unknown`; `static fromApi(?string)` |
| `RiskFactors` | `cvc()`, `avsPostcode()`, `avsAddress()`, `riskProfile()` (all `?Risk`), `hasMismatch()`, `isEmpty()`, `toArray()` |
| `Risk` (enum) | `NotChecked`, `NotMatched`, `NotSupplied`, `VerificationFailed`, `Unknown`; `isFailure()`; `static fromApi(?string)` (accepts camelCase and snake_case) |
| `Refusal` | `readonly ?string $code, $description, $adviceCode`; `retryAdvice(): RetryAdvice`; `retryAfter(): ?DateInterval`; `isSoftDecline(): bool` |
| `RetryAdvice` (enum) | `RetryWithUpdatedDetails`, `RetryLater`, `DoNotRetry`, `None` |
| `CardSummary` | `readonly ?string $type, $brand, $bin, $lastFour, $fundingType, $category, $countryCode, $issuerName, $paymentAccountReference`; `readonly ?int $expiryMonth, $expiryYear`; `label()` |
| `FraudAssessment` | `readonly ?string $outcome`, `readonly ?float $score`, `readonly array $reasons`; `isHighRisk()`, `needsReview()` |
| `CreatedToken` | `readonly ?string $href, $tokenId, $expiresAt, $maskedCardNumber, $cardHolderName`; `readonly ?int $expiryMonth, $expiryYear`; `readonly bool $hasConflicts`; `toInstrument(): ?WorldpayToken` |

---

## Support

### `Support\Money`

| Member | Description |
|---|---|
| `static fromDecimal(string\|int $amount, string $currency)` | Rejects amounts with more decimals than the currency allows |
| `static ofMinor(int $minorUnits, string $currency)` | From minor units |
| `static exponent(string $currency): int` | Number of decimals for the currency |
| `readonly int $minorUnits`, `readonly string $currency` | |
| `toDecimal(): string`, `isZero()`, `equals(Money)`, `toArray()`, `__toString()` | `"250.00 GBP"` |

### `Support\TransactionReference`

`static generate(string $prefix = 'AT'): string` and `static assertValid(string $reference): void`.

### `Version`

`Version::SDK`, e.g. `'0.1.0'`.

---

## Exceptions

All in `Exceptions\`. The tree and the recommended handling are in [Errors & retries](errors-and-retries.md).

| Class | Extra members |
|---|---|
| `WorldpayException` (abstract) | — |
| `ConfigurationException` | — |
| `InvalidRequestException` | `readonly ?string $field` |
| `ActionNotAvailableException` | `readonly Action $action`, `readonly array $available` |
| `TransportException` (`OutcomeUnknown`) | — |
| `ApiException` | `readonly int $statusCode`, `readonly ?string $errorName`, `readonly array $validationErrors`, `readonly array $body`, `errorNames()` |
| `ValidationFailedException`, `PaymentInstrumentNotSupportedException`, `EntityNotConfiguredException`, `AuthenticationException`, `NotFoundException`, `PaymentNotYetQueryableException` | as `ApiException` |
| `DuplicateTransactionReferenceException` | `bool $fromPreviousAttempt`, `ERROR_NAMES` |
| `ServerException` (`OutcomeUnknown`) | as `ApiException` |
| `OutcomeUnknown` (interface) | marker |

---

## Laravel

| Class | Description |
|---|---|
| `Laravel\WorldpayServiceProvider` | Auto-discovered. Binds `Config`, `WorldpayClient` (alias `worldpay`) and `PaymentsApi`, and publishes the `worldpay-config` tag. |
| `Laravel\Facades\Worldpay` | Proxies `WorldpayClient`: `payments()`, `config()`, `checkoutSettings()`, plus `static fake(): WorldpayFake` |

---

## Testing

| Class | Members |
|---|---|
| `Testing\WorldpayFake` | `static create(?Config $config = null)`, `queue(ResponseInterface\|Throwable ...$responses)`, `client()`, `requests(): list<RecordedRequest>`, `lastRequest()`, `remaining()`, `assertSentCount(int)`, `assertNothingSent()`, `assertSent(callable)` |
| `Testing\RecordedRequest` | `readonly string $method, $url`, `readonly ?array $body`, `readonly RequestInterface $psrRequest`, `path()`, `header(string)`, `get(string $dotPath)` |
| `Testing\FakeResponses` | Factories listed in [Testing](testing.md#fakeresponses), plus `json(int, array)` and `links(Environment, list<Action>)` |
| `Testing\TestCards` | `VISA`, `MASTERCARD`, `AMEX`, `JCB`, `DISCOVER`, `CARTES_BANCAIRES_VISA`, `UNIONPAY`, `EXPIRY_MONTH`, `EXPIRY_YEAR` |
| `Testing\MagicValues` | Cardholder-name, CVC and AVS-postcode constants, plus `static refusedWithCode(int)` |
| `Testing\CheckoutSessionFactory` | `__construct(string $checkoutId, Environment $environment = Environment::Try, ?ClientInterface $http = null)`, `cardSession(string $cardNumber, int $month, int $year, string $cvc): string`, `cvcSession(string $cvc): string`. **Try only.** |
