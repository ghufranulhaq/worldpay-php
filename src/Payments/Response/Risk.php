<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Payments\Response;

/** Result of a CVC or AVS check that did not cleanly pass. A clean pass has no risk factor at all. */
enum Risk: string
{
    case NotChecked = 'notChecked';
    case NotMatched = 'notMatched';
    case NotSupplied = 'notSupplied';
    case VerificationFailed = 'verificationFailed';
    case Unknown = 'unknown';

    /** Accepts camelCase (what the API returns) and snake_case (what some docs show). */
    public static function fromApi(?string $value): self
    {
        if ($value === null) {
            return self::Unknown;
        }
        $camel = lcfirst(str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $value))));

        return self::tryFrom($camel) ?? self::Unknown;
    }

    public function isFailure(): bool
    {
        return $this === self::NotMatched || $this === self::VerificationFailed;
    }
}
