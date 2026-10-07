<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Payments\Instrument;

use AeroTickets\Worldpay\Payments\Request\Address;
use AeroTickets\Worldpay\Support\Validate;

/**
 * A card session created in the browser by Worldpay's Checkout SDK (use the
 *
 * @aerotickets/worldpay-checkout package in aero-app). The card number, expiry and CVC go from the
 * browser straight to Worldpay, so they never reach your servers.
 *
 * The session lasts one minute and can be used once: authorize immediately after receiving it.
 *
 *   CheckoutSession::card($sessionHref, 'J Smith', Address::of('221B Baker Street', 'London', 'GB', 'NW1 6XE'))
 */
final class CheckoutSession implements PaymentInstrument
{
    use HasRouting;

    private function __construct(
        public readonly string $sessionHref,
        public readonly ?string $cardHolderName,
        public readonly ?Address $billingAddress,
    ) {}

    public static function card(string $sessionHref, ?string $cardHolderName = null, ?Address $billingAddress = null): self
    {
        return new self(trim($sessionHref), $cardHolderName !== null ? trim($cardHolderName) : null, $billingAddress);
    }

    public function type(): string
    {
        return 'checkout';
    }

    public function toArray(): array
    {
        Validate::notBlank($this->sessionHref, 'paymentInstrument.sessionHref');

        $instrument = ['type' => 'checkout', 'sessionHref' => $this->sessionHref];
        if ($this->cardHolderName !== null && $this->cardHolderName !== '') {
            Validate::length($this->cardHolderName, 'paymentInstrument.cardHolderName', 1, 255);
            $instrument['cardHolderName'] = $this->cardHolderName;
        }
        if ($this->billingAddress !== null) {
            $instrument['billingAddress'] = $this->billingAddress->toArray('paymentInstrument.billingAddress');
        }

        return $this->withRouting($instrument);
    }

    public function __debugInfo(): array
    {
        return ['sessionHref' => '[redacted]', 'cardHolderName' => $this->cardHolderName, 'billingAddress' => $this->billingAddress];
    }
}
