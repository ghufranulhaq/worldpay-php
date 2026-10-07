<?php

declare(strict_types=1);

use AeroTickets\Worldpay\Config\Config;
use AeroTickets\Worldpay\Config\Environment;
use AeroTickets\Worldpay\Exceptions\ActionNotAvailableException;
use AeroTickets\Worldpay\Exceptions\AuthenticationException;
use AeroTickets\Worldpay\Exceptions\DuplicateTransactionReferenceException;
use AeroTickets\Worldpay\Exceptions\EntityNotConfiguredException;
use AeroTickets\Worldpay\Exceptions\InvalidRequestException;
use AeroTickets\Worldpay\Exceptions\NotFoundException;
use AeroTickets\Worldpay\Exceptions\OutcomeUnknown;
use AeroTickets\Worldpay\Exceptions\PaymentInstrumentNotSupportedException;
use AeroTickets\Worldpay\Exceptions\PaymentNotYetQueryableException;
use AeroTickets\Worldpay\Exceptions\ServerException;
use AeroTickets\Worldpay\Exceptions\TransportException;
use AeroTickets\Worldpay\Exceptions\ValidationFailedException;
use AeroTickets\Worldpay\Payments\Action;
use AeroTickets\Worldpay\Payments\Instrument\CheckoutSession;
use AeroTickets\Worldpay\Payments\PaymentHandle;
use AeroTickets\Worldpay\Payments\Request\AuthorizeRequest;
use AeroTickets\Worldpay\Payments\Request\Sequence;
use AeroTickets\Worldpay\Payments\Response\LastEvent;
use AeroTickets\Worldpay\Payments\Response\Outcome;
use AeroTickets\Worldpay\Payments\Response\RetryAdvice;
use AeroTickets\Worldpay\Payments\Response\Risk;
use AeroTickets\Worldpay\Support\Money;
use AeroTickets\Worldpay\Testing\FakeResponses;
use AeroTickets\Worldpay\Testing\RecordedRequest;
use AeroTickets\Worldpay\Testing\WorldpayFake;

function motoRequest(string $ref = 'AT-1'): AuthorizeRequest
{
    return AuthorizeRequest::moto($ref, Money::fromDecimal('250.00', 'GBP'))
        ->paymentInstrument(CheckoutSession::card('https://try.access.worldpay.com/sessions/abc'));
}

function authorizedHandle(WorldpayFake $fake): PaymentHandle
{
    $fake->queue(FakeResponses::authorized());

    return $fake->client()->payments()->authorize(motoRequest())->handle();
}

it('sends a correctly authenticated MOTO authorization', function () {
    $fake = WorldpayFake::create(new Config('try', 'PO4098288921', 'user', 'pass', defaultNarrative: 'AeroTickets'));
    $fake->queue(FakeResponses::authorized('AT-1'));

    $result = $fake->client()->payments()->authorize(motoRequest());

    $request = $fake->lastRequest();
    expect($request->method)->toBe('POST')
        ->and($request->url)->toBe('https://try.access.worldpay.com/api/payments')
        ->and($request->header('Authorization'))->toBe('Basic '.base64_encode('user:pass'))
        ->and($request->header('WP-Api-Version'))->toBe('2024-06-01')
        ->and($request->header('Content-Type'))->toBe('application/json')
        ->and($request->header('User-Agent'))->toStartWith('aerotickets-worldpay-moto/')
        ->and($request->get('channel'))->toBe('moto')
        ->and($request->get('merchant.entity'))->toBe('PO4098288921')
        ->and($request->get('instruction.narrative.line1'))->toBe('AeroTickets');

    expect($result->outcome)->toBe(Outcome::Authorized)
        ->and($result->isAuthorized())->toBeTrue()
        ->and($result->httpStatus)->toBe(201)
        ->and($result->paymentId())->toStartWith('payI-')
        ->and($result->transactionReference())->toBe('AT-1')
        ->and($result->authorizationCode())->toBe('675725')
        ->and($result->card()->lastFour)->toBe('2701')
        ->and($result->card()->label())->toBe('visa •••• 2701')
        ->and($result->riskFactors()->isEmpty())->toBeTrue()
        ->and($result->handle()->currency)->toBe('GBP')
        ->and($result->handle()->availableActions())->toContain(Action::SettlePayment, Action::CancelPayment, Action::ReversePayment);
});

