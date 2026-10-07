<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Payments;

use AeroTickets\Worldpay\Exceptions\ActionNotAvailableException;
use AeroTickets\Worldpay\Exceptions\InvalidRequestException;

/**
 * Everything needed to act on a payment later: its id, references, the `self` link and the actions
 * Worldpay currently allows (with their opaque URLs).
 *
 * Persist it with the booking (toJson() / fromJson()) and REPLACE it with the handle from every
 * new response: links change (e.g. after settlement) and old ones can stop working.
 *
 * It holds no card data and no credentials; the links only work with your API credentials.
 */
final class PaymentHandle implements \JsonSerializable
{
    public const SCHEMA_VERSION = 1;

    /**
     * @param  array<string, array{href: string, method: string}>  $actions  keyed by Action value
     */
    public function __construct(
        public readonly ?string $paymentId,
        public readonly ?string $transactionReference,
        public readonly ?string $selfHref,
        private readonly array $actions,
        public readonly ?string $currency = null,
    ) {}

    public function can(Action $action): bool
    {
        return isset($this->actions[$action->value]);
    }

    /** @return list<Action> actions allowed right now (unknown action names are ignored) */
    public function availableActions(): array
    {
        $out = [];
        foreach (array_keys($this->actions) as $name) {
            $action = Action::tryFrom((string) $name);
            if ($action !== null) {
                $out[] = $action;
            }
        }

        return $out;
    }

    /**
     * @return array{href: string, method: string}
     *
     * @throws ActionNotAvailableException
     */
    public function link(Action $action): array
    {
        if (! $this->can($action)) {
            throw new ActionNotAvailableException($action, $this->availableActions());
        }

        return $this->actions[$action->value];
    }

    /** True when Worldpay offers no further action (e.g. after a full refund or cancel). */
    public function isFinal(): bool
    {
        return $this->availableActions() === [];
    }

    /**
     * Combine with a newer response: the newer actions always win (even when empty); the older
     * self link, ids and currency are kept when the newer response omits them.
     *
     * @internal
     */
    public function mergedWith(self $newer): self
    {
        return new self(
            $newer->paymentId ?? $this->paymentId,
            $newer->transactionReference ?? $this->transactionReference,
            $newer->selfHref ?? $this->selfHref,
            $newer->actions,
            $newer->currency ?? $this->currency,
        );
    }

    /**
     * Build from a Worldpay response body.
     *
     * @param  array<string, mixed>  $body
     *
     * @internal
     */
    public static function fromResponse(array $body, ?string $currency = null): self
    {
        $actions = [];
        foreach ((array) ($body['_actions'] ?? []) as $name => $link) {
            if (is_array($link) && isset($link['href']) && is_string($link['href'])) {
                $actions[(string) $name] = [
                    'href' => $link['href'],
                    'method' => strtoupper((string) ($link['method'] ?? 'POST')),
                ];
            }
        }

        $self = $body['_links']['self']['href'] ?? null;

        return new self(
            isset($body['paymentId']) ? (string) $body['paymentId'] : null,
            isset($body['transactionReference']) ? (string) $body['transactionReference'] : null,
            is_string($self) ? $self : null,
            $actions,
            $currency,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'v' => self::SCHEMA_VERSION,
            'paymentId' => $this->paymentId,
            'transactionReference' => $this->transactionReference,
            'selfHref' => $this->selfHref,
            'currency' => $this->currency,
            'actions' => $this->actions,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /** @param array<string, mixed> $data from toArray() */
    public static function fromArray(array $data): self
    {
        $actions = [];
        foreach ((array) ($data['actions'] ?? []) as $name => $link) {
            if (! is_array($link) || ! is_string($link['href'] ?? null)) {
                throw new InvalidRequestException(sprintf('Stored payment handle has an invalid action "%s".', $name), 'handle.actions');
            }
            $actions[(string) $name] = ['href' => $link['href'], 'method' => strtoupper((string) ($link['method'] ?? 'POST'))];
        }

        $string = static fn (string $key): ?string => isset($data[$key]) && $data[$key] !== '' ? (string) $data[$key] : null;

        return new self($string('paymentId'), $string('transactionReference'), $string('selfHref'), $actions, $string('currency'));
    }

    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidRequestException('Stored payment handle is not valid JSON.', 'handle', $e);
        }
        if (! is_array($data)) {
            throw new InvalidRequestException('Stored payment handle must be a JSON object.', 'handle');
        }

        return self::fromArray($data);
    }
}
