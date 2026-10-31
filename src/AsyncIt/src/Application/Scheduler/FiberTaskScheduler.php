<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Scheduler;

use Fiber;
use ItPack\AsyncIt\Application\Artifact\ArtifactVocabulary;
use ItPack\AsyncIt\Application\Artifact\SchedulerFailureArtifact;
use ItPack\AsyncIt\Application\Factory\CancellationScopeFactoryInterface;
use ItPack\AsyncIt\Application\Factory\ExecutionScopeFactoryInterface;
use ItPack\AsyncIt\Application\Factory\FiberFactoryInterface;
use ItPack\AsyncIt\Application\Factory\SchedulerLifecycleArtifactFactory;
use ItPack\AsyncIt\Application\Factory\TaskFactoryInterface;
use ItPack\AsyncIt\Application\Factory\TaskRecordFactoryInterface;
use ItPack\AsyncIt\Application\SchedulerHealth;
use ItPack\AsyncIt\Contract\CapabilityDescriptorInterface;
use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Contract\Port\AwaitPoint;
use ItPack\AsyncIt\Contract\Port\CancellationReason;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\RetryPolicyInterface;
use ItPack\AsyncIt\Contract\SchedulerEventInterface;
use ItPack\AsyncIt\Contract\SchedulerEventListenerInterface;
use ItPack\AsyncIt\Contract\SchedulerHealthInterface;
use ItPack\AsyncIt\Contract\SchedulerInterface;
use ItPack\AsyncIt\Contract\TaskIdInterface;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Domain\SchedulerEvent;
use ItPack\AsyncIt\Domain\TaskId;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Domain\Shared\Duration;
use ItPack\RunIt\Domain\Shared\Instant;
use LogicException;
use Throwable;

/**
 * Reference reactor implementing {@see SchedulerInterface}.
 *
 * Every collaborator is injected (factories, poller, clock, context storage,
 * cancellation scope, scope factory, publisher); the scheduler never
 * constructs them. The reactor attaches a RunIt execution scope before each
 * Fiber resume and detaches it under `finally` on suspend, so an interleaved
 * (suspended) task never holds an ambient context (conformance Assertion A).
 * On terminal transition it releases every registered poll handle (Assertion
 * C), and on throw it publishes through the recursion-guarded publisher and
 * rethrows the exact original {@see \Throwable}.
 *
 * @see https://w3id.org/itpack/asyncit#Scheduler
 * @see https://w3id.org/itpack/runit#Execution
  * @api

  */
final class FiberTaskScheduler implements EngineSchedulerInterface
{
    /**
     * @var array<string, TaskRecord>
     */
    private array $records = [];

    /**
     * @var list<TaskRecord>
     */
    private array $ready = [];

    /**
     * @var array<string, mixed>
     */
    private array $results = [];

    /**
     * Array of pending timer deadlines, kept sorted by deadline.
     *
     * Each entry is `['deadline' => int, 'record' => TaskRecord]`. The nearest
     * deadline floats to the front so {@see drainExpiredTimers()} and
     * {@see computeTimeout()} are O(1) when no timers are pending.
     *
     * @var list<array{deadline: int, record: TaskRecord}>
     */
    private array $timers = [];

    /**
     * Tasks awaiting completion of other tasks.
     *
     * Maps the awaited task's id to a list of records suspended on it.
     *
     * @var array<string, list<TaskRecord>>
     */
    private array $awaiters = [];

    /**
     * Resume values keyed by the awaiter task's id. Set when an awaited task
     * completes, consumed by {@see resume()} when the awaiter is stepped.
     *
     * @var array<string, mixed>
     */
    private array $pendingResumeValues = [];

    private bool $cancelledRecordPending = false;

    private ?TaskInterface $currentTask = null;

    /**
     * @var array<string, int> Monotonic start time per task, used for duration measurements.
     */
    private array $taskStartTimes = [];

    private readonly int $startTimeNanos;

