<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Payments\Request;

use AeroTickets\Worldpay\Exceptions\InvalidRequestException;
use AeroTickets\Worldpay\Support\Validate;

/**
 * FraudSight risk assessment (instruction.fraud). Only works if FraudSight is enabled on your
 * Worldpay account; otherwise Worldpay rejects or ignores it.
 *
 *   FraudSight::assess()                         // stop high-risk payments (outcome fraudHighRisk)
 *   FraudSight::assess()->silentMode()           // only score, never stop
 *   FraudSight::assess()->custom(string1: 'call-centre')
 */
final class FraudSight
{
    private bool $silentMode = false;

    /** @var array<string, int|string> */
    private array $custom = [];

    private ?string $deviceDataCollectionReference = null;

    private function __construct() {}

    public static function assess(): self
    {
        return new self;
    }

    /** Score the payment but never stop it (fraud.outcome gets a "(silentMode)" suffix). */
    public function silentMode(bool $enabled = true): self
    {
        $copy = clone $this;
        $copy->silentMode = $enabled;

        return $copy;
    }

    /**
     * Custom fields for your FraudSight rules: number1–number9 (int) and string1–string9 (1–100 chars).
     */
    public function custom(int|string ...$fields): self
    {
        $copy = clone $this;
        foreach ($fields as $key => $value) {
            if (! is_string($key) || ! preg_match('/^(number|string)[1-9]$/', $key)) {
                throw InvalidRequestException::forField('fraud.custom', 'keys must be number1–number9 or string1–string9.');
            }
            if (str_starts_with($key, 'number') && ! is_int($value)) {
                throw InvalidRequestException::forField('fraud.custom.'.$key, 'must be an integer.');
            }
            if (str_starts_with($key, 'string')) {
                Validate::length((string) $value, 'fraud.custom.'.$key, 1, 100);
                $value = (string) $value;
            }
            $copy->custom[$key] = $value;
        }

        return $copy;
    }

    /** Ravelin device data collection reference, if you run device profiling. Rare for MOTO. */
    public function ravelinDeviceData(string $collectionReference): self
    {
        Validate::length($collectionReference, 'fraud.deviceData.collectionReference', 1, 300);
        $copy = clone $this;
        $copy->deviceDataCollectionReference = $collectionReference;

        return $copy;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $out = ['type' => 'fraudSight'];
        if ($this->silentMode) {
            $out['silentMode'] = true;
        }
        if ($this->custom !== []) {
            $out['custom'] = $this->custom;
        }
        if ($this->deviceDataCollectionReference !== null) {
            $out['deviceData'] = ['provider' => 'ravelin', 'collectionReference' => $this->deviceDataCollectionReference];
        }

        return $out;
    }
}
