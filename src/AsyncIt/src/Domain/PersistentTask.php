<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain;

use InvalidArgumentException;
use ItPack\AsyncIt\Contract\PersistentTaskInterface;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskIdInterface;
use ItPack\RunIt\Contract\Domain\DurationInterface;

/**
 * A durable, serializable task definition that survives process restarts.
 *
 * Only task *definitions* are persisted — never suspended Fibers or raw
 * closures. The operation is a fully-qualified job class name plus a payload
 * array, which can be rehydrated into a {@see \ItPack\AsyncIt\Domain\Task} on
 * restart. At-least-once delivery is the default; exactly-once is achieved
 * through idempotency keys supplied by the caller.
 *
 * @see https://w3id.org/itpack/asyncit#PersistentTask
 * @see https://w3id.org/itpack/runt-taxonomy#occ-task-rehydrated
 */
final readonly class PersistentTask implements PersistentTaskInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public TaskId $id,
        public string $operation,
        public array $payload = [],
        public ?DurationInterface $deadline = null,
        public TaskStatus $status = TaskStatus::Created,
    ) {
        if ($operation === '') {
            throw new InvalidArgumentException('PersistentTask operation must not be empty.');
        }
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

    public function status(): TaskStatus
    {
        return $this->status;
    }

    public function isDeadlineExceeded(): bool
    {
        if (!$this->deadline instanceof DurationInterface) {
            return false;
        }

        return $this->deadline->nanoseconds() <= hrtime(as_number: true);
    }
}
