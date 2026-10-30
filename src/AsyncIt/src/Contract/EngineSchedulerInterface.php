<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

use ItPack\AsyncIt\Domain\TaskId;
use ItPack\RunIt\Contract\Domain\DurationInterface;

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
    public function spawn(callable $operation, ?TaskId $id = null): TaskInterface;

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
}
