<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Scheduler;

use Fiber;
use InvalidArgumentException;
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
use ItPack\AsyncIt\Contract\Port\JitterStrategy;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Domain\CapabilityDescriptor;
use ItPack\AsyncIt\Domain\RetryPolicy;
use ItPack\AsyncIt\Domain\TaskId;
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

        $scheduler->submit($task, static function () use (&$log, $scheduler): void {
            $log[] = 'a';
            $scheduler->awaitDelay(Duration::ofNanoseconds(1));
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

        $task = $scheduler->spawn(static function (): void {
            Fiber::suspend();
        });

        $scheduler->runUntil(0);

        self::assertSame(0, $clock->monotonicNanoseconds());
        self::assertSame(TaskStatus::Cancelled, $task->status());
    }

    public function testRunUntilCompletesTasksBeforeDeadline(): void
    {
        $clock = new DeterministicMonotonicClock();
        $scheduler = $this->schedulerWithClock($clock);

        $scheduler->spawn(static fn (): string => 'done');

        $scheduler->runUntil(PHP_INT_MAX);

        self::assertSame(0, $clock->monotonicNanoseconds());
    }

    public function testTaskResumesOnlyWhenDependencyCompletes(): void
    {
        $scheduler = $this->scheduler();
        $childResult = '';

        $child = $scheduler->spawn(static function () use ($scheduler): string {
            $scheduler->awaitDelay(Duration::ofMilliseconds(10));

            return 'child-done';
        });

        $scheduler->spawn(static function () use ($scheduler, $child, &$childResult): void {
            $childResult = $scheduler->await($child);
        });

        $scheduler->run();

        self::assertSame('child-done', $childResult, 'Parent must receive actual child result, not null');
    }

    public function testTimerDoesNotPrematurelyResumeTask(): void
    {
        $clock = new SystemMonotonicClock();
        $scheduler = $this->schedulerWithClock($clock);

        $resumed = false;

        $task = $scheduler->spawn(static function () use ($scheduler, &$resumed): void {
            $scheduler->awaitDelay(Duration::ofMilliseconds(100));
            $resumed = true;
        });

        $deadline = $clock->monotonicNanoseconds() + Duration::ofMilliseconds(5)->nanoseconds();
        $scheduler->runUntil($deadline);

        self::assertFalse($resumed, 'Task must not resume before timer expires');
        self::assertSame(TaskStatus::Cancelled, $task->status());
    }

    public function testTimerClearedOnTaskCompletion(): void
    {
        $clock = new DeterministicMonotonicClock();
        $scheduler = $this->schedulerWithClock($clock);

        $resumed = false;

        $task = $scheduler->spawn(static function () use ($scheduler, &$resumed): void {
            $scheduler->awaitDelay(Duration::ofMilliseconds(1));
            $resumed = true;
        });

        $task->cancellationScope()->cancel(CancellationReason::ParentCancelled);

        $scheduler->runUntil(PHP_INT_MAX);

        self::assertSame(TaskStatus::Cancelled, $task->status());
        self::assertFalse($resumed, 'Cancelled task must not be resumed by its abandoned timer');
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

    public function testDrainCancelledRecordsCompletesSuspendedCancelledTask(): void
    {
        $scheduler = $this->scheduler();

        $suspended = $scheduler->spawn(static function () use ($scheduler): void {
            $scheduler->awaitDelay(Duration::ofMilliseconds(500));
        });

        $scheduler->spawn(static function () use ($suspended): void {
            $suspended->cancellationScope()->cancel(CancellationReason::ParentCancelled);
        });

        $scheduler->run();

        self::assertSame(TaskStatus::Cancelled, $suspended->status());
    }

    public function testHealthReturnsSnapshotWithZeroHandlesWhenIdle(): void
    {
        $scheduler = $this->scheduler();

        $health = $scheduler->health();

        self::assertTrue($health->isHealthy());
        self::assertSame(0, $health->pendingTaskCount());
        self::assertSame(0, $health->activeHandleCount());
    }

    public function testHealthReportsPendingTasksAfterSpawn(): void
    {
        $scheduler = $this->scheduler();

        $scheduler->spawn(static function (): void {
            Fiber::suspend();
        });

        $health = $scheduler->health();

        self::assertSame(1, $health->pendingTaskCount());
    }

    public function testCapabilitiesIsNullByDefault(): void
    {
        $scheduler = $this->scheduler();

        self::assertNull($scheduler->capabilities());
    }

    public function testSwapCapabilitiesSetsDescriptor(): void
    {
        $scheduler = $this->scheduler();
        $descriptor = new CapabilityDescriptor([
            CapabilityKind::FiberExecution->value => CapabilityStatus::Supported,
        ]);

        $scheduler->swapCapabilities($descriptor);

        self::assertSame($descriptor, $scheduler->capabilities());
    }

    public function testTaskRecordsIsEmptyInitially(): void
    {
        $scheduler = $this->scheduler();

        self::assertSame([], $scheduler->taskRecords());
    }

    public function testDispatchReturnsOperationResult(): void
    {
        $scheduler = $this->scheduler();
        $task = $this->task();

        $result = $scheduler->dispatch($task, static fn (): string => 'dispatched');

        self::assertSame('dispatched', $result);
        self::assertSame(TaskStatus::Succeeded, $task->status());
    }

    public function testSpawnWithExplicitTaskIdUsesProvidedId(): void
    {
        $scheduler = $this->scheduler();
        $explicitId = new TaskId('explicit-id');

        $task = $scheduler->spawn(static fn (): string => 'result', $explicitId);

        self::assertSame('explicit-id', $task->id()->value);
    }

    public function testAwaitReturnsResultImmediatelyWhenAlreadyAvailable(): void
    {
        $scheduler = $this->scheduler();
        $result = '';

        $child = $scheduler->spawn(static fn (): string => 'child');
        $scheduler->spawn(static function () use ($scheduler, $child, &$result): void {
            $scheduler->await($child);
            $result = $scheduler->await($child);
        });

        $scheduler->run();

        self::assertSame('child', $result);
    }

    public function testAwaitThrowsWhenCurrentTaskNotTracked(): void
    {
        $scheduler = $this->scheduler();
        $untrackedTask = $this->task();

        $scheduler->spawn(static function () use ($scheduler, $untrackedTask): void {
            $scheduler->await($untrackedTask);
        });

        $thrown = null;
        try {
            $scheduler->run();
        } catch (LogicException $logicException) {
            $thrown = $logicException;
        }

        self::assertNotNull($thrown);
        self::assertSame('Cannot await a task this scheduler does not own.', $thrown->getMessage());
    }

    public function testAwaitThrowsWhenNoCurrentTask(): void
    {
        $scheduler = $this->scheduler();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('await() must be called within a running task');

        $scheduler->await($this->task());
    }

    public function testAwaitDelayThrowsWhenNoCurrentTask(): void
    {
        $scheduler = $this->scheduler();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('awaitDelay() must be called within a running task');

        $scheduler->awaitDelay(Duration::ofNanoseconds(1));
    }

    public function testAwaitReadableReturnsTrueWhenStreamBecomesReadable(): void
    {
        $scheduler = $this->scheduler();
        $log = [];
        $pair = stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);
        self::assertNotFalse($pair);

        $readHandle = $scheduler->registerStream($pair[0]);
        $writeStream = $pair[1];

        $scheduler->spawn(static function () use (&$log, $scheduler, $readHandle): void {
            $log[] = 'before';
            $ready = $scheduler->awaitReadable($readHandle, Duration::ofSeconds(5));
            $log[] = 'after';
            $log[] = $ready ? 'ready' : 'timeout';
        });

        $scheduler->spawn(static function () use ($writeStream): void {
            fwrite($writeStream, 'x');
        });

        $scheduler->run();

        self::assertSame(['before', 'after', 'ready'], $log);

        fclose($pair[0]);
        fclose($pair[1]);
    }

    public function testAwaitReadableReturnsFalseOnTimeout(): void
    {
        $scheduler = $this->scheduler();
        $log = [];
        $pair = stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);
        self::assertNotFalse($pair);

        $readHandle = $scheduler->registerStream($pair[0]);

        $scheduler->spawn(static function () use (&$log, $scheduler, $readHandle): void {
            $log[] = $scheduler->awaitReadable($readHandle, Duration::ofNanoseconds(1)) ? 'ready' : 'timeout';
        });

        $scheduler->run();

        self::assertSame(['timeout'], $log);

        fclose($pair[0]);
        fclose($pair[1]);
    }

    public function testAwaitReadableThrowsWhenNoCurrentTask(): void
    {
        $scheduler = $this->scheduler();
        $pair = stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);
        self::assertNotFalse($pair);

        $handle = $scheduler->registerStream($pair[0]);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Readable() must be called within a running task.');

        $scheduler->awaitReadable($handle);

        fclose($pair[0]);
        fclose($pair[1]);
    }

    public function testAwaitWritableReturnsTrueWhenStreamIsWritable(): void
    {
        $scheduler = $this->scheduler();
        $log = [];
        $pair = stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);
        self::assertNotFalse($pair);

        $writeHandle = $scheduler->registerStream($pair[0]);

        $scheduler->spawn(static function () use (&$log, $scheduler, $writeHandle): void {
            $log[] = 'before';
            $ready = $scheduler->awaitWritable($writeHandle, Duration::ofSeconds(5));
            $log[] = 'after';
            $log[] = $ready ? 'ready' : 'timeout';
        });

        $scheduler->run();

        self::assertSame(['before', 'after', 'ready'], $log);

        fclose($pair[0]);
        fclose($pair[1]);
    }

    public function testAwaitWritableReturnsFalseOnTimeout(): void
    {
        $scheduler = $this->scheduler();
        $log = [];
        $pair = stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);
        self::assertNotFalse($pair);

        $writeHandle = $scheduler->registerStream($pair[0]);

        $scheduler->spawn(static function () use (&$log, $scheduler, $writeHandle): void {
            $log[] = $scheduler->awaitWritable($writeHandle, Duration::ofNanoseconds(1)) ? 'ready' : 'timeout';
        });

        $scheduler->run();

        self::assertSame(['timeout'], $log);

        fclose($pair[0]);
        fclose($pair[1]);
    }

    public function testRetryReturnsResultOnFirstSuccess(): void
    {
        $scheduler = $this->scheduler();
        $policy = new RetryPolicy(
            maxAttempts: 3,
            baseDelay: Duration::ofNanoseconds(1),
            jitter: JitterStrategy::None,
        );
        $calls = 0;

        $result = $scheduler->retry(static function () use (&$calls): string {
            $calls++;

            return 'success';
        }, $policy);

        self::assertSame('success', $result);
        self::assertSame(1, $calls);
    }

    public function testRetryRetriesOnFailureThenSucceeds(): void
    {
        $scheduler = $this->scheduler();
        $policy = new RetryPolicy(
            maxAttempts: 3,
            baseDelay: Duration::ofNanoseconds(1),
            jitter: JitterStrategy::None,
        );
        $calls = 0;
        $result = null;

        $task = $scheduler->spawn(static function () use ($scheduler, $policy, &$calls, &$result): void {
            $result = $scheduler->retry(static function () use (&$calls): string {
                $calls++;

                if ($calls < 3) {
                    throw new RuntimeException('transient');
                }

                return 'recovered';
            }, $policy);
        });

        $scheduler->run();

        self::assertSame('recovered', $result);
        self::assertSame(3, $calls);
        self::assertSame(TaskStatus::Succeeded, $task->status());
    }

    public function testRetryDoesNotRetryNonRetryableException(): void
    {
        $scheduler = $this->scheduler();
        $policy = new RetryPolicy(
            maxAttempts: 3,
            baseDelay: Duration::ofNanoseconds(1),
            jitter: JitterStrategy::None,
            retryable: ['RuntimeException'],
        );

        $scheduler->spawn(static function () use ($scheduler, $policy): void {
            $scheduler->retry(static fn (): never => throw new InvalidArgumentException('non-retryable'), $policy);
        });

        try {
            $scheduler->run();
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame('non-retryable', $invalidArgumentException->getMessage());
            return;
        }

        self::fail('Expected InvalidArgumentException was not thrown.');
    }

    public function testRetryExhaustsMaxAttemptsAndRethrows(): void
    {
        $scheduler = $this->scheduler();
        $policy = new RetryPolicy(
            maxAttempts: 2,
            baseDelay: Duration::ofNanoseconds(1),
            jitter: JitterStrategy::None,
        );
        $calls = 0;
        $task = $scheduler->spawn(static function () use ($scheduler, $policy, &$calls): void {
            $scheduler->retry(static function () use (&$calls): never {
                $calls++;
                throw new RuntimeException('always fails');
            }, $policy);
        });

        try {
            $scheduler->run();
        } catch (RuntimeException $runtimeException) {
            self::assertSame('always fails', $runtimeException->getMessage());
            self::assertSame(2, $calls);
            self::assertSame(TaskStatus::Failed, $task->status());
            return;
        }

        self::fail('Expected RuntimeException was not thrown.');
    }

    public function testResultTypedReturnsTypedOutcome(): void
    {
        $scheduler = $this->scheduler();
        $task = $this->task();

        $scheduler->submit($task, static fn (): string => 'typed result');

        $scheduler->run();

        $result = $scheduler->resultTyped($task);

        self::assertTrue($result->isSuccess());
        self::assertFalse($result->isFailure());
        self::assertSame('typed result', $result->value());
        self::assertNull($result->error());
        self::assertSame(TaskStatus::Succeeded, $result->status());
    }

    public function testResultTypedThrowsForNonTerminalTask(): void
    {
        $scheduler = $this->scheduler();
        $task = $this->task();

        $this->expectException(LogicException::class);
        $scheduler->resultTyped($task);
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
