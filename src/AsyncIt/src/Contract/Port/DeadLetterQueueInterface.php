<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract\Port;

use ItPack\AsyncIt\Contract\RemoteTaskInterface;

/**
 * Application port for routing timed-out or undeliverable RemoteTask
 * submissions to a dead-letter queue for later inspection and retry.
 *
 * Tasks that exceed a local timeout or whose remote scheduler is unreachable
 * are enqueued here rather than left hanging indefinitely, so operators can
 * replay, requeue, or discard them.
 *
 * @see https://w3id.org/itpack/asyncit#DeadLetterQueue
 * @api
 */
interface DeadLetterQueueInterface
{
    /**
     * Enqueues a task definition that could not be delivered or timed out.
     *
     * @param RemoteTaskInterface $task The task definition to dead-letter.
     * @param string $reason Human-readable reason for dead-lettering
     *                       (e.g. 'local timeout', 'remote unreachable').
     */
    public function enqueue(RemoteTaskInterface $task, string $reason): void;

    /**
     * Returns the number of entries in the dead-letter queue.
     */
    public function count(): int;

    /**
     * Returns all dead-letter entries.
     *
     * @return list<array{task: RemoteTaskInterface, reason: string}>
     */
    public function list(): array;

    /**
     * Removes all entries from the dead-letter queue.
     */
    public function clear(): void;
}
