<?php

declare(strict_types=1);

use AeroTickets\Worldpay\Exceptions\InvalidRequestException;
use AeroTickets\Worldpay\Payments\Instrument\CardBrand;
use AeroTickets\Worldpay\Payments\Instrument\CheckoutSession;
use AeroTickets\Worldpay\Payments\Instrument\PlainCard;
use AeroTickets\Worldpay\Payments\Instrument\RawInstrument;
use AeroTickets\Worldpay\Payments\Instrument\WorldpayToken;
use AeroTickets\Worldpay\Payments\Request\Address;
use AeroTickets\Worldpay\Payments\Request\AuthorizeRequest;
use AeroTickets\Worldpay\Payments\Request\Customer;
use AeroTickets\Worldpay\Payments\Request\FraudSight;
use AeroTickets\Worldpay\Payments\Request\Shipping;
use AeroTickets\Worldpay\Payments\Request\ShippingMethod;
use AeroTickets\Worldpay\Payments\Request\ShippingTimeFrame;
use AeroTickets\Worldpay\Payments\Request\TokenCreation;
use AeroTickets\Worldpay\Support\Money;
use AeroTickets\Worldpay\Testing\TestCards;

function gbp(string $amount = '250.00'): Money
{
    return Money::fromDecimal($amount, 'GBP');
}

function address(): Address
{
    return Address::of('221B Baker Street', 'London', 'gb', 'NW1 6XE');
}

it('builds a MOTO checkout-session authorization', function () {
    $payload = AuthorizeRequest::moto('AT-1', gbp())
        ->paymentInstrument(CheckoutSession::card('https://try.access.worldpay.com/sessions/abc', 'J Smith', address()))
        ->orderReference('BKG-102938')
        ->narrative(line2: 'BKG-102938')
        ->toPayload('PO4098288921', 'AeroTickets');

    expect($payload)->toBe([
        'transactionReference' => 'AT-1',
        'orderReference' => 'BKG-102938',
        'merchant' => ['entity' => 'PO4098288921'],
        'channel' => 'moto',
        'instruction' => [
            'method' => 'card',
            'paymentInstrument' => [
                'type' => 'checkout',
                'sessionHref' => 'https://try.access.worldpay.com/sessions/abc',
                'cardHolderName' => 'J Smith',
                'billingAddress' => [
                    'address1' => '221B Baker Street', 'postalCode' => 'NW1 6XE', 'city' => 'London', 'countryCode' => 'GB',
                ],
            ],
            'narrative' => ['line1' => 'AeroTickets', 'line2' => 'BKG-102938'],
            'value' => ['amount' => 25000, 'currency' => 'GBP'],
        ],
    ]);
});

it('builds a plain card instrument, stripping spaces', function () {
    $instrument = PlainCard::of('4000 0000 0000 2701', 5, 2035, '555', 'AUTHORISED', address())->toArray();

    expect($instrument)->toMatchArray([
        'type' => 'plain',
        'cardNumber' => TestCards::VISA,
        'expiryDate' => ['month' => 5, 'year' => 2035],
        'cvc' => '555',
        'cardHolderName' => 'AUTHORISED',
    ]);
});

it('builds token instruments with a CVC or a CVC session', function () {
    expect(WorldpayToken::href('https://try.access.worldpay.com/tokens/x')->withCvcSession('https://try.access.worldpay.com/sessions/cvc')->toArray())
        ->toBe(['type' => 'token', 'href' => 'https://try.access.worldpay.com/tokens/x', 'cvcSessionHref' => 'https://try.access.worldpay.com/sessions/cvc'])
        ->and(WorldpayToken::id('9876543210ABCDEF', 'customer-42')->withCvc('123')->toArray())
        ->toBe(['type' => 'token', 'tokenId' => '9876543210ABCDEF', 'namespace' => 'customer-42', 'cvc' => '123']);
});

it('adds routing for co-badged cards', function () {
    expect(CheckoutSession::card('s')->preferredCardBrand(CardBrand::CartesBancaires)->toArray()['routing'])
        ->toBe(['preferredCardBrand' => 'cartesBancaires']);
});

it('passes raw instruments through', function () {
    expect(RawInstrument::fromArray(['type' => 'networkToken', 'tokenNumber' => '1'])->toArray())
        ->toBe(['type' => 'networkToken', 'tokenNumber' => '1']);
    expect(fn () => RawInstrument::fromArray([])->toArray())->toThrow(InvalidRequestException::class);
});

it('maps every option to the Worldpay schema', function () {
    $payload = AuthorizeRequest::moto('AT-2', gbp())
        ->paymentInstrument(CheckoutSession::card('s'))
        ->autoSettle(cancelOnCvcNotMatched: false, cancelOnAvsNotMatched: false)
        ->createToken(TokenCreation::worldpay('customer-42'))
        ->fraud(FraudSight::assess()->silentMode()->custom(string1: 'call-centre', number1: 3))
        ->customer(new Customer(customerId: 'C42', firstName: 'Jo', email: 'jo@example.com', phone: '+44 20 7946 0000'))
        ->shipping(new Shipping(method: ShippingMethod::UnshippedTickets, timeFrame: ShippingTimeFrame::Electronic))
        ->acceptPartialAmount()
        ->mcc('4722')
        ->entity('PO999')
        ->withExtra('instruction.debtRepayment', true)
        ->toPayload('PO4098288921', 'AeroTickets');

    expect($payload['merchant'])->toBe(['entity' => 'PO999', 'mcc' => '4722'])
        ->and($payload['instruction']['settlement'])->toBe(['auto' => true, 'cancelOn' => ['cvcNotMatched' => 'disabled', 'avsNotMatched' => 'disabled']])
        ->and($payload['instruction']['tokenCreation'])->toBe(['type' => 'worldpay', 'namespace' => 'customer-42'])
        ->and($payload['instruction']['fraud'])->toBe(['type' => 'fraudSight', 'silentMode' => true, 'custom' => ['string1' => 'call-centre', 'number1' => 3]])
        ->and($payload['instruction']['customer'])->toBe(['customerId' => 'C42', 'firstName' => 'Jo', 'phone' => '442079460000', 'email' => 'jo@example.com'])
        ->and($payload['instruction']['shipping'])->toBe(['method' => 'unshippedTickets', 'timeFrame' => 'electronic'])
        ->and($payload['instruction']['value'])->toBe(['amount' => 25000, 'currency' => 'GBP', 'acceptPartialAmount' => true])
        ->and($payload['instruction']['debtRepayment'])->toBeTrue();
});

