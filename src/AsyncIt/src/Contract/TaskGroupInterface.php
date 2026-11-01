<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

use ItPack\AsyncIt\Contract\Port\BackpressurePolicy;
use Throwable;

/**
 * Structured-concurrency task group.
 *
 * A task group scopes a family of child tasks that run concurrently and are
 * automatically cancelled when any child fails (FirstFailure strategy).
 * The group itself is a {@see TaskInterface} — it can be awaited like any task
 * and carries the same lifecycle semantics.
 *
 * @see https://w3id.org/itpack/asyncit#TaskGroup
 * @see https://w3id.org/itpack/asyncit-taxonomy#task-group-strategy-first-failure
 * @see https://w3id.org/itpack/asyncit#BackpressurePolicy
 * @see https://w3id.org/itpack/asyncit-taxonomy#backpressure-block
 * @see https://w3id.org/itpack/asyncit-taxonomy#backpressure-drop
 * @see https://w3id.org/itpack/asyncit-taxonomy#backpressure-error
 */
interface TaskGroupInterface extends TaskInterface
{
    /**
     * Spawns a child task on the group's scheduler, inheriting the group's
     * cancellation scope.
     *
     * If the group has a maxConcurrency limit, the call respects the configured
     * {@see BackpressurePolicy}: Block suspends the caller until a slot frees,
     * Drop returns a no-op child immediately, Error throws.
     *
     * @return TaskInterface the spawned child task (awaitable)
     */
    public function go(callable $operation): TaskInterface;

    /**
     * Blocks the calling task until every child spawned via {@see go()} has
     * completed.
     *
     * @return list<mixed> results of each child, in spawn order
     */
    public function await(): array;

    /**
     * Marks the group as closed — no further children will be added.
     *
     * Idempotent. After close() the group waits for in-flight children to
     * finish before transitioning to a terminal state.
     */
    public function close(): void;

    /**
     * The group's cancellation scope, inherited by every child spawned via go().
     */
    public function cancellationScope(): CancellationScopeInterface;

    /**
     * Any failure that occurred in a child task.
     */
    public function failure(): ?Throwable;

    /**
     * Marks the group as failed with the given throwable.
     */
    public function fail(Throwable $throwable): void;

    /**
     * The backpressure policy applied when maxConcurrency is exceeded.
     *
     * @see https://w3id.org/itpack/asyncit-taxonomy#backpressure-block
     */
    public function backpressurePolicy(): BackpressurePolicy;
}
