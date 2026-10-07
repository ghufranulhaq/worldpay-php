<?php

declare(strict_types=1);

/*
 * Real calls to Worldpay Try. Skipped unless WORLDPAY_TRY_USERNAME and WORLDPAY_TRY_PASSWORD are set
 * (in the environment or in .env.testing; start from .env.testing.example).
 *
 *   composer test:integration
 *
 * These mirror the Postman flows verified on 2026-10-07 (MOTO_SETTLE_CANCEL_REFUND.md §8).
 */

use AeroTickets\Worldpay\Config\Config;
use AeroTickets\Worldpay\Exceptions\AuthenticationException;
use AeroTickets\Worldpay\Exceptions\DuplicateTransactionReferenceException;
use AeroTickets\Worldpay\Exceptions\PaymentNotYetQueryableException;
use AeroTickets\Worldpay\Payments\Action;
use AeroTickets\Worldpay\Payments\Instrument\CheckoutSession;
use AeroTickets\Worldpay\Payments\Instrument\PlainCard;
use AeroTickets\Worldpay\Payments\Request\Address;
use AeroTickets\Worldpay\Payments\Request\AuthorizeRequest;
use AeroTickets\Worldpay\Payments\Request\Sequence;
use AeroTickets\Worldpay\Payments\Response\Outcome;
use AeroTickets\Worldpay\Payments\Response\PaymentResult;
use AeroTickets\Worldpay\Payments\Response\Risk;
use AeroTickets\Worldpay\Support\Money;
use AeroTickets\Worldpay\Support\TransactionReference;
use AeroTickets\Worldpay\Testing\CheckoutSessionFactory;
use AeroTickets\Worldpay\Testing\MagicValues;
use AeroTickets\Worldpay\Testing\TestCards;
use AeroTickets\Worldpay\WorldpayClient;

function tryEnv(string $key): ?string
{
    static $loaded = false;
    if (! $loaded && is_file(__DIR__.'/../../.env.testing')) {
        Dotenv\Dotenv::createImmutable(__DIR__.'/../..', '.env.testing')->safeLoad();
        $loaded = true;
    }
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    return is_string($value) && $value !== '' ? $value : null;
}

function tryClient(): WorldpayClient
{
    return WorldpayClient::create(new Config(
        'try',
        tryEnv('WORLDPAY_TRY_ENTITY') ?? 'PO4098288921',
        (string) tryEnv('WORLDPAY_TRY_USERNAME'),
        (string) tryEnv('WORLDPAY_TRY_PASSWORD'),
        checkoutId: tryEnv('WORLDPAY_TRY_CHECKOUT_ID'),
    ));
}

function tryCard(string $holder = MagicValues::AUTHORISED, string $cvc = MagicValues::CVC_APPROVED, string $postcode = MagicValues::AVS_ALL_MATCHED, string $pan = TestCards::VISA): PlainCard
{
    return PlainCard::of($pan, TestCards::EXPIRY_MONTH, TestCards::EXPIRY_YEAR, $cvc, $holder,
        Address::of('221B Baker Street', 'London', 'GB', $postcode));
}

function tryAuthorize(PlainCard|CheckoutSession $instrument, bool $autoSettle = false, ?string $reference = null): PaymentResult
{
    $request = AuthorizeRequest::moto($reference ?? TransactionReference::generate('ATSDK'), Money::fromDecimal('250.00', 'GBP'))
        ->paymentInstrument($instrument)
        ->orderReference('SDK-TEST')
        ->narrative(line2: 'SDK TEST');

    return tryClient()->payments()->authorize($autoSettle ? $request->autoSettle() : $request);
}

beforeEach(function () {
    if (tryEnv('WORLDPAY_TRY_USERNAME') === null || tryEnv('WORLDPAY_TRY_PASSWORD') === null) {
        $this->markTestSkipped('Set WORLDPAY_TRY_USERNAME / WORLDPAY_TRY_PASSWORD (see .env.testing.example) to run Try integration tests.');
    }
});

it('authorizes Visa, Mastercard, Amex and JCB', function (string $pan, string $cvc) {
    $result = tryAuthorize(tryCard(cvc: $cvc, pan: $pan));

    expect($result->outcome)->toBe(Outcome::Authorized)
        ->and($result->paymentId())->not->toBeNull()
        ->and($result->handle()->can(Action::SettlePayment))->toBeTrue();
})->with([
    'visa' => [TestCards::VISA, MagicValues::CVC_APPROVED],
    'mastercard' => [TestCards::MASTERCARD, MagicValues::CVC_APPROVED],
    'amex' => [TestCards::AMEX, MagicValues::CVC_APPROVED_AMEX],
    'jcb' => [TestCards::JCB, MagicValues::CVC_APPROVED],
]);

it('reports a refusal with its code', function () {
    $result = tryAuthorize(tryCard(MagicValues::REFUSED_LIMIT_EXCEEDED));

    expect($result->outcome)->toBe(Outcome::Refused)
        ->and($result->refusal()->code)->toBe('51')
        ->and($result->handle()->isFinal())->toBeTrue();
});

