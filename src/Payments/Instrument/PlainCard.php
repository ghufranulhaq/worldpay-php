<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Payments\Instrument;

use AeroTickets\Worldpay\Payments\Request\Address;
use AeroTickets\Worldpay\Support\Validate;

/**
 * Raw card details sent from your server.
 *
 * ⚠ Full PCI DSS scope: the card number and CVC pass through your application. Prefer
 * CheckoutSession. If you must use this: never store, log or display the card number or CVC.
 *
 *   PlainCard::of('4000000000002701', 5, 2035, '555', 'J Smith', Address::of(...))
 */
final class PlainCard implements PaymentInstrument
{
    use HasRouting;

    private function __construct(
        private readonly string $cardNumber,
        public readonly int $expiryMonth,
        public readonly int $expiryYear,
        private readonly ?string $cvc,
        public readonly ?string $cardHolderName,
        public readonly ?Address $billingAddress,
    ) {}

    /**
     * @param  string  $cardNumber  spaces and dashes are removed
     * @param  string|null  $cvc  3 digits (4 for Amex); strongly recommended for MOTO, it is the main fraud check
     */
    public static function of(
        #[\SensitiveParameter] string $cardNumber,
        int $expiryMonth,
        int $expiryYear,
        #[\SensitiveParameter] ?string $cvc = null,
        ?string $cardHolderName = null,
        ?Address $billingAddress = null,
    ): self {
        return new self(
            (string) preg_replace('/[\s-]+/', '', $cardNumber),
            $expiryMonth,
            $expiryYear,
            $cvc !== null && trim($cvc) !== '' ? trim($cvc) : null,
            $cardHolderName !== null ? trim($cardHolderName) : null,
            $billingAddress,
        );
    }

    public function type(): string
    {
        return 'plain';
    }

    /** Last four digits, safe to display or store. */
    public function lastFour(): string
    {
        return substr($this->cardNumber, -4);
    }

    public function toArray(): array
    {
        Validate::cardNumber($this->cardNumber, 'paymentInstrument.cardNumber');
        Validate::expiry($this->expiryMonth, $this->expiryYear, 'paymentInstrument.expiryDate');

        $instrument = [
            'type' => 'plain',
            'cardNumber' => $this->cardNumber,
            'expiryDate' => ['month' => $this->expiryMonth, 'year' => $this->expiryYear],
        ];
        if ($this->cvc !== null) {
            Validate::cvc($this->cvc, 'paymentInstrument.cvc');
            $instrument['cvc'] = $this->cvc;
        }
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
        return [
            'cardNumber' => '**** **** **** '.$this->lastFour(),
            'expiryMonth' => $this->expiryMonth,
            'expiryYear' => $this->expiryYear,
            'cvc' => $this->cvc !== null ? '***' : null,
            'cardHolderName' => $this->cardHolderName,
            'billingAddress' => $this->billingAddress,
        ];
    }
}
