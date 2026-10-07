<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Support;

use AeroTickets\Worldpay\Exceptions\InvalidRequestException;

/**
 * Local validation rules taken from the Worldpay Payments API schema (2024-06-01).
 * Every method throws InvalidRequestException naming the field.
 *
 * @internal
 */
final class Validate
{
    public const TRANSACTION_REFERENCE_PATTERN = '/^[-A-Za-z0-9_!@#$%()*=.:;?\[\]{}~`\/+]*$/';

    public const ORDER_REFERENCE_PATTERN = '/^[-A-Za-z0-9_!@#$%()*=.:;?\[\]{}~\/+]*$/';

    public const NARRATIVE_PATTERN = '/^[a-zA-Z0-9 ,.\/-]*$/';

    public const CANCEL_REFERENCE_PATTERN = '/^[-A-Za-z0-9]*$/';

    public static function length(string $value, string $field, int $min, int $max): void
    {
        $length = mb_strlen($value);
        if ($length < $min || $length > $max) {
            throw InvalidRequestException::forField($field, sprintf('must be %d–%d characters (got %d).', $min, $max, $length));
        }
    }

    public static function pattern(string $value, string $field, string $pattern, string $allowed): void
    {
        if (! preg_match($pattern, $value)) {
            throw InvalidRequestException::forField($field, sprintf('contains characters that are not allowed (allowed: %s).', $allowed));
        }
    }

    public static function transactionReference(string $value, string $field = 'transactionReference'): void
    {
        self::length($value, $field, 1, 64);
        self::pattern($value, $field, self::TRANSACTION_REFERENCE_PATTERN, 'letters, digits and - _ ! @ # $ % ( ) * = . : ; ? [ ] { } ~ ` / +');
    }

    public static function orderReference(string $value, string $field = 'orderReference'): void
    {
        self::length($value, $field, 1, 64);
        self::pattern($value, $field, self::ORDER_REFERENCE_PATTERN, 'letters, digits and - _ ! @ # $ % ( ) * = . : ; ? [ ] { } ~ / +');
    }

    public static function narrativeLine(string $value, string $field): void
    {
        self::length($value, $field, 1, 24);
        self::pattern($value, $field, self::NARRATIVE_PATTERN, 'letters, digits, space and , . / -');
    }

    /** Reference for cancel and partial cancel: 1–128 letters, digits or hyphens. */
    public static function cancelReference(string $value, string $field = 'reference'): void
    {
        self::length($value, $field, 1, 128);
        self::pattern($value, $field, self::CANCEL_REFERENCE_PATTERN, 'letters, digits and -');
    }

    /** Reference for partial settle and partial refund: non-empty, max 128 (kept to the cancel limit for safety). */
    public static function actionReference(string $value, string $field = 'reference'): void
    {
        self::length($value, $field, 1, 128);
    }

    public static function countryCode(string $value, string $field): void
    {
        if (! preg_match('/^[A-Z]{2}$/', $value)) {
            throw InvalidRequestException::forField($field, 'must be an ISO 3166-1 alpha-2 code in upper case, e.g. "GB".');
        }
    }

    public static function currency(string $value, string $field = 'currency'): void
    {
        if (! preg_match('/^[A-Z]{3}$/', $value)) {
            throw InvalidRequestException::forField($field, 'must be an ISO 4217 code in upper case, e.g. "GBP".');
        }
    }

    public static function cardNumber(string $digits, string $field): void
    {
        if (! preg_match('/^\d{12,19}$/', $digits)) {
            throw InvalidRequestException::forField($field, 'must be 12–19 digits.');
        }
        if (! self::luhn($digits)) {
            throw InvalidRequestException::forField($field, 'is not a valid card number (Luhn check failed).');
        }
    }

    public static function cvc(string $cvc, string $field): void
    {
        if (! preg_match('/^\d{3,4}$/', $cvc)) {
            throw InvalidRequestException::forField($field, 'must be 3 or 4 digits.');
        }
    }

    public static function expiry(int $month, int $year, string $field, ?\DateTimeInterface $now = null): void
    {
        if ($month < 1 || $month > 12) {
            throw InvalidRequestException::forField($field.'.month', 'must be 1–12.');
        }
        if ($year < 1000 || $year > 9999) {
            throw InvalidRequestException::forField($field.'.year', 'must be a 4-digit year.');
        }
        $now ??= new \DateTimeImmutable('now');
        $currentYear = (int) $now->format('Y');
        $currentMonth = (int) $now->format('n');
        if ($year < $currentYear || ($year === $currentYear && $month < $currentMonth)) {
            throw InvalidRequestException::forField($field, 'the card has expired.');
        }
    }

    public static function notBlank(string $value, string $field): void
    {
        if (trim($value) === '') {
            throw InvalidRequestException::forField($field, 'must not be empty.');
        }
    }

    public static function luhn(string $digits): bool
    {
        $sum = 0;
        $double = false;
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $digit = (int) $digits[$i];
            if ($double) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
            $double = ! $double;
        }

        return $sum % 10 === 0;
    }
}
