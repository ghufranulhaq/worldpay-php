<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Exceptions;

/**
 * Base class of every exception the SDK throws. Catch this to handle "anything Worldpay-related".
 */
abstract class WorldpayException extends \RuntimeException {}
