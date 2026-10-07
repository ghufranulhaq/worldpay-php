<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Payments\Response;

/** Masked card details from the response (`paymentInstrument`). Safe to store and display. */
final class CardSummary
{
    public function __construct(
        public readonly ?string $type,
        public readonly ?string $brand,
        public readonly ?string $bin,
        public readonly ?string $lastFour,
        public readonly ?int $expiryMonth,
        public readonly ?int $expiryYear,
        public readonly ?string $fundingType,
        public readonly ?string $category,
        public readonly ?string $countryCode,
        public readonly ?string $issuerName,
        public readonly ?string $paymentAccountReference,
    ) {}

    /** @param array<string, mixed> $raw */
    public static function fromApi(array $raw): self
    {
        $s = static fn (string $k): ?string => isset($raw[$k]) && $raw[$k] !== '' ? (string) $raw[$k] : null;

        return new self(
            $s('type'),
            $s('cardBrand'),
            $s('cardBin'),
            $s('lastFour'),
            isset($raw['expiryDate']['month']) ? (int) $raw['expiryDate']['month'] : null,
            isset($raw['expiryDate']['year']) ? (int) $raw['expiryDate']['year'] : null,
            $s('fundingType'),
            $s('category'),
            $s('countryCode'),
            $s('issuerName'),
            $s('paymentAccountReference'),
        );
    }

    /** e.g. "visa •••• 2701" */
    public function label(): string
    {
        return trim(($this->brand ?? 'card').' •••• '.($this->lastFour ?? '????'));
    }
}
