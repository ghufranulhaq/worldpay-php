<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Testing;

use AeroTickets\Worldpay\Config\Environment;
use AeroTickets\Worldpay\Payments\Action;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * Canned Worldpay responses for WorldpayFake, shaped like the real ones verified in Try.
 * Links point at the Try host unless you pass another Environment.
 */
final class FakeResponses
{
    public static function authorized(string $transactionReference = 'AT-TEST-1', array $overrides = [], Environment $env = Environment::Try): Response
    {
        return self::json(201, array_replace_recursive([
            'outcome' => 'authorized',
            'paymentId' => 'payI-'.self::id(),
            'commandId' => 'cmd'.self::id(),
            'transactionReference' => $transactionReference,
            'schemeReference' => '060720116005060',
            'issuer' => ['authorizationCode' => '675725'],
            'paymentInstrument' => [
                'type' => 'card/checkout+masked', 'cardBin' => '400000', 'lastFour' => '2701', 'countryCode' => 'GB',
                'expiryDate' => ['year' => 2035, 'month' => 5], 'cardBrand' => 'visa', 'fundingType' => 'debit', 'category' => 'consumer',
            ],
        ], $overrides) + self::links($env, [
            Action::CancelPayment, Action::PartiallyCancelPayment, Action::SettlePayment,
            Action::PartiallySettlePayment, Action::ReversePayment,
        ]));
    }

    /** Authorized via auto settlement (202). */
    public static function autoSettled(string $transactionReference = 'AT-TEST-1', Environment $env = Environment::Try): Response
    {
        $body = json_decode((string) self::authorized($transactionReference, [], $env)->getBody(), true);
        $body['outcome'] = 'sentForSettlement';
        unset($body['_actions']);

        return self::json(202, array_merge($body, self::links($env, [
            Action::RefundPayment, Action::PartiallyRefundPayment, Action::ReversePayment,
        ])));
    }

    public static function refused(string $code = '5', string $description = 'REFUSED', ?string $adviceCode = null, string $transactionReference = 'AT-TEST-1'): Response
    {
        $body = [
            'outcome' => 'refused',
            'paymentId' => 'payI-'.self::id(),
            'commandId' => 'cmd'.self::id(),
            'transactionReference' => $transactionReference,
            'refusalCode' => $code,
            'refusalDescription' => $description,
            'riskFactors' => [['risk' => 'notChecked', 'type' => 'cvc']],
        ];
        if ($adviceCode !== null) {
            $body['advice'] = ['code' => $adviceCode];
        }

        return self::json(201, $body);
    }

    public static function fraudHighRisk(string $transactionReference = 'AT-TEST-1'): Response
    {
        return self::json(201, [
            'outcome' => 'fraudHighRisk', 'paymentId' => 'payI-'.self::id(), 'transactionReference' => $transactionReference,
            'score' => 97, 'reason' => ['Recent unexpected card activity'],
        ]);
    }

    /** Auto settlement + CVC/AVS mismatch → auto-cancelled. */
    public static function autoCancelled(string $transactionReference = 'AT-TEST-1', Environment $env = Environment::Try): Response
    {
        return self::json(202, [
            'outcome' => 'sentForCancellation', 'paymentId' => 'payI-'.self::id(), 'transactionReference' => $transactionReference,
            'riskFactors' => [['risk' => 'notMatched', 'type' => 'cvc']],
        ] + self::links($env, []));
    }

    public static function settled(Environment $env = Environment::Try): Response
    {
        return self::json(202, ['outcome' => 'sentForSettlement', 'paymentId' => 'payI-'.self::id()]
            + self::links($env, [Action::RefundPayment, Action::PartiallyRefundPayment, Action::ReversePayment]));
    }

    public static function partiallySettled(Environment $env = Environment::Try): Response
    {
        return self::json(202, ['outcome' => 'sentForSettlement', 'paymentId' => 'payI-'.self::id()]
            + self::links($env, [
                Action::RefundPayment, Action::PartiallyRefundPayment, Action::PartiallySettlePayment,
                Action::CancelPayment, Action::ReversePayment,
            ]));
    }

    public static function cancelled(Environment $env = Environment::Try): Response
    {
        return self::json(202, ['outcome' => 'sentForCancellation'] + self::links($env, []));
    }

