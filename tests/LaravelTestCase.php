<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Tests;

use AeroTickets\Worldpay\Laravel\WorldpayServiceProvider;
use Orchestra\Testbench\TestCase;

abstract class LaravelTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [WorldpayServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('worldpay', [
            'mode' => 'try',
            'entity' => 'PO4098288921',
            'username' => 'user',
            'password' => 'secret',
            'checkout_id' => 'checkout-123',
            'default_narrative' => 'AeroTickets',
        ]);
    }
}
