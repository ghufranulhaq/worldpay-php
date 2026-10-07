# Authorizing payments

An authorization **holds** the money on the customer's card. No money moves until you settle it (unless you turn on
auto settlement). Every authorization made by this SDK uses `channel: "moto"`.

← [Checkout sessions](checkout-sessions.md) · Next: [Managing payments](managing-payments.md)

## The request

```php
use AeroTickets\Worldpay\Payments\Request\AuthorizeRequest;
use AeroTickets\Worldpay\Support\Money;
use AeroTickets\Worldpay\Support\TransactionReference;

$request = AuthorizeRequest::moto(TransactionReference::generate('AT'), Money::fromDecimal('250.00', 'GBP'))
    ->paymentInstrument($instrument)       // required, see below
    ->orderReference('BKG-102938')         // optional
    ->narrative(line2: 'BKG-102938');      // optional

$result = $worldpay->payments()->authorize($request);
```

`AuthorizeRequest` is **immutable**: each method returns a new copy, so keep the return value. The SDK checks
everything locally before sending. Breaking a rule throws `InvalidRequestException`, and `$e->field` names the
offending field. Nothing is sent to Worldpay in that case.

### References

| Value | Rules | Use |
|---|---|---|
| `transactionReference` (required) | 1–64 chars: letters, digits, ``- _ ! @ # $ % ( ) * = . : ; ? [ ] { } ~ ` / +`` | One per payment attempt. `TransactionReference::generate('AT')` gives `AT-20261007T153012-9f2c4a1b7d3e`. Reuse it **only** to resend an authorization whose outcome was unknown. |
| `orderReference()` | 1–64 chars, same characters except the backtick | Groups payments for one order. Can repeat across payments, e.g. the file or booking number. |

### Amounts: `Money`

Amounts are always held as integer minor units. Floats are never used.

```php
Money::fromDecimal('250.00', 'GBP');   // £250.00 → 25000
Money::fromDecimal(250, 'GBP');        // same
Money::ofMinor(25000, 'GBP');          // same
Money::fromDecimal('1500', 'JPY');     // zero-decimal currency → 1500
Money::fromDecimal('1.005', 'GBP');    // ✗ InvalidRequestException: too many decimals; nothing is rounded
```

Pass amounts from your database as **strings** (Laravel `decimal` casts return strings). Don't pass floats.

### Statement text: `narrative()`

| Line | Default | Rules |
|---|---|---|
| `line1` | `Config::$defaultNarrative` (`AeroTickets`) | 1–24 chars of letters, digits, space and `, . / -` |
| `line2` | none | same rules, e.g. the booking reference or a phone number |

`#`, `&` and `_` aren't allowed. `narrative(line2: 'Booking #1')` throws.

## Payment instruments

### Checkout session (recommended)

The session comes from the browser. See [Checkout sessions](checkout-sessions.md).

```php
use AeroTickets\Worldpay\Payments\Instrument\CheckoutSession;
use AeroTickets\Worldpay\Payments\Request\Address;

CheckoutSession::card(
    $sessionHref,                                   // from @aerotickets/worldpay-checkout, valid 1 min, single use
    cardHolderName: 'Jo Smith',                     // optional, 1–255 chars
    billingAddress: Address::of('221B Baker Street', 'London', 'GB', 'NW1 6XE'),  // optional; drives AVS
);
```

### Worldpay token

This pays with a card stored earlier. Your Worldpay account needs the token entitlement.

```php
use AeroTickets\Worldpay\Payments\Instrument\WorldpayToken;

WorldpayToken::href($tokenHref)->withCvcSession($cvcSessionHref);  // CVC typed into the browser CVC field (preferred)
WorldpayToken::href($tokenHref)->withCvc('123');                   // CVC sent from your server (PCI scope)
WorldpayToken::id('9876543210ABCDEF', namespace: 'customer-42');   // by token id instead of href
```

To create a token, add `->createToken()` to an authorization, optionally with
`TokenCreation::worldpay(namespace: 'customer-42')`. Then read `$result->token()`:

```php
$token = $result->token();       // CreatedToken: href, tokenId, expiresAt, maskedCardNumber …
$customer->worldpay_token_href = $token?->href;
// later: ->paymentInstrument($token->toInstrument()->withCvcSession($cvc))
```

> MOTO is for customer-initiated payments only. Charge a token only while the customer is on the phone and
> agrees, never for recurring or merchant-initiated charges.

### Plain card

⚠ **Full PCI DSS scope.** The card passes through your server. Use this only if Checkout isn't possible, and never
store, log or display the number or CVC.

```php
use AeroTickets\Worldpay\Payments\Instrument\PlainCard;

PlainCard::of('4000 0000 0000 2701', 5, 2035, '555', 'Jo Smith', Address::of(...));
```

The SDK checks the card locally before sending:

