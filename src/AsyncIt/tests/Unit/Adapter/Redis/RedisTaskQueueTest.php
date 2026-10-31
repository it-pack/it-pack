<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Redis;

use InvalidArgumentException;
use ItPack\AsyncIt\Adapter\Redis\Exception\RedisTaskQueueException;
use ItPack\AsyncIt\Adapter\Redis\RedisClientInterface;
use ItPack\AsyncIt\Adapter\Redis\RedisTaskQueue;
use ItPack\AsyncIt\Contract\Port\PersistentTaskQueueInterface;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Domain\PersistentTask;
use ItPack\AsyncIt\Domain\TaskId;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Domain\Shared\Duration;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \ItPack\AsyncIt\Adapter\Redis\RedisTaskQueue
 * @covers \ItPack\AsyncIt\Adapter\Redis\Exception\RedisTaskQueueException
 */
final class RedisTaskQueueTest extends TestCase
{
    public function testImplementsPersistentTaskQueueInterface(): void
    {
        self::assertInstanceOf(PersistentTaskQueueInterface::class, $this->queue());
    }

    public function testEnqueueAndDequeueRoundTrip(): void
    {
        $queue = $this->queue();
        $task = new PersistentTask(
            id: new TaskId('task-1'),
            operation: 'App\Job\SendEmail',
            payload: ['to' => 'user@example.com'],
            deadline: Duration::ofSeconds(300),
            status: TaskStatus::Created,
        );

        $queue->enqueue($task);

        self::assertSame(1, $queue->pendingCount());

        $dequeued = $queue->dequeue();
        self::assertNotNull($dequeued);
        self::assertInstanceOf(PersistentTask::class, $dequeued);
        self::assertSame('task-1', $dequeued->id()->value);
        self::assertSame('App\Job\SendEmail', $dequeued->operation());
        self::assertSame(['to' => 'user@example.com'], $dequeued->payload());
        $deadline = $dequeued->deadline();
        self::assertNotNull($deadline);
        self::assertInstanceOf(DurationInterface::class, $deadline);
        self::assertTrue(Duration::ofSeconds(300)->equals($deadline));
        self::assertSame(TaskStatus::Created, $dequeued->status());

        self::assertSame(0, $queue->pendingCount());
    }

    public function testDequeueReturnsNullWhenEmpty(): void
    {
        $queue = $this->queue();
        self::assertNull($queue->dequeue());
    }

    public function testAcknowledgeRemovesTaskById(): void
    {
        $queue = $this->queue();
        $task = new PersistentTask(
            id: new TaskId('task-ack'),
            operation: 'App\Job\ProcessPayment',
        );

        $queue->enqueue($task);
        self::assertSame(1, $queue->pendingCount());

        $queue->acknowledge(new TaskId('task-ack'));
        self::assertSame(0, $queue->pendingCount());
        self::assertNull($queue->dequeue());
    }

    public function testRequeueRestoresPendingTask(): void
    {
        $queue = $this->queue();
        $task = new PersistentTask(
            id: new TaskId('task-requeue'),
            operation: 'App\Job\ExportReport',
        );

        $queue->enqueue($task);
        $queue->dequeue();
        self::assertSame(0, $queue->pendingCount());

        $queue->requeue($task);
        self::assertSame(1, $queue->pendingCount());

        $dequeued = $queue->dequeue();
        self::assertNotNull($dequeued);
        assert($dequeued instanceof PersistentTask);
        self::assertSame('task-requeue', $dequeued->id()->value);
    }

    public function testListPendingReturnsAllTasks(): void
    {
        $queue = $this->queue();
        $tasks = [
            new PersistentTask(id: new TaskId('task-a'), operation: 'App\Job\A'),
            new PersistentTask(id: new TaskId('task-b'), operation: 'App\Job\B'),
            new PersistentTask(id: new TaskId('task-c'), operation: 'App\Job\C'),
        ];

        foreach ($tasks as $task) {
            $queue->enqueue($task);
        }

        self::assertSame(3, $queue->pendingCount());

        /** @var list<PersistentTask> $pending */
        $pending = $queue->listPending();
        self::assertCount(3, $pending);

        $ids = array_map(fn (PersistentTask $t): string => $t->id()->value, $pending);
        sort($ids);
        self::assertSame(['task-a', 'task-b', 'task-c'], $ids);
    }

    public function testListPendingWithLimitReturnsAtMostN(): void
    {
        $queue = $this->queue();
        for ($i = 0; $i < 5; $i++) {
            $queue->enqueue(new PersistentTask(
                id: new TaskId("task-limit-$i"),
                operation: 'App\Job\Limit',
            ));
        }

        $limited = $queue->listPending(limit: 2);
        self::assertCount(2, $limited);

        $all = $queue->listPending();
        self::assertCount(5, $all);
    }

    public function testListPendingWithZeroLimitReturnsEmpty(): void
    {
        $queue = $this->queue();
        $queue->enqueue(new PersistentTask(
            id: new TaskId('task-zero'),
            operation: 'App\Job\Zero',
        ));

        self::assertSame([], $queue->listPending(limit: 0));
    }

