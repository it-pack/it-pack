<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use ItPack\AsyncIt\Contract\Port\AwaitPoint;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Domain\SchedulerEvent;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Domain\SchedulerEvent
 */
final class SchedulerEventTest extends TestCase
{
    public function testSpawnedEventCarriesTaskAndCreatedStatus(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $event = SchedulerEvent::spawned($task);

        self::assertSame($task, $event->task);
        self::assertSame(TaskStatus::Created, $event->status);
        self::assertNull($event->awaitPoint);
    }

    public function testResumedEventCarriesOptionalAwaitPoint(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $event = SchedulerEvent::resumed($task, AwaitPoint::Delay);

        self::assertSame($task, $event->task);
        self::assertSame(TaskStatus::Running, $event->status);
        self::assertSame(AwaitPoint::Delay, $event->awaitPoint);
    }

    public function testSuspendedEventCarriesAwaitPoint(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $event = SchedulerEvent::suspended($task, AwaitPoint::Channel);

        self::assertSame(AwaitPoint::Channel, $event->awaitPoint);
        self::assertSame(TaskStatus::Running, $event->status);
    }

    public function testCompletedEventCarriesTerminalStatus(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $event = SchedulerEvent::completed($task, TaskStatus::Succeeded);

        self::assertSame(TaskStatus::Succeeded, $event->status);
    }

    public function testCancellationObservedEvent(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $event = SchedulerEvent::cancellationObserved($task);

        self::assertSame(TaskStatus::Cancelled, $event->status);
    }
}
