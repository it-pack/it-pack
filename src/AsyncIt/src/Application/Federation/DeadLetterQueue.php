<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Federation;

use ItPack\AsyncIt\Contract\Port\DeadLetterQueueInterface;
use ItPack\AsyncIt\Contract\RemoteTaskInterface;

/**
 * In-memory dead-letter queue for federated RemoteTask submissions.
 *
 * Stores timed-out or undeliverable task definitions with their dead-letter
 * reason so operators can inspect and replay them. This is a reference /
 * development implementation; production deployments should back this with a
 * durable store.
 *
 * @see https://w3id.org/itpack/asyncit#DeadLetterQueue
 */
final class DeadLetterQueue implements DeadLetterQueueInterface
{
    /**
     * @var list<array{task: RemoteTaskInterface, reason: string}>
     */
    private array $entries = [];

    public function enqueue(RemoteTaskInterface $task, string $reason): void
    {
        $this->entries[] = ['task' => $task, 'reason' => $reason];
    }

    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * @return list<array{task: RemoteTaskInterface, reason: string}>
     */
    public function list(): array
    {
        return $this->entries;
    }

    public function clear(): void
    {
        $this->entries = [];
    }
}
