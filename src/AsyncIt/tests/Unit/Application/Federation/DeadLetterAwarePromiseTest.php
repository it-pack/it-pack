<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Federation;

use ItPack\AsyncIt\Application\Federation\DeadLetterAwarePromise;
use ItPack\AsyncIt\Application\Federation\DeadLetterQueue;
use ItPack\AsyncIt\Contract\PromiseInterface;
use ItPack\AsyncIt\Domain\RemoteTask;
use ItPack\AsyncIt\Domain\TaskId;
use ItPack\RunIt\Domain\Shared\Duration;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \ItPack\AsyncIt\Application\Federation\DeadLetterAwarePromise
 */
final class DeadLetterAwarePromiseTest extends TestCase
{
    public function testImplementsPromiseInterface(): void
    {
        $queue = new DeadLetterQueue();
        $task = new RemoteTask(id: new TaskId('dlp-1'), operation: 'App\Job\Job');
        $promise = new DeadLetterAwarePromise(
            new ResolvedPromise('result'),
            $task,
            $queue,
        );

        self::assertInstanceOf(PromiseInterface::class, $promise);
    }

    public function testWaitResolvedDoesNotDeadLetter(): void
    {
        $queue = new DeadLetterQueue();
        $task = new RemoteTask(id: new TaskId('dlp-resolved'), operation: 'App\Job\Job');
        $promise = new DeadLetterAwarePromise(
            new ResolvedPromise('result'),
            $task,
            $queue,
        );

        self::assertTrue($promise->isDone());
        self::assertTrue($promise->wait());
        self::assertSame('result', $promise->get());
        self::assertSame(0, $queue->count());
    }

    public function testWaitTimeoutDeadLettersOnce(): void
    {
        $queue = new DeadLetterQueue();
        $task = new RemoteTask(id: new TaskId('dlp-timeout'), operation: 'App\Job\Job');
        $promise = new DeadLetterAwarePromise(
            new PendingPromise(),
            $task,
            $queue,
            'local timeout',
        );

        self::assertFalse($promise->isDone());
        self::assertFalse($promise->wait(Duration::ofSeconds(1)));
        self::assertSame(1, $queue->count());

        $entries = $queue->list();
        self::assertSame($task, $entries[0]['task']);
        self::assertSame('local timeout', $entries[0]['reason']);

        // Second wait should not enqueue again.
        self::assertFalse($promise->wait(Duration::ofSeconds(1)));
        self::assertSame(1, $queue->count());
    }

    public function testGetThrowsOnPending(): void
    {
        $queue = new DeadLetterQueue();
        $task = new RemoteTask(id: new TaskId('dlp-get'), operation: 'App\Job\Job');
        $promise = new DeadLetterAwarePromise(
            new PendingPromise(),
            $task,
            $queue,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Promise never resolved.');
        $promise->get();
    }

    public function testCustomTimeoutReason(): void
    {
        $queue = new DeadLetterQueue();
        $task = new RemoteTask(id: new TaskId('dlp-reason'), operation: 'App\Job\Job');
        $promise = new DeadLetterAwarePromise(
            new PendingPromise(),
            $task,
            $queue,
            'remote unreachable',
        );

        self::assertFalse($promise->wait(Duration::ofSeconds(1)));

        $entries = $queue->list();
        self::assertSame('remote unreachable', $entries[0]['reason']);
    }
}
