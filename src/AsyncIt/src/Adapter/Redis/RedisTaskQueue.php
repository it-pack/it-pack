<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Adapter\Redis;

use ItPack\AsyncIt\Adapter\Redis\Exception\RedisTaskQueueException;
use ItPack\AsyncIt\Contract\PersistentTaskInterface;
use ItPack\AsyncIt\Contract\Port\PersistentTaskQueueInterface;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskIdInterface;
use ItPack\AsyncIt\Domain\PersistentTask;
use ItPack\AsyncIt\Domain\TaskId;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Domain\Shared\Duration;
use Throwable;

/**
 * Redis-backed {@see PersistentTaskQueueInterface}.
 *
 * Pending task *definitions* (never raw closures or suspended Fibers) are
 * stored in Redis: a hash maps task id → serialized definition, and a list
 * preserves FIFO enqueue order. On restart, {@see self::listPending()}
 * rehydrates definitions into the scheduler.
 *
 * Delivery is at-least-once by default; exactly-once is achieved through
 * caller-supplied idempotency keys.
 *
 * A Redis client implementing {@see RedisClientInterface} is injected via the
 * constructor, keeping the adapter vendor-neutral and testable without a live
 * Redis server or the `ext-redis` extension.
 *
 * @see https://w3id.org/itpack/asyncit#PersistentTaskQueue
 * @see https://w3id.org/itpack/runit-taxonomy#occ-task-rehydrated
 * @api
 */
final readonly class RedisTaskQueue implements PersistentTaskQueueInterface
{
    public const string PREFIX = 'asyncit:task:';

    public const string DEFINITIONS_KEY = self::PREFIX . 'definitions';

    public const string QUEUE_KEY = self::PREFIX . 'queue';

    private const int LIST_PAGE_SIZE = 100;

    public function __construct(
        private RedisClientInterface $client,
    ) {
    }

    public function enqueue(PersistentTaskInterface $task): void
    {
        $serialized = $this->serialize($task);
        $id = (string) $task->id();

        try {
            $this->client->pipeline([
                ['hSet', [self::DEFINITIONS_KEY, $id, $serialized]],
                ['lPush', [self::QUEUE_KEY, $id]],
            ]);
        } catch (Throwable $throwable) {
            throw new RedisTaskQueueException('Failed to enqueue task.', 0, $throwable);
        }
    }

    public function dequeue(): ?PersistentTaskInterface
    {
        try {
            $id = $this->client->rPop(self::QUEUE_KEY);
            if ($id === null || $id === '') {
                return null;
            }

            /** @var array{0: ?string, 1: int} $results */
            $results = $this->client->pipeline([
                ['hGet', [self::DEFINITIONS_KEY, $id]],
                ['hDel', [self::DEFINITIONS_KEY, $id]],
            ]);
            $serialized = $results[0];

            if ($serialized === null || $serialized === '') {
                return null;
            }

            return $this->deserialize($serialized);
        } catch (Throwable $throwable) {
            throw new RedisTaskQueueException('Failed to dequeue task.', 0, $throwable);
        }
    }

    public function acknowledge(TaskIdInterface $id): void
    {
        $taskId = (string) $id;

        try {
            $this->client->pipeline([
                ['lRem', [self::QUEUE_KEY, 0, $taskId]],
                ['hDel', [self::DEFINITIONS_KEY, $taskId]],
            ]);
        } catch (Throwable $throwable) {
            throw new RedisTaskQueueException('Failed to acknowledge task.', 0, $throwable);
        }
    }

    public function requeue(PersistentTaskInterface $task): void
    {
        $this->enqueue($task);
    }

    /**
     * @return list<PersistentTaskInterface>
     */
    public function listPending(?int $limit = null): array
    {
        try {
            if ($limit === null) {
                $entries = $this->client->hGetAll(self::DEFINITIONS_KEY);
                if ($entries === []) {
                    return [];
                }

                /** @var list<PersistentTaskInterface> $tasks */
                $tasks = [];
                foreach ($entries as $serialized) {
                    $task = $this->tryDeserialize($serialized);
                    if ($task instanceof PersistentTaskInterface) {
                        $tasks[] = $task;
                    }
                }

                return $tasks;
            }

            /** @var list<PersistentTaskInterface> $tasks */
            $tasks = [];
            $cursor = '0';
            $remaining = $limit;

            while ($remaining > 0) {
                $pageSize = min($remaining, self::LIST_PAGE_SIZE);
                /** @var array{0: string, 1: array<string, string>} $page */
                $page = $this->client->hScan(self::DEFINITIONS_KEY, $cursor, $pageSize);
                $entries = $page[1];
                $cursor = $page[0];

                foreach ($entries as $serialized) {
                    $task = $this->tryDeserialize($serialized);
                    if ($task instanceof PersistentTaskInterface) {
                        $tasks[] = $task;
                        $remaining--;
                        if ($remaining <= 0) {
                            break;
                        }
                    }
                }

                if ($cursor === '0') {
                    break;
                }
            }

            return $tasks;
        } catch (Throwable $throwable) {
            throw new RedisTaskQueueException('Failed to list pending tasks.', 0, $throwable);
        }
    }

    public function pendingCount(): int
    {
        return $this->client->hLen(self::DEFINITIONS_KEY);
    }

    private function serialize(PersistentTaskInterface $task): string
    {
        $data = [
            'id' => (string) $task->id(),
            'operation' => $task->operation(),
            'payload' => $task->payload(),
            'deadline' => $task->deadline() instanceof DurationInterface ? $task->deadline()->nanoseconds() : null,
            'status' => $task->status()->value,
        ];

        return json_encode($data, JSON_THROW_ON_ERROR);
    }

    private function deserialize(string $serialized): PersistentTaskInterface
    {
        $data = json_decode($serialized, associative: true, depth: 512, flags: JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            throw new RedisTaskQueueException('Invalid serialized task payload: expected JSON object.');
        }

        $id = $data['id'] ?? '';
        if (!is_string($id) || $id === '') {
            throw new RedisTaskQueueException('Invalid serialized task payload: missing or empty id.');
        }

        $operation = $data['operation'] ?? '';
        if (!is_string($operation) || $operation === '') {
            throw new RedisTaskQueueException('Invalid serialized task payload: missing or empty operation.');
        }

        $payload = [];
        $rawPayload = $data['payload'] ?? [];
        if (is_array($rawPayload)) {
            foreach ($rawPayload as $key => $value) {
                $payload[(string) $key] = $value;
            }
        }

        $deadline = null;
        $rawDeadline = $data['deadline'] ?? null;
        if (is_int($rawDeadline)) {
            $deadline = Duration::ofNanoseconds($rawDeadline);
        }

        $status = TaskStatus::Created;
        $rawStatus = $data['status'] ?? null;
        if (is_string($rawStatus)) {
            $status = TaskStatus::from($rawStatus);
        }

        return new PersistentTask(
            id: new TaskId($id),
            operation: $operation,
            payload: $payload,
            deadline: $deadline,
            status: $status,
        );
    }

    private function tryDeserialize(string $serialized): ?PersistentTaskInterface
    {
        try {
            return $this->deserialize($serialized);
        } catch (Throwable) {
            return null;
        }
    }
}