it('defaults auto settlement cancelOn to enabled', function () {
    $payload = AuthorizeRequest::moto('AT-3', gbp())->paymentInstrument(CheckoutSession::card('s'))->autoSettle()->toPayload('PO1', 'AeroTickets');

    expect($payload['instruction']['settlement']['cancelOn'])->toBe(['cvcNotMatched' => 'enabled', 'avsNotMatched' => 'enabled']);
});

it('is immutable', function () {
    $base = AuthorizeRequest::moto('AT-4', gbp())->paymentInstrument(CheckoutSession::card('s'));
    $base->autoSettle();

    expect($base->isAutoSettle())->toBeFalse();
});

it('refuses MOTO-forbidden and SDK-managed extras', function (string $path) {
    AuthorizeRequest::moto('AT-5', gbp())->withExtra($path, []);
})->with(['channel', 'instruction.threeDS', 'instruction.threeDS.type', 'instruction.exemption', 'instruction.customerAgreement', 'instruction.value.amount', 'instruction.paymentInstrument'])
    ->throws(InvalidRequestException::class);

it('validates locally before anything is sent', function (callable $build, string $field) {
    try {
        $build()->toPayload('PO1', 'AeroTickets');
        $this->fail('expected InvalidRequestException');
    } catch (InvalidRequestException $e) {
        expect($e->field)->toStartWith($field);
    }
})->with([
    'no instrument' => [fn () => AuthorizeRequest::moto('AT', gbp()), 'paymentInstrument'],
    'bad luhn' => [fn () => AuthorizeRequest::moto('AT', gbp())->paymentInstrument(PlainCard::of('4000000000002702', 5, 2035, '555')), 'paymentInstrument.cardNumber'],
    'short pan' => [fn () => AuthorizeRequest::moto('AT', gbp())->paymentInstrument(PlainCard::of('4000', 5, 2035, '555')), 'paymentInstrument.cardNumber'],
    'expired' => [fn () => AuthorizeRequest::moto('AT', gbp())->paymentInstrument(PlainCard::of(TestCards::VISA, 1, 2020, '555')), 'paymentInstrument.expiryDate'],
    'bad cvc' => [fn () => AuthorizeRequest::moto('AT', gbp())->paymentInstrument(PlainCard::of(TestCards::VISA, 5, 2035, '12')), 'paymentInstrument.cvc'],
    'no postcode' => [fn () => AuthorizeRequest::moto('AT', gbp())->paymentInstrument(CheckoutSession::card('s', 'J', Address::of('1 St', 'London', 'GB'))), 'paymentInstrument.billingAddress.postalCode'],
    'bad country' => [fn () => AuthorizeRequest::moto('AT', gbp())->paymentInstrument(CheckoutSession::card('s', 'J', Address::of('1 St', 'London', 'UK1', 'X'))), 'paymentInstrument.billingAddress.countryCode'],
    'estimated + auto' => [fn () => AuthorizeRequest::moto('AT', gbp())->paymentInstrument(CheckoutSession::card('s'))->estimated()->autoSettle(), 'value.estimated'],
    'blank session' => [fn () => AuthorizeRequest::moto('AT', gbp())->paymentInstrument(CheckoutSession::card('  ')), 'paymentInstrument.sessionHref'],
    'bad token id' => [fn () => AuthorizeRequest::moto('AT', gbp())->paymentInstrument(WorldpayToken::id('short')), 'paymentInstrument.tokenId'],
]);

it('validates references, narrative and amount up front', function () {
    expect(fn () => AuthorizeRequest::moto('has space', gbp()))->toThrow(InvalidRequestException::class)
        ->and(fn () => AuthorizeRequest::moto(str_repeat('a', 65), gbp()))->toThrow(InvalidRequestException::class)
        ->and(fn () => AuthorizeRequest::moto('AT', Money::ofMinor(0, 'GBP')))->toThrow(InvalidRequestException::class)
        ->and(fn () => AuthorizeRequest::moto('AT', gbp())->narrative(line2: 'Booking #1'))->toThrow(InvalidRequestException::class)
        ->and(fn () => AuthorizeRequest::moto('AT', gbp())->orderReference('BKG 1'))->toThrow(InvalidRequestException::class);
});

it('allows IE addresses without a postcode', function () {
    expect(Address::of('1 Main St', 'Dublin', 'IE')->toArray())->toBe(['address1' => '1 Main St', 'city' => 'Dublin', 'countryCode' => 'IE']);
});

it('never shows card data in debug output', function () {
    $dump = print_r(PlainCard::of(TestCards::VISA, 5, 2035, '555'), true)
        .print_r(CheckoutSession::card('https://try.access.worldpay.com/sessions/secret'), true)
        .print_r(WorldpayToken::href('https://try.access.worldpay.com/tokens/secret')->withCvc('987'), true);

    expect($dump)->not->toContain(TestCards::VISA)->not->toContain('555')->not->toContain('secret')->not->toContain('987');
});
