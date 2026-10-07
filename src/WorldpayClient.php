<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay;

use AeroTickets\Worldpay\Config\Config;
use AeroTickets\Worldpay\Http\Transport;
use AeroTickets\Worldpay\Payments\PaymentsApi;

/**
 * Entry point of the SDK. Create one at bootstrap and reuse it.
 *
 *   $worldpay = WorldpayClient::create(new Config('try', 'PO4098288921', $user, $pass));
 *   $result = $worldpay->payments()->authorize($request);
 */
final class WorldpayClient
{
    private PaymentsApi $payments;

    public function __construct(private readonly Config $config)
    {
        $this->payments = new PaymentsApi(new Transport($config));
    }

    public static function create(Config $config): self
    {
        return new self($config);
    }

    /** @param array<string, mixed> $config see Config::fromArray() */
    public static function fromArray(array $config): self
    {
        return new self(Config::fromArray($config));
    }

    public function payments(): PaymentsApi
    {
        return $this->payments;
    }

    public function config(): Config
    {
        return $this->config;
    }

    /**
     * What the browser Checkout SDK needs, safe to send to the SPA (contains no secrets):
     * ['checkoutId' => ..., 'mode' => 'try'|'live'].
     *
     * @return array{checkoutId: ?string, mode: string}
     */
    public function checkoutSettings(): array
    {
        return ['checkoutId' => $this->config->checkoutId, 'mode' => $this->config->environment->value];
    }
}
