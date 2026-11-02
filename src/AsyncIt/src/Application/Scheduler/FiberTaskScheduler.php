<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Scheduler;

use Fiber;
use ItPack\AsyncIt\Application\Factory\CancellationScopeFactoryInterface;
use ItPack\AsyncIt\Application\Factory\ExecutionScopeFactoryInterface;
use ItPack\AsyncIt\Application\Factory\FiberFactoryInterface;
use ItPack\AsyncIt\Application\Factory\TaskFactoryInterface;
use ItPack\AsyncIt\Application\Factory\TaskRecordFactoryInterface;
use ItPack\AsyncIt\Contract\AwaitableIOInterface;
use ItPack\AsyncIt\Contract\CapabilityDescriptorInterface;
use ItPack\AsyncIt\Contract\DeadlineSchedulerInterface;
use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Contract\Port\AwaitPoint;
use ItPack\AsyncIt\Contract\Port\StreamPollHandleInterface;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\ResultTypedSchedulerInterface;
use ItPack\AsyncIt\Contract\RetryPolicyInterface;
use ItPack\AsyncIt\Contract\SchedulerEventInterface;
use ItPack\AsyncIt\Contract\SchedulerEventListenerInterface;
use ItPack\AsyncIt\Contract\SchedulerHealthInterface;
use ItPack\AsyncIt\Contract\SchedulerInterface;
use ItPack\AsyncIt\Contract\TaskIdInterface;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Contract\TaskResultInterface;
use ItPack\AsyncIt\Domain\SchedulerEvent;
use ItPack\AsyncIt\Domain\TaskResult;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Domain\Shared\Duration;
use LogicException;
use RuntimeException;
use Throwable;

/**
 * Reference reactor implementing {@see SchedulerInterface}.
 *
 * Every collaborator is injected (factories, poller, clock, context storage,
 * cancellation scope, scope factory, publisher); the scheduler never
 * constructs them. Timer, IO-await, lifecycle publication, cancellation, and
 * health concerns are delegated to focused services. The reactor attaches a
 * RunIt execution scope before each Fiber resume and detaches it under `finally`
 * on suspend, so an interleaved (suspended) task never holds an ambient context
 * (conformance Assertion A). On terminal transition it releases every registered
 * poll handle (Assertion C), and on throw it publishes through the
 * recursion-guarded publisher and rethrows the exact original {@see \Throwable}.
 *
 * @see https://w3id.org/itpack/asyncit#Scheduler
 * @see https://w3id.org/itpack/runit#Execution
 * @api
 */