    /** Partial cancel as Try returns it (with actions to settle the rest). */
    public static function partiallyCancelled(Environment $env = Environment::Try): Response
    {
        return self::json(202, ['outcome' => 'sentForCancellation'] + self::links($env, [
            Action::SettlePayment, Action::PartiallySettlePayment, Action::CancelPayment, Action::PartiallyCancelPayment,
        ]));
    }

    public static function refunded(Environment $env = Environment::Try): Response
    {
        return self::json(202, ['outcome' => 'sentForRefund', 'paymentId' => 'payI-'.self::id()] + self::links($env, []));
    }

    public static function partiallyRefunded(Environment $env = Environment::Try): Response
    {
        return self::json(202, ['outcome' => 'sentForPartialRefund', 'paymentId' => 'payI-'.self::id()]
            + self::links($env, [Action::PartiallyRefundPayment]));
    }

    public static function reversed(Environment $env = Environment::Try): Response
    {
        return self::json(202, ['outcome' => 'sentForReversal'] + self::links($env, []));
    }

    public static function authorizationIncreased(int $totalAuthorized, string $currency = 'GBP', Environment $env = Environment::Try): Response
    {
        return self::json(201, [
            'outcome' => 'authorized',
            'amounts' => ['totalAuthorized' => $totalAuthorized, 'currency' => $currency],
            'issuer' => ['authorizationCode' => '675726'],
        ] + self::links($env, [Action::SettlePayment, Action::PartiallySettlePayment, Action::CancelPayment, Action::IncreaseAuthorizedAmount]));
    }

    /** @param list<Action> $actions */
    public static function query(string $lastEvent = 'Authorized', array $actions = [Action::SettlePayment, Action::CancelPayment], Environment $env = Environment::Try): Response
    {
        return self::json(200, ['lastEvent' => $lastEvent] + self::links($env, $actions));
    }

    public static function notYetQueryable(): Response
    {
        return self::error(404, 'urlContainsInvalidValue', 'Please provide a valid url value or values.');
    }

    public static function duplicateTransactionReference(): Response
    {
        return self::error(400, 'transactionHasAlreadyBeenProcessed', 'The transaction has already been processed.');
    }

    public static function serverError(): Response
    {
        return self::error(500, 'internalErrorOccurred', 'We cannot currently process your request. Please contact support.');
    }

    public static function accessDenied(): Response
    {
        return self::error(401, 'accessDenied', 'Access to the requested resource has been denied.');
    }

    /** @param list<array{errorName: string, message: string, jsonPath: string}> $validationErrors */
    public static function schemaError(array $validationErrors = [['errorName' => 'fieldIsMissing', 'message' => 'Field is missing.', 'jsonPath' => '$.instruction.value']]): Response
    {
        return self::json(400, [
            'errorName' => 'bodyDoesNotMatchSchema',
            'message' => 'A JSON body matching the expected schema must be provided.',
            'validationErrors' => $validationErrors,
        ]);
    }

    public static function error(int $status, string $errorName, string $message): Response
    {
        return self::json($status, ['errorName' => $errorName, 'message' => $message]);
    }

    /** A network failure (no response): the SDK throws TransportException. */
    public static function connectionFailure(): ConnectException
    {
        return new ConnectException('cURL error 28: Operation timed out', new Request('POST', Environment::Try->baseUrl().'/api/payments'));
    }

    /** @param array<string, mixed> $body */
    public static function json(int $status, array $body): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body, JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  list<Action>  $actions
     * @return array<string, mixed>
     */
    public static function links(Environment $env, array $actions): array
    {
        $base = $env->baseUrl().'/api/payments/'.self::id(40);
        $paths = [
            Action::SettlePayment->value => '/settlements',
            Action::PartiallySettlePayment->value => '/partialSettlements',
            Action::CancelPayment->value => '/cancellations',
            Action::PartiallyCancelPayment->value => '/partialCancellations',
            Action::RefundPayment->value => '/refunds',
            Action::PartiallyRefundPayment->value => '/partialRefunds',
            Action::ReversePayment->value => '/reversals',
            Action::IncreaseAuthorizedAmount->value => '/incrementalAuthorizations',
        ];
        $out = ['_links' => ['self' => ['href' => $base]]];
        foreach ($actions as $action) {
            $out['_actions'][$action->value] = ['href' => $base.$paths[$action->value], 'method' => 'POST'];
        }

        return $out;
    }

    private static function id(int $length = 20): string
    {
        return substr(strtr(base64_encode(random_bytes($length)), '+/=', '-_A'), 0, $length);
    }
}
