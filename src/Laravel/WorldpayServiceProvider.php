<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Laravel;

use AeroTickets\Worldpay\Config\Config;
use AeroTickets\Worldpay\Payments\PaymentsApi;
use AeroTickets\Worldpay\WorldpayClient;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Auto-discovered by Laravel. Binds WorldpayClient (and PaymentsApi) as singletons built from
 * config/worldpay.php. The client is created lazily, so a missing config only fails when used.
 */
final class WorldpayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/worldpay.php', 'worldpay');

        $this->app->singleton(Config::class, static function (Application $app): Config {
            $values = (array) $app['config']->get('worldpay', []);
            $channel = $values['log_channel'] ?? null;
            if (is_string($channel) && $channel !== '') {
                $values['logger'] = $app['log']->channel($channel);
            }

            return Config::fromArray($values);
        });

        $this->app->singleton(WorldpayClient::class, static fn (Application $app) => new WorldpayClient($app->make(Config::class)));
        $this->app->alias(WorldpayClient::class, 'worldpay');
        $this->app->bind(PaymentsApi::class, static fn (Application $app) => $app->make(WorldpayClient::class)->payments());
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/worldpay.php' => $this->app->configPath('worldpay.php'),
            ], 'worldpay-config');
        }
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [Config::class, WorldpayClient::class, PaymentsApi::class, 'worldpay'];
    }
}
