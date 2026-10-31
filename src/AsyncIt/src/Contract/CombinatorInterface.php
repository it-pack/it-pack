<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

/**
 * Structured concurrency combinator.
 *
 * A combinator groups a set of child tasks and coordinates their completion
 * according to a specific strategy (race, all, any, select). Each combinator
 * wraps a shared {@see CancellationScope} and a set of child tasks, and is
 * itself awaitable like a {@see TaskInterface}.
 *
 * Combinators are created and driven by the scheduler; user code typically
 * interacts with them through {@see \ItPack\AsyncIt\SDK\AsyncRuntime} methods
 * such as {@see \ItPack\AsyncIt\SDK\AsyncRuntime::race()}.
 *
 * @see https://w3id.org/itpack/asyncit#TaskGroup
 * @see https://w3id.org/itpack/asyncit-taxonomy#task-group-strategy-race
 * @see https://w3id.org/itpack/asyncit-taxonomy#task-group-strategy-all
 * @see https://w3id.org/itpack/asyncit-taxonomy#task-group-strategy-any
 * @see https://w3id.org/itpack/asyncit-taxonomy#await-point-select
 * @api
 */
interface CombinatorInterface extends TaskInterface
{
    /**
     * Spawns a child task on the combinator's scheduler and adds it to the
     * group. The child inherits the combinator's cancellation scope.
     *
     * @return TaskInterface the spawned child task (awaitable)
     */
    public function add(callable $operation): TaskInterface;

    /**
     * Blocks the calling task until the combinator's completion condition is
     * met, then returns the result.
     *
     * The return type depends on the combinator strategy:
     * - {@see RaceGroup}: result of the first completed task
     * - {@see AllGroup}: list of all results in spawn order
     * - {@see AnyGroup}: result of the first successful task
     * - {@see SelectGroup}: array with the winning task and its result
     */
    public function await(): mixed;
}
