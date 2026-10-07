# Managing payments

After authorizing, you **settle** (take the money), **cancel** (release the hold), **refund** (send settled money
back) or **reverse** (let Worldpay decide between cancel and refund). Settle, cancel and refund each have a partial
version.

← [Authorizing payments](authorizing-payments.md) · Next: [Responses](responses.md)

## The payment handle

Every result carries a `PaymentHandle`. It holds the payment's id and references, its `self` link, and the
**actions Worldpay currently allows**, each with its own opaque URL. Worldpay requires you to follow these links
rather than build URLs, and the SDK does exactly that.

```php
$handle = $result->handle();

$handle->can(Action::SettlePayment);     // allowed right now?
$handle->availableActions();             // [Action::SettlePayment, Action::CancelPayment, …]
$handle->isFinal();                      // no further actions (e.g. after a full refund)

$json = $handle->toJson();               // store with the booking (no card data, no credentials)
$handle = PaymentHandle::fromJson($json);
```

**Golden rule:** after every successful call, **replace** the stored handle with `$newResult->handle()`. Links
change. For example, `self` changes after settlement, and old links may stop working.

If you call an action the handle doesn't offer, the SDK throws `ActionNotAvailableException` without contacting
Worldpay.

A handle remembers the authorization currency, and partial amounts must use it. It's also tied to its environment:
a Try handle is refused in Live mode.

## Actions

```php
$payments = $worldpay->payments();

$payments->settle($handle);                                                  // full capture
$payments->partialSettle($handle, Money::fromDecimal('125', 'GBP'), 'BKG-102938-PS1', Sequence::of(1, 2));
$payments->cancel($handle);                                                  // full release (optional reference)
$payments->cancel($handle, 'BKG-102938-CANCEL');
$payments->partialCancel($handle, Money::fromDecimal('50', 'GBP'), 'BKG-102938-PC');
$payments->refund($handle);                                                  // full refund
$payments->partialRefund($handle, Money::fromDecimal('50', 'GBP'), 'BKG-102938-PR1');
$payments->reverse($handle);                                                 // cancel or refund, Worldpay decides
$payments->increaseAuthorization($handle, Money::fromDecimal('20', 'GBP'));  // estimated authorizations only
```

| Method | Allowed when | Body | Outcome | What's allowed next |
|---|---|---|---|---|
| `settle` | authorized | — | `SentForSettlement` | refund, partial refund, reverse |
| `partialSettle` | authorized, or partially settled | `reference`, `value`, optional `sequence` | `SentForSettlement` | partial settle, cancel (the rest), refund, partial refund, reverse |
| `cancel` | authorized, before settlement | optional `reference` (letters, digits, `-`) | `SentForCancellation` | nothing |
| `partialCancel` | authorized | `reference` (letters, digits, `-`), `value` | `SentForCancellation` | In Try: settle / cancel the rest. The spec says nothing. |
| `refund` | settled | — | `SentForRefund` | nothing |
| `partialRefund` | settled | `reference`, `value` | `SentForPartialRefund` | partial refund |
| `reverse` | authorized or settled | — | `SentForReversal` | nothing |
| `increaseAuthorization` | authorized with `estimated()` | `value` (the increase) | `Authorized` (`totalAuthorized()`) or `Refused` | settle, cancel, … |

Use a unique `reference` for every partial action (`-PS1`, `-PS2`, `-PR1` …), so finance can reconcile them.

### `202` means "accepted", not "done"

Every manage action returns HTTP 202. Worldpay has accepted the request, but the money hasn't moved yet. Put the
booking in a pending state such as "settlement requested" or "refund requested". Confirm the final state later:
query the payment, use Worldpay reports, or use event notifications if they're configured on your account.

### Your application must enforce the rules

Worldpay Try **didn't reject** these invalid requests synchronously; each returned `202` (verified 2026-10-07):

- settling a cancelled payment, using an old link
- cancelling a settled payment, using an old link
- a partial refund larger than the settled amount (£999.99 against £250.00)

Live may reject them later instead. So:

1. Always use the **latest** handle. The SDK then only offers actions Worldpay currently allows.
2. Track the settled and refunded totals per payment in your database, and never refund more than was settled.
3. A **second partial cancel cancels everything that's left**, whatever amount you send. Allow only one.

## Query

```php
$query = $payments->query($handle);                                   // throws PaymentNotYetQueryableException if too early
$query = $payments->query($handle, waitSeconds: 120, pollIntervalSeconds: 5);  // polls through the 404 window

$query->lastEvent;   // LastEvent::Authorized, SentForSettlement, Settled, Refunded, RefundFailed, Expired, …
$query->handle();    // the actions the query reported
```

⚠ **The query lags** (verified in Try):

- For **25–65 seconds** after authorization, the query returns `404`, which the SDK raises as
  `PaymentNotYetQueryableException`.
- After a settle or cancel, `lastEvent` still showed `Authorized` for **at least 2.5 minutes**.

So use query for **reconciliation** only: after an unknown outcome, or in a scheduled job that confirms
`Settled` / `Refunded`. Don't use it to decide the next action. Each action's own response and handle are what
count. Ask Worldpay whether Live has the same delay.

## Recommended Aero Tickets flows

| Scenario | Calls |
|---|---|
| Normal booking | authorize → issue ticket → `settle` |
| Ticketing failed | authorize → `cancel` |
| Fare went down before issuing | authorize → `partialCancel` (the difference) → `settle` (the rest) |
| Several passengers, ticketed separately | authorize the total → `partialSettle` 1/N … N/N |
| Whole booking cancelled after issuing | `refund` |
| One passenger or segment cancelled | `partialRefund`, repeated as needed |
| Agent clicks "undo" right after booking | `reverse` |
| Timeout or 5xx on the **authorization** | resend with the **same** transactionReference (see [Errors & retries](errors-and-retries.md)) |
| Timeout or 5xx on a **manage action** | don't fire it again straight away; `query` later, or reconcile, then act |

> Authorizations expire (`LastEvent::Expired`) if they aren't settled in time. The window depends on the card scheme
> and your setup. Ask your Worldpay Implementation Manager, and settle well before then.

## Example: settle when the ticket is issued

```php
public function settleForTicketing(CardPayment $payment): void
{
    $handle = PaymentHandle::fromJson($payment->worldpay_handle);

    if (! $handle->can(Action::SettlePayment)) {
        throw new DomainException('Payment can no longer be settled: '.implode(', ', array_map(fn ($a) => $a->value, $handle->availableActions())));
    }

    $result = Worldpay::payments()->settle($handle);

    $payment->update([
        'status' => 'settlement_requested',
        'worldpay_handle' => $result->handle()->toJson(),     // replace, always
    ]);
}
```
