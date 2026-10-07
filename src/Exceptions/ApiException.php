<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Exceptions;

/**
 * Worldpay answered with an error status. Subclasses identify the common cases.
 *
 * - `statusCode`: HTTP status
 * - `errorName`: Worldpay's error name (e.g. "bodyDoesNotMatchSchema")
 * - `validationErrors`: list of {errorName, message, jsonPath} for schema errors
 * - `body`: the decoded error body (never contains card data)
 */
class ApiException extends WorldpayException
{
    /**
     * @param  list<array{errorName?: string, message?: string, jsonPath?: string}>  $validationErrors
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly ?string $errorName = null,
        public readonly array $validationErrors = [],
        public readonly array $body = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    /** Every errorName in the response, top level and nested validation errors. */
    public function errorNames(): array
    {
        $names = $this->errorName !== null ? [$this->errorName] : [];
        foreach ($this->validationErrors as $error) {
            if (isset($error['errorName'])) {
                $names[] = $error['errorName'];
            }
        }

        return array_values(array_unique($names));
    }
}
