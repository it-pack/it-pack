<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain;

use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskId;
use ItPack\AsyncIt\Contract\TaskInterface;
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
 */
final class Task implements TaskInterface
{
    public function __construct(
        public readonly TaskId $id,
        public readonly ExecutionIdInterface $executionId,
        public readonly CancellationScopeInterface $cancellationScope,
        private TaskStatus $status = TaskStatus::Created,
    ) {
    }

    public function id(): TaskId
    {
        return $this->id;
    }

    public function executionId(): ExecutionIdInterface
    {
        return $this->executionId;
    }

    public function cancellationScope(): CancellationScopeInterface
    {
        return $this->cancellationScope;
    }

    public function status(): TaskStatus
    {
        return $this->status;
    }

    public function transitionTo(TaskStatus $next): void
    {
        if ($next === $this->status) {
            return;
        }

        if (!$this->status->isTerminal() && !$this->status->canTransitionTo($next)) {
            throw new InvalidLifecycleTransitionException($this->id, $this->status, $next);
        }

        if ($this->status->isTerminal()) {
            if ($next->isTerminal()) {
                throw new ConflictingCompletionException($this->id, $this->status, $next);
            }

            throw new InvalidLifecycleTransitionException($this->id, $this->status, $next);
        }

        $this->status = $next;
    }

    public function complete(TaskStatus $outcome): void
    {
        $this->transitionTo($outcome);
    }
}
