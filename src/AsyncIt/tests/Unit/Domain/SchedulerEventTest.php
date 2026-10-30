<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use ItPack\AsyncIt\Contract\Port\AwaitPoint;
use ItPack\AsyncIt\Contract\Port\SchedulerEventType;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Domain\SchedulerEvent;
use ItPack\AsyncIt\Domain\TaskId;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Domain\SchedulerEvent
 */
final class SchedulerEventTest extends TestCase
{
    public function testSpawnedEventCarriesTaskCreatedStatusAndParentId(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $parentId = new TaskId('parent');

        $event = SchedulerEvent::spawned($task, $parentId);

        self::assertSame($task, $event->task);
        self::assertSame(SchedulerEventType::Spawned, $event->type);
        self::assertSame(TaskStatus::Created, $event->status);
        self::assertSame($parentId, $event->parentId);
        self::assertNull($event->awaitPoint);
    }

    public function testSpawnedEventWithoutParentIdHasNullParent(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $event = SchedulerEvent::spawned($task);

        self::assertNull($event->parentId);
    }

    public function testResumedEventCarriesOptionalAwaitPoint(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $event = SchedulerEvent::resumed($task, AwaitPoint::Delay);

        self::assertSame($task, $event->task);
        self::assertSame(SchedulerEventType::Resumed, $event->type);
        self::assertSame(TaskStatus::Running, $event->status);
        self::assertSame(AwaitPoint::Delay, $event->awaitPoint);
    }

    public function testSuspendedEventCarriesAwaitPoint(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $event = SchedulerEvent::suspended($task, AwaitPoint::Channel);

        self::assertSame(SchedulerEventType::Suspended, $event->type);
        self::assertSame(AwaitPoint::Channel, $event->awaitPoint);
        self::assertSame(TaskStatus::Running, $event->status);
    }

    public function testCompletedEventCarriesTerminalStatus(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $event = SchedulerEvent::completed($task, TaskStatus::Succeeded);

        self::assertSame(SchedulerEventType::Completed, $event->type);
        self::assertSame(TaskStatus::Succeeded, $event->status);
    }

    public function testCancellationObservedEvent(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $event = SchedulerEvent::cancellationObserved($task);

        self::assertSame(SchedulerEventType::Completed, $event->type);
        self::assertSame(TaskStatus::Cancelled, $event->status);
    }
}
