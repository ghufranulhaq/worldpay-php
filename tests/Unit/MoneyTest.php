<?php

declare(strict_types=1);

use AeroTickets\Worldpay\Exceptions\InvalidRequestException;
use AeroTickets\Worldpay\Support\Money;
use AeroTickets\Worldpay\Support\TransactionReference;

it('converts decimals to minor units without floats', function (string|int $in, string $currency, int $minor, string $back) {
    $money = Money::fromDecimal($in, $currency);

    expect($money->minorUnits)->toBe($minor)->and($money->toDecimal())->toBe($back);
})->with([
    ['250.00', 'GBP', 25000, '250.00'],
    ['250', 'gbp', 25000, '250.00'],
    [250, 'GBP', 25000, '250.00'],
    ['0.10', 'EUR', 10, '0.10'],
    ['19.9', 'USD', 1990, '19.90'],
    ['1500', 'JPY', 1500, '1500'],
    ['1.234', 'KWD', 1234, '1.234'],
    ['0.01', 'GBP', 1, '0.01'],
]);

it('rejects amounts it cannot represent exactly', function (string $in, string $currency) {
    Money::fromDecimal($in, $currency);
})->with([
    ['1.005', 'GBP'],
    ['10.5', 'JPY'],
    ['-1', 'GBP'],
    ['abc', 'GBP'],
    ['1e5', 'GBP'],
    ['99999999999', 'GBP'],
])->throws(InvalidRequestException::class);

it('validates the currency code', function () {
    Money::ofMinor(100, 'POUND');
})->throws(InvalidRequestException::class);

it('generates valid unique transaction references', function () {
    $a = TransactionReference::generate('AT');
    $b = TransactionReference::generate('AT');

    expect($a)->toStartWith('AT-')->not->toBe($b)->and(strlen($a))->toBeLessThanOrEqual(64);
    TransactionReference::assertValid($a);
});
