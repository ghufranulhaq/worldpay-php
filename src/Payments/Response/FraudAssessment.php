<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Payments\Response;

/**
 * FraudSight result. `outcome` is lowRisk, highRisk or review (with a "(silentMode)" suffix in
 * silent mode); for a stopped payment (Outcome::FraudHighRisk) Worldpay sends score + reasons instead.
 */
final class FraudAssessment
{
    /** @param list<string> $reasons */
    public function __construct(
        public readonly ?string $outcome,
        public readonly ?float $score,
        public readonly array $reasons = [],
    ) {}

    public function isHighRisk(): bool
    {
        return $this->outcome !== null && str_starts_with($this->outcome, 'highRisk');
    }

    public function needsReview(): bool
    {
        return $this->outcome !== null && str_starts_with($this->outcome, 'review');
    }
}
