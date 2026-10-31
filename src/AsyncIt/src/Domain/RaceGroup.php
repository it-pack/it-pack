<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain;

use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\CombinatorInterface;
use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Domain\Behavior\TaskBehavior;
use ItPack\AsyncIt\Domain\Exception\InvalidLifecycleTransitionException;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use LogicException;

/**
 * Race combinator: waits for the first child task to complete, returns its
 * result, and cancels the remaining children.
 *
 * @see https://w3id.org/itpack/asyncit#TaskGroup
 * @see https://w3id.org/itpack/asyncit-taxonomy#task-group-strategy-race
 * @see https://w3id.org/itpack/asyncit#CancellationScope
 */
final class RaceGroup implements CombinatorInterface
{
    use TaskBehavior;

    /**
     * @var list<TaskInterface>
     */
    private array $children = [];

    private bool $resolved = false;

    public function __construct(
        TaskId $id,
        private readonly EngineSchedulerInterface $scheduler,
        CancellationScopeInterface $cancellationScope,
        ExecutionIdInterface $executionId,
    ) {
        $this->id = $id;
        $this->cancellationScope = $cancellationScope;
        $this->executionId = $executionId;
        $this->transitionTo(TaskStatus::Created);
    }

    public function add(callable $operation): TaskInterface
    {
        if ($this->resolved) {
            throw new InvalidLifecycleTransitionException(
                $this->id,
                TaskStatus::Created,
                TaskStatus::Running,
            );
        }

        $child = $this->scheduler->spawn($operation);
        $this->children[] = $child;

        return $child;
    }

    public function await(): mixed
    {
        if ($this->resolved) {
            throw new LogicException('RaceGroup::await() may only be called once.');
        }

        $this->resolved = true;
        $this->transitionTo(TaskStatus::Running);

        // Check for already-completed children.
        foreach ($this->children as $child) {
            if ($child->status()->isTerminal()) {
                $result = $this->scheduler->result($child);
                $this->cancelRemaining($child);
                $this->transitionTo(TaskStatus::Succeeded);

                return $result;
            }
        }

        if ($this->children === []) {
            $this->transitionTo(TaskStatus::Succeeded);

            return null;
        }

        // Wait for the first child to complete via awaitAny() when available.
        if (method_exists($this->scheduler, 'awaitAny')) {
            $completedId = $this->scheduler->awaitAny($this->children);
            assert(is_string($completedId));
            $completed = $this->findChild($completedId);

            if ($completed instanceof TaskInterface) {
                $result = $this->scheduler->result($completed);
                $this->cancelRemaining($completed);
                $this->transitionTo(TaskStatus::Succeeded);

                return $result;
            }
        }

        // Fallback: sequential await.
        $result = null;
        foreach ($this->children as $child) {
            if ($child->status()->isTerminal()) {
                $result = $this->scheduler->result($child);
                $this->cancelRemaining($child);
                $this->transitionTo(TaskStatus::Succeeded);
                break;
            }

            $result = $this->scheduler->await($child);

            $this->cancelRemaining($child);
            $this->transitionTo(TaskStatus::Succeeded);
            break;
        }

        return $result;
    }

    private function cancelRemaining(TaskInterface $exclude): void
    {
        foreach ($this->children as $child) {
            if ($child !== $exclude && !$child->status()->isTerminal()) {
                $child->cancellationScope()->cancel();
            }
        }
    }

    private function findChild(string $id): ?TaskInterface
    {
        foreach ($this->children as $child) {
            if ($child->id()->value === $id) {
                return $child;
            }
        }

        return null;
    }
}
