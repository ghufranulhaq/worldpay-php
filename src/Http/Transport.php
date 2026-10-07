<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Http;

use AeroTickets\Worldpay\Config\Config;
use AeroTickets\Worldpay\Exceptions\ApiException;
use AeroTickets\Worldpay\Exceptions\AuthenticationException;
use AeroTickets\Worldpay\Exceptions\DuplicateTransactionReferenceException;
use AeroTickets\Worldpay\Exceptions\EntityNotConfiguredException;
use AeroTickets\Worldpay\Exceptions\InvalidRequestException;
use AeroTickets\Worldpay\Exceptions\NotFoundException;
use AeroTickets\Worldpay\Exceptions\PaymentInstrumentNotSupportedException;
use AeroTickets\Worldpay\Exceptions\PaymentNotYetQueryableException;
use AeroTickets\Worldpay\Exceptions\ServerException;
use AeroTickets\Worldpay\Exceptions\TransportException;
use AeroTickets\Worldpay\Exceptions\ValidationFailedException;
use AeroTickets\Worldpay\Version;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Sends requests to the Worldpay Payments API: authentication, headers, JSON, logging (redacted)
 * and mapping of error responses to exceptions.
 *
 * @internal use WorldpayClient instead
 */
final class Transport
{
    private ClientInterface $http;

    private LoggerInterface $logger;

    public function __construct(private readonly Config $config)
    {
        $this->http = $config->httpClient ?? new Client;
        $this->logger = $config->logger ?? new NullLogger;
    }

    public function config(): Config
    {
        return $this->config;
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return array{status: int, body: array<string, mixed>}
     */
    public function send(string $method, string $url, ?array $body = null): array
    {
        $this->assertTrustedUrl($url);

        $headers = [
            'Authorization' => 'Basic '.base64_encode($this->config->username.':'.$this->config->password),
            'WP-Api-Version' => $this->config->apiVersion,
            'Accept' => 'application/json',
            'User-Agent' => sprintf('aerotickets-worldpay-moto/%s php/%s', Version::SDK, PHP_VERSION),
        ];
        $options = [
            RequestOptions::HEADERS => $headers,
            RequestOptions::TIMEOUT => $this->config->timeout,
            RequestOptions::CONNECT_TIMEOUT => $this->config->connectTimeout,
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::ALLOW_REDIRECTS => false,
        ];
        if ($body !== null) {
            $options[RequestOptions::HEADERS]['Content-Type'] = 'application/json';
            $options[RequestOptions::BODY] = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        }

        $context = ['method' => $method, 'url' => Redactor::url($url)];
        $this->logger->debug('Worldpay request', $context + ['body' => $body !== null ? Redactor::redact($body) : null]);

        $started = microtime(true);
        try {
            $response = $this->http->request($method, $url, $options);
        } catch (GuzzleException $e) {
            $this->logger->warning('Worldpay request failed without a response', $context + ['error' => $e->getMessage()]);

            throw new TransportException('No response from Worldpay (outcome unknown): '.$e->getMessage(), 0, $e);
        }

        $status = $response->getStatusCode();
        $raw = (string) $response->getBody();
        $decoded = $raw === '' ? [] : json_decode($raw, true);
        $decoded = is_array($decoded) ? $decoded : [];

        $this->logger->debug('Worldpay response', $context + [
            'status' => $status,
            'ms' => (int) round((microtime(true) - $started) * 1000),
            'body' => Redactor::redact($decoded),
        ]);

        if ($status >= 200 && $status < 300) {
            return ['status' => $status, 'body' => $decoded];
        }

        $this->logger->notice('Worldpay error response', $context + [
            'status' => $status,
            'errorName' => $decoded['errorName'] ?? null,
        ]);

        throw self::mapError($status, $decoded, $raw);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public static function mapError(int $status, array $body, string $raw = ''): ApiException
    {
        $errorName = isset($body['errorName']) ? (string) $body['errorName'] : null;
        $validationErrors = [];
        foreach ((array) ($body['validationErrors'] ?? []) as $error) {
            if (is_array($error)) {
                $validationErrors[] = array_filter([
                    'errorName' => isset($error['errorName']) ? (string) $error['errorName'] : null,
                    'message' => isset($error['message']) ? (string) $error['message'] : null,
                    'jsonPath' => isset($error['jsonPath']) ? (string) $error['jsonPath'] : null,
                ], static fn ($v) => $v !== null);
            }
        }

        $names = array_filter(array_merge([$errorName], array_column($validationErrors, 'errorName')));
        $message = isset($body['message']) ? (string) $body['message'] : ($raw !== '' ? substr($raw, 0, 300) : 'HTTP '.$status);
        $details = array_map(
            static fn (array $e) => trim(($e['jsonPath'] ?? '').' '.($e['errorName'] ?? '').': '.($e['message'] ?? '')),
            $validationErrors,
        );
        $text = sprintf('Worldpay %d %s: %s', $status, $errorName ?? 'error', $message).($details !== [] ? ' ['.implode('; ', $details).']' : '');

        $args = [$text, $status, $errorName, $validationErrors, $body];

        return match (true) {
            array_intersect($names, DuplicateTransactionReferenceException::ERROR_NAMES) !== [] => new DuplicateTransactionReferenceException(...$args),
            $status >= 500 => new ServerException(...$args),
            $status === 401 => new AuthenticationException(...$args),
            in_array('entityIsNotConfigured', $names, true) => new EntityNotConfiguredException(...$args),
            in_array('paymentInstrumentIsNotSupported', $names, true) => new PaymentInstrumentNotSupportedException(...$args),
            $status === 400 && $errorName === 'bodyDoesNotMatchSchema' => new ValidationFailedException(...$args),
            $status === 404 && $errorName === 'urlContainsInvalidValue' => new PaymentNotYetQueryableException(...$args),
            $status === 404 => new NotFoundException(...$args),
            default => new ApiException(...$args),
        };
    }

    /**
     * Credentials are only ever sent to the configured environment's host over HTTPS. Links from
     * a stored PaymentHandle are checked too, so a tampered or wrong-environment handle fails here.
     */
    private function assertTrustedUrl(string $url): void
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (($parts['scheme'] ?? '') !== 'https' || $host !== $this->config->environment->host()) {
            throw new InvalidRequestException(sprintf(
                'Refusing to send Worldpay credentials to "%s": links must be https://%s (%s mode). Was this handle created in another mode?',
                $host !== '' ? $host : $url,
                $this->config->environment->host(),
                $this->config->environment->value,
            ), 'href');
        }
    }
}
