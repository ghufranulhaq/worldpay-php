<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Payments\Response;

/**
 * CVC and AVS results. Worldpay only lists checks that did NOT cleanly pass, so a null result
 * from cvc()/avsPostcode()/avsAddress() means the check passed (or was not reported).
 */
final class RiskFactors
{
    /** @param list<array{type: string, risk: Risk, detail: ?string}> $factors */
    private function __construct(private readonly array $factors) {}

    /** @param mixed $raw the `riskFactors` array from a response */
    public static function fromApi(mixed $raw): self
    {
        $factors = [];
        foreach (is_array($raw) ? $raw : [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $factors[] = [
                'type' => (string) ($item['type'] ?? ''),
                'risk' => Risk::fromApi(isset($item['risk']) ? (string) $item['risk'] : null),
                'detail' => isset($item['detail']) ? (string) $item['detail'] : null,
            ];
        }

        return new self($factors);
    }

    public function cvc(): ?Risk
    {
        return $this->find('cvc', null);
    }

    public function avsPostcode(): ?Risk
    {
        return $this->find('avs', 'postcode');
    }

    public function avsAddress(): ?Risk
    {
        return $this->find('avs', 'address');
    }

    /** riskProfile entries (FraudSight / risk engine), if any. */
    public function riskProfile(): ?Risk
    {
        return $this->find('riskProfile', null);
    }

    /** True if any check failed (notMatched or verificationFailed). Decide by policy whether to settle. */
    public function hasMismatch(): bool
    {
        foreach ($this->factors as $factor) {
            if ($factor['risk']->isFailure()) {
                return true;
            }
        }

        return false;
    }

    public function isEmpty(): bool
    {
        return $this->factors === [];
    }

    /** @return list<array{type: string, risk: string, detail: ?string}> */
    public function toArray(): array
    {
        return array_map(
            static fn (array $f) => ['type' => $f['type'], 'risk' => $f['risk']->value, 'detail' => $f['detail']],
            $this->factors,
        );
    }

    private function find(string $type, ?string $detail): ?Risk
    {
        foreach ($this->factors as $factor) {
            if ($factor['type'] === $type && ($detail === null || $factor['detail'] === $detail)) {
                return $factor['risk'];
            }
        }

        return null;
    }
}
