<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

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
 */
interface TaskGroupInterface extends TaskInterface
{
    /**
     * Spawns a child task on the group's scheduler, inheriting the group's
     * cancellation scope.
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
}