- **Card number:** 12–19 digits that pass the Luhn check. Spaces and dashes are removed.
- **Expiry:** must not be in the past.
- **CVC:** 3–4 digits. Send it: without 3DS it's the main fraud check for MOTO.

### Any other instrument

`RawInstrument` passes a `paymentInstrument` object to Worldpay exactly as you give it. Use it for types the SDK
doesn't model, e.g. `networkToken`:

```php
use AeroTickets\Worldpay\Payments\Instrument\RawInstrument;

RawInstrument::fromArray(['type' => 'networkToken', /* … per the Worldpay API reference */]);
```

### Co-badged cards

Any instrument except `RawInstrument` accepts `->preferredCardBrand(CardBrand::CartesBancaires)`.

## Billing address: `Address`

```php
Address::of(
    address1: '221B Baker Street',   // required, 1–80
    city: 'London',                  // required, 1–50
    countryCode: 'GB',               // required, ISO 3166-1 alpha-2 (case-insensitive)
    postalCode: 'NW1 6XE',           // required for every country except IE, 1–15
    address2: null, address3: null, // optional, 1–80
    state: null,                     // optional, 1–30
);
```

Send the address whenever you have it. The AVS (address check) result comes back in `riskFactors`.

## Options

| Method | What it sends | Notes |
|---|---|---|
| `->autoSettle()` | `settlement.auto: true`, `cancelOn` enabled | The outcome becomes `SentForSettlement`; no `settle()` call is needed. Worldpay **auto-cancels** on a CVC/AVS mismatch (`SentForCancellation`). |
| `->autoSettle(cancelOnCvcNotMatched: false, cancelOnAvsNotMatched: false)` | `cancelOn` disabled | Keeps mismatched payments. Worldpay recommends this. |
| `->manualSettle()` | `settlement.auto: false` | The default behaviour, made explicit |
| `->estimated()` | `value.estimated: true` | You can raise the amount later with `increaseAuthorization()`. Can't be combined with `autoSettle()`. |
| `->acceptPartialAmount()` | `value.acceptPartialAmount: true` | The issuer may authorize less. Check `$result->totalAuthorized()`. |
| `->createToken(?TokenCreation)` | `tokenCreation` | Stores the card as a Worldpay token |
| `->fraud(?FraudSight)` | `fraud` | FraudSight must be enabled on the account. See below. |
| `->customer(new Customer(...))` | `customer` | `customerId`, `firstName`, `lastName` (≤22), `email`, `phone` (digits only), `dateOfBirth`, `ipAddress`, `documentReference` |
| `->shipping(new Shipping(...))` | `shipping` | `method` (`ShippingMethod::UnshippedTickets` suits airline tickets), `timeFrame`, `email`, `phone`, names, `address` |
| `->mcc('4722')` | `merchant.mcc` | Only if Worldpay enabled dynamic MCC for you |
| `->entity('PO…')` | `merchant.entity` | Overrides `Config::$entity` for this payment |
| `->withExtra('instruction.debtRepayment', true)` | any path | Sets a field the SDK doesn't model, sent as given. MOTO-forbidden paths (`channel`, `threeDS`, `exemption`, `customerAgreement`) and fields the SDK manages are refused. |

**Recommendation for Aero Tickets:** use manual settlement. Authorize while the customer is on the phone, settle when
the ticket is issued, and cancel if ticketing fails. That way the customer is never charged for a ticket that
wasn't issued, and a cancel costs less than a refund.

### FraudSight

```php
use AeroTickets\Worldpay\Payments\Request\FraudSight;

->fraud()                                                      // high-risk payments are stopped (Outcome::FraudHighRisk)
->fraud(FraudSight::assess()->silentMode())                    // score only, never stop
->fraud(FraudSight::assess()->custom(string1: 'call-centre', number1: 3))   // custom rule fields
```

Read the result with `$result->fraud()`, which returns `FraudAssessment` (`outcome`, `score`, `reasons`,
`isHighRisk()`, `needsReview()`).

## Not available for MOTO

3DS (`threeDS`), SCA exemptions (`exemption`), and customer agreements for recurring or merchant-initiated payments
(`customerAgreement`). Worldpay rejects them on MOTO, so the SDK doesn't offer them.

## What comes back

`PaymentResult`. See [Responses](responses.md). In short:

```php
match ($result->outcome) {
    Outcome::Authorized          => $result->needsReview() ? 'approved, but a security check failed' : 'approved',
    Outcome::SentForSettlement   => 'payment taken (auto settle)',
    Outcome::SentForCancellation => 'card security check failed (auto-cancelled)',
    Outcome::Refused             => 'card declined',
    Outcome::FraudHighRisk       => 'unable to process this card',
    default                      => 'unexpected outcome, check $result->raw()',
};
```

Errors are exceptions. See [Errors & retries](errors-and-retries.md).