it('reports a CVC mismatch as a risk factor', function () {
    $result = tryAuthorize(tryCard(cvc: MagicValues::CVC_NOT_MATCHED));

    expect($result->outcome)->toBe(Outcome::Authorized)
        ->and($result->riskFactors()->cvc())->toBe(Risk::NotMatched)
        ->and($result->needsReview())->toBeTrue();
});

it('rejects a reused transaction reference', function () {
    $reference = TransactionReference::generate('ATSDK');
    tryAuthorize(tryCard(), reference: $reference);

    tryAuthorize(tryCard(), reference: $reference);
})->throws(DuplicateTransactionReferenceException::class);

it('rejects wrong credentials', function () {
    WorldpayClient::create(new Config('try', 'PO4098288921', 'wrong', 'wrong'))->payments()->authorize(
        AuthorizeRequest::moto(TransactionReference::generate('ATSDK'), Money::fromDecimal('1', 'GBP'))->paymentInstrument(tryCard())
    );
})->throws(AuthenticationException::class);

it('flow A: authorize → settle → full refund', function () {
    $payments = tryClient()->payments();
    $auth = tryAuthorize(tryCard());

    $settled = $payments->settle($auth->handle());
    $refunded = $payments->refund($settled->handle());

    expect($settled->outcome)->toBe(Outcome::SentForSettlement)
        ->and($refunded->outcome)->toBe(Outcome::SentForRefund);
});

it('flow B: two partial settlements, then two partial refunds', function () {
    $payments = tryClient()->payments();
    $handle = tryAuthorize(tryCard())->handle();
    $half = Money::fromDecimal('125.00', 'GBP');

    $ps1 = $payments->partialSettle($handle, $half, 'SDK-PS1', Sequence::of(1, 2));
    $ps2 = $payments->partialSettle($ps1->handle(), $half, 'SDK-PS2', Sequence::of(2, 2));
    $pr1 = $payments->partialRefund($ps2->handle(), Money::fromDecimal('50', 'GBP'), 'SDK-PR1');
    $pr2 = $payments->partialRefund($pr1->handle(), Money::fromDecimal('50', 'GBP'), 'SDK-PR2');

    expect($ps1->outcome)->toBe(Outcome::SentForSettlement)
        ->and($ps2->outcome)->toBe(Outcome::SentForSettlement)
        ->and($pr1->outcome)->toBe(Outcome::SentForPartialRefund)
        ->and($pr2->outcome)->toBe(Outcome::SentForPartialRefund);
});

it('flow C: authorize → cancel → query', function () {
    $payments = tryClient()->payments();
    $auth = tryAuthorize(tryCard());

    $cancelled = $payments->cancel($auth->handle(), 'SDK-CANCEL');
    expect($cancelled->outcome)->toBe(Outcome::SentForCancellation);

    try {
        $query = $payments->query($cancelled->handle(), waitSeconds: 120, pollIntervalSeconds: 5);
        expect($query->lastEvent)->not->toBeNull();
    } catch (PaymentNotYetQueryableException) {
        // Try can take longer than 2 minutes; the query lag is documented, not a failure.
    }
})->group('slow');

it('flow D: partial cancel → settle the remainder', function () {
    $payments = tryClient()->payments();
    $auth = tryAuthorize(tryCard());

    $pc = $payments->partialCancel($auth->handle(), Money::fromDecimal('50', 'GBP'), 'SDK-PC');
    expect($pc->outcome)->toBe(Outcome::SentForCancellation);

    if ($pc->handle()->can(Action::SettlePayment)) {
        expect($payments->settle($pc->handle())->outcome)->toBe(Outcome::SentForSettlement);
    }
});

it('flow E: auto-settled payment → reverse', function () {
    $auth = tryAuthorize(tryCard(), autoSettle: true);
    expect($auth->outcome)->toBe(Outcome::SentForSettlement);

    expect(tryClient()->payments()->reverse($auth->handle())->outcome)->toBe(Outcome::SentForReversal);
});

it('auto-cancels on CVC mismatch with auto settlement', function () {
    $auth = tryAuthorize(tryCard(cvc: MagicValues::CVC_NOT_MATCHED), autoSettle: true);

    expect($auth->outcome)->toBe(Outcome::SentForCancellation);
});

it('authorizes with a Checkout session', function () {
    $checkoutId = tryEnv('WORLDPAY_TRY_CHECKOUT_ID');
    if ($checkoutId === null) {
        $this->markTestSkipped('Set WORLDPAY_TRY_CHECKOUT_ID to test Checkout sessions.');
    }

    $session = (new CheckoutSessionFactory($checkoutId))
        ->cardSession(TestCards::VISA, TestCards::EXPIRY_MONTH, TestCards::EXPIRY_YEAR, MagicValues::CVC_APPROVED);
    $auth = tryAuthorize(CheckoutSession::card($session, MagicValues::AUTHORISED, Address::of('221B Baker Street', 'London', 'GB', MagicValues::AVS_ALL_MATCHED)));

    expect($auth->outcome)->toBe(Outcome::Authorized)
        ->and(tryClient()->payments()->settle($auth->handle())->outcome)->toBe(Outcome::SentForSettlement);
});
