<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Exceptions;

use AeroTickets\Worldpay\Payments\Action;

/**
 * The payment's current state does not allow this action: Worldpay did not offer it in the latest
 * response's `_actions`. No request was sent.
 */
final class ActionNotAvailableException extends WorldpayException
{
    /** @param list<Action> $available */
    public function __construct(public readonly Action $action, public readonly array $available)
    {
        $names = array_map(static fn (Action $a) => $a->value, $available);
        parent::__construct(sprintf(
            'Action "%s" is not available for this payment. Available now: %s.',
            $action->value,
            $names === [] ? 'none' : implode(', ', $names),
        ));
    }
}
