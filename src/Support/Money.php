<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Support;

use AeroTickets\Worldpay\Exceptions\InvalidRequestException;

/**
 * An amount in a currency, held as integer minor units (pence, cents). No floats anywhere.
 *
 *   Money::fromDecimal('250.00', 'GBP')->minorUnits   // 25000
 *   Money::ofMinor(25000, 'GBP')->toDecimal()          // "250.00"
 */
final class Money implements \JsonSerializable
{
    /** ISO 4217 currencies whose minor unit is not 2 decimals. Everything else uses 2. */
    private const EXPONENTS = [
        'BIF' => 0, 'CLP' => 0, 'DJF' => 0, 'GNF' => 0, 'ISK' => 0, 'JPY' => 0, 'KMF' => 0, 'KRW' => 0,
        'PYG' => 0, 'RWF' => 0, 'UGX' => 0, 'UYI' => 0, 'VND' => 0, 'VUV' => 0, 'XAF' => 0, 'XOF' => 0, 'XPF' => 0,
        'BHD' => 3, 'IQD' => 3, 'JOD' => 3, 'KWD' => 3, 'LYD' => 3, 'OMR' => 3, 'TND' => 3,
    ];

    private function __construct(
        public readonly int $minorUnits,
        public readonly string $currency,
    ) {}

    /** From minor units, e.g. ofMinor(25000, 'GBP') = £250.00. */
    public static function ofMinor(int $minorUnits, string $currency): self
    {
        $currency = strtoupper($currency);
        Validate::currency($currency);
        if ($minorUnits < 0) {
            throw InvalidRequestException::forField('value.amount', 'must not be negative.');
        }
        if ($minorUnits > 2147483647) {
            throw InvalidRequestException::forField('value.amount', 'is larger than Worldpay accepts (int32).');
        }

        return new self($minorUnits, $currency);
    }

    /**
     * From a decimal amount as a string or int, e.g. fromDecimal('250.00', 'GBP') or fromDecimal(250, 'GBP').
     * Strings with more decimals than the currency allows are rejected rather than rounded.
     */
    public static function fromDecimal(string|int $amount, string $currency): self
    {
        $currency = strtoupper($currency);
        Validate::currency($currency);
        $exponent = self::exponent($currency);

        $amount = trim((string) $amount);
        if (! preg_match('/^(\d+)(?:\.(\d+))?$/', $amount, $m)) {
            throw InvalidRequestException::forField('value.amount', sprintf('"%s" is not a valid non-negative decimal amount.', $amount));
        }
        $whole = $m[1];
        $fraction = rtrim($m[2] ?? '', '0');
        if (strlen($fraction) > $exponent) {
            throw InvalidRequestException::forField('value.amount', sprintf('%s allows at most %d decimal places.', $currency, $exponent));
        }
        $minor = ltrim($whole.str_pad($fraction, $exponent, '0'), '0');

        if ($minor === '') {
            $minor = '0';
        }
        if (strlen($minor) > 10 || (int) $minor > 2147483647) {
            throw InvalidRequestException::forField('value.amount', 'is larger than Worldpay accepts (int32).');
        }

        return new self((int) $minor, $currency);
    }

    public static function exponent(string $currency): int
    {
        return self::EXPONENTS[strtoupper($currency)] ?? 2;
    }

    /** Decimal string, e.g. "250.00". */
    public function toDecimal(): string
    {
        $exponent = self::exponent($this->currency);
        if ($exponent === 0) {
            return (string) $this->minorUnits;
        }
        $padded = str_pad((string) $this->minorUnits, $exponent + 1, '0', STR_PAD_LEFT);

        return substr($padded, 0, -$exponent).'.'.substr($padded, -$exponent);
    }

    public function isZero(): bool
    {
        return $this->minorUnits === 0;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->minorUnits === $other->minorUnits;
    }

    /** @return array{amount: int, currency: string} the Worldpay "value" object */
    public function toArray(): array
    {
        return ['amount' => $this->minorUnits, 'currency' => $this->currency];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return $this->toDecimal().' '.$this->currency;
    }
}
