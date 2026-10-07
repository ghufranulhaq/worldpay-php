<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Payments\Request;

use AeroTickets\Worldpay\Exceptions\InvalidRequestException;
use AeroTickets\Worldpay\Payments\Instrument\PaymentInstrument;
use AeroTickets\Worldpay\Support\Money;
use AeroTickets\Worldpay\Support\Validate;

/**
 * A MOTO card authorization (POST /api/payments with channel "moto").
 *
 * Immutable builder: every method returns a modified copy.
 *
 *   AuthorizeRequest::moto(TransactionReference::generate('AT'), Money::fromDecimal('250.00', 'GBP'))
 *       ->paymentInstrument(CheckoutSession::card($sessionHref, 'J Smith', $address))
 *       ->orderReference('BKG-102938')
 *       ->narrative(line2: 'BKG-102938');
 *
 * Defaults: manual settlement (you call settle() later), statement line 1 = Config::$defaultNarrative.
 * 3DS, SCA exemptions and customer agreements do not apply to MOTO and are not offered.
 */
final class AuthorizeRequest
{
    /** Request paths that MOTO does not allow, refused by withExtra(). */
    private const FORBIDDEN_EXTRA_PATHS = [
        'channel',
        'transactionReference',
        'merchant.entity',
        'instruction.method',
        'instruction.paymentInstrument',
        'instruction.value',
        'instruction.threeDS',
        'instruction.exemption',
        'instruction.customerAgreement',
    ];

    private ?PaymentInstrument $instrument = null;

    private ?string $orderReference = null;

    private ?string $narrativeLine1 = null;

    private ?string $narrativeLine2 = null;

    private ?bool $autoSettle = null;

    private ?bool $cancelOnCvcNotMatched = null;

    private ?bool $cancelOnAvsNotMatched = null;

    private bool $estimated = false;

    private bool $acceptPartialAmount = false;

    private ?TokenCreation $tokenCreation = null;

    private ?FraudSight $fraud = null;

    private ?Customer $customer = null;

    private ?Shipping $shipping = null;

    private ?string $mcc = null;

    private ?string $entity = null;

    /** @var array<string, mixed> */
    private array $extras = [];

    private function __construct(
        public readonly string $transactionReference,
        public readonly Money $value,
    ) {}

    /**
     * @param  string  $transactionReference  unique per payment (1–64 chars), e.g. TransactionReference::generate('AT').
     *                                        Reuse it ONLY to resend an authorization whose outcome was unknown.
     * @param  Money  $value  amount to authorize; must be greater than zero
     */
    public static function moto(string $transactionReference, Money $value): self
    {
        Validate::transactionReference($transactionReference);
        if ($value->isZero()) {
            throw InvalidRequestException::forField('value.amount', 'must be greater than zero.');
        }

        return new self($transactionReference, $value);
    }

    public function paymentInstrument(PaymentInstrument $instrument): self
    {
        return $this->with(fn (self $r) => $r->instrument = $instrument);
    }

    /** Groups payments for one order; can be reused across payments, e.g. the booking/file number. */
    public function orderReference(string $orderReference): self
    {
        Validate::orderReference($orderReference);

        return $this->with(fn (self $r) => $r->orderReference = $orderReference);
    }

    /**
     * Text on the customer's statement. Each line max 24 chars of letters, digits, space and , . / -
     *
     * @param  string|null  $line1  trading name; defaults to Config::$defaultNarrative
     * @param  string|null  $line2  e.g. the booking reference or a phone number
     */
    public function narrative(?string $line1 = null, ?string $line2 = null): self
    {
        if ($line1 !== null) {
            Validate::narrativeLine($line1, 'narrative.line1');
        }
        if ($line2 !== null) {
            Validate::narrativeLine($line2, 'narrative.line2');
        }

        return $this->with(function (self $r) use ($line1, $line2) {
            $r->narrativeLine1 = $line1 ?? $r->narrativeLine1;
            $r->narrativeLine2 = $line2 ?? $r->narrativeLine2;
        });
    }

    /**
     * Settle automatically right after authorization (outcome sentForSettlement, no settle() call).
     *
     * By default Worldpay then auto-CANCELS the payment if the CVC or AVS check is notMatched
     * (outcome sentForCancellation). Pass false to keep such payments; Worldpay recommends disabling.
     */
    public function autoSettle(bool $cancelOnCvcNotMatched = true, bool $cancelOnAvsNotMatched = true): self
    {
        return $this->with(function (self $r) use ($cancelOnCvcNotMatched, $cancelOnAvsNotMatched) {
            $r->autoSettle = true;
            $r->cancelOnCvcNotMatched = $cancelOnCvcNotMatched;
            $r->cancelOnAvsNotMatched = $cancelOnAvsNotMatched;
        });
    }

    /** Explicit manual settlement (the default): authorize now, settle() or cancel() later. */
    public function manualSettle(): self
    {
        return $this->with(function (self $r) {
            $r->autoSettle = false;
            $r->cancelOnCvcNotMatched = null;
            $r->cancelOnAvsNotMatched = null;
        });
    }

    /** The amount is an estimate you may raise later with increaseAuthorization(). Requires manual settlement. */
    public function estimated(bool $estimated = true): self
    {
        return $this->with(fn (self $r) => $r->estimated = $estimated);
    }

    /** Accept an authorization for less than the requested amount (check PaymentResult::totalAuthorized()). */
    public function acceptPartialAmount(bool $accept = true): self
    {
        return $this->with(fn (self $r) => $r->acceptPartialAmount = $accept);
    }

    /** Store the card as a Worldpay token after the payment. */
    public function createToken(?TokenCreation $tokenCreation = null): self
    {
        return $this->with(fn (self $r) => $r->tokenCreation = $tokenCreation ?? TokenCreation::worldpay());
    }

