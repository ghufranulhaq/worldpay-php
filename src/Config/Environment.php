<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Config;

use AeroTickets\Worldpay\Exceptions\ConfigurationException;

/**
 * Worldpay Access environment. The SDK knows the base URL of each one.
 */
enum Environment: string
{
    /** Worldpay "Try" sandbox: simulated money, test cards only. */
    case Try = 'try';

    /** Live: real money. Needs separate Live credentials. */
    case Live = 'live';

    public function baseUrl(): string
    {
        return match ($this) {
            self::Try => 'https://try.access.worldpay.com',
            self::Live => 'https://access.worldpay.com',
        };
    }

    public function host(): string
    {
        return (string) parse_url($this->baseUrl(), PHP_URL_HOST);
    }

    /**
     * Accepts "try", "TRY", "sandbox", "test", "live", "LIVE", "production" or "prod".
     */
    public static function fromString(string $mode): self
    {
        return match (strtolower(trim($mode))) {
            'try', 'sandbox', 'test' => self::Try,
            'live', 'production', 'prod' => self::Live,
            default => throw new ConfigurationException(
                sprintf('Unknown Worldpay mode "%s". Use "try" or "live".', $mode)
            ),
        };
    }
}
