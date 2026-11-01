<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain;

use InvalidArgumentException;
use ItPack\AsyncIt\Contract\TaskIdInterface;
use ItPack\AsyncIt\Contract\ValueObjectInterface;

/**
 * A deduplication record mapping an idempotency key to a persisted task id.
 *
 * Enables exactly-once delivery: when a task is enqueued with an idempotency
 * key, the queue checks whether that key already maps to a task id. If it
 * does, the enqueue is a no-op (the task was already persisted). If not, the
 * task is persisted and the mapping is recorded atomically.
 *
 * Keys are supplied by the caller via RunIt's {@see \ItPack\RunIt\Contract\Port\IdentifierGeneratorInterface},
 * ensuring globally unique, collision-resistant identifiers across processes
 * and restarts.
 *
 * Only task definitions are persisted — never suspended Fibers or raw closures.
 *
 * @see https://w3id.org/itpack/asyncit#DeduplicationRecord
 * @see https://w3id.org/itpack/asyncit#hasDeduplicationKey
 * @see https://w3id.org/itpack/runit#executionId
 */
final readonly class DeduplicationRecord implements ValueObjectInterface, DeduplicationRecordInterface
{
    public function __construct(
        public string $idempotencyKey,
        public TaskId $taskId,
        public int $recordedAt,
    ) {
        if ($idempotencyKey === '') {
            throw new InvalidArgumentException('DeduplicationRecord idempotency key must not be empty.');
        }
    }

    public function taskId(): TaskIdInterface
    {
        return $this->taskId;
    }

    public function idempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function recordedAt(): int
    {
        return $this->recordedAt;
    }
}
