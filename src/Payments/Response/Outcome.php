<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Payments\Response;

/** The `outcome` of a payment request or action. */
enum Outcome: string
{
    /** Funds held; settle or cancel later. */
    case Authorized = 'authorized';
    /** Declined by the issuer. Nothing to settle/cancel. See PaymentResult::refusal(). */
    case Refused = 'refused';
    /** Capture accepted (settle, partial settle, or auto settlement). */
    case SentForSettlement = 'sentForSettlement';
    /** Cancel accepted, or auto-cancelled after a CVC/AVS mismatch with auto settlement. */
    case SentForCancellation = 'sentForCancellation';
    case SentForRefund = 'sentForRefund';
    case SentForPartialRefund = 'sentForPartialRefund';
    case SentForReversal = 'sentForReversal';
    /** FraudSight stopped the payment. Do not retry with the same card. */
    case FraudHighRisk = 'fraudHighRisk';
    /** An outcome this SDK version does not know. Inspect PaymentResult::raw(). */
    case Unknown = 'unknown';

    public static function fromApi(?string $value): self
    {
        return $value === null ? self::Unknown : (self::tryFrom($value) ?? self::Unknown);
    }

    /** Money is (or will be) captured or held: authorized or sent for settlement. */
    public function isSuccessful(): bool
    {
        return $this === self::Authorized || $this === self::SentForSettlement;
    }
}
