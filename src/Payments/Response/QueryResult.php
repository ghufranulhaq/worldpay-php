<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Payments\Response;

use AeroTickets\Worldpay\Payments\PaymentHandle;

/**
 * Result of query(). Use it for reconciliation, not for real-time decisions:
 * in Try, lastEvent and _actions lagged minutes behind settle/cancel (verified 2026-10-07).
 * The outcome and handle of each action's own response are what count.
 */
final class QueryResult
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public readonly LastEvent $lastEvent,
        private readonly PaymentHandle $handle,
        private readonly array $raw,
    ) {}

    /** @param array<string, mixed> $body */
    public static function fromResponse(array $body, PaymentHandle $previous): self
    {
        return new self(
            LastEvent::fromApi(isset($body['lastEvent']) ? (string) $body['lastEvent'] : null),
            $previous->mergedWith(PaymentHandle::fromResponse($body)),
            $body,
        );
    }

    /**
     * Handle with the actions the query reported. Because the query can lag, prefer the handle
     * from the latest action response unless you are reconciling after an unknown outcome.
     */
    public function handle(): PaymentHandle
    {
        return $this->handle;
    }

    /** @return array<string, mixed> */
    public function raw(): array
    {
        return $this->raw;
    }
}
