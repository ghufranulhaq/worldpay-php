<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Payments\Response;

/**
 * Why the issuer refused. Show the agent a generic "card declined" message: do not read out
 * fraud, lost or stolen reasons to the caller.
 */
final class Refusal
{
    private const RETRY_AFTER = [
        '02' => 'PT72H', '24' => 'PT1H', '25' => 'PT24H', '26' => 'P2D',
        '27' => 'P4D', '28' => 'P6D', '29' => 'P8D', '30' => 'P10D',
    ];

    public function __construct(
        public readonly ?string $code,
        public readonly ?string $description,
        public readonly ?string $adviceCode,
    ) {}

    public function retryAdvice(): RetryAdvice
    {
        return match (true) {
            $this->adviceCode === null => RetryAdvice::None,
            $this->adviceCode === '01' => RetryAdvice::RetryWithUpdatedDetails,
            $this->adviceCode === '03' => RetryAdvice::DoNotRetry,
            isset(self::RETRY_AFTER[$this->adviceCode]) => RetryAdvice::RetryLater,
            default => RetryAdvice::None,
        };
    }

    /** How long to wait before retrying, when the advice code says so. */
    public function retryAfter(): ?\DateInterval
    {
        return $this->adviceCode !== null && isset(self::RETRY_AFTER[$this->adviceCode])
            ? new \DateInterval(self::RETRY_AFTER[$this->adviceCode])
            : null;
    }

    /** Refusal code 65: the issuer wants authentication. On MOTO you cannot step up to 3DS. */
    public function isSoftDecline(): bool
    {
        return $this->code === '65';
    }
}
