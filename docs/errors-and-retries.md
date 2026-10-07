# Errors & retries

Outcomes such as `Refused` or `FraudHighRisk` are **not** exceptions; they come back in a normal `PaymentResult`.
Exceptions mean the request was invalid, was rejected, or its outcome is unknown.

← [Responses](responses.md) · Next: [Testing](testing.md)

## Exception tree

Every exception extends `AeroTickets\Worldpay\Exceptions\WorldpayException`.

```
WorldpayException
├── ConfigurationException             bad/missing config (thrown at bootstrap)
├── InvalidRequestException            failed local validation; nothing was sent ($e->field)
├── ActionNotAvailableException        action not offered by the latest handle; nothing was sent
├── TransportException  ◆              no response: timeout, DNS, TLS, connection reset
└── ApiException                       Worldpay answered with an error ($e->statusCode, errorName, validationErrors, body)
    ├── ValidationFailedException      400 bodyDoesNotMatchSchema (see validationErrors[].jsonPath)
    │   └── PaymentInstrumentNotSupportedException   card scheme not enabled for the entity
    ├── EntityNotConfiguredException   400 entityIsNotConfigured: wrong merchant entity
    ├── DuplicateTransactionReferenceException       400: transactionReference already used ($e->fromPreviousAttempt)
    ├── AuthenticationException        401: wrong credentials, or Try credentials used on Live
    ├── NotFoundException              404: bad link
    │   └── PaymentNotYetQueryableException          404 on query: too soon after authorization
    └── ServerException  ◆             5xx, e.g. internalErrorOccurred

◆ implements OutcomeUnknown
```

## What to do

| Exception | Did Worldpay process it? | Action |
|---|---|---|
| `ConfigurationException` | — | Fix `.env` or config |
| `InvalidRequestException` | No | Fix the input. `$e->field` says which field. Show the agent the message. |
| `ActionNotAvailableException` | No | The payment's state doesn't allow it. Check `$e->available`. |
| `ValidationFailedException` | No | A bug in your request or an SDK gap. Log `$e->validationErrors`. |
| `PaymentInstrumentNotSupportedException` | No | Card scheme not enabled (e.g. Discover on the Aero Tickets Try entity). Ask for another card, or ask Worldpay to enable the scheme. |
| `EntityNotConfiguredException` / `AuthenticationException` | No | Configuration problem. Alert ops. |
| `DuplicateTransactionReferenceException` | **An earlier request with this reference was** | Don't create a new payment. Reconcile the earlier one. |
| `PaymentNotYetQueryableException` | — | Query again later, or pass `waitSeconds` |
| `ServerException` / `TransportException` (**`OutcomeUnknown`**) | **Unknown** | See below |

## Outcome unknown

A timeout or 5xx doesn't mean the payment failed. Worldpay may have authorized it, so **never** just ask the
customer for the card again with a new reference: they could be charged twice.

```php
use AeroTickets\Worldpay\Exceptions\OutcomeUnknown;

try {
    $result = Worldpay::payments()->authorize($request);
} catch (OutcomeUnknown $e) {
    // Resend ONCE with the same request, so the same transactionReference.
    // Worldpay rejects a duplicate reference, so this cannot charge twice.
    try {
        $result = Worldpay::payments()->authorize($request);
    } catch (DuplicateTransactionReferenceException $dup) {
        // The first attempt DID reach Worldpay. Mark it "unknown, reconcile" and check Worldpay reports
        // or the Dashboard. Do not take the card again until resolved.
    }
}
```

### Automatic authorization retries

Set `authorizeRetries` (`WORLDPAY_AUTHORIZE_RETRIES`) to have the SDK do this for you. It resends an authorization
with the **same** `transactionReference` up to that many times, waiting `retryDelayMs` between attempts, but only
when the outcome is unknown. When a retry gets a duplicate-reference error, the SDK throws
`DuplicateTransactionReferenceException` with `$e->fromPreviousAttempt === true`, which means "the first attempt got
through, reconcile it".

The default is `0`. Keep it at 0 or 1. Retrying many times only delays the agent.

### Settle, cancel, refund and reverse are never retried automatically

Worldpay advises against resending a manage action after a timeout. Instead:

1. Mark the booking "action unknown".
2. Later, call `query()`, or check reports, to learn the real state.
3. Act again only if it's needed.

## Reading API errors

```php
use AeroTickets\Worldpay\Exceptions\ApiException;

catch (ApiException $e) {
    $e->statusCode;          // 400
    $e->errorName;           // "bodyDoesNotMatchSchema"
    $e->errorNames();        // ["bodyDoesNotMatchSchema", "fieldIsNotAllowed"]
    $e->validationErrors;    // [['errorName' => 'fieldIsNotAllowed', 'message' => '…', 'jsonPath' => '$.instruction.threeDS']]
    $e->body;                // decoded body (never contains card data)
    $e->getMessage();        // "Worldpay 400 bodyDoesNotMatchSchema: … [$.instruction.threeDS fieldIsNotAllowed: …]"
}
```

## Common causes

| Symptom | Cause |
|---|---|
| `EntityNotConfiguredException` | `WORLDPAY_ENTITY` is wrong, or you're using the Live entity in Try or the reverse |
| `AuthenticationException` in Live | Live needs its own credentials. Try credentials don't work. |
| `ValidationFailedException` on the session | The session expired (1 minute) or was already used. Create a new one in the browser. |
| `InvalidRequestException` on `href` | The handle was created in the other mode (Try vs Live), or was tampered with |
| `PaymentInstrumentNotSupportedException` | That card scheme isn't enabled on your entity |
