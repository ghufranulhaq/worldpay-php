<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Payments;

/**
 * Follow-up actions Worldpay can offer for a payment (the keys of `_actions`).
 * PaymentHandle::can() tells you which ones are allowed right now.
 */
enum Action: string
{
    case SettlePayment = 'settlePayment';
    case PartiallySettlePayment = 'partiallySettlePayment';
    case CancelPayment = 'cancelPayment';
    case PartiallyCancelPayment = 'partiallyCancelPayment';
    case RefundPayment = 'refundPayment';
    case PartiallyRefundPayment = 'partiallyRefundPayment';
    case ReversePayment = 'reversePayment';
    case IncreaseAuthorizedAmount = 'increaseAuthorizedAmount';
}
