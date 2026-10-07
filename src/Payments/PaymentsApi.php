<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Payments;

use AeroTickets\Worldpay\Exceptions\DuplicateTransactionReferenceException;
use AeroTickets\Worldpay\Exceptions\InvalidRequestException;
use AeroTickets\Worldpay\Exceptions\OutcomeUnknown;
use AeroTickets\Worldpay\Exceptions\PaymentNotYetQueryableException;
use AeroTickets\Worldpay\Exceptions\WorldpayException;
use AeroTickets\Worldpay\Http\Transport;
use AeroTickets\Worldpay\Payments\Request\AuthorizeRequest;
use AeroTickets\Worldpay\Payments\Request\Sequence;
use AeroTickets\Worldpay\Payments\Response\PaymentResult;
use AeroTickets\Worldpay\Payments\Response\QueryResult;
use AeroTickets\Worldpay\Support\Money;
use AeroTickets\Worldpay\Support\Validate;

/**
 * MOTO payments: authorize, then settle / cancel / refund / reverse (full or partial), query.
 *
 * Every manage method takes the latest PaymentHandle and returns a PaymentResult whose handle()
 * replaces it. The SDK never builds Worldpay URLs: it uses the links Worldpay returned, and throws
 * ActionNotAvailableException (without calling Worldpay) when the action is not currently offered.
 */
final class PaymentsApi
{
    public function __construct(private readonly Transport $transport) {}

    /**
     * Authorize a MOTO card payment.
     *
     * Outcomes: Authorized (201), Refused (201), FraudHighRisk (201), SentForSettlement /
     * SentForCancellation (202, with autoSettle()). Errors throw WorldpayException subclasses;
     * those implementing OutcomeUnknown mean "resend with the same transactionReference / reconcile".
     *
     * @throws WorldpayException
     */
    public function authorize(AuthorizeRequest $request): PaymentResult
    {
        $config = $this->transport->config();
        $payload = $request->toPayload($config->entity, $config->defaultNarrative);
        $url = $config->baseUrl().'/api/payments';

        $attempt = 0;
        while (true) {
            try {
                $response = $this->transport->send('POST', $url, $payload);

                return PaymentResult::fromResponse($response['status'], $response['body'], null, $request->value->currency);
            } catch (DuplicateTransactionReferenceException $e) {
                throw $attempt > 0 ? DuplicateTransactionReferenceException::fromPreviousAttempt($e) : $e;
            } catch (WorldpayException $e) {
                if (! $e instanceof OutcomeUnknown || $attempt >= $config->authorizeRetries) {
                    throw $e;
                }
                $attempt++;
                if ($config->retryDelayMs > 0) {
                    usleep($config->retryDelayMs * 1000);
                }
            }
        }
    }

    /**
     * Current state of the payment (lastEvent + actions).
     *
     * Right after authorization Worldpay answers 404 for a while (25–65 s in Try). Pass
     * $waitSeconds to keep polling every $pollIntervalSeconds until it is queryable; otherwise
     * PaymentNotYetQueryableException is thrown immediately.
     *
     * @throws WorldpayException
     */
    public function query(PaymentHandle $handle, int $waitSeconds = 0, int $pollIntervalSeconds = 5): QueryResult
    {
        if ($handle->selfHref === null) {
            throw new InvalidRequestException('This payment handle has no self link to query.', 'handle.selfHref');
        }

        $deadline = time() + max(0, $waitSeconds);
        while (true) {
            try {
                $response = $this->transport->send('GET', $handle->selfHref);

                return QueryResult::fromResponse($response['body'], $handle);
            } catch (PaymentNotYetQueryableException $e) {
                if (time() >= $deadline) {
                    throw $e;
                }
                sleep(max(0, $pollIntervalSeconds));
            }
        }
    }

    /** Capture the full authorized amount (manual settlement). Outcome: SentForSettlement. */
    public function settle(PaymentHandle $handle): PaymentResult
    {
        return $this->act($handle, Action::SettlePayment);
    }