it('uses the Live base URL in live mode', function () {
    $fake = WorldpayFake::create(new Config(Environment::Live, 'PO1', 'u', 'p'));
    $fake->queue(FakeResponses::authorized('AT-1', [], Environment::Live));

    $fake->client()->payments()->authorize(motoRequest());

    expect($fake->lastRequest()->url)->toBe('https://access.worldpay.com/api/payments');
});

it('reports refusals with retry advice', function () {
    $fake = WorldpayFake::create()->queue(FakeResponses::refused('83', 'Lost/Stolen Card', '03'));

    $result = $fake->client()->payments()->authorize(motoRequest());

    expect($result->outcome)->toBe(Outcome::Refused)
        ->and($result->isSuccessful())->toBeFalse()
        ->and($result->refusal()->code)->toBe('83')
        ->and($result->refusal()->retryAdvice())->toBe(RetryAdvice::DoNotRetry)
        ->and($result->handle()->isFinal())->toBeTrue();
});

it('explains retry-later advice and soft declines', function () {
    $fake = WorldpayFake::create()->queue(FakeResponses::refused('51', 'LIMIT EXCEEDED', '26'), FakeResponses::refused('65', 'Authentication requested'));

    $later = $fake->client()->payments()->authorize(motoRequest('AT-a'))->refusal();
    $soft = $fake->client()->payments()->authorize(motoRequest('AT-b'))->refusal();

    expect($later->retryAdvice())->toBe(RetryAdvice::RetryLater)
        ->and($later->retryAfter()->d)->toBe(2)
        ->and($soft->isSoftDecline())->toBeTrue()
        ->and($soft->retryAdvice())->toBe(RetryAdvice::None);
});

it('parses CVC/AVS risk factors in camelCase and snake_case', function () {
    $fake = WorldpayFake::create()->queue(FakeResponses::authorized('AT-1', ['riskFactors' => [
        ['risk' => 'notMatched', 'type' => 'cvc'],
        ['risk' => 'not_checked', 'detail' => 'postcode', 'type' => 'avs'],
        ['risk' => 'not_matched', 'detail' => 'address', 'type' => 'avs'],
    ]]));

    $result = $fake->client()->payments()->authorize(motoRequest());

    expect($result->riskFactors()->cvc())->toBe(Risk::NotMatched)
        ->and($result->riskFactors()->avsPostcode())->toBe(Risk::NotChecked)
        ->and($result->riskFactors()->avsAddress())->toBe(Risk::NotMatched)
        ->and($result->riskFactors()->hasMismatch())->toBeTrue()
        ->and($result->needsReview())->toBeTrue();
});

it('reports FraudSight high risk and auto-settle outcomes', function () {
    $fake = WorldpayFake::create()->queue(FakeResponses::fraudHighRisk(), FakeResponses::autoSettled(), FakeResponses::autoCancelled());
    $payments = $fake->client()->payments();

    $fraud = $payments->authorize(motoRequest('AT-f'));
    $settled = $payments->authorize(motoRequest('AT-s')->autoSettle());
    $cancelled = $payments->authorize(motoRequest('AT-c')->autoSettle());

    expect($fraud->outcome)->toBe(Outcome::FraudHighRisk)
        ->and($fraud->fraud()->score)->toBe(97.0)
        ->and($fraud->fraud()->isHighRisk())->toBeTrue()
        ->and($settled->outcome)->toBe(Outcome::SentForSettlement)
        ->and($settled->handle()->can(Action::RefundPayment))->toBeTrue()
        ->and($settled->handle()->can(Action::CancelPayment))->toBeFalse()
        ->and($cancelled->outcome)->toBe(Outcome::SentForCancellation)
        ->and($cancelled->riskFactors()->cvc())->toBe(Risk::NotMatched);
});

it('returns the created token', function () {
    $fake = WorldpayFake::create()->queue(FakeResponses::authorized('AT-1', ['token' => [
        'href' => 'https://try.access.worldpay.com/tokens/abc', 'tokenId' => '9876543210ABCDEF', 'cardNumber' => '4000********2701',
        'cardExpiry' => ['month' => 5, 'year' => 2035],
    ]]));

    $token = $fake->client()->payments()->authorize(motoRequest()->createToken())->token();

    expect($token->href)->toBe('https://try.access.worldpay.com/tokens/abc')
        ->and($token->maskedCardNumber)->toBe('4000********2701')
        ->and($token->toInstrument()->toArray())->toBe(['type' => 'token', 'href' => 'https://try.access.worldpay.com/tokens/abc']);
});

