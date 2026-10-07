<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Exceptions;

/** 400: the request body did not match Worldpay's schema. See validationErrors (jsonPath + errorName). */
class ValidationFailedException extends ApiException {}
