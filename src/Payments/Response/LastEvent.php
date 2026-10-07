<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Payments\Response;

/** `lastEvent` from a payment query. */
enum LastEvent: string
{
    case Authorized = 'Authorized';
    case Refused = 'Refused';
    case SentForSettlement = 'Sent for Settlement';
    case Settled = 'Settled';
    case SettlementFailed = 'Settlement failed';
    case SentForRefund = 'Sent for Refund';
    case Refunded = 'Refunded';
    case RefundFailed = 'Refund failed';
    case SentForCancellation = 'Sent for Cancellation';
    case Error = 'Error';
    case Expired = 'Expired';
    case Unknown = 'Unknown';

    public static function fromApi(?string $value): self
    {
        if ($value === null) {
            return self::Unknown;
        }
        foreach (self::cases() as $case) {
            if (strcasecmp($case->value, $value) === 0) {
                return $case;
            }
        }

        return self::Unknown;
    }
}
