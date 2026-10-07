<?php

declare(strict_types=1);

use AeroTickets\Worldpay\Config\Config;
use AeroTickets\Worldpay\Config\Environment;
use AeroTickets\Worldpay\Exceptions\ConfigurationException;

it('knows the base URL of each environment', function () {
    expect(Environment::Try->baseUrl())->toBe('https://try.access.worldpay.com')
        ->and(Environment::Live->baseUrl())->toBe('https://access.worldpay.com')
        ->and(Environment::fromString('TRY'))->toBe(Environment::Try)
        ->and(Environment::fromString('LIVE'))->toBe(Environment::Live)
        ->and(Environment::fromString('production'))->toBe(Environment::Live);
});

it('rejects an unknown mode', function () {
    Environment::fromString('staging');
})->throws(ConfigurationException::class);

it('builds from a Laravel-style array', function () {
    $config = Config::fromArray([
        'mode' => 'live', 'entity' => 'PO123', 'username' => 'u', 'password' => 'p',
        'checkout_id' => 'abc', 'default_narrative' => 'Aero Tickets', 'timeout' => '15', 'authorize_retries' => '1',
    ]);

    expect($config->environment)->toBe(Environment::Live)
        ->and($config->checkoutId)->toBe('abc')
        ->and($config->timeout)->toBe(15.0)
        ->and($config->authorizeRetries)->toBe(1)
        ->and($config->apiVersion)->toBe('2024-06-01');
});

it('fails fast on bad configuration', function (array $values, string $message) {
    expect(fn () => Config::fromArray($values + ['mode' => 'try', 'entity' => 'PO1', 'username' => 'u', 'password' => 'p']))
        ->toThrow(ConfigurationException::class, $message);
})->with([
    'default entity' => [['entity' => 'default'], 'default'],
    'empty password' => [['password' => ''], 'password'],
    'narrative too long' => [['default_narrative' => 'Aero Tickets Ltd London UK'], 'defaultNarrative'],
    'bad narrative chars' => [['default_narrative' => 'Aero&Tickets'], 'defaultNarrative'],
    'too many retries' => [['authorize_retries' => 9], 'authorizeRetries'],
]);

it('requires a mode', function () {
    Config::fromArray(['entity' => 'PO1', 'username' => 'u', 'password' => 'p']);
})->throws(ConfigurationException::class, 'mode');

it('never exposes the password in dumps', function () {
    $config = new Config('try', 'PO1', 'user', 'super-secret');

    expect(print_r($config, true))->not->toContain('super-secret');
});

it('copies with changes', function () {
    $config = (new Config('try', 'PO1', 'user', 'pass'))->with(timeout: 5.0);

    expect($config->timeout)->toBe(5.0)->and($config->entity)->toBe('PO1');
    expect(fn () => $config->with(nope: 1))->toThrow(ConfigurationException::class);
});
