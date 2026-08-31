<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;

/**
 * Behavioural contract of a scheduled task.
 *
 * A task is the micro-unit of scheduled work: it belongs to exactly one RunIt
 * execution, carries an ambient cancellation scope, and advances through the
 * {@see TaskStatus} matrix. The concrete {@see \ItPack\AsyncIt\Domain\Task}
 * implements this contract; schedulers depend on the contract, never the entity.
 *
 * @see https://w3id.org/itpack/asyncit#Task
 * @see https://w3id.org/itpack/runit#Execution
  * @api

  */
interface TaskInterface
{
    public function id(): TaskId;

    public function executionId(): ExecutionIdInterface;

    public function cancellationScope(): CancellationScopeInterface;

    public function status(): TaskStatus;

    /**
     * Moves the task to the next status, enforcing the lifecycle matrix.
     *
     * Illegal transitions throw {@see \ItPack\AsyncIt\Domain\Exception\InvalidLifecycleTransitionException};
     * a conflicting transition between two terminal statuses throws
     * {@see \ItPack\AsyncIt\Domain\Exception\ConflictingCompletionException}.
     */
    public function transitionTo(TaskStatus $next): void;

    public function complete(TaskStatus $outcome): void;
}
