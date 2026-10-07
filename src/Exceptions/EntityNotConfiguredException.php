<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Exceptions;

/** 400 entityIsNotConfigured: the merchant entity is wrong or not set up for this API. */
final class EntityNotConfiguredException extends ApiException {}
