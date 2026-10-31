<?php

declare(strict_types=1);

namespace Tests\Unit\SDK\Support;

use ItPack\AsyncIt\Contract\Port\AwaitPoint;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Domain\SchedulerEvent;
use ItPack\AsyncIt\Domain\TaskId;
use ItPack\AsyncIt\SDK\Support\ArrayTelemetryRecorder;
use ItPack\AsyncIt\SDK\Support\LazyTelemetryBridge;
use ItPack\AsyncIt\SDK\Support\TelemetryBridgeEventListener;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\SDK\Support\TelemetryBridgeEventListener
 */
final class TelemetryBridgeEventListenerTest extends TestCase
{
    public function testSpawnedEventRecordsEventWithTaskIdAndParentId(): void
    {
        $child = $this->createMock(TaskInterface::class);
        $child->method('id')->willReturn(new TaskId('child'));

        $parent = $this->createMock(TaskInterface::class);
        $parent->method('id')->willReturn(new TaskId('parent'));

        $bridge = new LazyTelemetryBridge(static fn (): ArrayTelemetryRecorder => new ArrayTelemetryRecorder());
        $listener = new TelemetryBridgeEventListener($bridge);

        $listener->onEvent(SchedulerEvent::spawned($child, $parent->id()));

        $events = $bridge->events();

        self::assertCount(1, $events);
        self::assertSame('task.spawned', $events[0]['event']);
        self::assertSame('child', $events[0]['context']['taskId']);
        self::assertSame('parent', $events[0]['context']['parentId']);
    }

    public function testSpawnedEventWithoutParentIdOmitsParentId(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->method('id')->willReturn(new TaskId('root'));

        $bridge = new LazyTelemetryBridge(static fn (): ArrayTelemetryRecorder => new ArrayTelemetryRecorder());
        $listener = new TelemetryBridgeEventListener($bridge);

        $listener->onEvent(SchedulerEvent::spawned($task));

        $events = $bridge->events();

        self::assertSame('root', $events[0]['context']['taskId']);
        self::assertArrayNotHasKey('parentId', $events[0]['context']);
    }

    public function testSuspendedEventRecordsWithAwaitPoint(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->method('id')->willReturn(new TaskId('t1'));

        $bridge = new LazyTelemetryBridge(static fn (): ArrayTelemetryRecorder => new ArrayTelemetryRecorder());
        $listener = new TelemetryBridgeEventListener($bridge);

        $listener->onEvent(SchedulerEvent::suspended($task, AwaitPoint::Join));

        $events = $bridge->events();

        self::assertSame('task.suspended', $events[0]['event']);
        self::assertSame('Join', $events[0]['context']['awaitPoint']);
    }

    public function testResumedEventRecordsEventName(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->method('id')->willReturn(new TaskId('t1'));

        $bridge = new LazyTelemetryBridge(static fn (): ArrayTelemetryRecorder => new ArrayTelemetryRecorder());
        $listener = new TelemetryBridgeEventListener($bridge);

        $listener->onEvent(SchedulerEvent::resumed($task));

        $events = $bridge->events();

        self::assertSame('task.resumed', $events[0]['event']);
    }

    public function testCompletedEventRecordsStatus(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->method('id')->willReturn(new TaskId('t1'));

        $bridge = new LazyTelemetryBridge(static fn (): ArrayTelemetryRecorder => new ArrayTelemetryRecorder());
        $listener = new TelemetryBridgeEventListener($bridge);

        $listener->onEvent(SchedulerEvent::completed($task, TaskStatus::Succeeded));

        $events = $bridge->events();

        self::assertSame('task.completed', $events[0]['event']);
        self::assertSame('succeeded', $events[0]['context']['status']);
    }

    public function testStaticContextIsMergedIntoEveryEvent(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->method('id')->willReturn(new TaskId('t1'));

        $bridge = new LazyTelemetryBridge(static fn (): ArrayTelemetryRecorder => new ArrayTelemetryRecorder());
        $listener = new TelemetryBridgeEventListener($bridge, ['executionId' => 'trace-exec']);

        $listener->onEvent(SchedulerEvent::spawned($task));

        $events = $bridge->events();

        self::assertSame('trace-exec', $events[0]['context']['executionId']);
    }

    public function testCompletedEventWithNonTerminalStatusUsesStatusName(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->method('id')->willReturn(new TaskId('t1'));

        $bridge = new LazyTelemetryBridge(static fn (): ArrayTelemetryRecorder => new ArrayTelemetryRecorder());
        $listener = new TelemetryBridgeEventListener($bridge);

        $listener->onEvent(SchedulerEvent::completed($task, TaskStatus::Running));

        $events = $bridge->events();

        self::assertSame('Running', $events[0]['context']['status']);
    }
}
