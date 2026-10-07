<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Exceptions;

/**
 * No HTTP response was received (timeout, DNS, TLS, connection reset).
 * The request may still have reached Worldpay, so the outcome is unknown.
 */
final class TransportException extends WorldpayException implements OutcomeUnknown {}