    /**
     * Capture part of the authorization, e.g. one passenger's ticket. Repeat for the rest.
     *
     * @param  string  $reference  your reference for this settlement, e.g. "BKG-102938-PS1"
     */
    public function partialSettle(PaymentHandle $handle, Money $amount, string $reference, ?Sequence $sequence = null): PaymentResult
    {
        Validate::actionReference($reference);
        $body = ['reference' => $reference, 'value' => $this->value($handle, $amount)];
        if ($sequence !== null) {
            $body['sequence'] = $sequence->toArray();
        }

        return $this->act($handle, Action::PartiallySettlePayment, $body);
    }

    /**
     * Release the whole hold before settlement (no money moves). Outcome: SentForCancellation.
     *
     * @param  string|null  $reference  optional, letters/digits/hyphens only
     */
    public function cancel(PaymentHandle $handle, ?string $reference = null): PaymentResult
    {
        $body = null;
        if ($reference !== null) {
            Validate::cancelReference($reference);
            $body = ['reference' => $reference];
        }

        return $this->act($handle, Action::CancelPayment, $body);
    }

    /**
     * Release part of the hold (e.g. the fare dropped). Only ONCE per payment: Worldpay treats a
     * second partial cancel as cancelling everything that is left.
     *
     * @param  string  $reference  letters/digits/hyphens only, max 128
     */
    public function partialCancel(PaymentHandle $handle, Money $amount, string $reference): PaymentResult
    {
        Validate::cancelReference($reference);

        return $this->act($handle, Action::PartiallyCancelPayment, ['reference' => $reference, 'value' => $this->value($handle, $amount)]);
    }

    /** Return the full settled amount. Outcome: SentForRefund. Nothing is allowed afterwards. */
    public function refund(PaymentHandle $handle): PaymentResult
    {
        return $this->act($handle, Action::RefundPayment);
    }

    /**
     * Return part of the settled amount; repeat as needed. Worldpay Try does NOT reject refunds above
     * the settled amount, so track the refunded total in your application.
     */
    public function partialRefund(PaymentHandle $handle, Money $amount, string $reference): PaymentResult
    {
        Validate::actionReference($reference);

        return $this->act($handle, Action::PartiallyRefundPayment, ['reference' => $reference, 'value' => $this->value($handle, $amount)]);
    }

    /**
     * "Undo": Worldpay cancels if early enough, otherwise refunds (non-US: refund after 15 minutes).
     * Outcome: SentForReversal.
     */
    public function reverse(PaymentHandle $handle): PaymentResult
    {
        return $this->act($handle, Action::ReversePayment);
    }

    /**
     * Raise an estimated authorization (AuthorizeRequest::estimated()) by $amount.
     * Outcome: Authorized (see totalAuthorized()) or Refused.
     */
    public function increaseAuthorization(PaymentHandle $handle, Money $amount): PaymentResult
    {
        if ($amount->isZero()) {
            throw InvalidRequestException::forField('value.amount', 'must be greater than zero.');
        }

        return $this->act($handle, Action::IncreaseAuthorizedAmount, ['value' => $this->value($handle, $amount)]);
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    private function act(PaymentHandle $handle, Action $action, ?array $body = null): PaymentResult
    {
        $link = $handle->link($action);
        $response = $this->transport->send($link['method'], $link['href'], $body);

        return PaymentResult::fromResponse($response['status'], $response['body'], $handle);
    }

    /** @return array{amount: int, currency: string} */
    private function value(PaymentHandle $handle, Money $amount): array
    {
        if ($amount->isZero()) {
            throw InvalidRequestException::forField('value.amount', 'must be greater than zero.');
        }
        if ($handle->currency !== null && $handle->currency !== $amount->currency) {
            throw InvalidRequestException::forField('value.currency', sprintf(
                'must be the authorization currency %s (got %s).',
                $handle->currency,
                $amount->currency,
            ));
        }

        return $amount->toArray();
    }
}
