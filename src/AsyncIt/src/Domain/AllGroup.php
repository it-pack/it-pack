<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain;

use ItPack\AsyncIt\Contract\CombinatorInterface;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Domain\Behavior\CombinatorTrait;
use LogicException;

/**
 * All combinator: waits for all child tasks to complete, returns all results
 * in spawn order. If any child fails, the group transitions to Failed.
 *
 * @implements CombinatorInterface<list<mixed>>
 *
 * @see https://w3id.org/itpack/asyncit#TaskGroup
 * @see https://w3id.org/itpack/asyncit-taxonomy#task-group-strategy-all
 * @see https://w3id.org/itpack/asyncit#CancellationScope
 */
final class AllGroup implements CombinatorInterface
{
    use CombinatorTrait;

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
