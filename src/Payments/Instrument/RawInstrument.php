<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Payments\Instrument;

use AeroTickets\Worldpay\Exceptions\InvalidRequestException;

/**
 * Escape hatch: send any paymentInstrument object the Payments API accepts (e.g. "networkToken",
 * "delegate") exactly as given. Only a non-empty "type" is checked; you are responsible for the rest.
 *
 *   RawInstrument::fromArray(['type' => 'networkToken', ...])
 */
final class RawInstrument implements PaymentInstrument
{
    /** @param array<string, mixed> $instrument */
    private function __construct(private readonly array $instrument) {}

    /** @param array<string, mixed> $instrument */
    public static function fromArray(array $instrument): self
    {
        return new self($instrument);
    }

    public function type(): string
    {
        return (string) ($this->instrument['type'] ?? '');
    }

    public function toArray(): array
    {
        if (! is_string($this->instrument['type'] ?? null) || $this->instrument['type'] === '') {
            throw InvalidRequestException::forField('paymentInstrument.type', 'is required.');
        }

        return $this->instrument;
    }

    public function __debugInfo(): array
    {
        return ['type' => $this->type(), 'fields' => array_keys($this->instrument)];
    }
}
