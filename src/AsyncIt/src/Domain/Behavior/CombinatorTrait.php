<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain\Behavior;

use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskIdInterface;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Domain\Exception\InvalidLifecycleTransitionException;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;

/**
 * Shared boilerplate for structured-concurrency combinators
 * (`RaceGroup`, `SelectGroup`, `AllGroup`, `AnyGroup`).
 *
 * Provides the constructor, `add()`, `cancelRemaining()`, and `findChild()`
 * which are character-for-character identical across all four groups. Each
 * group supplies only its `await()` strategy.
 *
 * @see https://w3id.org/itpack/asyncit#TaskGroup
 * @see https://w3id.org/itpack/asyncit-taxonomy#task-group-strategy-race
 * @see https://w3id.org/itpack/asyncit-taxonomy#task-group-strategy-all
 * @see https://w3id.org/itpack/asyncit-taxonomy#task-group-strategy-any
 * @see https://w3id.org/itpack/asyncit-taxonomy#await-point-select
 */
trait CombinatorTrait
{
    use TaskBehavior;

    /**
     * @var list<TaskInterface>
     */
    private array $children = [];

    private bool $resolved = false;

    public function __construct(
        TaskIdInterface $id,
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
