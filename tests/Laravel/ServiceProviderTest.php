<?php

declare(strict_types=1);

use AeroTickets\Worldpay\Config\Config;
use AeroTickets\Worldpay\Config\Environment;
use AeroTickets\Worldpay\Exceptions\ConfigurationException;
use AeroTickets\Worldpay\Laravel\Facades\Worldpay;
use AeroTickets\Worldpay\Payments\Instrument\CheckoutSession;
use AeroTickets\Worldpay\Payments\PaymentsApi;
use AeroTickets\Worldpay\Payments\Request\AuthorizeRequest;
use AeroTickets\Worldpay\Support\Money;
use AeroTickets\Worldpay\Testing\FakeResponses;
use AeroTickets\Worldpay\Testing\RecordedRequest;
use AeroTickets\Worldpay\WorldpayClient;

it('binds a singleton client built from config/worldpay.php', function () {
    $client = app(WorldpayClient::class);

    expect($client)->toBe(app(WorldpayClient::class))
        ->and(app('worldpay'))->toBe($client)
        ->and(app(PaymentsApi::class))->toBe($client->payments())
        ->and($client->config()->environment)->toBe(Environment::Try)
        ->and($client->config()->entity)->toBe('PO4098288921')
        ->and(Worldpay::checkoutSettings())->toBe(['checkoutId' => 'checkout-123', 'mode' => 'try']);
});

it('merges package defaults for keys the app did not set', function () {
    expect(config('worldpay.mode'))->toBe('try');
    expect(app(Config::class)->apiVersion)->toBe('2024-06-01');
});

it('fails clearly when credentials are missing', function () {
    config()->set('worldpay.username', null);
    app()->forgetInstance(Config::class);
    app()->forgetInstance(WorldpayClient::class);

    app(WorldpayClient::class);
})->throws(ConfigurationException::class, 'username');

it('wires the configured log channel', function () {
    config()->set('worldpay.log_channel', 'null');
    app()->forgetInstance(Config::class);

    expect(app(Config::class)->logger)->not->toBeNull();
});

it('fakes the client through the facade', function () {
    $fake = Worldpay::fake();
    $fake->queue(FakeResponses::authorized('AT-L1'));

    $result = app(WorldpayClient::class)->payments()->authorize(
        AuthorizeRequest::moto('AT-L1', Money::fromDecimal('99.99', 'GBP'))->paymentInstrument(CheckoutSession::card('s'))
    );

    expect($result->isAuthorized())->toBeTrue()
        ->and(Worldpay::payments())->toBe(app(WorldpayClient::class)->payments());
    $fake->assertSentCount(1);
    $fake->assertSent(fn (RecordedRequest $r) => $r->get('merchant.entity') === 'PO4098288921' && $r->get('instruction.value.amount') === 9999);
});

it('publishes the config file', function () {
    $this->artisan('vendor:publish', ['--tag' => 'worldpay-config', '--force' => true])->assertSuccessful();

    expect(file_exists(config_path('worldpay.php')))->toBeTrue();
    @unlink(config_path('worldpay.php'));
});
