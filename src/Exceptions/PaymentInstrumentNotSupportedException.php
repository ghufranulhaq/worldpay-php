<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Exceptions;

/** 400: the card scheme or instrument is not enabled for this merchant entity (e.g. Discover on the Aero Tickets Try entity). */
final class PaymentInstrumentNotSupportedException extends ValidationFailedException {}
