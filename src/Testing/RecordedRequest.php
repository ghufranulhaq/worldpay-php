<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Testing;

use Psr\Http\Message\RequestInterface;

/** A request captured by WorldpayFake. */
final class RecordedRequest
{
    /** @param array<string, mixed>|null $body */
    public function __construct(
        public readonly string $method,
        public readonly string $url,
        public readonly ?array $body,
        public readonly RequestInterface $psrRequest,
    ) {}

    /** Path without the opaque linkData, e.g. "/api/payments/{linkData}/settlements". */
    public function path(): string
    {
        return (string) preg_replace('#(/api/payments/)[^/?]+#', '$1{linkData}', (string) parse_url($this->url, PHP_URL_PATH));
    }

    public function header(string $name): string
    {
        return $this->psrRequest->getHeaderLine($name);
    }

    /** Read a body value by dot path, e.g. get('instruction.paymentInstrument.type'). */
    public function get(string $path): mixed
    {
        $value = $this->body;
        foreach (explode('.', $path) as $key) {
            if (! is_array($value) || ! array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }
}
