<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Exceptions;

/** 401 accessDenied: wrong username/password, or Try credentials used on Live (or the reverse). */
final class AuthenticationException extends ApiException {}
