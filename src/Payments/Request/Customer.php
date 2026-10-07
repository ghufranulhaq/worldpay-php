<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Payments\Request;

use AeroTickets\Worldpay\Exceptions\InvalidRequestException;
use AeroTickets\Worldpay\Support\Validate;

/**
 * Optional customer details (instruction.customer). Mostly useful with FraudSight.
 * All fields are optional; only the ones you set are sent.
 */
final class Customer
{
    public function __construct(
        public readonly ?string $customerId = null,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
        public readonly ?\DateTimeInterface $dateOfBirth = null,
        public readonly ?string $ipAddress = null,
        public readonly ?string $documentReference = null,
    ) {}

    /** @return array<string, string> */
    public function toArray(): array
    {
        $out = [];
        $put = static function (string $key, ?string $value, int $min, int $max) use (&$out): void {
            if ($value === null || $value === '') {
                return;
            }
            Validate::length($value, 'customer.'.$key, $min, $max);
            $out[$key] = $value;
        };

        $put('customerId', $this->customerId, 1, 128);
        $put('firstName', $this->firstName, 1, 22);
        $put('lastName', $this->lastName, 1, 22);
        if ($this->phone !== null && $this->phone !== '') {
            $phone = (string) preg_replace('/\D+/', '', $this->phone);
            Validate::length($phone, 'customer.phone', 4, 20);
            $out['phone'] = $phone;
        }
        if ($this->dateOfBirth !== null) {
            $out['dateOfBirth'] = $this->dateOfBirth->format('Y-m-d');
        }
        if ($this->email !== null && $this->email !== '') {
            Validate::length($this->email, 'customer.email', 1, 128);
            if (! str_contains($this->email, '@')) {
                throw InvalidRequestException::forField('customer.email', 'is not an email address.');
            }
            $out['email'] = $this->email;
        }
        $put('ipAddress', $this->ipAddress, 1, 64);
        if ($this->documentReference !== null && $this->documentReference !== '') {
            Validate::length($this->documentReference, 'customer.documentReference', 1, 50);
            Validate::pattern($this->documentReference, 'customer.documentReference', '/^[-A-Za-z0-9_\/\\\\*~+.,&()]*$/', 'letters, digits and - _ / \\ * ~ + . , & ( )');
            $out['documentReference'] = $this->documentReference;
        }

        return $out;
    }
}