    /**
     * @var list<Throwable>
     */
    private array $errors = [];

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
        private readonly ArtifactPublisherInterface $publisher,
        private readonly SchedulerLifecycleArtifactFactory $lifecycleFactory,
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
        $task = $id instanceof TaskId
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
            throw new LogicException('await() must be called within a running task.');
        }

        $currentRecord = $this->records[$current->id()->value] ?? null;

        if ($currentRecord === null) {
            throw new LogicException('Current task is not tracked by this scheduler.');
        }

        $taskId = $task->id()->value;

        if (array_key_exists($taskId, $this->results)) {
            return $this->results[$taskId];
        }

        if (!isset($this->records[$taskId])) {
            throw new LogicException('Cannot await a task this scheduler does not own.');
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
                $this->forceCancelRemaining();

                break;
            }

            $this->drainExpiredTimers();

            while ($this->ready !== []) {
                $record = array_shift($this->ready);
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
                    unset($this->records[$id]);
                }
            }

            if ($this->cancelledRecordPending) {
                $this->cancelledRecordPending = false;
                $this->drainCancelledRecords();
            }

            if ($this->records !== []) {
                $this->poller->await($this->computeTimeout($deadlineNanoseconds));

                $this->drainExpiredTimers();
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

    /**
     * @return list<TaskRecord>
     */
    public function taskRecords(): array
    {
        return array_values($this->records);
    }

    /**
     * Returns a health snapshot of the scheduler's current runtime state.
     */
    public function health(): SchedulerHealthInterface
    {
        return SchedulerHealth::capture(
            $this->taskRecords(),
            $this->poller->activeHandleCount(),
            $this->clock,
            $this->startTimeNanos,
            $this->errors,
        );
    }

    /**
     * Atomically swaps the negotiated capability descriptor.
     *
     * The new descriptor replaces the active one immediately. Backend selection
     * (e.g., channel strategy) re-evaluates on the next reactor tick.
     */
    public function swapCapabilities(CapabilityDescriptorInterface $descriptor): void
    {
        $this->capabilities = $descriptor;
    }

    /**
     * Returns the current capability descriptor, or null if none has been set.
     */
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
            throw new LogicException('awaitDelay() must be called within a running task.');
        }

        $now = $this->clock->monotonicNanoseconds();
        $deadline = $now + $duration->nanoseconds();
        $record = $this->records[$task->id()->value] ?? null;

        if ($record === null) {
            throw new LogicException('Current task is not tracked by this scheduler.');
        }

        $this->timers[] = ['deadline' => $deadline, 'record' => $record];

        $this->notifyListeners(SchedulerEvent::suspended($task, AwaitPoint::Delay));

        $this->currentTask = null;
        Fiber::suspend();
        $this->currentTask = $task;

        $this->publishLifecycle($task, ArtifactVocabulary::KIND_TASK_RESUMED, TaskStatus::Running);
        $this->notifyListeners(SchedulerEvent::resumed($task, AwaitPoint::Delay));
    }

    /**
     * Runs the operation with retry semantics governed by the policy.
     *
     * On each failed attempt, checks whether the exception is retryable
     * (per {@see RetryPolicy::$retryable}). If retryable, suspends for the
     * computed backoff delay (V.2 {@see awaitDelay()}) and retries.
     * After exhausting {@see RetryPolicy::$maxAttempts}, the **exact**
     * original {@see Throwable} is rethrown — no wrapping.
     *
     * @throws Throwable The original exception after max attempts exhaustion.
     *
     * @see https://w3id.org/itpack/asyncit#RetryPolicy
     */
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
    private function publishLifecycle(
        TaskInterface $task,
        string $kind,
        TaskStatus $status,
        ?int $startTime = null,
    ): void {
        $this->publisher->publish(
            $this->lifecycleFactory->create(
                $task->id(),
                $kind,
                $status,
                $startTime === null
                    ? null :
                    Duration::ofNanoseconds(
                        $this->clock->monotonicNanoseconds() - $startTime
                    ),
                Instant::now(),
            )
        );
    }

    private function notifyListeners(SchedulerEventInterface $event): void
    {
        foreach ($this->listeners as $listener) {
            $listener->onEvent($event);
        }
    }

    /**
     * Internal spawn: creates the fiber + record and pushes onto the ready queue.
     * @throws Throwable
     */
    private function doSpawn(TaskInterface $task, callable $operation): void
    {
        $this->ready[]
            = $this->records[$task->id()->value]
            = $this->taskRecordFactory->create(
                $task,
                $this->fiberFactory->create($operation)
            );

        $task->cancellationScope()->onCancel(function (): void {
            $this->cancelledRecordPending = true;
        });

        $this->taskStartTimes[$task->id()->value] = $this->clock->monotonicNanoseconds();

        $this->publishLifecycle($task, ArtifactVocabulary::KIND_TASK_SPAWNED, TaskStatus::Created);
        $this->notifyListeners(SchedulerEvent::spawned($task, $this->currentTask?->id()));
    }

    /**
     * @throws Throwable
     */
    private function step(TaskRecord $record): void
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
    private function resume(TaskRecord $record): void
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
        TaskRecord $record,
        TaskStatus $status,
        mixed $result = null,
        ?Throwable $throwable = null,
    ): void {
        $task = $record->task();
        $taskId = $task->id()->value;
        $startTime = $this->taskStartTimes[$taskId] ?? null;
        unset($this->taskStartTimes[$taskId]);

        $this->results[$taskId] = $result;

        if ($throwable instanceof Throwable) {
            $this->publisher->publish(new SchedulerFailureArtifact($throwable->getMessage()));
            $this->errors[] = $throwable;
        }

        $this->publishLifecycle($task, ArtifactVocabulary::KIND_TASK_COMPLETED, $status, $startTime);

        $this->notifyListeners(SchedulerEvent::completed($task, $status));

        $task->complete($status);
        $this->releaseHandles($record);
        $this->clearTimers($record);
        $record->markDone();
    }

    private function releaseHandles(TaskRecord $record): void
    {
        foreach ($record->handles() as $handle) {
            $this->poller->release($handle);
        }

        $record->clearHandles();
    }

    private function resumeAwaiters(TaskRecord $completed): void
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

    private function drainExpiredTimers(): void
    {
        $now = $this->clock->monotonicNanoseconds();
        $remaining = [];

        foreach ($this->timers as $entry) {
            if ($entry['deadline'] <= $now) {
                $this->ready[] = $entry['record'];

                continue;
            }

            $remaining[] = $entry;
        }

        $this->timers = $remaining;
    }

    /**
     * Removes any timer entries referencing the given record from
     * {@see $timers}. Called from {@see complete()} to ensure that tasks
     * which completed (successfully, failed, or cancelled) before their
     * delay timer expired do not leave stale entries that could re-queue
     * an already-terminal record on a future {@see drainExpiredTimers()} tick.
     */
    private function clearTimers(TaskRecord $record): void
    {
        $this->timers = array_values(
            array_filter(
                $this->timers,
                static fn (array $entry): bool => $entry['record'] !== $record,
            ),
        );
    }

    private function computeTimeout(int $deadlineNanos): int
    {
        $now = $this->clock->monotonicNanoseconds();
        $remaining = $deadlineNanos - $now;

        if ($remaining <= 0) {
            return 0;
        }

        $timerTimeout = $this->timers === []
            ? PHP_INT_MAX
            : $this->computeNearestTimerDeadline() - $now;

        return max(0, min($timerTimeout, $remaining));
    }

    private function computeNearestTimerDeadline(): int
    {
        $nearest = PHP_INT_MAX;

        foreach ($this->timers as $entry) {
            if ($entry['deadline'] < $nearest) {
                $nearest = $entry['deadline'];
            }
        }

        return $nearest;
    }

    /**
     * Completes tasks whose cancellation scope was cancelled externally
     * (e.g., by TaskGroup destructor) but whose records haven't been
     * drained yet. This ensures cancelled tasks are cleaned up, their
     * awaiters are resumed, and poll handles are released even when
     * the task is suspended (not in the ready queue).
     */
    private function drainCancelledRecords(): void
    {
        foreach ($this->records as $id => $record) {
            if (!$record->isDone() && $record->task()->cancellationScope()->isCancelled()) {
                $this->complete($record, TaskStatus::Cancelled);
                $this->resumeAwaiters($record);
                unset($this->records[$id]);
            }
        }
    }

    /**
     * Force-cancels every remaining task scope and releases their handles.
     *
     * Called when the shutdown deadline expires; tasks that did not unwind
     * cooperatively are force-cancelled so the reactor can terminate and
     * no poll handles are leaked (Assertion C).
     *
     * Tasks are completed via {@see complete()} with
     * {@see TaskStatus::Cancelled}, which handles handle release, lifecycle
     * publication, and markDone. {@see resumeAwaiters()} is called to set
     * pendingResumeValues for tasks awaiting this one — in the force-cancel
     * path these awaiters are themselves force-cancelled in the same loop,
     * so the values are harmlessly not consumed.
     */
    private function forceCancelRemaining(): void
    {
        $records = array_values($this->records);
        $this->records = [];

        foreach ($records as $record) {
            $record->task()->cancellationScope()->cancel(
                CancellationReason::DeadlineExceeded,
            );

            $this->complete($record, TaskStatus::Cancelled);
            $this->resumeAwaiters($record);
        }
    }
}
