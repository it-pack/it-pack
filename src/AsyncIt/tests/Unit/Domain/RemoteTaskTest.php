<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use InvalidArgumentException;
use ItPack\AsyncIt\Domain\RemoteTask;
use ItPack\AsyncIt\Domain\TaskId;
use ItPack\RunIt\Domain\Shared\Duration;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Domain\RemoteTask
 */
final class RemoteTaskTest extends TestCase
{
    public function testStoresAndExposesAllFields(): void
    {
        $task = new RemoteTask(
            id: new TaskId('remote-1'),
            operation: 'App\Job\SendEmail',
            payload: ['to' => 'user@example.com'],
            deadline: Duration::ofSeconds(300),
            priority: 5,
            correlationId: 'corr-abc',
        );

        self::assertSame('remote-1', $task->id()->value);
        self::assertSame('App\Job\SendEmail', $task->operation());
        self::assertSame(['to' => 'user@example.com'], $task->payload());
        $deadline = $task->deadline();
        self::assertNotNull($deadline);
        self::assertTrue(Duration::ofSeconds(300)->equals($deadline));
        self::assertSame(5, $task->priority());
        self::assertSame('corr-abc', $task->correlationId());
    }

    public function testDefaultsToEmptyPayloadNoDeadlineZeroPriorityAndDerivedCorrelationId(): void
    {
        $task = new RemoteTask(
            id: new TaskId('remote-2'),
            operation: 'App\Job\ProcessPayment',
        );

        self::assertSame([], $task->payload());
        self::assertNull($task->deadline());
        self::assertSame(0, $task->priority());
        self::assertSame('remote-2', $task->correlationId());
    }

    public function testRejectsEmptyOperation(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RemoteTask(
            id: new TaskId('remote-3'),
            operation: '',
        );
    }

    public function testRejectsNegativePriority(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RemoteTask(
            id: new TaskId('remote-4'),
            operation: 'App\Job\AnyJob',
            priority: -1,
        );
    }

    public function testIsImmutable(): void
    {
        $task = new RemoteTask(
            id: new TaskId('remote-5'),
            operation: 'App\Job\ImmutableJob',
        );

        self::assertSame('App\Job\ImmutableJob', $task->operation());
    }

    public function testToStringReturnsTaskId(): void
    {
        $task = new RemoteTask(
            id: new TaskId('remote-6'),
            operation: 'App\Job\ToStringJob',
        );

        self::assertSame('remote-6', (string) $task);
    }

    public function testSerializeRoundTripPreservesAllFields(): void
    {
        $task = new RemoteTask(
            id: new TaskId('remote-serial-1'),
            operation: 'App\Job\SendEmail',
            payload: ['to' => 'user@example.com'],
            deadline: Duration::ofSeconds(300),
            priority: 5,
            correlationId: 'corr-abc',
        );

        /** @var RemoteTask $restored */
        $restored = unserialize(serialize($task));

        self::assertSame($task->id()->value, $restored->id()->value);
        self::assertSame($task->operation(), $restored->operation());
        self::assertSame($task->payload(), $restored->payload());
        $deadline = $restored->deadline();
        self::assertNotNull($deadline);
        self::assertTrue(Duration::ofSeconds(300)->equals($deadline));
        self::assertSame($task->priority(), $restored->priority());
        self::assertSame($task->correlationId(), $restored->correlationId());
    }
}
