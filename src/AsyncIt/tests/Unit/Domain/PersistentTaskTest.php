<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use InvalidArgumentException;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Domain\PersistentTask;
use ItPack\AsyncIt\Domain\TaskId;
use ItPack\RunIt\Domain\Shared\Duration;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Domain\PersistentTask
 */
final class PersistentTaskTest extends TestCase
{
    public function testStoresAndExposesAllFields(): void
    {
        $task = new PersistentTask(
            id: new TaskId('persist-1'),
            operation: 'App\Job\SendEmail',
            payload: ['to' => 'user@example.com', 'template' => 'welcome'],
            deadline: Duration::ofSeconds(300),
            status: TaskStatus::Created,
        );

        self::assertSame('persist-1', $task->id()->value);
        self::assertSame('App\Job\SendEmail', $task->operation());
        self::assertSame(['to' => 'user@example.com', 'template' => 'welcome'], $task->payload());
        $deadline = $task->deadline();
        self::assertNotNull($deadline);
        self::assertTrue(Duration::ofSeconds(300)->equals($deadline));
        self::assertSame(TaskStatus::Created, $task->status());
    }

    public function testDefaultsToEmptyPayloadAndNoDeadline(): void
    {
        $task = new PersistentTask(
            id: new TaskId('persist-2'),
            operation: 'App\Job\ProcessPayment',
        );

        self::assertSame([], $task->payload());
        self::assertNull($task->deadline());
        self::assertSame(TaskStatus::Created, $task->status());
    }

    public function testRejectsEmptyOperation(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PersistentTask(
            id: new TaskId('persist-3'),
            operation: '',
        );
    }

    public function testStoresOperationAsClassNameString(): void
    {
        $task = new PersistentTask(
            id: new TaskId('persist-8'),
            operation: 'App\Job\AnyClassName',
        );

        self::assertSame('App\Job\AnyClassName', $task->operation());
    }

    public function testIsDeadlineExceededReturnsFalseWhenNoDeadline(): void
    {
        $task = new PersistentTask(
            id: new TaskId('persist-5'),
            operation: 'App\Job\NoDeadline',
        );

        self::assertFalse($task->isDeadlineExceeded());
    }

    public function testIsDeadlineExceededReturnsFalseBeforeDeadline(): void
    {
        $farFuture = Duration::ofNanoseconds(hrtime(true) + 86_400_000_000_000);

        $task = new PersistentTask(
            id: new TaskId('persist-6'),
            operation: 'App\Job\FutureDeadline',
            deadline: $farFuture,
        );

        self::assertFalse($task->isDeadlineExceeded());
    }

    public function testIsDeadlineExceededReturnsTrueAfterDeadline(): void
    {
        $past = Duration::ofNanoseconds(max(hrtime(true) - 1, 0));

        $task = new PersistentTask(
            id: new TaskId('persist-7'),
            operation: 'App\Job\ExpiredDeadline',
            deadline: $past,
        );

        self::assertTrue($task->isDeadlineExceeded());
    }
}
