<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Exceptions;

/**
 * 5xx from Worldpay (e.g. internalErrorOccurred). The outcome is unknown: reconcile, don't assume failure.
 */
final class ServerException extends ApiException implements OutcomeUnknown {}
