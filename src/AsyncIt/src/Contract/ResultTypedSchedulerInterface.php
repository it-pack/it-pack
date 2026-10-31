<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

use Throwable;

/**
 * Optional extension: typed terminal outcome access for tasks.
 *
 * Schedulers that implement this interface expose {@see resultTyped()} in
 * addition to the {@see SchedulerInterface::result()} method (which returns
 * `mixed` for backward compatibility). Third-party `EngineSchedulerInterface`
 * implementors are not required to adopt this interface; it is opt-in.
 *
 * @see \ItPack\AsyncIt\Contract\TaskResultInterface
 * @see https://w3id.org/itpack/asyncit#TaskResult
 * @api
 */
interface ResultTypedSchedulerInterface
{
    /**
     * Returns the terminal outcome of a task as a typed {@see TaskResultInterface}.
     *
     * @throws Throwable when the task is not tracked by this scheduler or
     *         has not yet reached a terminal state.
     *
     * @see \ItPack\AsyncIt\Contract\TaskResultInterface
     * @see https://w3id.org/itpack/asyncit#TaskResult
     */
    public function resultTyped(TaskInterface $task): TaskResultInterface;
}
