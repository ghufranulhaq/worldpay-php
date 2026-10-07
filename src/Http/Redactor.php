<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Http;

/**
 * Removes card data and single-use secrets from anything about to be logged.
 *
 * - cardNumber → "************2701"
 * - cvc → "***"
 * - sessionHref, cvcSessionHref, token href → "[redacted]"
 *
 * @internal
 */
final class Redactor
{
    private const SECRET_KEYS = ['sessionHref', 'cvcSessionHref'];

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    public static function redact(array $data, ?string $parentKey = null): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::redact($value, is_string($key) ? $key : $parentKey);

                continue;
            }
            if (! is_string($key) || $value === null) {
                continue;
            }
            if ($key === 'cardNumber') {
                $digits = (string) preg_replace('/\D/', '', (string) $value);
                $data[$key] = strlen($digits) >= 12 ? str_repeat('*', strlen($digits) - 4).substr($digits, -4) : (string) $value;
            } elseif ($key === 'cvc' || $key === 'cvv') {
                $data[$key] = '***';
            } elseif (in_array($key, self::SECRET_KEYS, true)) {
                $data[$key] = '[redacted]';
            } elseif ($key === 'href' && in_array($parentKey, ['paymentInstrument', 'token'], true)) {
                $data[$key] = '[redacted]';
            }
        }

        return $data;
    }

    /** Hide the opaque linkData part of a Worldpay URL in logs. */
    public static function url(string $url): string
    {
        return (string) preg_replace('#(/api/payments/)[^/?]+#', '$1{linkData}', $url);
    }
}
