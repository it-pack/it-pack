<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Scheduler;

use Fiber;
use ItPack\AsyncIt\Application\Clock\DeterministicMonotonicClock;
use ItPack\AsyncIt\Application\Context\MemoryContextStorage;
use ItPack\AsyncIt\Application\Factory\CancellationScopeFactory;
use ItPack\AsyncIt\Application\Factory\FiberFactory;
use ItPack\AsyncIt\Application\Factory\PollerFactory;
use ItPack\AsyncIt\Application\Factory\ReferenceExecutionScopeFactory;
use ItPack\AsyncIt\Application\Factory\SchedulerLifecycleArtifactFactory;
use ItPack\AsyncIt\Application\Factory\TaskFactory;
use ItPack\AsyncIt\Application\Factory\TaskRecordFactory;
use ItPack\AsyncIt\Application\Publisher\NullArtifactPublisher;
use ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler;
use ItPack\AsyncIt\Contract\Port\CancellationReason;
use ItPack\AsyncIt\Contract\Port\CapabilityKind;
use ItPack\AsyncIt\Contract\Port\CapabilityStatus;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Domain\CapabilityDescriptor;
use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Shared\Duration;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Exercises the reference reactor: cooperative suspend/resume, terminal
 * lifecycle transitions, and observed failure handling (publish + rethrow).
 *
 * @covers \ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler
 */
final class FiberTaskSchedulerTest extends TestCase
{
    public function testRunsTaskToCompletionWithSuspendResume(): void
    {
        $scheduler = $this->scheduler();
        $log = [];
        $task = $this->task();

        $scheduler->submit($task, static function () use (&$log): void {
            $log[] = 'a';
            Fiber::suspend();
            $log[] = 'b';
        });

        $scheduler->run();

        self::assertSame(['a', 'b'], $log);
        self::assertSame(TaskStatus::Succeeded, $task->status());
    }

    public function testThrowingTaskIsMarkedFailedAndRethrowsOriginal(): void
    {
        $scheduler = $this->scheduler();
        $task = $this->task();

        $scheduler->submit($task, static function (): never {
            throw new RuntimeException('boom');
        });

        $thrown = null;
        try {
            $scheduler->run();
        } catch (RuntimeException $runtimeException) {
            $thrown = $runtimeException;
        }

        self::assertNotNull($thrown, 'The original exception must be rethrown.');
        self::assertSame('boom', $thrown->getMessage());
        self::assertSame(TaskStatus::Failed, $task->status());
    }

    public function testSpawnConcurrentTasksAndAwaitEachOther(): void
    {
        $scheduler = $this->scheduler();
        $results = [];

        $scheduler->spawn(static function () use ($scheduler, &$results): void {
            $child = $scheduler->spawn(static fn (): string => 'child result');
            $value = $scheduler->await($child);
            $results[] = $value;
        });

        $scheduler->run();

        self::assertSame(['child result'], $results);
    }

    public function testAwaitBeforeSpawnThrows(): void
    {
        $scheduler = $this->scheduler();
        $scheduler->run();

        $this->expectException(LogicException::class);
        $scheduler->await($this->task());
    }

    public function testCurrentTaskReturnsNullOutsideRun(): void
    {
        $scheduler = $this->scheduler();

        self::assertNull($scheduler->currentTask());
    }

    public function testCurrentTaskReturnsTaskInsideRunningFiber(): void
    {
        $scheduler = $this->scheduler();
        $current = null;

        $scheduler->spawn(static function () use ($scheduler, &$current): void {
            $current = $scheduler->currentTask();
        });

        $scheduler->run();

        self::assertNotNull($current);
    }

    public function testAwaitDelayResumesTaskAfterDeadline(): void
    {
        $scheduler = $this->scheduler();
        $log = [];

        $scheduler->spawn(static function () use ($scheduler, &$log): void {
            $log[] = 'before';
            $scheduler->awaitDelay(Duration::ofMilliseconds(10));
            $log[] = 'after';
        });

        $scheduler->run();

        self::assertSame(['before', 'after'], $log);
    }

    public function testRunUntilForceCancelsOnDeadlineExpiry(): void
    {
        $clock = new DeterministicMonotonicClock();
        $scheduler = $this->schedulerWithClock($clock);

        $scheduler->spawn(static function (): void {
            Fiber::suspend();
        });

        $scheduler->runUntil(1000);

        self::assertSame(0, $clock->monotonicNanoseconds());
    }

    public function testRunUntilCompletesTasksBeforeDeadline(): void
    {
        $clock = new DeterministicMonotonicClock();
        $scheduler = $this->schedulerWithClock($clock);

        $scheduler->spawn(static fn (): string => 'done');

        $scheduler->runUntil(PHP_INT_MAX);

        self::assertSame(0, $clock->monotonicNanoseconds());
    }

    public function testExternallyCancelledTaskBeforeRunningIsCompletedAsCancelled(): void
    {
        $scheduler = $this->scheduler();

        $task = $scheduler->spawn(static function (): void {
            Fiber::suspend();
        });

        $task->cancellationScope()->cancel(CancellationReason::ParentCancelled);

        $scheduler->run();

        self::assertSame(TaskStatus::Cancelled, $task->status());
    }

    public function testExternallyCancelledTaskWhileSuspendedIsCompletedAsCancelled(): void
    {
        $scheduler = $this->scheduler();
        $sibling = $scheduler->spawn(static fn (): string => 'sibling');
        $task = $scheduler->spawn(static function () use ($scheduler, $sibling): void {
            $scheduler->await($sibling);
            Fiber::suspend();
        });

        $task->cancellationScope()->cancel(CancellationReason::ParentCancelled);

        $scheduler->run();

        self::assertSame(TaskStatus::Cancelled, $task->status());
        self::assertSame(TaskStatus::Succeeded, $sibling->status());
    }

    private function scheduler(): FiberTaskScheduler
    {
        return $this->schedulerWithClock(new SystemMonotonicClock());
    }

    private function schedulerWithClock(MonotonicClockInterface $clock): FiberTaskScheduler
    {
        $executionId = ExecutionId::fromString('exec-1');
        $sequenceAllocator = new InMemorySequenceAllocator();

        return new FiberTaskScheduler(
            new FiberFactory(),
            new TaskRecordFactory(),
            new TaskFactory(new RandomIdentifierGenerator()),
            new CancellationScopeFactory(),
            $clock,
            $executionId,
            new PollerFactory()->create(new CapabilityDescriptor([
                CapabilityKind::EdgeTriggeredPolling->value => CapabilityStatus::Emulated,
            ])),
            new MemoryContextStorage(),
            new ReferenceExecutionScopeFactory(),
            new NullArtifactPublisher(),
            new SchedulerLifecycleArtifactFactory($sequenceAllocator, $executionId),
        );
    }

    private function task(): TaskInterface
    {
        return new TaskFactory(new RandomIdentifierGenerator())
            ->create(ExecutionId::fromString('exec-1'), new CancellationScopeFactory()->create())
        ;
    }
}
