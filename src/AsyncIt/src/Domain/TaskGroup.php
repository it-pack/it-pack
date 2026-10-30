<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain;

use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskGroupInterface;
use ItPack\AsyncIt\Contract\TaskId;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Domain\Exception\ConflictingCompletionException;
use ItPack\AsyncIt\Domain\Exception\InvalidLifecycleTransitionException;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use Throwable;

/**
 * Structured-concurrency task group: spawns children that share a cancellation
 * scope and runs to completion (or failure) as a unit.
 *
 * On any child failure, the group cancels its scope (cascading to siblings)
 * and propagates the first failure once all children have unwound. The group
 * itself is a {@see TaskInterface} so it can be awaited by a parent.
 *
 * @see https://w3id.org/itpack/asyncit#TaskGroup
 * @see https://w3id.org/itpack/asyncit-taxonomy#task-group-strategy-first-failure
 */
final class TaskGroup implements TaskGroupInterface
{
    private bool $closed = false;

    /**
     * @var list<TaskInterface>
     */
    private array $children = [];

    private ?Throwable $failure = null;

    private TaskStatus $status = TaskStatus::Created;

    public function __construct(
        private readonly TaskId $id,
        private readonly ExecutionIdInterface $executionId,
        private readonly CancellationScopeInterface $cancellationScope,
        private readonly EngineSchedulerInterface $scheduler,
    ) {
    }

    /**
     * Auto-cancels any still-pending children when the group goes out of scope
     * without being explicitly closed or completed. This guarantees structured
     * concurrency: leaked groups never leave child tasks running indefinitely.
     */
    public function __destruct()
    {
        if ($this->children === [] || $this->status->isTerminal()) {
            return;
        }

        foreach ($this->children as $child) {
            if (!$child->status()->isTerminal()) {
                $child->complete(TaskStatus::Cancelled);
            }
        }
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

    public function failure(): ?Throwable
    {
        return $this->failure;
    }

    /**
     * Records the first failure observed among the group's children.
     */
    public function fail(Throwable $failure): void
    {
        $this->failure ??= $failure;
    }

    public function go(callable $operation): TaskInterface
    {
        if ($this->closed) {
            throw new InvalidLifecycleTransitionException(
                $this->id,
                TaskStatus::Running,
                TaskStatus::Cancelled,
            );
        }

        $child = $this->scheduler->spawn($operation);
        $this->children[] = $child;

        return $child;
    }

    public function await(): array
    {
        if ($this->children === []) {
            return [];
        }

        $results = [];

        foreach ($this->children as $child) {
            $results[] = $this->scheduler->await($child);
        }

        return $results;
    }

    public function close(): void
    {
        $this->closed = true;
    }

    public function status(): TaskStatus
    {
        if ($this->failure instanceof Throwable && $this->status !== TaskStatus::Failed) {
            return TaskStatus::Failed;
        }

        return $this->status;
    }

    public function complete(TaskStatus $outcome): void
    {
        $this->transitionTo($outcome);
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
}
