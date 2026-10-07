<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Payments\Response;

use AeroTickets\Worldpay\Payments\PaymentHandle;
use AeroTickets\Worldpay\Support\Money;

/**
 * The result of an authorization or of a settle/cancel/refund/reverse/increase action.
 *
 * Always persist handle() with the booking: it carries the links for the next action.
 * Manage actions return 202 "accepted for processing", not "done": set your booking to a pending
 * state (e.g. "refund requested") and confirm later via query() or Worldpay reports.
 */
final class PaymentResult
{
    /** @param array<string, mixed> $raw decoded response body */
    public function __construct(
        public readonly Outcome $outcome,
        public readonly int $httpStatus,
        private readonly PaymentHandle $handle,
        private readonly array $raw,
    ) {}

    /** @param array<string, mixed> $body */
    public static function fromResponse(int $status, array $body, ?PaymentHandle $previous = null, ?string $currency = null): self
    {
        $handle = PaymentHandle::fromResponse($body, $currency);
        if ($previous !== null) {
            $handle = $previous->mergedWith($handle);
        }

        return new self(Outcome::fromApi(isset($body['outcome']) ? (string) $body['outcome'] : null), $status, $handle, $body);
    }

    /** Store this (toJson()) and use it for the next action. */
    public function handle(): PaymentHandle
    {
        return $this->handle;
    }

    public function paymentId(): ?string
    {
        return $this->handle->paymentId;
    }

    public function transactionReference(): ?string
    {
        return $this->handle->transactionReference;
    }

    public function commandId(): ?string
    {
        return $this->string('commandId');
    }

    public function schemeReference(): ?string
    {
        return $this->string('schemeReference') ?? (isset($this->raw['scheme']['reference']) ? (string) $this->raw['scheme']['reference'] : null);
    }

    public function authorizationCode(): ?string
    {
        return isset($this->raw['issuer']['authorizationCode']) ? (string) $this->raw['issuer']['authorizationCode'] : null;
    }

    public function isAuthorized(): bool
    {
        return $this->outcome === Outcome::Authorized;
    }

    public function isRefused(): bool
    {
        return $this->outcome === Outcome::Refused;
    }

    /** Authorized or sent for settlement. */
    public function isSuccessful(): bool
    {
        return $this->outcome->isSuccessful();
    }

    /** Successful but a CVC/AVS check failed: decide by policy whether to settle or cancel. */
    public function needsReview(): bool
    {
        return $this->isSuccessful() && ($this->riskFactors()->hasMismatch() || ($this->fraud()?->needsReview() ?? false));
    }

    public function refusal(): ?Refusal
    {
        if ($this->outcome !== Outcome::Refused) {
            return null;
        }

        return new Refusal(
            $this->string('refusalCode'),
            $this->string('refusalDescription'),
            isset($this->raw['advice']['code']) ? (string) $this->raw['advice']['code'] : null,
        );
    }

    public function riskFactors(): RiskFactors
    {
        return RiskFactors::fromApi($this->raw['riskFactors'] ?? []);
    }

    public function card(): ?CardSummary
    {
        return isset($this->raw['paymentInstrument']) && is_array($this->raw['paymentInstrument'])
            ? CardSummary::fromApi($this->raw['paymentInstrument'])
            : null;
    }

    public function fraud(): ?FraudAssessment
    {
        if ($this->outcome === Outcome::FraudHighRisk) {
            return new FraudAssessment(
                'highRisk',
                isset($this->raw['score']) ? (float) $this->raw['score'] : null,
                array_values(array_map('strval', (array) ($this->raw['reason'] ?? []))),
            );
        }
        if (isset($this->raw['fraud']) && is_array($this->raw['fraud'])) {
            return new FraudAssessment(
                isset($this->raw['fraud']['outcome']) ? (string) $this->raw['fraud']['outcome'] : null,
                isset($this->raw['fraud']['score']) ? (float) $this->raw['fraud']['score'] : null,
            );
        }

        return null;
    }

    public function token(): ?CreatedToken
    {
        return isset($this->raw['token']) && is_array($this->raw['token']) ? CreatedToken::fromApi($this->raw['token']) : null;
    }

    /** Total now authorized, when Worldpay reports it (partial amounts, increased authorizations). */
    public function totalAuthorized(): ?Money
    {
        $amounts = $this->raw['amounts'] ?? null;
        if (! is_array($amounts)) {
            return null;
        }
        $amount = $amounts['totalAuthorized'] ?? $amounts['authorized'] ?? null;
        $currency = $amounts['currency'] ?? $this->handle->currency;

        return is_int($amount) && is_string($currency) ? Money::ofMinor($amount, $currency) : null;
    }

    /** @return array<string, mixed> the decoded response body (card data is already masked by Worldpay) */
    public function raw(): array
    {
        return $this->raw;
    }

    /**
     * A compact, card-safe summary for audit logs and your database.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $card = $this->card();
        $refusal = $this->refusal();

        return array_filter([
            'outcome' => $this->outcome->value,
            'httpStatus' => $this->httpStatus,
            'paymentId' => $this->paymentId(),
            'transactionReference' => $this->transactionReference(),
            'commandId' => $this->commandId(),
            'authorizationCode' => $this->authorizationCode(),
            'schemeReference' => $this->schemeReference(),
            'card' => $card ? ['brand' => $card->brand, 'lastFour' => $card->lastFour, 'fundingType' => $card->fundingType] : null,
            'riskFactors' => $this->riskFactors()->toArray() ?: null,
            'refusal' => $refusal ? ['code' => $refusal->code, 'description' => $refusal->description, 'advice' => $refusal->adviceCode] : null,
            'fraud' => ($f = $this->fraud()) ? ['outcome' => $f->outcome, 'score' => $f->score] : null,
            'availableActions' => array_map(static fn ($a) => $a->value, $this->handle->availableActions()),
        ], static fn ($v) => $v !== null);
    }

    private function string(string $key): ?string
    {
        return isset($this->raw[$key]) && $this->raw[$key] !== '' ? (string) $this->raw[$key] : null;
    }
}
