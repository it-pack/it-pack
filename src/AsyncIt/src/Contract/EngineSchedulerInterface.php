<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

use ItPack\AsyncIt\Domain\TaskId;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use Throwable;

/**
 * Scheduler contract used by the RunIt execution engine.
 *
 * Extends {@see SchedulerInterface} with the single-operation dispatch the
 * {@see \ItPack\RunIt\Contract\Application\ExecutionEngineInterface} needs: run
 * one operation to completion and return its value. The additional method is a
 * focused extension rather than a modification of the shared scheduling
 * contract, so third-party schedulers opt in only when used as an engine.
 *
 * @see https://w3id.org/itpack/asyncit#Scheduler
 * @see https://w3id.org/itpack/runit#Execution
  * @api

  */
interface EngineSchedulerInterface extends SchedulerInterface
{
    /**
     * Queues the operation, drains the reactor until it terminates, and returns
     * the operation's result (whatever it is, including null).
     */
    public function dispatch(TaskInterface $task, callable $operation): mixed;

    /**
     * Spawns a new task onto the runnable queue.
     *
     * The task is assigned a generated {@see TaskId} (or the supplied one) and
     * bound to the current execution scope. The returned {@see TaskInterface}
     * can be awaited, cancelled, or observed for its outcome.
     *
     * @see https://w3id.org/itpack/asyncit#Task
     * @see https://w3id.org/itpack/asyncit-taxonomy#task-group-strategy
     */
    public function spawn(callable $operation, ?TaskIdInterface $id = null): TaskInterface;

    /**
     * Suspends the current task until another task completes, returning its result.
     *
     * @see https://w3id.org/itpack/asyncit#Awaitable
     */
    public function await(TaskInterface $task): mixed;

    /**
     * Returns the currently executing task, or null when called outside a task.
     *
     * @see https://w3id.org/itpack/asyncit#Task
     */
    public function currentTask(): ?TaskInterface;

    /**
     * Suspends the current task for at least the given monotonic duration.
     *
     * Uses the injected {@see \ItPack\RunIt\Contract\Port\MonotonicClockInterface}
     * so durations are free of wall-clock skew. The scheduler maintains a timer
     * heap and re-queues the task when the deadline elapses.
     *
     * @see https://w3id.org/itpack/asyncit#Timer
     * @see https://w3id.org/itpack/asyncit-taxonomy#await-point-timer
     */
    public function awaitDelay(DurationInterface $duration): void;

    /**
     * Runs the operation with retry semantics governed by the policy.
     *
     * On each failed attempt, checks whether the exception is retryable
     * (per {@see RetryPolicy::$retryable}). If retryable and the circuit
     * breaker (if supplied) allows execution, suspends for the computed
     * backoff delay and retries. After exhausting {@see RetryPolicy::$maxAttempts},
     * the **exact** original {@see Throwable} is rethrown — no wrapping.
     *
     * @throws Throwable The original exception after max attempts exhaustion.
     *
     * @see https://w3id.org/itpack/asyncit#RetryPolicy
     * @see https://w3id.org/itpack/asyncit#CircuitBreaker
     */
    public function retry(callable $operation, RetryPolicyInterface $policy): mixed;

    /**
     * Returns a health snapshot of the scheduler's runtime state.
     *
     * Implementations should capture the state at call time; the returned object
     * is an immutable snapshot.
     */
    public function health(): SchedulerHealthInterface;

    /**
     * Atomically swaps the negotiated capability descriptor.
     *
     * Called by the {@see CapabilityObserverInterface}
     * when the runtime adapter signals a capability change (e.g., native channels
     * became unavailable). The scheduler increments the descriptor version and
     * re-selects backends on the next reactor tick.
     */
    public function swapCapabilities(CapabilityDescriptorInterface $descriptor): void;
}
