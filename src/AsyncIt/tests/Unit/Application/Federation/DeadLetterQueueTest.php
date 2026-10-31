<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Federation;

use ItPack\AsyncIt\Application\Federation\DeadLetterQueue;
use ItPack\AsyncIt\Contract\Port\DeadLetterQueueInterface;
use ItPack\AsyncIt\Domain\RemoteTask;
use ItPack\AsyncIt\Domain\TaskId;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Application\Federation\DeadLetterQueue
 * @covers \ItPack\AsyncIt\Contract\Port\DeadLetterQueueInterface
 */
final class DeadLetterQueueTest extends TestCase
{
    public function testImplementsDeadLetterQueueInterface(): void
    {
        $queue = new DeadLetterQueue();
        self::assertInstanceOf(DeadLetterQueueInterface::class, $queue);
    }

    public function testEnqueueAndCount(): void
    {
        $queue = new DeadLetterQueue();
        self::assertSame(0, $queue->count());

        $task = new RemoteTask(id: new TaskId('dlq-1'), operation: 'App\Job\DeadJob');
        $queue->enqueue($task, 'local timeout');

        self::assertSame(1, $queue->count());

        $task2 = new RemoteTask(id: new TaskId('dlq-2'), operation: 'App\Job\DeadJob2');
        $queue->enqueue($task2, 'remote unreachable');

        self::assertSame(2, $queue->count());
    }

    public function testListReturnsEntries(): void
    {
        $queue = new DeadLetterQueue();

        $task = new RemoteTask(id: new TaskId('dlq-list'), operation: 'App\Job\ListJob');
        $queue->enqueue($task, 'test reason');

        $entries = $queue->list();
        self::assertCount(1, $entries);
        self::assertArrayHasKey('task', $entries[0]);
        self::assertArrayHasKey('reason', $entries[0]);
        self::assertSame($task, $entries[0]['task']);
        self::assertSame('test reason', $entries[0]['reason']);
    }

    public function testClearRemovesAllEntries(): void
    {
        $queue = new DeadLetterQueue();

        $queue->enqueue(new RemoteTask(id: new TaskId('dlq-clear-1'), operation: 'App\Job\Job1'), 'reason 1');
        $queue->enqueue(new RemoteTask(id: new TaskId('dlq-clear-2'), operation: 'App\Job\Job2'), 'reason 2');

        self::assertSame(2, $queue->count());

        $queue->clear();

        self::assertSame(0, $queue->count());
        self::assertSame([], $queue->list());
    }
}
