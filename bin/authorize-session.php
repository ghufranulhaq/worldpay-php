<?php

declare(strict_types=1);

/*
 * Manual end-to-end check: authorize (and settle) a Checkout session created in the browser demo
 * (worldpay-checkout-js/examples/vite-demo) against Worldpay TRY.
 *
 *   php bin/authorize-session.php '<session href>'
 *
 * Reads WORLDPAY_TRY_* from .env.testing. The session is valid for ONE minute and single use.
 */

use AeroTickets\Worldpay\Config\Config;
use AeroTickets\Worldpay\Exceptions\WorldpayException;
use AeroTickets\Worldpay\Payments\Instrument\CheckoutSession;
use AeroTickets\Worldpay\Payments\Request\Address;
use AeroTickets\Worldpay\Payments\Request\AuthorizeRequest;
use AeroTickets\Worldpay\Support\Money;
use AeroTickets\Worldpay\Support\TransactionReference;
use AeroTickets\Worldpay\WorldpayClient;

require __DIR__.'/../vendor/autoload.php';

$session = $argv[1] ?? '';
if ($session === '') {
    fwrite(STDERR, "Usage: php bin/authorize-session.php '<session href>'\n");
    exit(1);
}
if (is_file(__DIR__.'/../.env.testing')) {
    Dotenv\Dotenv::createImmutable(__DIR__.'/..', '.env.testing')->safeLoad();
}

try {
    $worldpay = WorldpayClient::create(new Config(
        'try',
        $_ENV['WORLDPAY_TRY_ENTITY'] ?? 'PO4098288921',
        $_ENV['WORLDPAY_TRY_USERNAME'] ?? '',
        $_ENV['WORLDPAY_TRY_PASSWORD'] ?? '',
    ));

    $result = $worldpay->payments()->authorize(
        AuthorizeRequest::moto(TransactionReference::generate('ATDEMO'), Money::fromDecimal('1.00', 'GBP'))
            ->paymentInstrument(CheckoutSession::card($session, 'AUTHORISED', Address::of('221B Baker Street', 'London', 'GB', 'AAAA')))
            ->narrative(line2: 'SDK DEMO')
    );
    echo 'Authorize: '.json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;

    if ($result->isAuthorized()) {
        $settled = $worldpay->payments()->settle($result->handle());
        echo 'Settle: '.$settled->outcome->value.PHP_EOL;
    }
} catch (WorldpayException $e) {
    fwrite(STDERR, get_class($e).': '.$e->getMessage().PHP_EOL);
    exit(1);
}