    public function testListPendingSkipsCorruptEntries(): void
    {
        $queue = $this->queue();
        $queue->enqueue(new PersistentTask(
            id: new TaskId('task-valid'),
            operation: 'App\Job\Valid',
        ));

        // Inject a corrupt serialized value directly into the underlying client
        $client = new InMemoryRedisClient();
        $client->hSet(RedisTaskQueue::DEFINITIONS_KEY, 'task-valid', $this->serializeTask('task-valid', 'App\Job\Valid'));
        $client->hSet(RedisTaskQueue::DEFINITIONS_KEY, 'task-corrupt', '{not valid json');
        $client->lPush(RedisTaskQueue::QUEUE_KEY, 'task-valid');
        $client->lPush(RedisTaskQueue::QUEUE_KEY, 'task-corrupt');

        $corruptQueue = new RedisTaskQueue($client);
        $pending = $corruptQueue->listPending();

        self::assertCount(1, $pending);
        self::assertSame('task-valid', $pending[0]->id()->value);
    }

    public function testEnqueueWithSameIdempotencyKeyIsDeduplicated(): void
    {
        $queue = $this->queue();
        $task = new PersistentTask(
            id: new TaskId('task-1'),
            operation: 'App\Job\SendEmail',
            payload: ['to' => 'user@example.com'],
        );

        $queue->enqueue($task, idempotencyKey: 'idem-1');
        $queue->enqueue($task, idempotencyKey: 'idem-1');

        self::assertSame(1, $queue->pendingCount());
    }

    public function testEnqueueWithDifferentIdempotencyKeysQueuesBoth(): void
    {
        $queue = $this->queue();

        $queue->enqueue(new PersistentTask(
            id: new TaskId('task-a'),
            operation: 'App\Job\A',
        ), idempotencyKey: 'idem-a');

        $queue->enqueue(new PersistentTask(
            id: new TaskId('task-b'),
            operation: 'App\Job\B',
        ), idempotencyKey: 'idem-b');

        self::assertSame(2, $queue->pendingCount());
    }

    public function testEnqueueWithoutIdempotencyKeyIsAtLeastOnce(): void
    {
        $queue = $this->queue();

        // No idempotency key: enqueueing two different tasks creates two entries.
        $queue->enqueue(new PersistentTask(
            id: new TaskId('task-1'),
            operation: 'App\Job\SendEmail',
        ));
        $queue->enqueue(new PersistentTask(
            id: new TaskId('task-2'),
            operation: 'App\Job\SendEmail',
        ));

        self::assertSame(2, $queue->pendingCount());
    }

    public function testEnqueueRejectsEmptyIdempotencyKey(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $queue = $this->queue();
        $queue->enqueue(
            new PersistentTask(id: new TaskId('task-1'), operation: 'App\Job\SendEmail'),
            idempotencyKey: '',
        );
    }

    public function testEnqueueRejectsOverlongIdempotencyKey(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $queue = $this->queue();
        $queue->enqueue(
            new PersistentTask(id: new TaskId('task-1'), operation: 'App\Job\SendEmail'),
            idempotencyKey: str_repeat('a', 256),
        );
    }

    public function testEnqueueRejectsInvalidIdempotencyKeyChars(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $queue = $this->queue();
        $queue->enqueue(
            new PersistentTask(id: new TaskId('task-1'), operation: 'App\Job\SendEmail'),
            idempotencyKey: 'bad key!',
        );
    }

    public function testDequeueCleansUpDedupMapping(): void
    {
        $queue = $this->queue();
        $task = new PersistentTask(
            id: new TaskId('task-1'),
            operation: 'App\Job\SendEmail',
        );

        $queue->enqueue($task, idempotencyKey: 'idem-1');
        self::assertSame(1, $queue->pendingCount());

        $dequeued = $queue->dequeue();
        self::assertNotNull($dequeued);
        self::assertSame(0, $queue->pendingCount());

        // Same idempotency key can be reused after dequeue.
        $queue->enqueue(
            new PersistentTask(id: new TaskId('task-2'), operation: 'App\Job\SendEmail'),
            idempotencyKey: 'idem-1',
        );
        self::assertSame(1, $queue->pendingCount());
    }

    public function testAcknowledgeCleansUpDedupMapping(): void
    {
        $queue = $this->queue();
        $task = new PersistentTask(
            id: new TaskId('task-1'),
            operation: 'App\Job\SendEmail',
        );

        $queue->enqueue($task, idempotencyKey: 'idem-1');
        self::assertSame(1, $queue->pendingCount());

        $queue->acknowledge(new TaskId('task-1'));
        self::assertSame(0, $queue->pendingCount());

        // Same idempotency key can be reused after acknowledge.
        $queue->enqueue(
            new PersistentTask(id: new TaskId('task-2'), operation: 'App\Job\SendEmail'),
            idempotencyKey: 'idem-1',
        );
        self::assertSame(1, $queue->pendingCount());
    }

