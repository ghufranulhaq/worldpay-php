<?php

declare(strict_types=1);

namespace AeroTickets\Worldpay\Tests\Support;

use Psr\Log\AbstractLogger;

final class MemoryLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array}> */
    public array $records = [];

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->records[] = ['level' => (string) $level, 'message' => (string) $message, 'context' => $context];
    }

    public function dump(): string
    {
        return json_encode($this->records, JSON_UNESCAPED_SLASHES) ?: '';
    }
}
