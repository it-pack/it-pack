<?php

declare(strict_types=1);

namespace Tests\Functional\Observability;

use Error;
use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\Port\AwaitPoint;
use ItPack\AsyncIt\Contract\Port\SchedulerEventType;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskIdInterface;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Domain\CancellationScope;
use ItPack\AsyncIt\Domain\SchedulerEvent;
use ItPack\AsyncIt\Domain\TaskId;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * @covers \ItPack\AsyncIt\Domain\SchedulerEvent
 * @covers \ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler
 */
final class SchedulerEventTest extends TestCase
{
    public function testSchedulerEventFactories(): void
    {
        $task = $this->makeTaskStub();

        $spawned = SchedulerEvent::spawned($task);
        self::assertSame(SchedulerEventType::Spawned, $spawned->type);
        self::assertSame(TaskStatus::Created, $spawned->status);

        $resumed = SchedulerEvent::resumed($task);
        self::assertSame(SchedulerEventType::Resumed, $resumed->type);
        self::assertSame(TaskStatus::Running, $resumed->status);

        $suspended = SchedulerEvent::suspended($task, AwaitPoint::Delay);
        self::assertSame(SchedulerEventType::Suspended, $suspended->type);

        $completed = SchedulerEvent::completed($task, TaskStatus::Succeeded);
        self::assertSame(SchedulerEventType::Completed, $completed->type);

        $cancelled = SchedulerEvent::cancellationObserved($task);
        self::assertSame(SchedulerEventType::Completed, $cancelled->type);
        self::assertSame(TaskStatus::Cancelled, $cancelled->status);
    }

    public function testSchedulerEventReadonly(): void
    {
        self::assertTrue(
            new ReflectionProperty(SchedulerEvent::class, 'type')->isReadOnly(),
        );
    }

    private function makeTaskStub(): TaskInterface
    {
        return new class () implements TaskInterface {
            public function id(): TaskIdInterface
            {
                return new TaskId('stub-1');
            }

            public function executionId(): ExecutionIdInterface
            {
                return ExecutionId::fromString('exec-1');
            }

            public function cancellationScope(): CancellationScopeInterface
            {
                return new CancellationScope();
            }

            public function status(): TaskStatus
            {
                return TaskStatus::Created;
            }

            public function transitionTo(TaskStatus $next): void
            {
            }

            public function complete(TaskStatus $outcome): void
            {
            }
        };
    }
}
