<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Testing;

use AeroTickets\Worldpay\Config\Environment;
use AeroTickets\Worldpay\Exceptions\ConfigurationException;
use AeroTickets\Worldpay\Exceptions\TransportException;
use AeroTickets\Worldpay\Http\Transport;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;

/**
 * Creates Checkout sessions from raw TEST card data on the server, the way the browser Checkout SDK
 * does, so Checkout payments can be tested end to end without a browser.
 *
 * Like the browser SDK, it discovers the sessions endpoints from the Access API root (HAL links
 * "service:sessions" → "sessions:card" / "sessions:paymentsCvc"); in Try they live on
 * hpp-sandbox.worldpay.com.
 *
 * ⚠ Try only, test cards only. In production, sessions must be created in the browser
 * (@aerotickets/worldpay-checkout) so card data never reaches your servers.
 */
final class CheckoutSessionFactory
{
    private const MEDIA_TYPE = 'application/vnd.worldpay.sessions-v1.hal+json';

    private ClientInterface $http;

    /** @var array<string, string>|null discovered link rel → href */
    private ?array $links = null;

    public function __construct(private readonly string $checkoutId, private readonly Environment $environment = Environment::Try, ?ClientInterface $http = null)
    {
        if ($environment !== Environment::Try) {
            throw new ConfigurationException('CheckoutSessionFactory is for Try test cards only.');
        }
        if (trim($checkoutId) === '') {
            throw new ConfigurationException('A Checkout ID is required.');
        }
        $this->http = $http ?? new Client;
    }

    /** Card session (number + expiry + CVC) for CheckoutSession::card(). Valid 1 minute, single use. */
    public function cardSession(string $cardNumber, int $expiryMonth, int $expiryYear, string $cvc): string
    {
        return $this->create('sessions:card', [
            'identity' => $this->checkoutId,
            'cardNumber' => $cardNumber,
            'cardExpiryDate' => ['month' => $expiryMonth, 'year' => $expiryYear],
            'cvc' => $cvc,
        ]);
    }

    /** CVC-only session for WorldpayToken::withCvcSession(). Valid 15 minutes, single use. */
    public function cvcSession(string $cvc): string
    {
        return $this->create('sessions:paymentsCvc', ['identity' => $this->checkoutId, 'cvc' => $cvc]);
    }

    /** @param array<string, mixed> $body */
    private function create(string $rel, array $body): string
    {
        $url = $this->discover($rel);
        $decoded = $this->request('POST', $url, $body);

        $href = $decoded['_links']['sessions:session']['href'] ?? null;
        if (! is_string($href) || $href === '') {
            throw new TransportException('Worldpay sessions API returned no session href.');
        }

        return $href;
    }

    private function discover(string $rel): string
    {
        if ($this->links === null) {
            $root = $this->request('GET', $this->environment->baseUrl().'/');
            $sessionsRoot = $root['_links']['service:sessions']['href'] ?? null;
            if (! is_string($sessionsRoot)) {
                throw new TransportException('Worldpay API root has no "service:sessions" link.');
            }
            $service = $this->request('GET', $sessionsRoot);
            $this->links = [];
            foreach ((array) ($service['_links'] ?? []) as $name => $link) {
                if (is_array($link) && is_string($link['href'] ?? null)) {
                    $this->links[(string) $name] = $link['href'];
                }
            }
        }

        if (! isset($this->links[$rel])) {
            throw new TransportException(sprintf('Worldpay sessions service has no "%s" link.', $rel));
        }

        return $this->links[$rel];
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $url, ?array $body = null): array
    {
        $options = [
            RequestOptions::HEADERS => ['Accept' => self::MEDIA_TYPE],
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::TIMEOUT => 30,
        ];
        if ($body !== null) {
            $options[RequestOptions::HEADERS]['Content-Type'] = self::MEDIA_TYPE;
            $options[RequestOptions::BODY] = json_encode($body, JSON_THROW_ON_ERROR);
        }

        try {
            $response = $this->http->request($method, $url, $options);
        } catch (GuzzleException $e) {
            throw new TransportException('Could not reach the Worldpay sessions API: '.$e->getMessage(), 0, $e);
        }

        $raw = (string) $response->getBody();
        $decoded = json_decode($raw, true);
        $decoded = is_array($decoded) ? $decoded : [];
        if ($response->getStatusCode() >= 300) {
            throw Transport::mapError($response->getStatusCode(), $decoded, $raw);
        }

        return $decoded;
    }
}
