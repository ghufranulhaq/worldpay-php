<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Config;

use AeroTickets\Worldpay\Exceptions\ConfigurationException;
use AeroTickets\Worldpay\Exceptions\InvalidRequestException;
use AeroTickets\Worldpay\Support\Validate;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Everything the SDK needs to talk to Worldpay. Build it once at application bootstrap.
 *
 * Construction validates every value and throws ConfigurationException on the first problem,
 * so a misconfigured app fails at boot rather than on the first payment.
 */
final class Config
{
    public const DEFAULT_API_VERSION = '2024-06-01';

    public readonly Environment $environment;

    /**
     * @param  Environment|string  $environment  Environment::Try / Environment::Live, or "try" / "live"
     * @param  string  $entity  Merchant entity from Worldpay Dashboard → Developer Tools (e.g. "PO4098288921")
     * @param  string  $username  Payments API username for this environment
     * @param  string  $password  Payments API password for this environment
     * @param  string  $defaultNarrative  Statement line 1 (trading name), max 24 chars of [a-zA-Z0-9 ,./-]
     * @param  string|null  $checkoutId  Access Checkout ID, used by the browser Checkout SDK (public, not a secret)
     * @param  string  $apiVersion  WP-Api-Version header value
     * @param  float  $timeout  Total request timeout in seconds
     * @param  float  $connectTimeout  Connection timeout in seconds
     * @param  int  $authorizeRetries  Automatic resends of an authorization whose outcome is unknown (5xx / network), same transactionReference
     * @param  int  $retryDelayMs  Delay between those resends
     * @param  LoggerInterface|null  $logger  Optional PSR-3 logger. Card data is always redacted before logging.
     * @param  ClientInterface|null  $httpClient  Optional Guzzle client (proxies, custom handlers, tests)
     */
    public function __construct(
        Environment|string $environment,
        public readonly string $entity,
        public readonly string $username,
        #[\SensitiveParameter] public readonly string $password,
        public readonly string $defaultNarrative = 'AeroTickets',
        public readonly ?string $checkoutId = null,
        public readonly string $apiVersion = self::DEFAULT_API_VERSION,
        public readonly float $timeout = 30.0,
        public readonly float $connectTimeout = 10.0,
        public readonly int $authorizeRetries = 0,
        public readonly int $retryDelayMs = 1000,
        public readonly ?LoggerInterface $logger = null,
        public readonly ?ClientInterface $httpClient = null,
    ) {
        $this->environment = $environment instanceof Environment ? $environment : Environment::fromString($environment);

        if (trim($entity) === '') {
            throw new ConfigurationException('Worldpay entity is required (Dashboard → Developer Tools).');
        }
        if (! preg_match('/^[A-Za-z0-9]+[A-Za-z0-9 ]*$/', $entity) || strlen($entity) > 32) {
            throw new ConfigurationException('Worldpay entity must be 1–32 letters, digits or spaces, starting with a letter or digit.');
        }
        if (strtolower($entity) === 'default') {
            throw new ConfigurationException('Worldpay entity "default" is not configured for MOTO; use the entity reference from Dashboard → Developer Tools.');
        }
        if ($username === '' || $password === '') {
            throw new ConfigurationException('Worldpay username and password are required.');
        }
        try {
            Validate::narrativeLine($defaultNarrative, 'defaultNarrative');
        } catch (InvalidRequestException $e) {
            throw new ConfigurationException($e->getMessage(), previous: $e);
        }
        if ($checkoutId !== null && trim($checkoutId) === '') {
            throw new ConfigurationException('checkoutId must be null or a non-empty string.');
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $apiVersion)) {
            throw new ConfigurationException('apiVersion must look like "2024-06-01".');
        }
        if ($timeout <= 0 || $connectTimeout <= 0) {
            throw new ConfigurationException('timeout and connectTimeout must be greater than zero.');
        }
        if ($authorizeRetries < 0 || $authorizeRetries > 5) {
            throw new ConfigurationException('authorizeRetries must be between 0 and 5.');
        }
        if ($retryDelayMs < 0) {
            throw new ConfigurationException('retryDelayMs must not be negative.');
        }
    }

    /**
     * Build from a plain array (e.g. Laravel config). Keys match the constructor parameter names;
     * "mode" is accepted as an alias for "environment".
     *
     * @param  array<string, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        $environment = $values['environment'] ?? $values['mode'] ?? null;
        if ($environment === null || $environment === '') {
            throw new ConfigurationException('Worldpay mode is required ("try" or "live").');
        }

        $optional = static fn (string $key) => isset($values[$key]) && $values[$key] !== '' ? $values[$key] : null;

        return new self(
            environment: $environment,
            entity: (string) ($values['entity'] ?? ''),
            username: (string) ($values['username'] ?? ''),
            password: (string) ($values['password'] ?? ''),
            defaultNarrative: (string) ($optional('default_narrative') ?? $optional('defaultNarrative') ?? 'AeroTickets'),
            checkoutId: $optional('checkout_id') ?? $optional('checkoutId'),
            apiVersion: (string) ($optional('api_version') ?? $optional('apiVersion') ?? self::DEFAULT_API_VERSION),
            timeout: (float) ($optional('timeout') ?? 30.0),
            connectTimeout: (float) ($optional('connect_timeout') ?? $optional('connectTimeout') ?? 10.0),
            authorizeRetries: (int) ($optional('authorize_retries') ?? $optional('authorizeRetries') ?? 0),
            retryDelayMs: (int) ($optional('retry_delay_ms') ?? $optional('retryDelayMs') ?? 1000),
            logger: ($values['logger'] ?? null) instanceof LoggerInterface ? $values['logger'] : null,
            httpClient: ($values['http_client'] ?? $values['httpClient'] ?? null) instanceof ClientInterface
                ? ($values['http_client'] ?? $values['httpClient'])
                : null,
        );
    }

    /** A copy with some values replaced (named arguments matching the constructor). */
    public function with(mixed ...$changes): self
    {
        $current = [
            'environment' => $this->environment,
            'entity' => $this->entity,
            'username' => $this->username,
            'password' => $this->password,
            'defaultNarrative' => $this->defaultNarrative,
            'checkoutId' => $this->checkoutId,
            'apiVersion' => $this->apiVersion,
            'timeout' => $this->timeout,
            'connectTimeout' => $this->connectTimeout,
            'authorizeRetries' => $this->authorizeRetries,
            'retryDelayMs' => $this->retryDelayMs,
            'logger' => $this->logger,
            'httpClient' => $this->httpClient,
        ];

        foreach (array_keys($changes) as $key) {
            if (! array_key_exists($key, $current)) {
                throw new ConfigurationException(sprintf('Unknown config option "%s".', $key));
            }
        }

        return new self(...array_merge($current, $changes));
    }

    public function baseUrl(): string
    {
        return $this->environment->baseUrl();
    }

    /** Never expose the password when the config is dumped. */
    public function __debugInfo(): array
    {
        return [
            'environment' => $this->environment,
            'entity' => $this->entity,
            'username' => $this->username,
            'password' => '********',
            'defaultNarrative' => $this->defaultNarrative,
            'checkoutId' => $this->checkoutId,
            'apiVersion' => $this->apiVersion,
            'timeout' => $this->timeout,
            'connectTimeout' => $this->connectTimeout,
            'authorizeRetries' => $this->authorizeRetries,
            'retryDelayMs' => $this->retryDelayMs,
        ];
    }
}
