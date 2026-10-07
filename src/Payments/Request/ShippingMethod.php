<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Payments\Request;

/** instruction.shipping.method. Airline tickets are usually UnshippedTickets or Digital. */
enum ShippingMethod: string
{
    case BillingAddress = 'billingAddress';
    case VerifiedAddress = 'verifiedAddress';
    case OtherAddress = 'otherAddress';
    case Store = 'store';
    case Digital = 'digital';
    case UnshippedTickets = 'unshippedTickets';
    case Other = 'other';
}
