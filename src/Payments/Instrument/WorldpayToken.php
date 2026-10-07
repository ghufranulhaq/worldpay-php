<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Payments\Instrument;

use AeroTickets\Worldpay\Exceptions\InvalidRequestException;
use AeroTickets\Worldpay\Support\Validate;

/**
 * A card stored as a Worldpay token (created with AuthorizeRequest::createToken()).
 * Requires token entitlement on your Worldpay account.
 *
 * MOTO is for customer-initiated payments only: use a token when the customer is on the phone and
 * agrees to pay with their saved card, never for recurring/merchant-initiated charges.
 *
 *   WorldpayToken::href($tokenHref)->withCvcSession($cvcSessionHref)   // CVC typed into the browser CVC field
 *   WorldpayToken::href($tokenHref)->withCvc('123')                    // CVC on your server (PCI scope)
 *   WorldpayToken::id('9…', namespace: 'customer-42')
 */
final class WorldpayToken implements PaymentInstrument
{
    use HasRouting;

    private ?string $cvc = null;

    private ?string $cvcSessionHref = null;

    private function __construct(
        public readonly ?string $href,
        public readonly ?string $tokenId,
        public readonly ?string $namespace,
    ) {}

    /** By token href (the `token.href` from the response that created it). */
    public static function href(string $href): self
    {
        return new self(trim($href), null, null);
    }

    /** By token id (15–21 chars) and optional namespace. */
    public static function id(string $tokenId, ?string $namespace = null): self
    {
        return new self(null, trim($tokenId), $namespace);
    }

    /** CVC typed by the agent and sent from your server. Do not store it. */
    public function withCvc(#[\SensitiveParameter] string $cvc): self
    {
        $copy = clone $this;
        $copy->cvc = trim($cvc);
        $copy->cvcSessionHref = null;

        return $copy;
    }

    /** CVC session from the browser Checkout SDK (15 minutes, single use). */
    public function withCvcSession(string $cvcSessionHref): self
    {
        $copy = clone $this;
        $copy->cvcSessionHref = trim($cvcSessionHref);
        $copy->cvc = null;

        return $copy;
    }

    public function type(): string
    {
        return 'token';
    }

    public function toArray(): array
    {
        $instrument = ['type' => 'token'];

        if ($this->href !== null) {
            Validate::notBlank($this->href, 'paymentInstrument.href');
            $instrument['href'] = $this->href;
        } else {
            $tokenId = (string) $this->tokenId;
            if (! preg_match('/^[0-9A-HJ-NP-Z]{15,21}$/', $tokenId)) {
                throw InvalidRequestException::forField('paymentInstrument.tokenId', 'must be 15–21 characters of 0-9 and A-Z (no I or O).');
            }
            $instrument['tokenId'] = $tokenId;
            if ($this->namespace !== null) {
                self::validateNamespace($this->namespace, 'paymentInstrument.namespace');
                $instrument['namespace'] = $this->namespace;
            }
        }

        if ($this->cvc !== null) {
            Validate::cvc($this->cvc, 'paymentInstrument.cvc');
            $instrument['cvc'] = $this->cvc;
        } elseif ($this->cvcSessionHref !== null) {
            Validate::notBlank($this->cvcSessionHref, 'paymentInstrument.cvcSessionHref');
            $instrument['cvcSessionHref'] = $this->cvcSessionHref;
        }

        return $this->withRouting($instrument);
    }

    /** @internal */
    public static function validateNamespace(string $namespace, string $field): void
    {
        Validate::length($namespace, $field, 1, 64);
        if (! preg_match('/^[^_][^ &<]*$/', $namespace)) {
            throw InvalidRequestException::forField($field, 'must not start with "_" or contain spaces, "&" or "<".');
        }
    }

    public function __debugInfo(): array
    {
        return [
            'href' => $this->href !== null ? '[redacted]' : null,
            'tokenId' => $this->tokenId,
            'namespace' => $this->namespace,
            'cvc' => $this->cvc !== null ? '***' : null,
            'cvcSessionHref' => $this->cvcSessionHref !== null ? '[redacted]' : null,
        ];
    }
}
