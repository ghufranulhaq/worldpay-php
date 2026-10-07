<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Testing;

use AeroTickets\Worldpay\Config\Config;
use AeroTickets\Worldpay\Config\Environment;
use AeroTickets\Worldpay\WorldpayClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A WorldpayClient that never touches the network, for your application's tests.
 *
 *   $fake = WorldpayFake::create();
 *   $fake->queue(FakeResponses::authorized(), FakeResponses::settled());
 *   $service = new BookingPaymentService($fake->client());
 *   ...
 *   $fake->assertSentCount(2);
 *   $fake->assertSent(fn (RecordedRequest $r) => $r->path() === '/api/payments/{linkData}/settlements');
 *
 * In Laravel: $fake = Worldpay::fake(); (swaps the container binding).
 */
final class WorldpayFake
{
    private MockHandler $mock;

    /** @var list<array{request: RequestInterface}> */
    private array $history = [];

    private WorldpayClient $client;

    private function __construct(Config $config)
    {
        $this->mock = new MockHandler;
        $stack = HandlerStack::create($this->mock);
        $stack->push(Middleware::history($this->history));

        $this->client = new WorldpayClient($config->with(httpClient: new Client(['handler' => $stack]), retryDelayMs: 0));
    }

    /** @param Config|null $config defaults to a Try config with dummy credentials */
    public static function create(?Config $config = null): self
    {
        return new self($config ?? new Config(Environment::Try, 'PO0000000000', 'fake-user', 'fake-pass', checkoutId: 'fake-checkout-id'));
    }

    /** Queue responses (or exceptions such as FakeResponses::connectionFailure()) in call order. */
    public function queue(ResponseInterface|\Throwable ...$responses): self
    {
        foreach ($responses as $response) {
            $this->mock->append($response);
        }

        return $this;
    }

    public function client(): WorldpayClient
    {
        return $this->client;
    }

    /** @return list<RecordedRequest> */
    public function requests(): array
    {
        return array_map(static function (array $entry): RecordedRequest {
            $request = $entry['request'];
            $raw = (string) $request->getBody();
            $body = $raw === '' ? null : json_decode($raw, true);

            return new RecordedRequest($request->getMethod(), (string) $request->getUri(), is_array($body) ? $body : null, $request);
        }, $this->history);
    }

    public function lastRequest(): ?RecordedRequest
    {
        $requests = $this->requests();

        return $requests === [] ? null : $requests[array_key_last($requests)];
    }

    /** Responses queued but not used yet. */
    public function remaining(): int
    {
        return $this->mock->count();
    }

    public function assertSentCount(int $count): void
    {
        self::assert(count($this->history) === $count, sprintf('Expected %d Worldpay request(s), %d sent.', $count, count($this->history)));
    }

    public function assertNothingSent(): void
    {
        $this->assertSentCount(0);
    }

    /** @param callable(RecordedRequest): bool $matches */
    public function assertSent(callable $matches): void
    {
        foreach ($this->requests() as $request) {
            if ($matches($request)) {
                self::assert(true, '');

                return;
            }
        }
        self::assert(false, 'No matching Worldpay request was sent.');
    }

    private static function assert(bool $condition, string $message): void
    {
        if (class_exists(Assert::class)) {
            Assert::assertTrue($condition, $message);

            return;
        }
        if (! $condition) {
            throw new \RuntimeException($message);
        }
    }
}