    /** Run a FraudSight risk assessment (account must have FraudSight enabled). */
    public function fraud(?FraudSight $fraud = null): self
    {
        return $this->with(fn (self $r) => $r->fraud = $fraud ?? FraudSight::assess());
    }

    public function customer(Customer $customer): self
    {
        return $this->with(fn (self $r) => $r->customer = $customer);
    }

    public function shipping(Shipping $shipping): self
    {
        return $this->with(fn (self $r) => $r->shipping = $shipping);
    }

    /** Merchant category code (4 digits). Only if Worldpay enabled dynamic MCC for you. */
    public function mcc(string $mcc): self
    {
        if (! preg_match('/^\d{4}$/', $mcc)) {
            throw InvalidRequestException::forField('merchant.mcc', 'must be 4 digits.');
        }

        return $this->with(fn (self $r) => $r->mcc = $mcc);
    }

    /** Use a different merchant entity than Config::$entity for this payment. */
    public function entity(string $entity): self
    {
        if (! preg_match('/^[A-Za-z0-9]+[A-Za-z0-9 ]*$/', $entity) || strlen($entity) > 32) {
            throw InvalidRequestException::forField('merchant.entity', 'must be 1–32 letters, digits or spaces.');
        }

        return $this->with(fn (self $r) => $r->entity = $entity);
    }

    /**
     * Set any other field of the Payments API request by dot path, for options the SDK does not model,
     * e.g. withExtra('instruction.debtRepayment', true). Values are sent as given (no validation).
     * MOTO-forbidden and SDK-managed paths (channel, threeDS, value, paymentInstrument …) are refused.
     */
    public function withExtra(string $path, mixed $value): self
    {
        foreach (self::FORBIDDEN_EXTRA_PATHS as $forbidden) {
            if ($path === $forbidden || str_starts_with($path, $forbidden.'.')) {
                throw InvalidRequestException::forField($path, 'cannot be set with withExtra(); use the dedicated method or it is not allowed for MOTO.');
            }
        }
        if (! preg_match('/^[A-Za-z][A-Za-z0-9]*(\.[A-Za-z][A-Za-z0-9]*)*$/', $path)) {
            throw InvalidRequestException::forField($path, 'is not a valid dot path.');
        }

        return $this->with(fn (self $r) => $r->extras[$path] = $value);
    }

    public function instrument(): ?PaymentInstrument
    {
        return $this->instrument;
    }

    public function isAutoSettle(): bool
    {
        return $this->autoSettle === true;
    }

    /**
     * The JSON body sent to Worldpay.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidRequestException
     */
    public function toPayload(string $defaultEntity, string $defaultNarrative): array
    {
        if ($this->instrument === null) {
            throw InvalidRequestException::forField('paymentInstrument', 'is required; call paymentInstrument().');
        }
        if ($this->estimated && $this->autoSettle === true) {
            throw InvalidRequestException::forField('value.estimated', 'cannot be combined with autoSettle().');
        }

        $line1 = $this->narrativeLine1 ?? $defaultNarrative;
        Validate::narrativeLine($line1, 'narrative.line1');

        $merchant = ['entity' => $this->entity ?? $defaultEntity];
        if ($this->mcc !== null) {
            $merchant['mcc'] = $this->mcc;
        }

        $value = $this->value->toArray();
        if ($this->acceptPartialAmount) {
            $value['acceptPartialAmount'] = true;
        }
        if ($this->estimated) {
            $value['estimated'] = true;
        }

        $instruction = [
            'method' => 'card',
            'paymentInstrument' => $this->instrument->toArray(),
            'narrative' => array_filter(['line1' => $line1, 'line2' => $this->narrativeLine2], static fn ($v) => $v !== null),
            'value' => $value,
        ];

        if ($this->autoSettle !== null) {
            $settlement = ['auto' => $this->autoSettle];
            if ($this->autoSettle) {
                $settlement['cancelOn'] = [
                    'cvcNotMatched' => $this->cancelOnCvcNotMatched === false ? 'disabled' : 'enabled',
                    'avsNotMatched' => $this->cancelOnAvsNotMatched === false ? 'disabled' : 'enabled',
                ];
            }
            $instruction['settlement'] = $settlement;
        }
        if ($this->tokenCreation !== null) {
            $instruction['tokenCreation'] = $this->tokenCreation->toArray();
        }
        if ($this->fraud !== null) {
            $instruction['fraud'] = $this->fraud->toArray();
        }
        if ($this->customer !== null && ($customer = $this->customer->toArray()) !== []) {
            $instruction['customer'] = $customer;
        }
        if ($this->shipping !== null && ($shipping = $this->shipping->toArray()) !== []) {
            $instruction['shipping'] = $shipping;
        }

        $payload = ['transactionReference' => $this->transactionReference];
        if ($this->orderReference !== null) {
            $payload['orderReference'] = $this->orderReference;
        }
        $payload['merchant'] = $merchant;
        $payload['channel'] = 'moto';
        $payload['instruction'] = $instruction;

        foreach ($this->extras as $path => $extra) {
            $payload = self::setPath($payload, explode('.', $path), $extra);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $array
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private static function setPath(array $array, array $keys, mixed $value): array
    {
        $key = array_shift($keys);
        if ($keys === []) {
            $array[$key] = $value;

            return $array;
        }
        $child = isset($array[$key]) && is_array($array[$key]) ? $array[$key] : [];
        $array[$key] = self::setPath($child, $keys, $value);

        return $array;
    }

    private function with(callable $change): self
    {
        $copy = clone $this;
        $change($copy);

        return $copy;
    }
}
