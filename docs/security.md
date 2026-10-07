# Security

← [Testing](testing.md) · Next: [API reference](api-reference.md)

## What the SDK guarantees

| Guarantee | How |
|---|---|
| **Card data is never logged** | Before anything is logged, `Redactor` reduces `cardNumber` to its last 4 digits, replaces `cvc` with `***`, and replaces `sessionHref`, `cvcSessionHref` and token `href` with `[redacted]`. A unit test (`RedactionTest`) checks the captured logs for every test PAN, CVC, session, token and credential. |
| **Credentials are never logged** | The `Authorization` header is not logged. `Config::__debugInfo()` masks the password, and the password parameter is a `#[SensitiveParameter]`, so it doesn't show up in stack traces. |
| **Card data doesn't leak through dumps** | `PlainCard`, `CheckoutSession` and `WorldpayToken` mask their secrets in `var_dump` / `print_r`. |
| **Credentials go only to Worldpay** | Every request URL, including links from stored handles, must be `https://` on the configured environment's host. Anything else throws `InvalidRequestException` before sending, so a tampered handle or one from the wrong mode can't redirect your credentials. Redirects are not followed. |
| **Exceptions carry no card data** | Error messages are built from Worldpay's error body, never from your request. |
| **Responses are masked** | Worldpay returns only the BIN and last four. `PaymentResult::toArray()` is a card-safe summary. |

## What your application must do

1. **Prefer Checkout sessions.** With `CheckoutSession`, card data never reaches aero-api. See
   [Checkout sessions](checkout-sessions.md).
2. **Never store** the card number or CVC, in any form, anywhere. Storing a CVC after authorization is forbidden by
   PCI DSS. Store only `card()->label()` / `lastFour` / `brand`.
3. **Never store or log** session hrefs. They're single use and short-lived, but treat them as card data.
4. **Never log raw requests** yourself. In Laravel, check that no middleware or exception reporter logs
   `$request->all()` on the payment endpoints. The current `FileCcPaymentController::store` does exactly that; see
   `docs/file-payment-process.md`.
5. **Keep credentials in `.env`** (or a secrets manager), never in git. Use different credentials for Try and Live.
6. **Restrict who can take and manage payments** with the existing permission system. Settle, cancel and refund
   move money, so they should be gated per action and per brand, like the rest of aero-api.
7. **Store a `PaymentHandle` like any internal reference.** It contains no card data and its links are useless
   without your credentials, but don't expose it to the browser.
8. **Call recordings:** pause recording while the card is read out.

## PCI DSS scope (summary)

| Instrument | Card data on your servers | Notes |
|---|---|---|
| `CheckoutSession` | No | Server scope greatly reduced. Agent desktops are still in scope. |
| `WorldpayToken` + CVC session | No | Same as Checkout |
| `WorldpayToken` + `withCvc()` | CVC only | The CVC passes through your server |
| `PlainCard` | **Yes** | Full scope (SAQ D) |

Confirm your exact SAQ and scope with your QSA or acquirer.
