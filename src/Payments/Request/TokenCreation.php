<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Payments\Request;

use AeroTickets\Worldpay\Payments\Instrument\WorldpayToken;

/**
 * Ask Worldpay to store the card as a token after the payment (instruction.tokenCreation).
 * Requires token entitlement. The created token comes back in PaymentResult::token().
 *
 * @param  string|null  $namespace  groups tokens, e.g. one per Aero Tickets customer ("customer-42")
 */
final class TokenCreation
{
    private function __construct(public readonly ?string $namespace) {}

    public static function worldpay(?string $namespace = null): self
    {
        return new self($namespace);
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        $out = ['type' => 'worldpay'];
        if ($this->namespace !== null) {
            WorldpayToken::validateNamespace($this->namespace, 'tokenCreation.namespace');
            $out['namespace'] = $this->namespace;
        }

        return $out;
    }
}
