<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain;

use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Domain\Behavior\TaskBehavior;
use ItPack\AsyncIt\Domain\Exception\ConflictingCompletionException;
use ItPack\AsyncIt\Domain\Exception\InvalidLifecycleTransitionException;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;

/**
 * The micro-unit of scheduled work within an execution.
 *
 * A task belongs to exactly one RunIt execution, carries an ambient
 * {@see CancellationScopeInterface}, and advances through the
 * {@see TaskStatus} matrix. Status is the only mutable field; identity,
 * execution, and scope are immutable. Every transition is validated against the
 * matrix: illegal moves throw {@see InvalidLifecycleTransitionException}, and a
 * conflicting move between two terminal states throws
 * {@see ConflictingCompletionException}. A repeat of the current status is a
 * no-op (idempotency is also enforced upstream by the coordinator).
 *
 * @see https://w3id.org/itpack/asyncit#Task
 * @see https://w3id.org/itpack/runit#Execution
  * @api

  */
final class Task implements TaskInterface
{
    use TaskBehavior;

    public function __construct(
        TaskId $id,
        ExecutionIdInterface $executionId,
        CancellationScopeInterface $cancellationScope,
        TaskStatus $status = TaskStatus::Created,
    ) {
        $this->id = $id;
        $this->executionId = $executionId;
        $this->cancellationScope = $cancellationScope;
        $this->transitionTo($status);
    }
}
