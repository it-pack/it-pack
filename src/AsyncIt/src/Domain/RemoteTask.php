<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain;

use InvalidArgumentException;
use ItPack\AsyncIt\Contract\RemoteTaskInterface;
use ItPack\AsyncIt\Contract\TaskIdInterface;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use Stringable;

/**
 * A serializable task definition for execution on a remote AsyncIt scheduler
 * instance.
 *
 * Only task *definitions* are persisted — never suspended Fibers or raw
 * closures. The operation is a fully-qualified job class name plus a payload
 * array, which can be rehydrated into a {@see \ItPack\AsyncIt\Domain\Task} on
 * the remote side. A correlation id propagates across federation boundaries
 * so distributed cancellation and dead-letter handling can trace the work back
 * to its origin.
 *
 * Priority is advisory: remote schedulers may ignore it if they do not
 * support prioritised queues.
 *
 * @see https://w3id.org/itpack/asyncit#RemoteTask
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class RemoteTask implements RemoteTaskInterface, Stringable
{
    public string $correlationId;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public TaskIdInterface $id,
        public string $operation,
        public array $payload = [],
        public ?DurationInterface $deadline = null,
        public int $priority = 0,
        string $correlationId = '',
    ) {
        if ($operation === '') {
            throw new InvalidArgumentException('RemoteTask operation must not be empty.');
        }

        if (preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*(\\\\[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)*$/', $operation) !== 1) {
            throw new InvalidArgumentException('RemoteTask operation must be a valid fully-qualified class name.');
        }

        if ($priority < 0) {
            throw new InvalidArgumentException('RemoteTask priority must be non-negative.');
        }

        $this->correlationId = $correlationId === '' ? (string) $this->id : $correlationId;
    }

    public function __toString(): string
    {
        return (string) $this->id;
    }

    public function id(): TaskIdInterface
    {
        return $this->id;
    }

    public function operation(): string
    {
        return $this->operation;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    public function deadline(): ?DurationInterface
    {
        return $this->deadline;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    public function correlationId(): string
    {
        return $this->correlationId;
    }
}
