<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Laravel\Facades;

use AeroTickets\Worldpay\Config\Config;
use AeroTickets\Worldpay\Testing\WorldpayFake;
use AeroTickets\Worldpay\WorldpayClient;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \AeroTickets\Worldpay\Payments\PaymentsApi payments()
 * @method static \AeroTickets\Worldpay\Config\Config config()
 * @method static array{checkoutId: ?string, mode: string} checkoutSettings()
 *
 * @see WorldpayClient
 */
final class Worldpay extends Facade
{
    /**
     * Swap the bound client for a fake that never calls Worldpay. Queue responses on the returned
     * WorldpayFake. Uses your real config (entity, mode) when it is valid, dummy values otherwise.
     */
    public static function fake(): WorldpayFake
    {
        $app = self::getFacadeApplication();

        try {
            $config = $app->make(Config::class);
        } catch (\Throwable) {
            $config = null;
        }

        $fake = WorldpayFake::create($config);
        $app->instance(WorldpayClient::class, $fake->client());
        self::swap($fake->client());

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return WorldpayClient::class;
    }
}
