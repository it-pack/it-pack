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
 * All combinator: waits for all child tasks to complete, returns all results
 * in spawn order. If any child fails, the group transitions to Failed.
 *
 * @see https://w3id.org/itpack/asyncit#TaskGroup
 * @see https://w3id.org/itpack/asyncit-taxonomy#task-group-strategy-all
 * @see https://w3id.org/itpack/asyncit#CancellationScope
 */
final class AllGroup implements CombinatorInterface
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

    /**
     * @return list<mixed>
     */
    public function await(): array
    {
        if ($this->resolved) {
            throw new LogicException('AllGroup::await() may only be called once.');
        }

        $this->resolved = true;
        $this->transitionTo(TaskStatus::Running);

        $results = [];

        foreach ($this->children as $child) {
            $result = $this->scheduler->await($child);
            $results[] = $result;
        }

        // Check for any failure.
        foreach ($this->children as $child) {
            if ($child->status() === TaskStatus::Failed) {
                $this->transitionTo(TaskStatus::Failed);

                return $results;
            }
        }

        $this->transitionTo(TaskStatus::Succeeded);

        return $results;
    }
}
