<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Exceptions;

/**
 * Marker for failures where Worldpay may or may not have processed the request
 * (timeouts, network errors, 5xx). Never assume the payment failed: reconcile it.
 *
 * For an authorization, resend with the SAME transactionReference (Worldpay rejects a duplicate
 * reference, so the customer cannot be charged twice). For settle/cancel/refund, do not resend
 * straight away; query the payment later instead.
 */
interface OutcomeUnknown {}
