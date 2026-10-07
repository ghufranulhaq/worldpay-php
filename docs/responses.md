# Responses

Every authorization and every manage action returns a `PaymentResult`. `query()` returns a `QueryResult`.

← [Managing payments](managing-payments.md) · Next: [Errors & retries](errors-and-retries.md)

## `PaymentResult`

| Member | Type | Description |
|---|---|---|
| `outcome` | `Outcome` | See the table below |
| `httpStatus` | `int` | `201` (authorize), `202` (actions, auto settlement), `200` |
| `handle()` | `PaymentHandle` | **Store this**, and use it for the next action |
| `paymentId()` | `?string` | Worldpay payment id, e.g. `payI-dUcet9fk4_X4qZU0hpU0` |
| `transactionReference()` | `?string` | Your reference, echoed back |
| `commandId()` | `?string` | Id of this specific command |
| `authorizationCode()` | `?string` | Issuer authorization code (`issuer.authorizationCode`) |
| `schemeReference()` | `?string` | Card scheme reference |
| `isAuthorized()` / `isRefused()` | `bool` | |
| `isSuccessful()` | `bool` | `Authorized` or `SentForSettlement` |
| `needsReview()` | `bool` | Successful, but a CVC/AVS check failed or FraudSight said "review" |
| `refusal()` | `?Refusal` | Only when refused |
| `riskFactors()` | `RiskFactors` | CVC / AVS results |
| `card()` | `?CardSummary` | Masked card details: brand, BIN, last four, expiry, funding type … |
| `fraud()` | `?FraudAssessment` | FraudSight result |
| `token()` | `?CreatedToken` | The token created by `createToken()` |
| `totalAuthorized()` | `?Money` | Present for partial and increased authorizations |
| `toArray()` | `array` | Card-safe summary for audit logs and the database |
| `raw()` | `array` | The decoded Worldpay body. Worldpay already masks card data in responses. |

### `Outcome`

| Value | From | Meaning | What to do |
|---|---|---|---|
| `Authorized` | authorize (201), `increaseAuthorization` | Funds held | Settle when the ticket is issued, cancel if not. If `needsReview()`, decide by policy. |
| `Refused` | authorize (201), `increaseAuthorization` | Declined | Tell the agent "card declined" (generic). Use `refusal()->retryAdvice()`. |
| `FraudHighRisk` | authorize (201) | FraudSight stopped it | "Unable to process this card." Don't retry the same card. |
| `SentForSettlement` | `settle`, `partialSettle`, auto settle (202) | Capture accepted | Mark it "paid (settlement requested)" |
| `SentForCancellation` | `cancel`, `partialCancel`, auto settle + mismatch (202) | Released | Mark it "not paid". With auto settle, ask for another card. |
| `SentForRefund` / `SentForPartialRefund` | `refund` / `partialRefund` (202) | Refund accepted | Mark it "refund requested" |
| `SentForReversal` | `reverse` (202) | Reversal accepted | |
| `Unknown` | anything else | An outcome this SDK version doesn't know | Inspect `raw()`. Upgrade the SDK. |

## Risk factors (CVC and AVS)

Worldpay lists a check **only when it didn't cleanly pass**. A `null` result means the check passed.

```php
$risk = $result->riskFactors();
$risk->cvc();          // ?Risk: null (passed), NotMatched, NotChecked, NotSupplied, VerificationFailed
$risk->avsPostcode();  // ?Risk
$risk->avsAddress();   // ?Risk
$risk->hasMismatch();  // any NotMatched / VerificationFailed
$risk->toArray();      // [['type' => 'cvc', 'risk' => 'notMatched', 'detail' => null], …]
```

The API returns camelCase (`notMatched`) and some Worldpay docs show snake_case (`not_matched`). The SDK accepts
both.

## `Refusal`

| Member | Description |
|---|---|
| `code` | Issuer refusal code, e.g. `51` |
| `description` | e.g. `LIMIT EXCEEDED` |
| `adviceCode` | Mastercard `advice.code`, if present |
| `retryAdvice()` | `RetryAdvice::RetryWithUpdatedDetails` (01), `RetryLater` (02, 24–30), `DoNotRetry` (03), `None` |
| `retryAfter()` | `?DateInterval`: 72h for 02; 1h / 24h / 2d / 4d / 6d / 8d / 10d for 24–30 |
| `isSoftDecline()` | Code 65: the issuer wants authentication. On MOTO you can't step up to 3DS, so ask for another card. |

Don't read fraud, lost or stolen reasons out to the caller. Show a generic "card declined".

## `CardSummary`

`type`, `brand` (`visa`, `mastercard`, `amex`, …), `bin`, `lastFour`, `expiryMonth`, `expiryYear`, `fundingType`
(`credit`, `debit`, …), `category`, `countryCode`, `issuerName` (`UNKNOWN` for Try cards),
`paymentAccountReference`, and `label()` → `"visa •••• 2701"`.

## `FraudAssessment`

`outcome` (`lowRisk`, `highRisk`, `review`, with a `(silentMode)` suffix in silent mode), `score`, `reasons`
(`FraudHighRisk` only), `isHighRisk()` and `needsReview()`.

## `CreatedToken`

`href`, `tokenId`, `expiresAt`, `maskedCardNumber`, `cardHolderName`, `expiryMonth`, `expiryYear`, `hasConflicts`,
and `toInstrument()`, which returns a ready `WorldpayToken`.

## `QueryResult`

`lastEvent` (`LastEvent::Authorized`, `Refused`, `SentForSettlement`, `Settled`, `SettlementFailed`, `SentForRefund`,
`Refunded`, `RefundFailed`, `SentForCancellation`, `Error`, `Expired`, `Unknown`), `handle()` and `raw()`.
Remember the query lags. See [Managing payments](managing-payments.md#query).

## What to store

| Store | Why |
|---|---|
| `transactionReference` | Your idempotency key. Save it **before** calling authorize. |
| `paymentId()` | Worldpay's id, for support and reconciliation |
| `handle()->toJson()` | Needed for every later action. Replace it after each call. |
| `outcome`, `authorizationCode()`, `schemeReference()` | Audit and finance |
| `card()->label()` / `lastFour` / `brand` | Showing the agent which card was used |
| `riskFactors()->toArray()` | Why a payment needed review |
| `toArray()` | A complete, card-safe audit record |

**Never store** a card number, CVC, session href or CVC session href. The SDK gives you none of the first two, and
you shouldn't keep the last two.
