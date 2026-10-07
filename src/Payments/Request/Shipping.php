<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Payments\Request;

use AeroTickets\Worldpay\Support\Validate;

/**
 * Optional delivery details (instruction.shipping), used by FraudSight.
 *
 *   new Shipping(method: ShippingMethod::UnshippedTickets, timeFrame: ShippingTimeFrame::Electronic, email: 'jo@example.com')
 */
final class Shipping
{
    public function __construct(
        public readonly ?ShippingMethod $method = null,
        public readonly ?ShippingTimeFrame $timeFrame = null,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly ?Address $address = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $out = [];
        if ($this->method !== null) {
            $out['method'] = $this->method->value;
        }
        if ($this->timeFrame !== null) {
            $out['timeFrame'] = $this->timeFrame->value;
        }
        if ($this->email !== null && $this->email !== '') {
            Validate::length($this->email, 'shipping.email', 1, 128);
            $out['email'] = $this->email;
        }
        if ($this->phone !== null && $this->phone !== '') {
            $phone = (string) preg_replace('/\D+/', '', $this->phone);
            Validate::length($phone, 'shipping.phone', 4, 20);
            $out['phone'] = $phone;
        }
        foreach (['firstName' => $this->firstName, 'lastName' => $this->lastName] as $key => $value) {
            if ($value !== null && $value !== '') {
                Validate::length($value, 'shipping.'.$key, 1, 22);
                $out[$key] = $value;
            }
        }
        if ($this->address !== null) {
            $out['address'] = $this->address->toArray('shipping.address', postalCodeRequired: true);
        }

        return $out;
    }
}
