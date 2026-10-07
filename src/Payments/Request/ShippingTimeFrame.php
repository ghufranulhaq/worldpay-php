<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Payments\Request;

/** instruction.shipping.timeFrame */
enum ShippingTimeFrame: string
{
    case Electronic = 'electronic';
    case SameDay = 'sameDay';
    case NextDay = 'nextDay';
    case TwoDaysPlus = 'twoDaysPlus';
}
