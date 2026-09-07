<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Service;

use DateTimeImmutable;
use DateTimeInterface;
use ItPack\CommitIt\Contract\AuditLogger;
use ItPack\CommitIt\Domain\Value\AuditEvent;
use RuntimeException;

/**
 * File-based {@see AuditLogger} that appends JSON-lines to a log file.
 *
 * Each event is written as a single JSON object per line for easy
 * aggregation with log-shipping tools (jq, grep, etc.).
 *
 * @api
 */
final readonly class FileAuditLogger implements AuditLogger
{
    public function __construct(
        private readonly string $logFilePath,
    ) {
    }

    public function log(AuditEvent $event): void
    {
        $timestamp = $event->timestamp ?? new DateTimeImmutable();

        $payload = [
            'timestamp' => $timestamp->format(DateTimeInterface::ATOM),
            'level' => $event->level->value,
            'operation' => $event->operation,
            'branch' => $event->branch?->__toString(),
            'tag' => $event->tag?->__toString(),
            'context' => $event->context,
        ];

        $line = json_encode($payload, JSON_THROW_ON_ERROR) . "\n";

        $written = @file_put_contents($this->logFilePath, $line, FILE_APPEND | LOCK_EX);

        if ($written === false) {
            throw new RuntimeException(sprintf('Failed to write to audit log: %s', $this->logFilePath));
        }
    }
}
