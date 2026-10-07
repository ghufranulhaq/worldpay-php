<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Exceptions;

/**
 * 400: this transactionReference was already used, so Worldpay created no new payment.
 *
 * Covers the three error names seen in Try: transactionHasAlreadyBeenProcessed,
 * transactionStageHasAlreadyBeenProcessed and transactionReferenceIsADuplicate.
 *
 * When `fromPreviousAttempt` is true, the SDK itself resent an authorization whose first attempt
 * had an unknown outcome. The first attempt therefore reached Worldpay: reconcile it (query or reports)
 * rather than treating the payment as failed.
 */
final class DuplicateTransactionReferenceException extends ApiException
{
    public const ERROR_NAMES = [
        'transactionHasAlreadyBeenProcessed',
        'transactionStageHasAlreadyBeenProcessed',
        'transactionReferenceIsADuplicate',
    ];

    public bool $fromPreviousAttempt = false;

    public static function fromPreviousAttempt(self $e): self
    {
        $copy = new self(
            $e->getMessage().' The previous attempt with this reference reached Worldpay; reconcile it.',
            $e->statusCode,
            $e->errorName,
            $e->validationErrors,
            $e->body,
            $e,
        );
        $copy->fromPreviousAttempt = true;

        return $copy;
    }
}