it('keeps unknown outcomes instead of failing', function () {
    $fake = WorldpayFake::create()->queue(FakeResponses::json(201, ['outcome' => 'somethingNew']));

    $result = $fake->client()->payments()->authorize(motoRequest());

    expect($result->outcome)->toBe(Outcome::Unknown)->and($result->raw()['outcome'])->toBe('somethingNew');
});

it('runs flow A: settle then full refund, following the latest links', function () {
    $fake = WorldpayFake::create();
    $payments = $fake->client()->payments();
    $handle = authorizedHandle($fake);

    $fake->queue(FakeResponses::settled(), FakeResponses::refunded());
    $settled = $payments->settle($handle);
    $refunded = $payments->refund($settled->handle());

    $requests = $fake->requests();
    expect($requests[1]->path())->toBe('/api/payments/{linkData}/settlements')
        ->and($requests[1]->body)->toBeNull()
        ->and($requests[1]->url)->toBe($handle->link(Action::SettlePayment)['href'])
        ->and($requests[2]->url)->toBe($settled->handle()->link(Action::RefundPayment)['href'])
        ->and($settled->outcome)->toBe(Outcome::SentForSettlement)
        ->and($refunded->outcome)->toBe(Outcome::SentForRefund)
        ->and($refunded->handle()->isFinal())->toBeTrue()
        ->and($refunded->handle()->selfHref)->not->toBeNull()
        ->and($refunded->handle()->currency)->toBe('GBP');
});

it('sends partial settle, partial refund, partial cancel and increase bodies', function () {
    $fake = WorldpayFake::create();
    $payments = $fake->client()->payments();
    $handle = authorizedHandle($fake);

    $fake->queue(FakeResponses::partiallySettled(), FakeResponses::partiallyRefunded());
    $ps = $payments->partialSettle($handle, Money::fromDecimal('125.00', 'GBP'), 'BKG-102938-PS1', Sequence::of(1, 2));
    $payments->partialRefund($ps->handle(), Money::fromDecimal('50', 'GBP'), 'BKG-102938-PR1');

    $handle2 = authorizedHandle($fake);
    $fake->queue(FakeResponses::partiallyCancelled(), FakeResponses::settled());
    $pc = $payments->partialCancel($handle2, Money::fromDecimal('50', 'GBP'), 'BKG-102938-PC');
    $payments->settle($pc->handle());

    $r = $fake->requests();
    expect($r[1]->body)->toBe(['reference' => 'BKG-102938-PS1', 'value' => ['amount' => 12500, 'currency' => 'GBP'], 'sequence' => ['number' => 1, 'total' => 2]])
        ->and($r[2]->path())->toBe('/api/payments/{linkData}/partialRefunds')
        ->and($r[2]->body)->toBe(['reference' => 'BKG-102938-PR1', 'value' => ['amount' => 5000, 'currency' => 'GBP']])
        ->and($r[4]->path())->toBe('/api/payments/{linkData}/partialCancellations')
        ->and($r[5]->path())->toBe('/api/payments/{linkData}/settlements');
});

it('increases an estimated authorization', function () {
    $fake = WorldpayFake::create();
    $handle = PaymentHandle::fromResponse(json_decode((string) FakeResponses::authorizationIncreased(25000)->getBody(), true), 'GBP');

    $fake->queue(FakeResponses::authorizationIncreased(30000));
    $result = $fake->client()->payments()->increaseAuthorization($handle, Money::fromDecimal('50', 'GBP'));

    expect($fake->lastRequest()->body)->toBe(['value' => ['amount' => 5000, 'currency' => 'GBP']])
        ->and($result->totalAuthorized()->toDecimal())->toBe('300.00');
});

