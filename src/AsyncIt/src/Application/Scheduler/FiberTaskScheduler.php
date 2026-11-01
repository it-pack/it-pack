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
use ItPack\AsyncIt\Contract\AwaitableIOInterface;
use ItPack\AsyncIt\Contract\CapabilityDescriptorInterface;
use ItPack\AsyncIt\Contract\DeadlineSchedulerInterface;
use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Contract\Port\AwaitPoint;
use ItPack\AsyncIt\Contract\Port\CancellationReason;
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
use ItPack\AsyncIt\Domain\TaskId;
use ItPack\AsyncIt\Domain\TaskResult;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Domain\Shared\Duration;
use ItPack\RunIt\Domain\Shared\Instant;
use LogicException;
use RuntimeException;
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
final class FiberTaskScheduler implements EngineSchedulerInterface, ResultTypedSchedulerInterface, AwaitableIOInterface, DeadlineSchedulerInterface
{
    /**
     * Maximum number of terminal task outcomes retained in {@see $taskResults}.
     * Older outcomes are evicted (FIFO) to bound memory growth.
     */
    private const int MAX_TASK_RESULTS = 1000;

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
     * Typed terminal outcomes keyed by task id.
     *
     * @var array<string, TaskResultInterface>
     */
    private array $taskResults = [];

    /**
     * Array of pending timer deadlines, kept sorted by deadline.
     *
     * Each entry is `['deadline' => int, 'record' => TaskRecord]`. The nearest
     * deadline floats to the front so {@see drainExpiredTimers()} and
     * {@see computeTimeout()} are O(1) when no timers are pending.
     *
     * @var list<array{deadline: int, record: TaskRecord} | array{deadline: int, record: TaskRecord, type: 'io', taskId: string}>
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

    /**
     * Tasks awaiting IO readiness, keyed by handle id.
     *
     * @var array<string, TaskRecord>
     */
    private array $ioAwaiters = [];

    /**
     * Task id => handle id for IO-awaiting tasks, used to look up the handle
     * when a deadline timer expires for an IO-awaiting task.
     *
     * @var array<string, string>
     */
    private array $ioTaskHandles = [];

    /**
     * Tasks awaiting any of several tasks, keyed by the awaited task's id.
     * Used by awaitAny() to resume when the first of several tasks completes.
     *
     * @var array<string, list<TaskRecord>>
     */
    private array $anyAwaiters = [];

