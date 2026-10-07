# Changelog

All notable changes to `aerotickets/worldpay-moto`. The format follows [Keep a Changelog](https://keepachangelog.com),
and versions follow [SemVer](https://semver.org). See [Releasing](docs/releasing.md).

| SDK version | Worldpay API version | Compatible `@aerotickets/worldpay-checkout` |
|---|---|---|
| 0.1.0 | 2024-06-01 | 0.1.x |

## [Unreleased]

## [0.1.0] - 2026-10-08

First release.

### Added
- `WorldpayClient` and `Config` for Try and Live, with both base URLs built in. Configuration is validated at
  construction.
- MOTO authorization (`channel: moto`) with these instruments:
  - `CheckoutSession` (recommended)
  - `WorldpayToken`, by href or id, with a CVC or CVC session
  - `PlainCard`
  - `RawInstrument`
- Authorization options: auto/manual settlement with `cancelOn`, estimated amounts, partial amounts, token creation,
  FraudSight (silent mode, custom fields, device data), customer, shipping, MCC, entity override and `withExtra()`.
- Manage actions: settle, partial settle, cancel, partial cancel, refund, partial refund, reverse and increase
  authorization. All of them follow the `_actions` links from the latest response.
- `PaymentHandle`: JSON-serializable state for storing with a booking.
- `query()`, with optional polling through the post-authorization 404 window.
- Typed results: `PaymentResult`, `Outcome`, `RiskFactors`, `Refusal` (with retry advice), `CardSummary`,
  `FraudAssessment`, `CreatedToken` and `QueryResult` / `LastEvent`.
- A typed exception hierarchy, with the `OutcomeUnknown` marker and optional same-reference authorization retries.
- Local validation of references, narrative, card number (Luhn), CVC, expiry, addresses and currencies.
- `Money`, held in integer minor units.
- Card data and secrets are redacted from all logs. Credentials are only ever sent to the configured Worldpay host.
- A Laravel bridge: auto-discovered service provider, `config/worldpay.php`, the `Worldpay` facade and
  `Worldpay::fake()`.
- Testing tools: `WorldpayFake`, `FakeResponses`, `RecordedRequest`, `TestCards`, `MagicValues`, and
  `CheckoutSessionFactory` (Try only).
- Opt-in integration suite against Worldpay Try, porting Postman flows A–E.
