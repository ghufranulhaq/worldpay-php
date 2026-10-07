<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Payments\Instrument;

/** @internal shared preferredCardBrand support */
trait HasRouting
{
    private ?CardBrand $preferredCardBrand = null;

    /** For co-badged cards: ask Worldpay to route through this brand. */
    public function preferredCardBrand(CardBrand $brand): static
    {
        $copy = clone $this;
        $copy->preferredCardBrand = $brand;

        return $copy;
    }

    /** @param array<string, mixed> $instrument */
    private function withRouting(array $instrument): array
    {
        if ($this->preferredCardBrand !== null) {
            $instrument['routing'] = ['preferredCardBrand' => $this->preferredCardBrand->value];
        }

        return $instrument;
    }
}
