<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Payments\Request;

use AeroTickets\Worldpay\Exceptions\InvalidRequestException;

/** "Partial settlement 1 of 3": which partial settlement this is and how many you expect. */
final class Sequence
{
    public function __construct(public readonly int $number, public readonly int $total)
    {
        if ($number < 1 || $total < 1 || $number > $total) {
            throw InvalidRequestException::forField('sequence', 'number and total must be ≥ 1 and number ≤ total.');
        }
    }

    public static function of(int $number, int $total): self
    {
        return new self($number, $total);
    }

    /** @return array{number: int, total: int} */
    public function toArray(): array
    {
        return ['number' => $this->number, 'total' => $this->total];
    }
}
