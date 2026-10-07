<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Testing;

/**
 * Values that make Worldpay Try simulate an outcome.
 *
 * - cardHolderName → issuer decision
 * - cvc            → CVC check
 * - postalCode     → AVS check
 */
final class MagicValues
{
    // ---- cardHolderName: issuer decision -------------------------------------------------
    public const AUTHORISED = 'AUTHORISED';             // or any non-magic name → authorized

    public const REFUSED = 'REFUSED';

    /** Not a refusal: Worldpay answers HTTP 500 internalErrorOccurred (outcome unknown). */
    public const ERROR = 'ERROR';

    public const SOFT_DECLINED = 'SOFT_DECLINED';       // refused, code 65

    public const REFUSED_LIMIT_EXCEEDED = 'REFUSED51';

    public const REFUSED_CARD_EXPIRED = 'REFUSED33';

    public const REFUSED_STOLEN_CARD = 'REFUSED43';

    public const REFUSED_LOST_CARD = 'REFUSED41';

    public const REFUSED_INVALID_SECURITY_CODE = 'REFUSED55';

    public const REFUSED_FRAUD_SUSPICION = 'REFUSED34';

    /** Mastercard PAN only: advice 01 retry with updated details. */
    public const MC_ADVICE_RETRY_UPDATED = 'REFUSEDRC79MAC01';

    /** Mastercard PAN only: advice 02 retry after 72h. */
    public const MC_ADVICE_RETRY_LATER = 'REFUSEDRC79MAC02';

    /** Mastercard PAN only: advice 03 do not retry. */
    public const MC_ADVICE_DO_NOT_RETRY = 'REFUSEDRC83MAC03';

    /** FraudSight (if enabled): stopped as high risk. */
    public const FRAUD_HIGH_RISK = 'fs-highRisk';

    public const FRAUD_REVIEW = 'fs-review';

    public const FRAUD_LOW_RISK = 'fs-lowRisk';

    /** "REFUSED{code}" for any issuer refusal code from the Worldpay testing page. */
    public static function refusedWithCode(int $code): string
    {
        return 'REFUSED'.$code;
    }

    // ---- cvc ------------------------------------------------------------------------------
    public const CVC_APPROVED = '555';

    public const CVC_APPROVED_AMEX = '6666';

    public const CVC_NOT_MATCHED = '444';

    public const CVC_NOT_MATCHED_AMEX = '4444';

    public const CVC_NOT_CHECKED = '111';

    // ---- billingAddress.postalCode (AVS) ---------------------------------------------------
    public const AVS_ALL_MATCHED = 'AAAA';

    public const AVS_ADDRESS_NOT_MATCHED = 'CCCC';

    public const AVS_POSTCODE_NOT_MATCHED = 'FFFF';

    public const AVS_NOTHING_MATCHED = 'JJJJ';

    public const AVS_NOT_CHECKED = 'EEEE';

    public const AVS_NOT_SUPPLIED = 'HHHH';
}
