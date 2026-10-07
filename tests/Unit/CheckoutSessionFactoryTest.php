<?php

declare(strict_types=1);

use AeroTickets\Worldpay\Config\Environment;
use AeroTickets\Worldpay\Exceptions\ConfigurationException;
use AeroTickets\Worldpay\Exceptions\ValidationFailedException;
use AeroTickets\Worldpay\Testing\CheckoutSessionFactory;
use AeroTickets\Worldpay\Testing\FakeResponses;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

function sessionsClient(array $responses, array &$history): Client
{
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));

    return new Client(['handler' => $stack]);
}

it('discovers the sessions endpoints like the browser SDK and returns the session href', function () {
    $history = [];
    $http = sessionsClient([
        FakeResponses::json(200, ['_links' => ['service:sessions' => ['href' => 'https://hpp-sandbox.worldpay.com/public/sessions']]]),
        FakeResponses::json(200, ['_links' => [
            'sessions:card' => ['href' => 'https://hpp-sandbox.worldpay.com/public/sessions/card'],
            'sessions:paymentsCvc' => ['href' => 'https://hpp-sandbox.worldpay.com/public/sessions/payments/cvc'],
        ]]),
        FakeResponses::json(201, ['_links' => ['sessions:session' => ['href' => 'https://try.access.worldpay.com/sessions/CARD']]]),
        FakeResponses::json(201, ['_links' => ['sessions:session' => ['href' => 'https://try.access.worldpay.com/sessions/CVC']]]),
    ], $history);

    $factory = new CheckoutSessionFactory('chk-1', Environment::Try, $http);

    expect($factory->cardSession('4000000000002701', 5, 2035, '555'))->toBe('https://try.access.worldpay.com/sessions/CARD')
        ->and($factory->cvcSession('555'))->toBe('https://try.access.worldpay.com/sessions/CVC')
        ->and(count($history))->toBe(4);   // discovery is cached

    $card = $history[2]['request'];
    expect((string) $card->getUri())->toBe('https://hpp-sandbox.worldpay.com/public/sessions/card')
        ->and($card->getHeaderLine('Content-Type'))->toBe('application/vnd.worldpay.sessions-v1.hal+json')
        ->and(json_decode((string) $card->getBody(), true))->toBe([
            'identity' => 'chk-1', 'cardNumber' => '4000000000002701', 'cardExpiryDate' => ['month' => 5, 'year' => 2035], 'cvc' => '555',
        ])
        ->and((string) $history[3]['request']->getUri())->toBe('https://hpp-sandbox.worldpay.com/public/sessions/payments/cvc');
});

it('maps session API errors', function () {
    $history = [];
    $http = sessionsClient([
        FakeResponses::json(200, ['_links' => ['service:sessions' => ['href' => 'https://hpp-sandbox.worldpay.com/public/sessions']]]),
        FakeResponses::json(200, ['_links' => ['sessions:card' => ['href' => 'https://hpp-sandbox.worldpay.com/public/sessions/card']]]),
        FakeResponses::json(400, ['errorName' => 'bodyDoesNotMatchSchema', 'message' => 'x', 'validationErrors' => [['errorName' => 'fieldHasInvalidValue', 'message' => 'Identity is invalid', 'jsonPath' => '$.identity']]]),
    ], $history);

    (new CheckoutSessionFactory('bad', Environment::Try, $http))->cardSession('4000000000002701', 5, 2035, '555');
})->throws(ValidationFailedException::class, 'Identity is invalid');

it('refuses Live mode', function () {
    new CheckoutSessionFactory('chk', Environment::Live);
})->throws(ConfigurationException::class);