it('sends cancel with an optional reference and reverse with no body', function () {
    $fake = WorldpayFake::create();
    $payments = $fake->client()->payments();

    $first = authorizedHandle($fake);
    $fake->queue(FakeResponses::cancelled());
    $payments->cancel($first, 'BKG-102938-CANCEL');
    $cancelBody = $fake->lastRequest()->body;

    $second = authorizedHandle($fake);
    $fake->queue(FakeResponses::reversed());
    $reversed = $payments->reverse($second);

    expect($cancelBody)->toBe(['reference' => 'BKG-102938-CANCEL'])
        ->and($fake->lastRequest()->body)->toBeNull()
        ->and($reversed->outcome)->toBe(Outcome::SentForReversal);
});

it('refuses actions Worldpay did not offer, without calling Worldpay', function () {
    $fake = WorldpayFake::create();
    $handle = authorizedHandle($fake);

    expect(fn () => $fake->client()->payments()->refund($handle))
        ->toThrow(ActionNotAvailableException::class, 'refundPayment');
    $fake->assertSentCount(1);
});

it('refuses amounts in another currency and invalid action references', function () {
    $fake = WorldpayFake::create();
    $payments = $fake->client()->payments();
    $handle = authorizedHandle($fake);

    expect(fn () => $payments->partialSettle($handle, Money::fromDecimal('10', 'EUR'), 'R1'))->toThrow(InvalidRequestException::class, 'GBP')
        ->and(fn () => $payments->partialCancel($handle, Money::fromDecimal('10', 'GBP'), 'BKG_1'))->toThrow(InvalidRequestException::class)
        ->and(fn () => $payments->partialSettle($handle, Money::ofMinor(0, 'GBP'), 'R1'))->toThrow(InvalidRequestException::class);
    $fake->assertSentCount(1);
});

it('refuses to send credentials to another host', function () {
    $fake = WorldpayFake::create();
    $evil = new PaymentHandle('p', 'AT', 'https://evil.example.com/x', ['settlePayment' => ['href' => 'https://evil.example.com/settle', 'method' => 'POST']]);
    $liveOnTry = new PaymentHandle('p', 'AT', null, ['settlePayment' => ['href' => 'https://access.worldpay.com/api/payments/x/settlements', 'method' => 'POST']]);

    expect(fn () => $fake->client()->payments()->settle($evil))->toThrow(InvalidRequestException::class, 'evil.example.com')
        ->and(fn () => $fake->client()->payments()->settle($liveOnTry))->toThrow(InvalidRequestException::class, 'another mode');
    $fake->assertNothingSent();
});

it('round-trips a handle through JSON for storage', function () {
    $fake = WorldpayFake::create();
    $handle = authorizedHandle($fake);

    $restored = PaymentHandle::fromJson($handle->toJson());

    expect($restored->toArray())->toBe($handle->toArray())
        ->and($restored->can(Action::SettlePayment))->toBeTrue();
    expect(fn () => PaymentHandle::fromJson('{oops'))->toThrow(InvalidRequestException::class);
});

it('queries and waits while the payment is not yet queryable', function () {
    $fake = WorldpayFake::create();
    $handle = authorizedHandle($fake);
    $fake->queue(FakeResponses::notYetQueryable(), FakeResponses::query('Sent for Settlement', [Action::RefundPayment]));

    $query = $fake->client()->payments()->query($handle, waitSeconds: 30, pollIntervalSeconds: 0);

    expect($query->lastEvent)->toBe(LastEvent::SentForSettlement)
        ->and($query->handle()->can(Action::RefundPayment))->toBeTrue()
        ->and($query->handle()->paymentId)->toBe($handle->paymentId)
        ->and($fake->lastRequest()->method)->toBe('GET');
});

it('throws immediately when not yet queryable and no wait is requested', function () {
    $fake = WorldpayFake::create();
    $handle = authorizedHandle($fake);
    $fake->queue(FakeResponses::notYetQueryable());

    $fake->client()->payments()->query($handle);
})->throws(PaymentNotYetQueryableException::class);

