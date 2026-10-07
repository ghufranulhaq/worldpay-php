<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Exceptions;

/**
 * A request failed the SDK's local validation, so nothing was sent to Worldpay.
 * `field` names the offending input (e.g. "paymentInstrument.cardNumber").
 */
final class InvalidRequestException extends WorldpayException
{
    public function __construct(string $message, public readonly ?string $field = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function forField(string $field, string $message): self
    {
        return new self(sprintf('%s: %s', $field, $message), $field);
    }
}
