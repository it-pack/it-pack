<?php

declare(strict_types=1);

namespace Tests\Support\Fixture\Psr;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * In-memory PSR-3 logger capturing every entry for assertions.
 *
 * @phpstan-type LogEntry array{level: mixed, message: string, context: array<array-key, mixed>}
 */
final class InMemoryLogger extends AbstractLogger
{
    /**
     * @var list<array{level: mixed, message: string, context: array<array-key, mixed>}>
     */
    private array $entries = [];

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->entries[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    /**
     * @return array{level: mixed, message: string, context: array<array-key, mixed>}|null
     */
    public function last(): ?array
    {
        return $this->entries === [] ? null : end($this->entries);
    }

    public function count(): int
    {
        return count($this->entries);
    }
}
