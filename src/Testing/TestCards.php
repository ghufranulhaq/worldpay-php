<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Testing;

/**
 * Worldpay Try test cards (any future expiry works; 05/2035 is used in the samples).
 * Combine with MagicValues to simulate outcomes. Never use these on Live.
 */
final class TestCards
{
    /** Verified authorized on the Aero Tickets Try entity (2026-10-07). */
    public const VISA = '4000000000002701';

    public const MASTERCARD = '5200000000002235';

    /** Amex uses a 4-digit CVC (MagicValues::CVC_APPROVED_AMEX). */
    public const AMEX = '340000000002708';

    public const JCB = '3338000000000296';

    /** Not enabled on the Aero Tickets Try entity: 400 paymentInstrumentIsNotSupported. */
    public const DISCOVER = '6011000000002117';

    /** Not tested on the Aero Tickets entity. */
    public const CARTES_BANCAIRES_VISA = '4000000000004970';

    /** Not tested on the Aero Tickets entity. */
    public const UNIONPAY = '8100010000000142';

    public const EXPIRY_MONTH = 5;

    public const EXPIRY_YEAR = 2035;
}
