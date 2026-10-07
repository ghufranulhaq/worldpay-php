<?php

declare(strict_types=1);

use AeroTickets\Worldpay\Config\Config;
use AeroTickets\Worldpay\Payments\Instrument\CheckoutSession;
use AeroTickets\Worldpay\Payments\Instrument\PlainCard;
use AeroTickets\Worldpay\Payments\Instrument\WorldpayToken;
use AeroTickets\Worldpay\Payments\Request\AuthorizeRequest;
use AeroTickets\Worldpay\Support\Money;
use AeroTickets\Worldpay\Testing\FakeResponses;
use AeroTickets\Worldpay\Testing\TestCards;
use AeroTickets\Worldpay\Testing\WorldpayFake;
use AeroTickets\Worldpay\Tests\Support\MemoryLogger;

it('never logs card numbers, CVCs, session or token hrefs, or credentials', function () {
    $logger = new MemoryLogger;
    $fake = WorldpayFake::create(new Config('try', 'PO1', 'user-name', 'pass-word-123', logger: $logger));
    $fake->queue(
        FakeResponses::authorized(),
        FakeResponses::authorized(),
        FakeResponses::authorized('AT-3', ['token' => ['href' => 'https://try.access.worldpay.com/tokens/TOKENSECRET']]),
        FakeResponses::serverError(),
    );
    $payments = $fake->client()->payments();
    $gbp = Money::fromDecimal('10', 'GBP');

    $payments->authorize(AuthorizeRequest::moto('AT-1', $gbp)->paymentInstrument(PlainCard::of(TestCards::VISA, 5, 2035, '555', 'AUTHORISED')));
    $payments->authorize(AuthorizeRequest::moto('AT-2', $gbp)->paymentInstrument(CheckoutSession::card('https://try.access.worldpay.com/sessions/SESSIONSECRET')));
    $payments->authorize(AuthorizeRequest::moto('AT-3', $gbp)->paymentInstrument(WorldpayToken::href('https://try.access.worldpay.com/tokens/TOKENSECRET')->withCvc('987')));
    try {
        $payments->authorize(AuthorizeRequest::moto('AT-4', $gbp)->paymentInstrument(PlainCard::of(TestCards::MASTERCARD, 5, 2035, '444')));
    } catch (Throwable) {
    }

    $logs = $logger->dump();
    expect($logger->records)->not->toBeEmpty()
        ->and($logs)->not->toContain(TestCards::VISA)
        ->and($logs)->not->toContain(TestCards::MASTERCARD)
        ->and($logs)->not->toContain('"555"')
        ->and($logs)->not->toContain('"444"')
        ->and($logs)->not->toContain('987')
        ->and($logs)->not->toContain('SESSIONSECRET')
        ->and($logs)->not->toContain('TOKENSECRET')
        ->and($logs)->not->toContain('pass-word-123')
        ->and($logs)->not->toContain(base64_encode('user-name:pass-word-123'))
        ->and($logs)->toContain('2701');
});