    public function testRedisClientExceptionIsWrapped(): void
    {
        $client = new class () implements RedisClientInterface {
            public function hSet(string $key, string $field, string $value): int
            {
                throw new RuntimeException('redis down');
            }

            public function hSetNx(string $key, string $field, string $value): int
            {
                throw new RuntimeException('redis down');
            }

            public function hGet(string $key, string $field): ?string
            {
                throw new RuntimeException('redis down');
            }

            public function hDel(string $key, string $field): int
            {
                throw new RuntimeException('redis down');
            }

            public function hGetAll(string $key): array
            {
                throw new RuntimeException('redis down');
            }

            public function hLen(string $key): int
            {
                throw new RuntimeException('redis down');
            }

            public function lPush(string $key, string $value): int
            {
                throw new RuntimeException('redis down');
            }

            public function rPop(string $key): ?string
            {
                throw new RuntimeException('redis down');
            }

            public function lRem(string $key, int $count, string $value): int
            {
                throw new RuntimeException('redis down');
            }

            public function hScan(string $key, string &$cursor, int $count): array
            {
                throw new RuntimeException('redis down');
            }

            public function pipeline(array $commands): array
            {
                throw new RuntimeException('redis down');
            }
        };

        $queue = new RedisTaskQueue($client);
        $task = new PersistentTask(
            id: new TaskId('task-fail'),
            operation: 'App\Job\Fails',
        );

        $this->expectException(RedisTaskQueueException::class);
        $queue->enqueue($task);
    }

    private function serializeTask(string $id, string $operation): string
    {
        $result = json_encode([
            'id' => $id,
            'operation' => $operation,
            'payload' => [],
            'deadline' => null,
            'status' => 'created',
        ]);
        assert($result !== false);

        return $result;
    }

    private function queue(): RedisTaskQueue
    {
        return new RedisTaskQueue(new InMemoryRedisClient());
    }
}

final class InMemoryRedisClient implements RedisClientInterface
{
    /** @var array<string, array<string, string>> hash => field => value */
    private array $hashes = [];

    /** @var array<string, list<string>> list key => values (head first) */
    private array $lists = [];

    public function hSet(string $key, string $field, string $value): int
    {
        $this->hashes[$key][$field] = $value;

        return array_key_exists($key, $this->hashes) && array_key_exists($field, $this->hashes[$key]) ? 1 : 0;
    }

    public function hSetNx(string $key, string $field, string $value): int
    {
        if (!array_key_exists($key, $this->hashes)) {
            $this->hashes[$key] = [];
        }

        if (array_key_exists($field, $this->hashes[$key])) {
            return 0;
        }

        $this->hashes[$key][$field] = $value;

        return 1;
    }

    public function hGet(string $key, string $field): ?string
    {
        return array_key_exists($key, $this->hashes) && array_key_exists($field, $this->hashes[$key])
            ? $this->hashes[$key][$field]
            : null;
    }

    public function hDel(string $key, string $field): int
    {
        if (!array_key_exists($key, $this->hashes) || !array_key_exists($field, $this->hashes[$key])) {
            return 0;
        }

        unset($this->hashes[$key][$field]);

        return 1;
    }

    public function hGetAll(string $key): array
    {
        return array_key_exists($key, $this->hashes) ? $this->hashes[$key] : [];
    }

    public function hLen(string $key): int
    {
        return array_key_exists($key, $this->hashes) ? count($this->hashes[$key]) : 0;
    }

    public function lPush(string $key, string $value): int
    {
        if (!array_key_exists($key, $this->lists)) {
            $this->lists[$key] = [];
        }

        array_unshift($this->lists[$key], $value);

        return count($this->lists[$key]);
    }

    public function rPop(string $key): ?string
    {
        if (!array_key_exists($key, $this->lists) || $this->lists[$key] === []) {
            return null;
        }

        return array_pop($this->lists[$key]);
    }

    public function lRem(string $key, int $count, string $value): int
    {
        if (!array_key_exists($key, $this->lists)) {
            return 0;
        }

        $removed = 0;
        $filtered = [];
        foreach ($this->lists[$key] as $v) {
            if ($v === $value && ($count === 0 || $removed < $count)) {
                $removed++;
                continue;
            }

            $filtered[] = $v;
        }

        $this->lists[$key] = $filtered;

        return $removed;
    }

    public function hScan(string $key, string &$cursor, int $count): array
    {
        $hash = $this->hashes[$key] ?? [];
        $fields = array_keys($hash);
        $start = (int) $cursor;
        $end = min($start + $count, count($fields));
        $entries = [];
        for ($i = $start; $i < $end; $i++) {
            $field = $fields[$i];
            $entries[$field] = $hash[$field];
        }

        $cursor = $end >= count($fields) ? '0' : (string) $end;

        return [$cursor, $entries];
    }

    /**
     * @param list<array<mixed, mixed>> $commands
     * @return list<mixed>
     */
    public function pipeline(array $commands): array
    {
        $results = [];
        foreach ($commands as $cmd) {
            $method = $cmd[0];
            $args = $cmd[1];
            $results[] = $this->$method(...$args);
        }

        return $results;
    }
}