it('maps Worldpay errors to specific exceptions', function (Closure $response, string $class) {
    $fake = WorldpayFake::create()->queue($response());

    expect(fn () => $fake->client()->payments()->authorize(motoRequest()))->toThrow($class);
})->with([
    'schema' => [fn () => FakeResponses::schemaError(), ValidationFailedException::class],
    'scheme not enabled' => [fn () => FakeResponses::schemaError([['errorName' => 'paymentInstrumentIsNotSupported', 'message' => 'discover not enabled', 'jsonPath' => '$.instruction.paymentInstrument']]), PaymentInstrumentNotSupportedException::class],
    'entity' => [fn () => FakeResponses::error(400, 'entityIsNotConfigured', 'Entity is not configured'), EntityNotConfiguredException::class],
    'duplicate 1' => [fn () => FakeResponses::duplicateTransactionReference(), DuplicateTransactionReferenceException::class],
    'duplicate 2' => [fn () => FakeResponses::error(400, 'transactionReferenceIsADuplicate', 'dup'), DuplicateTransactionReferenceException::class],
    'duplicate 3' => [fn () => FakeResponses::error(400, 'transactionStageHasAlreadyBeenProcessed', 'dup'), DuplicateTransactionReferenceException::class],
    'auth' => [fn () => FakeResponses::accessDenied(), AuthenticationException::class],
    'not found' => [fn () => FakeResponses::error(404, 'endpointNotFound', 'nope'), NotFoundException::class],
    'server' => [fn () => FakeResponses::serverError(), ServerException::class],
    'network' => [fn () => FakeResponses::connectionFailure(), TransportException::class],
]);

it('exposes validation error details', function () {
    $fake = WorldpayFake::create()->queue(FakeResponses::schemaError([['errorName' => 'fieldIsNotAllowed', 'message' => 'Field at path is not allowed.', 'jsonPath' => '$.instruction.threeDS']]));

    try {
        $fake->client()->payments()->authorize(motoRequest());
    } catch (ValidationFailedException $e) {
        expect($e->statusCode)->toBe(400)
            ->and($e->errorNames())->toBe(['bodyDoesNotMatchSchema', 'fieldIsNotAllowed'])
            ->and($e->validationErrors[0]['jsonPath'])->toBe('$.instruction.threeDS')
            ->and($e->getMessage())->toContain('$.instruction.threeDS');
    }
});

it('marks server and network failures as outcome-unknown', function () {
    $fake = WorldpayFake::create()->queue(FakeResponses::serverError());

    try {
        $fake->client()->payments()->authorize(motoRequest());
    } catch (ServerException $e) {
        expect($e)->toBeInstanceOf(OutcomeUnknown::class);
    }
});

it('resends an unknown-outcome authorization with the same reference when retries are enabled', function () {
    $fake = WorldpayFake::create(new Config('try', 'PO1', 'u', 'p', authorizeRetries: 2));
    $fake->queue(FakeResponses::connectionFailure(), FakeResponses::serverError(), FakeResponses::authorized('AT-retry'));

    $result = $fake->client()->payments()->authorize(motoRequest('AT-retry'));

    $refs = array_map(fn (RecordedRequest $r) => $r->get('transactionReference'), $fake->requests());
    expect($result->isAuthorized())->toBeTrue()->and($refs)->toBe(['AT-retry', 'AT-retry', 'AT-retry']);
});

it('flags a duplicate on retry as coming from the previous attempt', function () {
    $fake = WorldpayFake::create(new Config('try', 'PO1', 'u', 'p', authorizeRetries: 1));
    $fake->queue(FakeResponses::serverError(), FakeResponses::duplicateTransactionReference());

    try {
        $fake->client()->payments()->authorize(motoRequest());
        $this->fail('expected duplicate');
    } catch (DuplicateTransactionReferenceException $e) {
        expect($e->fromPreviousAttempt)->toBeTrue();
    }
});

it('does not retry by default, nor on definite errors', function () {
    $fake = WorldpayFake::create(new Config('try', 'PO1', 'u', 'p', authorizeRetries: 2));
    $fake->queue(FakeResponses::schemaError());

    expect(fn () => $fake->client()->payments()->authorize(motoRequest()))->toThrow(ValidationFailedException::class);
    $fake->assertSentCount(1);

    $noRetry = WorldpayFake::create()->queue(FakeResponses::serverError());
    expect(fn () => $noRetry->client()->payments()->authorize(motoRequest()))->toThrow(ServerException::class);
    $noRetry->assertSentCount(1);
});

it('exposes browser checkout settings without secrets', function () {
    $client = WorldpayFake::create(new Config('live', 'PO1', 'user', 'pass', checkoutId: 'chk-1'))->client();

    expect($client->checkoutSettings())->toBe(['checkoutId' => 'chk-1', 'mode' => 'live']);
});
