<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Payments\Request;

use AeroTickets\Worldpay\Exceptions\InvalidRequestException;
use AeroTickets\Worldpay\Support\Validate;

/**
 * Billing (or shipping) address. The billing address drives the AVS check, which is one of the
 * main fraud signals for MOTO payments, so send it whenever you have it.
 *
 *   Address::of('221B Baker Street', 'London', 'GB', 'NW1 6XE')
 */
final class Address
{
    private function __construct(
        public readonly string $address1,
        public readonly string $city,
        public readonly string $countryCode,
        public readonly ?string $postalCode,
        public readonly ?string $address2,
        public readonly ?string $address3,
        public readonly ?string $state,
    ) {}

    /**
     * @param  string  $countryCode  ISO 3166-1 alpha-2, e.g. "GB"
     * @param  string|null  $postalCode  required for every country except IE
     */
    public static function of(
        string $address1,
        string $city,
        string $countryCode,
        ?string $postalCode = null,
        ?string $address2 = null,
        ?string $address3 = null,
        ?string $state = null,
    ): self {
        $clean = static fn (?string $v): ?string => $v === null || trim($v) === '' ? null : trim($v);

        return new self(
            trim($address1),
            trim($city),
            strtoupper(trim($countryCode)),
            $clean($postalCode),
            $clean($address2),
            $clean($address3),
            $clean($state),
        );
    }

    /** @return array<string, string> */
    public function toArray(string $field = 'billingAddress', bool $postalCodeRequired = false): array
    {
        Validate::length($this->address1, $field.'.address1', 1, 80);
        Validate::length($this->city, $field.'.city', 1, 50);
        Validate::countryCode($this->countryCode, $field.'.countryCode');

        if ($this->postalCode === null && ($postalCodeRequired || $this->countryCode !== 'IE')) {
            throw InvalidRequestException::forField($field.'.postalCode', 'is required (only IE addresses may omit it).');
        }

        $address = ['address1' => $this->address1];
        foreach (['address2' => $this->address2, 'address3' => $this->address3] as $key => $value) {
            if ($value !== null) {
                Validate::length($value, $field.'.'.$key, 1, 80);
                $address[$key] = $value;
            }
        }
        if ($this->postalCode !== null) {
            Validate::length($this->postalCode, $field.'.postalCode', 1, 15);
            $address['postalCode'] = $this->postalCode;
        }
        $address['city'] = $this->city;
        if ($this->state !== null) {
            Validate::length($this->state, $field.'.state', 1, 30);
            $address['state'] = $this->state;
        }
        $address['countryCode'] = $this->countryCode;

        return $address;
    }
}
