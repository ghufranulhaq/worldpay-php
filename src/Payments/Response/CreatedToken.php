<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Payments\Response;

use AeroTickets\Worldpay\Payments\Instrument\WorldpayToken;

/** The token Worldpay created (AuthorizeRequest::createToken()). Store `href` to pay with it later. */
final class CreatedToken
{
    public function __construct(
        public readonly ?string $href,
        public readonly ?string $tokenId,
        public readonly ?string $expiresAt,
        public readonly ?string $maskedCardNumber,
        public readonly ?string $cardHolderName,
        public readonly ?int $expiryMonth,
        public readonly ?int $expiryYear,
        public readonly bool $hasConflicts,
    ) {}

    /** @param array<string, mixed> $raw */
    public static function fromApi(array $raw): self
    {
        $s = static fn (string $k): ?string => isset($raw[$k]) && $raw[$k] !== '' ? (string) $raw[$k] : null;

        return new self(
            $s('href'),
            $s('tokenId'),
            $s('tokenExpiryDateTime'),
            $s('cardNumber'),
            $s('cardHolderName'),
            isset($raw['cardExpiry']['month']) ? (int) $raw['cardExpiry']['month'] : null,
            isset($raw['cardExpiry']['year']) ? (int) $raw['cardExpiry']['year'] : null,
            isset($raw['conflicts']),
        );
    }

    /** Ready-to-use instrument for a later payment. */
    public function toInstrument(): ?WorldpayToken
    {
        return match (true) {
            $this->href !== null => WorldpayToken::href($this->href),
            $this->tokenId !== null => WorldpayToken::id($this->tokenId),
            default => null,
        };
    }
}
