<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Payments\Instrument;

use AeroTickets\Worldpay\Exceptions\InvalidRequestException;

/**
 * How the customer pays. Implementations:
 *
 * - CheckoutSession: session from the browser Checkout SDK (recommended: no card data on your servers)
 * - WorldpayToken:   a stored Worldpay token
 * - PlainCard:       raw card number/expiry/CVC (full PCI scope)
 * - RawInstrument:   any other instrument type, passed through as given
 */
interface PaymentInstrument
{
    /** Worldpay's paymentInstrument.type, e.g. "checkout". */
    public function type(): string;

    /**
     * The paymentInstrument object sent to Worldpay. Validates first.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidRequestException
     */
    public function toArray(): array;
}
