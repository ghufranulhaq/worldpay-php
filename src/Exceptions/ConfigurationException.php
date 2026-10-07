<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Exceptions;

/** The SDK configuration is missing or invalid. Thrown at bootstrap, before any request. */
final class ConfigurationException extends WorldpayException {}