    /**
     * Awaiter task id => id of the task that completed first (for awaitAny()).
     *
     * @var array<string, string>
     */
    private array $anyCompletedTaskId = [];

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
                    $this->resumeAnyAwaiters($record);
                    unset($this->records[$id]);
                }
            }

            if ($this->cancelledRecordPending) {
                $this->cancelledRecordPending = false;
                $this->drainCancelledRecords();
            }

            if ($this->records !== []) {
                if ($this->ready !== []) {
                    continue;
                }

                $this->poller->await($this->computeTimeout($deadlineNanoseconds));

                $this->drainExpiredTimers();

                $this->drainReadyHandles();
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
     * Suspends the current task until the handle's stream is ready for reading,
     * or the optional monotonic timeout elapses.
     *
     * Returns true when readable, false when the timeout expired.
     */
    public function awaitReadable(StreamPollHandleInterface $handle, DurationInterface|null $timeout = null): bool
    {
        return $this->awaitIo($handle, AwaitPoint::Readable, $timeout);
    }

    /**
     * Suspends the current task until the handle's stream is ready for writing,
     * or the optional monotonic timeout elapses.
     *
     * Returns true when writable, false when the timeout expired.
     */
    public function awaitWritable(StreamPollHandleInterface $handle, DurationInterface|null $timeout = null): bool
    {
        return $this->awaitIo($handle, AwaitPoint::Writable, $timeout);
    }

    /**
     * Registers a stream with the scheduler's poller and returns a handle.
     *
     * The handle can be passed to {@see awaitReadable()} or {@see awaitWritable()}.
     * The caller is responsible for ensuring the handle is released (via
     * {@see PollerPortInterface::release()}) when no longer needed; the scheduler
     * releases it automatically on task terminal transition.
     */
    public function registerStream(mixed $stream): StreamPollHandleInterface
    {
        return $this->poller->register($stream);
    }

    /**
     * Suspends the current task until ANY of the given tasks completes.
     *
     * Returns the id of the first completed task. All registrations are cleaned
     * up after resume so the awaiter is not re-resumed when other tasks later
     * complete.
     *
     * @param list<TaskInterface> $tasks
     * @return string The id of the first completed task
     *
     * @throws LogicException when called outside a running task
     */
    public function awaitAny(array $tasks): string
    {
        $current = $this->currentTask;

        if (!$current instanceof TaskInterface) {
            throw new LogicException('awaitAny() must be called within a running task.');
        }

        $record = $this->records[$current->id()->value] ?? null;

        if ($record === null) {
            throw new LogicException('Current task is not tracked by this scheduler.');
        }

        foreach ($tasks as $task) {
            $taskId = $task->id()->value;

            if (isset($this->results[$taskId])) {
                return $taskId;
            }

            if (!isset($this->records[$taskId])) {
                throw new LogicException('Cannot await a task this scheduler does not own.');
            }

            $this->anyAwaiters[$taskId][] = $record;
        }

        Fiber::suspend();
        $this->currentTask = $current;

        // Find which task completed first.
        $completedId = $this->anyCompletedTaskId[$current->id()->value] ?? null;
        unset($this->anyCompletedTaskId[$current->id()->value]);

        return $completedId ?? $current->id()->value;
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
     * Shared implementation for awaitReadable/awaitWritable.
     *
     * Registers the handle on the task record, suspends the fiber, and resumes
     * with true when the poller signals readiness or false when the deadline
     * timer fires. The handle is released on terminal transition.
     */
    private function awaitIo(StreamPollHandleInterface $handle, AwaitPoint $point, DurationInterface|null $timeout): bool
    {
        $task = $this->currentTask;

        if (!$task instanceof TaskInterface) {
            throw new LogicException(sprintf('%s() must be called within a running task.', $point->name));
        }

        $taskId = $task->id()->value;
        $record = $this->records[$taskId] ?? null;

        if ($record === null) {
            throw new LogicException('Current task is not tracked by this scheduler.');
        }

        $handleId = $handle->id;

        $this->ioAwaiters[$handleId] = $record;
        $this->ioTaskHandles[$taskId] = $handleId;
        $record->addHandle($handle);

        if ($timeout instanceof DurationInterface) {
            $now = $this->clock->monotonicNanoseconds();
            $deadline = $now + $timeout->nanoseconds();
            $this->timers[] = ['deadline' => $deadline, 'record' => $record, 'type' => 'io', 'taskId' => $taskId];
        }

        $this->notifyListeners(SchedulerEvent::suspended($task, $point));

        $this->currentTask = null;
        $result = Fiber::suspend();
        $this->currentTask = $task;

        unset($this->ioAwaiters[$handleId]);
        unset($this->ioTaskHandles[$taskId]);

        $this->publishLifecycle($task, ArtifactVocabulary::KIND_TASK_RESUMED, TaskStatus::Running);
        $this->notifyListeners(SchedulerEvent::resumed($task, $point));

        return (bool) $result;
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

        $duration = $startTime === null
            ? Duration::ofNanoseconds(0)
            : Duration::ofNanoseconds($this->clock->monotonicNanoseconds() - $startTime);

        if ($throwable instanceof Throwable) {
            $this->taskResults[$taskId] = TaskResult::failure($throwable, $status, $duration);
            $this->publisher->publish(new SchedulerFailureArtifact($throwable->getMessage()));
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

        // Bound the typed-results buffer: evict oldest outcomes when the cap is exceeded.
        while (\count($this->taskResults) > self::MAX_TASK_RESULTS) {
            array_shift($this->taskResults);
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
        $taskId = $record->task()->id()->value;

        foreach ($record->handles() as $handle) {
            $this->poller->release($handle);
            unset($this->ioAwaiters[$handle->id]);
        }

        unset($this->ioTaskHandles[$taskId]);

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

    /**
     * Resumes tasks that were awaiting any of several tasks via awaitAny().
     * When the completed task is one of the awaited tasks, all awaiters are
     * resumed with its result and cleaned up from any other anyAwaiters lists.
     */
    private function resumeAnyAwaiters(TaskRecord $completed): void
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

        // Clean up: remove resumed awaiters from any other anyAwaiters lists
        // so they are not re-resumed when other tasks complete.
        foreach ($awaiters as $awaiterRecord) {
            $awaiterId = $awaiterRecord->task()->id()->value;
            foreach ($this->anyAwaiters as $otherId => $others) {
                $this->anyAwaiters[$otherId] = array_values(
                    array_filter(
                        $others,
                        static fn (TaskRecord $r): bool => $r->task()->id()->value !== $awaiterId
                    ),
                );
            }
        }
    }

    private function drainExpiredTimers(): void
    {
        $now = $this->clock->monotonicNanoseconds();
        $remaining = [];

        foreach ($this->timers as $entry) {
            if ($entry['deadline'] <= $now) {
                $record = $entry['record'];
                $taskId = $record->task()->id()->value;

                if (isset($entry['type'])) {
                    // IO deadline expired: resume the awaiting task with false.
                    $handleId = $this->ioTaskHandles[$taskId] ?? null;
                    if ($handleId !== null) {
                        unset($this->ioAwaiters[$handleId]);
                    }

                    unset($this->ioTaskHandles[$taskId]);
                    $this->pendingResumeValues[$taskId] = false;
                    $this->ready[] = $record;
                    continue;
                }

                $this->ready[] = $record;

                continue;
            }

            $remaining[] = $entry;
        }

        $this->timers = $remaining;
    }

    /**
     * Resumes tasks whose poll handles became ready, passing true to the
     * suspended Fiber. Handles that have no awaiting task are ignored.
     */
    private function drainReadyHandles(): void
    {
        $readyHandles = $this->poller->readyHandles();

        foreach ($readyHandles as $handle) {
            $handleId = $handle->id;

            if (!isset($this->ioAwaiters[$handleId])) {
                continue;
            }

            $record = $this->ioAwaiters[$handleId];
            $taskId = $record->task()->id()->value;

            unset($this->ioAwaiters[$handleId]);
            unset($this->ioTaskHandles[$taskId]);

            $this->pendingResumeValues[$taskId] = true;
            $this->ready[] = $record;
        }
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
                $this->resumeAnyAwaiters($record);
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
            $this->resumeAnyAwaiters($record);
        }
    }
}
