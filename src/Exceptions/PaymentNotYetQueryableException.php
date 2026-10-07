<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Exceptions;

/** 404 urlContainsInvalidValue on a query: the payment exists but cannot be queried yet (Try takes ~25–65 s after authorization). Retry later. */
final class PaymentNotYetQueryableException extends NotFoundException {}
