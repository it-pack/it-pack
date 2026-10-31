<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract\Port;

use ItPack\AsyncIt\Contract\PersistentTaskInterface;
use ItPack\AsyncIt\Contract\TaskIdInterface;

/**
 * Application port for durable task-queue persistence.
 *
 * Implementations store pending task *definitions* (never raw closures or
 * suspended Fibers) in a durable backend such as Redis, PostgreSQL, or
 * RunIt's artifact store. On restart, pending definitions are rehydrated into
 * the scheduler via {@see self::listPending()}.
 *
 * Delivery is at-least-once by default; exactly-once is achieved through
 * caller-supplied idempotency keys (from RunIt's `IdentifierGeneratorInterface`).
 *
 * @see https://w3id.org/itpack/asyncit#PersistentTaskQueue
 * @see https://w3id.org/itpack/runt-taxonomy#occ-task-rehydrated
 */
interface PersistentTaskQueueInterface
{
    /**
     * Adds a task definition to the pending queue.
     *
     * When an idempotency key is supplied, exactly-once delivery is enforced:
     * if the key was already used for a persisted task, the enqueue is a no-op
     * and the existing task is not duplicated. Without a key, delivery is
     * at-least-once (the default). Idempotency keys are typically generated
     * via RunIt's {@see \ItPack\RunIt\Contract\Port\IdentifierGeneratorInterface}.
     */
    public function enqueue(PersistentTaskInterface $task, ?string $idempotencyKey = null): void;

    /**
     * Removes and returns the next pending task definition, or null if the
     * queue is empty.
     */
    public function dequeue(): ?PersistentTaskInterface;

    /**
     * Marks a task as durably completed and removes it from the queue.
     */
    public function acknowledge(TaskIdInterface $id): void;

    /**
     * Requeues a failed task definition for retry (at-least-once delivery).
     */
    public function requeue(PersistentTaskInterface $task): void;

    /**
     * Returns all pending task definitions for rehydration on restart.
     *
     * @param int|null $limit Maximum number of definitions to return (null = all).
     * @return list<PersistentTaskInterface>
     */
    public function listPending(?int $limit = null): array;

    /**
     * Returns the number of pending task definitions in the queue.
     */
    public function pendingCount(): int;
}