final class FiberTaskScheduler implements
    EngineSchedulerInterface,
    ResultTypedSchedulerInterface,
    SchedulerIntrospectionInterface,
    AwaitableIOInterface,
    DeadlineSchedulerInterface
{
    private const int MAX_TASK_RESULTS = 1000;

    private const string MUST_BE_CALLED_WITHIN_RUNNING_TASK = ' must be called within a running task.';

    private const string NOT_TRACKED_BY_THIS_SCHEDULER = 'Current task is not tracked by this scheduler.';

    private const string CANNOT_AWAIT_TASK_NOT_OWNED = 'Cannot await a task this scheduler does not own.';

    /** @var array<string, TaskRecordInterface> */
    private array $records = [];

    /** @var list<TaskRecordInterface> */
    private array $ready = [];

    private int $readyHead = 0;

    /** @var array<string, mixed> */
    private array $results = [];

    /** @var array<string, TaskResultInterface> */
    private array $taskResults = [];

    /** @var array<string, list<TaskRecordInterface>> */
    private array $awaiters = [];

    /** @var array<string, mixed> */
    private array $pendingResumeValues = [];

    /** @var array<string, int> Monotonic start time per task, used for duration measurements. */
    private array $taskStartTimes = [];

    /** @var array<string, list<TaskRecordInterface>> */
    private array $anyAwaiters = [];

    /** @var array<string, string> */
    private array $anyCompletedTaskId = [];

    /** @var list<Throwable> */
    private array $errors = [];

    private bool $cancelledRecordPending = false;

    private ?TaskInterface $currentTask = null;

    private readonly int $startTimeNanos;

    private CapabilityDescriptorInterface|null $capabilities = null;

    /** @var list<SchedulerEventListenerInterface> */
    private readonly array $listeners;

    public function __construct(
        private readonly FiberFactoryInterface $fiberFactory,
        private readonly TaskRecordFactoryInterface $taskRecordFactory,
        private readonly TaskFactoryInterface $taskFactory,
        private readonly CancellationScopeFactoryInterface $cancellationScopeFactory,
        private readonly MonotonicClockInterface $clock,
        private readonly ExecutionIdInterface $executionId,
        private readonly PollerPortInterface $poller,
        private readonly ContextStorageInterface $contextStorage,
        private readonly ExecutionScopeFactoryInterface $scopeFactory,
        private readonly TaskTimerServiceInterface $timerService,
        private readonly TaskIoAwaitServiceInterface $ioAwaitService,
        private readonly TaskLifecyclePublisherInterface $lifecyclePublisher,
        private readonly TaskCancellationServiceInterface $cancellationService,
        private readonly TaskHealthServiceInterface $healthService,
        SchedulerEventListenerInterface ...$listeners,
    ) {
        $this->startTimeNanos = $this->clock->monotonicNanoseconds();
        $this->listeners = array_values($listeners);
    }

    public function submit(TaskInterface $task, callable $operation): void
    {
        $this->doSpawn($task, $operation);
    }

    public function spawn(callable $operation, TaskIdInterface|null $id = null): TaskInterface
    {
        $scope = $this->cancellationScopeFactory->create();
        $task = $id !== null
            ? $this->taskFactory->createWithId($id, $this->executionId, $scope)
            : $this->taskFactory->create($this->executionId, $scope);

        $this->doSpawn($task, $operation);

        return $task;
    }

    /**
     * @throws Throwable
     */
    public function await(TaskInterface $task): mixed
    {
        $current = $this->currentTask;

        if (!$current instanceof TaskInterface) {
            throw new LogicException('await()' . self::MUST_BE_CALLED_WITHIN_RUNNING_TASK);
        }

        $currentRecord = $this->records[$current->id()->value] ?? null;

        if ($currentRecord === null) {
            throw new LogicException(self::NOT_TRACKED_BY_THIS_SCHEDULER);
        }

        $taskId = $task->id()->value;

        if (array_key_exists($taskId, $this->results)) {
            return $this->results[$taskId];
        }

        if (!isset($this->records[$taskId])) {
            throw new LogicException(self::CANNOT_AWAIT_TASK_NOT_OWNED);
        }

        $this->awaiters[$taskId][] = $currentRecord;

        $this->notifyListeners(SchedulerEvent::suspended(
            $currentRecord->task(),
            AwaitPoint::Join,
        ));

        return Fiber::suspend();
    }

    public function currentTask(): ?TaskInterface
    {
        return $this->currentTask;
    }

    public function run(): void
    {
        $this->runUntil(PHP_INT_MAX);
    }

    /**
     * Drains the reactor until all records are terminal, the deadline elapses,
     * or all remaining tasks have been force-cancelled.
     *
     * On deadline expiry, force-cancels every remaining task scope with
     * {@see CancellationReason::DeadlineExceeded} and releases their handles.
     */
    public function runUntil(int $deadlineNanoseconds): void
    {
        while ($this->records !== []) {
            $now = $this->clock->monotonicNanoseconds();

            if ($now >= $deadlineNanoseconds) {
                $records = array_values($this->records);
                $this->records = [];
                $this->cancellationService->cancelRemaining($records, $deadlineNanoseconds);

                foreach ($records as $record) {
                    $this->complete($record, TaskStatus::Cancelled);
                    $this->resumeAwaiters($record);
                    $this->resumeAnyAwaiters($record);
                }

                break;
            }

            $this->drainExpiredTimers();

            while ($this->readyHead < \count($this->ready)) {
                $record = $this->ready[$this->readyHead];
                $this->readyHead++;

                if ($this->readyHead > 0 && $this->readyHead >= (\count($this->ready) >> 1)) {
                    $this->ready = array_slice($this->ready, $this->readyHead);
                    $this->readyHead = 0;
                }

                if (!$record->isDone()) {
                    if ($record->task()->cancellationScope()->isCancelled()) {
                        $this->complete($record, TaskStatus::Cancelled);
                        continue;
                    }

                    $this->step($record);
                }
            }

            foreach ($this->records as $id => $record) {
                if ($record->isDone()) {
                    $this->resumeAwaiters($record);
                    $this->resumeAnyAwaiters($record);
                    unset($this->records[$id]);
                }
            }

            if ($this->cancelledRecordPending) {
                $this->cancelledRecordPending = false;
                $this->drainCancelledRecords();
            }

            if ($this->records !== []) {
                if ($this->readyHead < \count($this->ready)) {
                    continue;
                }

                $this->poller->await($this->computeTimeout($deadlineNanoseconds));

                $this->drainExpiredTimers();

                foreach ($this->ioAwaitService->drainReady() as $record) {
                    $taskId = $record->task()->id()->value;
                    $this->pendingResumeValues[$taskId] = true;
                    $this->ready[] = $record;
                }
            }
        }
    }

    public function dispatch(TaskInterface $task, callable $operation): mixed
    {
        $this->submit($task, $operation);
        $this->run();

        return $this->result($task);
    }

    public function result(TaskInterface $task): mixed
    {
        return $this->results[$task->id()->value] ?? null;
    }

    public function resultTyped(TaskInterface $task): TaskResultInterface
    {
        $taskId = $task->id()->value;

        if (!isset($this->taskResults[$taskId])) {
            throw new LogicException(
                sprintf('Task %s is not tracked by this scheduler or has not reached a terminal state.', $taskId),
            );
        }

        return $this->taskResults[$taskId];
    }

    public function taskRecords(): array
    {
        return $this->records;
    }

    public function health(): SchedulerHealthInterface
    {
        return $this->healthService->capture(
            array_values($this->records),
            $this->startTimeNanos,
            $this->errors,
        );
    }

    public function swapCapabilities(CapabilityDescriptorInterface $descriptor): void
    {
        $this->capabilities = $descriptor;
    }

    public function capabilities(): ?CapabilityDescriptorInterface
    {
        return $this->capabilities;
    }

    /**
     * @throws Throwable
     */
    public function awaitDelay(DurationInterface $duration): void
    {
        $task = $this->currentTask;

        if (!$task instanceof TaskInterface) {
            throw new LogicException('awaitDelay()' . self::MUST_BE_CALLED_WITHIN_RUNNING_TASK);
        }

        $record = $this->records[$task->id()->value] ?? null;

        if ($record === null) {
            throw new LogicException(self::NOT_TRACKED_BY_THIS_SCHEDULER);
        }

        $this->timerService->scheduleTimer($record, $duration);

        $this->notifyListeners(SchedulerEvent::suspended($task, AwaitPoint::Delay));

        $this->currentTask = null;
        Fiber::suspend();
        $this->currentTask = $task;

        $this->lifecyclePublisher->publishResumed($task);
        $this->notifyListeners(SchedulerEvent::resumed($task, AwaitPoint::Delay));
    }

    public function awaitReadable(StreamPollHandleInterface $handle, DurationInterface|null $timeout = null): bool
    {
        return $this->awaitIo($handle, AwaitPoint::Readable, $timeout);
    }

    public function awaitWritable(StreamPollHandleInterface $handle, DurationInterface|null $timeout = null): bool
    {
        return $this->awaitIo($handle, AwaitPoint::Writable, $timeout);
    }

    public function registerStream(mixed $stream): StreamPollHandleInterface
    {
        return $this->poller->register($stream);
    }

    public function awaitAny(array $tasks): string
    {
        $current = $this->currentTask;

        if (!$current instanceof TaskInterface) {
            throw new LogicException('awaitAny()' . self::MUST_BE_CALLED_WITHIN_RUNNING_TASK);
        }

        $record = $this->records[$current->id()->value] ?? null;

        if ($record === null) {
            throw new LogicException(self::NOT_TRACKED_BY_THIS_SCHEDULER);
        }

        foreach ($tasks as $task) {
            $taskId = $task->id()->value;

            if (isset($this->results[$taskId])) {
                return $taskId;
            }

            if (!isset($this->records[$taskId])) {
                throw new LogicException(self::CANNOT_AWAIT_TASK_NOT_OWNED);
            }

            $this->anyAwaiters[$taskId][] = $record;
        }

        Fiber::suspend();
        $this->currentTask = $current;

        $completedId = $this->anyCompletedTaskId[$current->id()->value] ?? null;
        unset($this->anyCompletedTaskId[$current->id()->value]);

        return $completedId ?? $current->id()->value;
    }

    public function retry(callable $operation, RetryPolicyInterface $policy): mixed
    {
        $attempt = 1;

        while (true) {
            try {
                return $operation();
            } catch (Throwable $e) {
                $original = $e;
                $attempt++;

                if ($attempt > $policy->maxAttempts) {
                    throw $original;
                }

                if (!$policy->isRetryable($e::class)) {
                    throw $e;
                }

                $this->awaitDelay(
                    $policy->delayForAttempt($attempt - 1)
                );
            }
        }
    }

    /**
     * @throws Throwable
     */
    private function doSpawn(TaskInterface $task, callable $operation): void
    {
        $record = $this->taskRecordFactory->create(
            $task,
            $this->fiberFactory->create($operation)
        );

        $this->records[$task->id()->value] = $record;
        $this->ready[] = $record;

        $task->cancellationScope()->onCancel(function (): void {
            $this->cancelledRecordPending = true;
        });

        $this->taskStartTimes[$task->id()->value] = $this->clock->monotonicNanoseconds();

        $this->lifecyclePublisher->publishSpawned($task);
        $this->notifyListeners(SchedulerEvent::spawned($task, $this->currentTask?->id()));
    }

    /**
     * @throws Throwable
     */
    private function step(TaskRecordInterface $record): void
    {
        $token = $this->contextStorage->attach(
            $this->scopeFactory->create(
                $record->task()->id()
            )
        );

        $this->currentTask = $record->task();

        $this->notifyListeners(SchedulerEvent::resumed($record->task()));

        try {
            $this->resume($record);
        } catch (Throwable $throwable) {
            throw $throwable;
        } finally {
            $this->currentTask = null;
            $this->contextStorage->detach($token);
        }
    }

    /**
     * @throws Throwable
     */
    private function resume(TaskRecordInterface $record): void
    {
        $fiber = $record->fiber();

        try {
            $record->task()->transitionTo(TaskStatus::Running);

            if ($fiber->isSuspended()) {
                $taskId = $record->task()->id()->value;
                $awaitValue = $this->pendingResumeValues[$taskId] ?? null;
                unset($this->pendingResumeValues[$taskId]);
                $fiber->resume($awaitValue);
            }

            if (!$fiber->isStarted()) {
                $fiber->start();
            }

            if ($fiber->isTerminated()) {
                $this->complete($record, TaskStatus::Succeeded, $fiber->getReturn());
            }
        } catch (Throwable $throwable) {
            $this->complete($record, TaskStatus::Failed, throwable: $throwable);
            throw $throwable;
        }
    }

    private function complete(
        TaskRecordInterface $record,
        TaskStatus $status,
        mixed $result = null,
        ?Throwable $throwable = null,
    ): void {
        $task = $record->task();
        $taskId = $task->id()->value;
        $startTime = $this->taskStartTimes[$taskId] ?? null;
        unset($this->taskStartTimes[$taskId]);

        $this->results[$taskId] = $result;

        $duration = $startTime === null
            ? Duration::ofNanoseconds(0)
            : Duration::ofNanoseconds($this->clock->monotonicNanoseconds() - $startTime);

        if ($throwable instanceof Throwable) {
            $this->taskResults[$taskId] = TaskResult::failure($throwable, $status, $duration);
            $this->errors[] = $throwable;
        }

        if (!isset($this->taskResults[$taskId]) && $status === TaskStatus::Succeeded) {
            $this->taskResults[$taskId] = TaskResult::success($result, $status, $duration);
        }

        if (!isset($this->taskResults[$taskId]) && $status !== TaskStatus::Succeeded) {
            $this->taskResults[$taskId] = TaskResult::failure(
                new RuntimeException('Task completed with non-success status.'),
                $status,
                $duration,
            );
        }

        while (\count($this->taskResults) > self::MAX_TASK_RESULTS) {
            array_shift($this->taskResults);
        }

        $this->lifecyclePublisher->publishCompleted($task, $status, $startTime, $throwable);

        $this->notifyListeners(SchedulerEvent::completed($task, $status));

        $task->complete($status);
        $this->ioAwaitService->releaseForRecord($record);
        $this->timerService->clearForRecord($record);
        $record->markDone();
    }

    private function resumeAwaiters(TaskRecordInterface $completed): void
    {
        $taskId = $completed->task()->id()->value;
        $awaiters = $this->awaiters[$taskId] ?? [];
        unset($this->awaiters[$taskId]);

        $result = $this->results[$taskId] ?? null;

        foreach ($awaiters as $awaiterRecord) {
            $awaiterId = $awaiterRecord->task()->id()->value;
            $this->pendingResumeValues[$awaiterId] = $result;
            $this->ready[] = $awaiterRecord;
        }
    }

    private function resumeAnyAwaiters(TaskRecordInterface $completed): void
    {
        $completedId = $completed->task()->id()->value;
        $awaiters = $this->anyAwaiters[$completedId] ?? [];
        unset($this->anyAwaiters[$completedId]);

        if ($awaiters === []) {
            return;
        }

        $result = $this->results[$completedId] ?? null;

        foreach ($awaiters as $awaiterRecord) {
            $awaiterId = $awaiterRecord->task()->id()->value;
            $this->anyCompletedTaskId[$awaiterId] = $completedId;
            $this->pendingResumeValues[$awaiterId] = $result;
            $this->ready[] = $awaiterRecord;
        }

        foreach ($awaiters as $awaiterRecord) {
            $awaiterId = $awaiterRecord->task()->id()->value;
            foreach ($this->anyAwaiters as $otherId => $others) {
                $this->anyAwaiters[$otherId] = array_values(
                    array_filter(
                        $others,
                        static fn (TaskRecordInterface $r): bool => $r->task()->id()->value !== $awaiterId
                    ),
                );
            }
        }
    }

    private function drainExpiredTimers(): void
    {
        $now = $this->clock->monotonicNanoseconds();

        foreach ($this->timerService->drainExpired($now) as $entry) {
            $record = $entry['record'];
            $taskId = $entry['taskId'] ?? $record->task()->id()->value;

            if ($entry['type'] !== null) {
                $this->ioAwaitService->clearIoAwaitForTask($taskId);
                $this->pendingResumeValues[$taskId] = false;
            }

            $this->ready[] = $record;
        }
    }

    private function computeTimeout(int $deadlineNanoseconds): int
    {
        $now = $this->clock->monotonicNanoseconds();
        $remaining = $deadlineNanoseconds - $now;

        if ($remaining <= 0) {
            return 0;
        }

        $timerTimeout = !$this->timerService->hasTimers()
            ? PHP_INT_MAX
            : $this->timerService->nearestDeadline() - $now;

        return max(0, min($timerTimeout, $remaining));
    }

    /**
     * Completes tasks whose cancellation scope was cancelled externally
     * (e.g., by TaskGroup destructor) but whose records haven't been
     * drained yet.
     */
    private function drainCancelledRecords(): void
    {
        foreach ($this->cancellationService->drainCancelled(array_values($this->records)) as $record) {
            $taskId = $record->task()->id()->value;
            $this->complete($record, TaskStatus::Cancelled);
            $this->resumeAwaiters($record);
            $this->resumeAnyAwaiters($record);
            unset($this->records[$taskId]);
        }
    }

    private function notifyListeners(SchedulerEventInterface $event): void
    {
        foreach ($this->listeners as $listener) {
            $listener->onEvent($event);
        }
    }

    /**
     * Shared implementation for awaitReadable/awaitWritable.
     */
    private function awaitIo(
        StreamPollHandleInterface $handle,
        AwaitPoint $point,
        DurationInterface|null $timeout,
    ): bool {
        $task = $this->currentTask;

        if (!$task instanceof TaskInterface) {
            throw new LogicException(sprintf('%s()%s', $point->name, self::MUST_BE_CALLED_WITHIN_RUNNING_TASK));
        }

        $taskId = $task->id()->value;
        $record = $this->records[$taskId] ?? null;

        if ($record === null) {
            throw new LogicException(self::NOT_TRACKED_BY_THIS_SCHEDULER);
        }

        $this->ioAwaitService->registerAwait($record, $handle, $point, $timeout);

        $this->notifyListeners(SchedulerEvent::suspended($task, $point));

        $this->currentTask = null;
        $result = Fiber::suspend();
        $this->currentTask = $task;

        $this->ioAwaitService->clearIoAwaitForTask($taskId);

        $this->lifecyclePublisher->publishResumed($task);
        $this->notifyListeners(SchedulerEvent::resumed($task, $point));

        return (bool) $result;
    }
}
