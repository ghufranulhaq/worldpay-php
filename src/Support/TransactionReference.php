<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Support;

/**
 * Generates and validates Worldpay transactionReference values.
 *
 * The reference identifies ONE payment across its whole lifecycle. Use a new one for every new
 * payment attempt, and the SAME one when resending an authorization whose outcome was unknown.
 */
final class TransactionReference
{
    /**
     * A unique reference like "AT-20261007T153012-9f2c4a1b7d3e".
     *
     * @param  string  $prefix  letters/digits, e.g. "AT" or a booking number; keep it short (total max 64)
     */
    public static function generate(string $prefix = 'AT'): string
    {
        $reference = sprintf('%s-%s-%s', $prefix, gmdate('Ymd\THis'), bin2hex(random_bytes(6)));
        Validate::transactionReference($reference);

        return $reference;
    }

    public static function assertValid(string $reference): void
    {
        Validate::transactionReference($reference);
    }
}
