<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Payments\Response;

/** What a refusal's `advice.code` (Mastercard) says about retrying. */
enum RetryAdvice: string
{
    /** Retry with updated card details or authentication (advice 01). */
    case RetryWithUpdatedDetails = 'retryWithUpdatedDetails';
    /** Insufficient funds: retry later (advice 02, 24–30). See Refusal::retryAfter(). */
    case RetryLater = 'retryLater';
    /** Do not retry; scheme fees may apply (advice 03). */
    case DoNotRetry = 'doNotRetry';
    /** No advice given. Ask for another card; don't loop retries. */
    case None = 'none';
}
