<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Payments\Instrument;

/** Card brands accepted by routing.preferredCardBrand (co-badged cards only). */
enum CardBrand: string
{
    case Visa = 'visa';
    case Mastercard = 'mastercard';
    case Maestro = 'maestro';
    case Amex = 'amex';
    case CartesBancaires = 'cartesBancaires';
    case Diners = 'diners';
    case Dankort = 'dankort';
    case Jcb = 'jcb';
    case Discover = 'discover';
    case Elo = 'elo';
    case EftposAU = 'eftposAU';
}
